#!/bin/bash
set -e
SCRIPT_NAME="5q12-index"
CONFIG_DIR="/etc/5q12-indexer"
CONFIG_FILE="$CONFIG_DIR/indexer.conf"
SYMLINK_PATH="/usr/local/bin/$SCRIPT_NAME"
NGINX_CONFIG_PATH="/etc/nginx/sites-available/5q12-indexer.conf"
NGINX_ENABLED_PATH="/etc/nginx/sites-enabled/5q12-indexer.conf"
REPO_BASE_URL="https://ccls.icu/src/repositories/5q12-indexer/main"
REPO_LIST_URL="$REPO_BASE_URL/repo/"
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}
log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}
log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}
log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}
check_root() {
    if [[ $EUID -eq 0 ]]; then
        return 0
    else
        return 1
    fi
}
get_repo_version() {
    local temp_file=$(mktemp)
    if ! curl -s -L "$REPO_LIST_URL" > "$temp_file"; then
        log_error "Failed to fetch repository data"
        rm -f "$temp_file"
        echo "unknown"
        return 1
    fi
    local version=$(head -n1 "$temp_file" | grep -oP 'VERSION=\K.*' || echo "unknown")
    rm -f "$temp_file"
    echo "$version"
}
save_config() {
    local version="$1"
    local path="$2"
    local install_date="$3"
    local nginx_installed="$4"
    local php_installed="$5"
    local php_extensions_installed="$6"
    sudo mkdir -p "$CONFIG_DIR"
    cat << EOF | sudo tee "$CONFIG_FILE" > /dev/null
INDEXER_VERSION="$version"
INDEXER_PATH="$path"
INDEXER_INSTALL_DATE="$install_date"
INDEXER_NGINX_CONFIG="$NGINX_CONFIG_PATH"
INDEXER_SCRIPT_VERSION="1.2.0-r2"
NGINX_INSTALLED="$nginx_installed"
PHP_INSTALLED="$php_installed"
PHP_EXTENSIONS_INSTALLED="$php_extensions_installed"
EOF
    log_info "Configuration saved to $CONFIG_FILE"
}
load_config() {
    if [[ -f "$CONFIG_FILE" ]]; then
        source "$CONFIG_FILE" 2>/dev/null || true
    fi
}
should_download_file() {
    local file_url="$1"
    local filename="$2"
    if [[ "$filename" == "index.php" && "$file_url" == "https://ccls.icu/src/repositories/5q12-indexer/main/index.php/" ]]; then
        return 0
    fi
    if [[ "$filename" == "5q12-indexer.conf" ]]; then
        return 1
    fi
    if [[ "$file_url" == *"/main/indexer_files/"* ]]; then
        return 0
    fi
    return 1
}
merge_config_json() {
    local existing_config="$1"
    local default_config="$2"
    local output_config="$3"
    local merge_script=$(mktemp)
    cat > "$merge_script" << 'MERGE_EOF'
<?php
if ($argc != 4) {
    echo "Usage: php merge_config.php <existing> <default> <output>\n";
    exit(1);
}
$existing_file = $argv[1];
$default_file = $argv[2];
$output_file = $argv[3];
// Read and parse JSON files
$existing = json_decode(file_get_contents($existing_file), true);
$default = json_decode(file_get_contents($default_file), true);
if (!$existing || !$default) {
    echo "Error: Could not parse JSON files\n";
    exit(1);
}
// Function to recursively merge arrays, adding missing keys from default
function merge_recursive($existing, $default) {
    $result = $existing;
    foreach ($default as $key => $value) {
        if (!array_key_exists($key, $result)) {
            // Key is missing in existing config, add it
            $result[$key] = $value;
            echo "Added missing config field: $key\n";
        } elseif (is_array($value) && is_array($result[$key])) {
            // Both are arrays, merge recursively
            $result[$key] = merge_recursive($result[$key], $value);
        }
        // If key exists and is not an array, keep existing value
    }
    return $result;
}
$merged = merge_recursive($existing, $default);
// Always update version to match default
$existing_version = $existing['version'] ?? 'unknown';
$default_version = $default['version'] ?? 'unknown';
if ($existing_version !== $default_version) {
    echo "Updating version from $existing_version to $default_version\n";
    $merged['version'] = $default_version;
}
// Write merged config
file_put_contents($output_file, json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "Config merge completed successfully\n";
MERGE_EOF
    php "$merge_script" "$existing_config" "$default_config" "$output_config"
    local merge_result=$?
    rm -f "$merge_script"
    return $merge_result
}
handle_config_json_update() {
    local install_path="$1"
    local config_dir="$install_path/.indexer_files"
    local current_config="$config_dir/config.json"
    local temp_default_config=$(mktemp)
    log_info "Handling config.json update with advanced merging..."
    if [[ ! -d "$config_dir" ]]; then
        sudo mkdir -p "$config_dir"
        sudo chown www-data:www-data "$config_dir"
        sudo chmod 755 "$config_dir"
    fi
    local default_config_url="$REPO_BASE_URL/indexer_files/config.json/"
    if ! curl -s -L -o "$temp_default_config" "$default_config_url"; then
        log_error "Failed to download default config.json"
        rm -f "$temp_default_config"
        return 1
    fi
    if [[ -f "$current_config" ]]; then
        log_info "Found existing config, merging with new defaults..."
        local temp_merged=$(mktemp)
        if merge_config_json "$current_config" "$temp_default_config" "$temp_merged"; then
            sudo cp "$temp_merged" "$current_config"
            sudo chown www-data:www-data "$current_config"
            sudo chmod 644 "$current_config"
            log_success "Config.json updated with missing fields and latest version"
        else
            log_warning "Config merge failed, keeping existing config"
        fi
        rm -f "$temp_merged"
    else
        log_info "No existing config found, using new default..."
        sudo cp "$temp_default_config" "$current_config"
        sudo chown www-data:www-data "$current_config"
        sudo chmod 644 "$current_config"
        log_success "Default config.json installed"
    fi
    rm -f "$temp_default_config"
    if ! php -r "json_decode(file_get_contents('$current_config'), true);" >/dev/null 2>&1; then
        log_error "Final config.json is invalid JSON!"
        return 1
    fi
    log_info "Config.json validation passed"
    return 0
}
download_indexer_files() {
    local install_path="$1"
    log_info "Downloading indexer files..."
    local repo_file=$(mktemp)
    if ! curl -s -L "$REPO_LIST_URL" > "$repo_file"; then
        log_error "Failed to fetch repository file list"
        rm -f "$repo_file"
        return 1
    fi
    log_info "Repository file first 10 lines:"
    head -n 10 "$repo_file" | while read line; do
        log_info "REPO LINE: $line"
    done
    log_info "Processing file list from repository..."
    local total_files=0
    local downloaded_files=0
    local failed_downloads=()
    while IFS= read -r line; do
        if [[ $line =~ ^VERSION= ]]; then
            continue
        fi
        if [[ -z "$line" ]]; then
            continue
        fi
        if [[ $line == *\"*\"* ]] && [[ $(echo "$line" | grep -o '"' | wc -l) -eq 4 ]]; then
            local filename=$(echo "$line" | cut -d'"' -f2)
            local fileurl=$(echo "$line" | cut -d'"' -f4)
            if should_download_file "$fileurl" "$filename"; then
                log_info "Will download: $filename from $fileurl"
                ((total_files++))
            else
                log_info "Skipping: $filename (not needed)"
            fi
        fi
    done < "$repo_file"
    log_info "Found $total_files files to download"
    if [[ $total_files -eq 0 ]]; then
        log_warning "No files found to download - this may indicate a problem"
        rm -f "$repo_file"
        return 1
    fi
    while IFS= read -r line; do
        if [[ $line =~ ^VERSION= ]] || [[ -z "$line" ]]; then
            continue
        fi
        if [[ $line == *\"*\"* ]] && [[ $(echo "$line" | grep -o '"' | wc -l) -eq 4 ]]; then
            local filename=$(echo "$line" | cut -d'"' -f2)
            local fileurl=$(echo "$line" | cut -d'"' -f4)
            if ! should_download_file "$fileurl" "$filename"; then
                continue
            fi
            local local_path=""
            if [[ $fileurl =~ /indexer_files/(.+)/ ]]; then
                local_path=".indexer_files/${BASH_REMATCH[1]}"
            elif [[ $filename == "index.php" || $filename == "5q12-indexer.conf" ]]; then
                local_path="$filename"
            else
                local_path="$filename"
            fi
            local full_path="$install_path/$local_path"
            local dir_path=$(dirname "$full_path")
            if [[ ! -d "$dir_path" ]]; then
                sudo mkdir -p "$dir_path"
                sudo chown www-data:www-data "$dir_path"
                sudo chmod 755 "$dir_path"
            fi
            ((downloaded_files++))
            log_info "[$downloaded_files/$total_files] Downloading $filename..."
            if curl -s -L -o "$full_path" "$fileurl"; then
                sudo chown www-data:www-data "$full_path"
                sudo chmod 644 "$full_path"
                log_info "Downloaded: $filename"
            else
                log_warning "Failed to download: $filename"
                failed_downloads+=("$filename")
            fi
        fi
    done < "$repo_file"
    rm -f "$repo_file"
    local files_dir="$install_path/files"
    if [[ ! -d "$files_dir" ]]; then
        sudo mkdir -p "$files_dir"
        sudo chown www-data:www-data "$files_dir"
        sudo chmod 755 "$files_dir"
        log_info "Created files directory: $files_dir"
    fi
    if [[ ${#failed_downloads[@]} -gt 0 ]]; then
        log_warning "Failed to download ${#failed_downloads[@]} files: ${failed_downloads[*]}"
        return 1
    fi
    log_success "Downloaded $downloaded_files files successfully"
    return 0
}
parse_source_path() {
    local args=("$@")
    local source_path=""
    for i in "${!args[@]}"; do
        if [[ "${args[$i]}" == "-source" ]]; then
            if [[ $((i + 1)) -lt ${#args[@]} ]]; then
                local next_arg="${args[$((i + 1))]}"
                source_path="${next_arg#-}"
                break
            fi
        fi
    done
    echo "$source_path"
}
download_source_by_path() {
    local install_path="$1"
    local source_path="$2"
    log_info "Downloading source files matching: $source_path"
    local repo_file=$(mktemp)
    if ! curl -s -L "$REPO_LIST_URL" > "$repo_file"; then
        log_error "Failed to fetch repository file list"
        rm -f "$repo_file"
        return 1
    fi
    local total_files=0
    local downloaded_files=0
    local failed_downloads=()
    local is_specific_file=false
    if [[ "$source_path" == *.* ]]; then
        is_specific_file=true
        log_info "Detecting specific file download mode"
    fi
    local url_pattern=""
    if [[ "$source_path" == "all" ]]; then
        url_pattern="/main/"
        log_info "Mode: Download ALL files from repository"
    elif [[ "$is_specific_file" == "true" ]]; then
        url_pattern="/main/$source_path/"
        log_info "Mode: Download specific file: $source_path"
    else
        url_pattern="/main/$source_path/"
        log_info "Mode: Download directory contents: $source_path"
    fi
    while IFS= read -r line; do
        if [[ $line =~ ^VERSION= ]] || [[ -z "$line" ]]; then
            continue
        fi
        if [[ $line == *\"*\"* ]] && [[ $(echo "$line" | grep -o '"' | wc -l) -eq 4 ]]; then
            local fileurl=$(echo "$line" | cut -d'"' -f4)
            if [[ "$source_path" == "all" ]]; then
                ((total_files++))
            elif [[ "$is_specific_file" == "true" ]]; then
                if [[ "$fileurl" == *"$url_pattern"* ]]; then
                    ((total_files++))
                fi
            else
                if [[ "$fileurl" == *"$url_pattern"* ]]; then
                    ((total_files++))
                fi
            fi
        fi
    done < "$repo_file"
    log_info "Found $total_files matching files to download"
    if [[ $total_files -eq 0 ]]; then
        log_error "No files found matching: $source_path"
        log_info "Pattern searched: $url_pattern"
        rm -f "$repo_file"
        return 1
    fi
    while IFS= read -r line; do
        if [[ $line =~ ^VERSION= ]] || [[ -z "$line" ]]; then
            continue
        fi
        if [[ $line == *\"*\"* ]] && [[ $(echo "$line" | grep -o '"' | wc -l) -eq 4 ]]; then
            local filename=$(echo "$line" | cut -d'"' -f2)
            local fileurl=$(echo "$line" | cut -d'"' -f4)
            local should_download=false
            if [[ "$source_path" == "all" ]]; then
                should_download=true
            elif [[ "$is_specific_file" == "true" ]]; then
                if [[ "$fileurl" == *"$url_pattern"* ]]; then
                    should_download=true
                fi
            else
                if [[ "$fileurl" == *"$url_pattern"* ]]; then
                    should_download=true
                fi
            fi
            if [[ "$should_download" == "false" ]]; then
                continue
            fi
            local local_path=""
            if [[ "$source_path" == "all" ]]; then
                if [[ $fileurl =~ /main/(.+)/ ]]; then
                    local_path="${BASH_REMATCH[1]}"
                else
                    local_path="$filename"
                fi
            elif [[ "$is_specific_file" == "true" ]]; then
                local_path="$filename"
            else
                if [[ $fileurl =~ /main/$source_path/(.+)/ ]]; then
                    local_path="${BASH_REMATCH[1]}"
                elif [[ $fileurl =~ /main/(.+)/ ]]; then
                    local_path="${BASH_REMATCH[1]}"
                else
                    local_path="$filename"
                fi
            fi
            local full_path="$install_path/$local_path"
            local dir_path=$(dirname "$full_path")
            if [[ ! -d "$dir_path" ]]; then
                sudo mkdir -p "$dir_path"
                sudo chown www-data:www-data "$dir_path"
                sudo chmod 755 "$dir_path"
            fi
            ((downloaded_files++))
            log_info "[$downloaded_files/$total_files] Downloading $filename -> $local_path"
            if curl -s -L -o "$full_path" "$fileurl"; then
                sudo chown www-data:www-data "$full_path"
                sudo chmod 644 "$full_path"
            else
                log_warning "Failed to download: $filename"
                failed_downloads+=("$filename")
            fi
        fi
    done < "$repo_file"
    rm -f "$repo_file"
    if [[ ${#failed_downloads[@]} -gt 0 ]]; then
        log_warning "Failed to download ${#failed_downloads[@]} files: ${failed_downloads[*]}"
        return 1
    fi
    log_success "Downloaded $downloaded_files source files successfully"
    return 0
}
install_source() {
    local install_path="$1"
    shift
    local source_path=$(parse_source_path "$@")
    if [[ -z "$source_path" ]]; then
        log_error "Source path not specified after -source flag"
        echo "Usage: $SCRIPT_NAME install <path> -source -<source_path>"
        echo ""
        echo "Examples:"
        echo "  $SCRIPT_NAME install /var/www -source -all              # Download everything"
        echo "  $SCRIPT_NAME install /var/www -source -docker           # Download /main/docker/"
        echo "  $SCRIPT_NAME install /var/www -source -docker/config    # Download /main/docker/config/"
        echo "  $SCRIPT_NAME install /var/www -source -index.php        # Download only index.php"
        echo "  $SCRIPT_NAME install /var/www -source -docker/build.sh  # Download specific file"
        return 1
    fi
    if [[ -z "$install_path" ]]; then
        log_error "Installation path is required"
        return 1
    fi
    if [[ ! "$install_path" = /* ]]; then
        install_path="$(pwd)/$install_path"
    fi
    log_info "Installing source files to: $install_path"
    log_info "Source filter: $source_path"
    if [[ ! -d "$install_path" ]]; then
        sudo mkdir -p "$install_path"
    fi
    sudo chown -R www-data:www-data "$install_path"
    sudo chmod 755 "$install_path"
    if ! download_source_by_path "$install_path" "$source_path"; then
        log_error "Failed to download source files"
        return 1
    fi
    log_success "Source installation completed successfully!"
    log_info "Files installed at: $install_path"
    return 0
}
check_system_requirements() {
    log_info "Checking system requirements..."
    local nginx_ok=false
    local php_ok=false
    local php_ext_ok=false
    local missing_requirements=()
    if command -v nginx >/dev/null 2>&1; then
        local nginx_version=$(nginx -v 2>&1 | grep -oP 'nginx/\K[0-9.]+')
        if [[ $(printf '%s\n' "1.14" "$nginx_version" | sort -V | head -n1) = "1.14" ]]; then
            log_success "Nginx $nginx_version found (>= 1.14 required)"
            nginx_ok=true
        else
            log_warning "Nginx $nginx_version found but >= 1.14 required"
            missing_requirements+=("nginx-upgrade")
        fi
    else
        log_warning "Nginx not found"
        missing_requirements+=("nginx")
    fi
    if command -v php >/dev/null 2>&1; then
        local php_version=$(php -v | grep -oP 'PHP \K[0-9.]+' | head -n1)
        if [[ $(printf '%s\n' "8.3" "$php_version" | sort -V | head -n1) = "8.3" ]]; then
            log_success "PHP $php_version found (>= 8.3 required)"
            php_ok=true
        else
            log_warning "PHP $php_version found but >= 8.3 required"
            missing_requirements+=("php-upgrade")
        fi
    else
        log_warning "PHP not found"
        missing_requirements+=("php")
    fi
    if ! command -v sqlite3 >/dev/null 2>&1; then
        log_warning "SQLite3 not found"
        missing_requirements+=("sqlite3")
    else
        log_success "SQLite3 found"
    fi
    if [[ "$php_ok" == "true" ]]; then
        local required_extensions=("json" "fileinfo" "mbstring" "sqlite3" "zip" "curl" "openssl")
        local missing_extensions=()
        for ext in "${required_extensions[@]}"; do
            if ! php -m | grep -q "^$ext$"; then
                missing_extensions+=("$ext")
            fi
        done
        if [[ ${#missing_extensions[@]} -eq 0 ]]; then
            log_success "All required PHP extensions found"
            php_ext_ok=true
        else
            log_warning "Missing PHP extensions: ${missing_extensions[*]}"
            missing_requirements+=("php-extensions")
        fi
    fi
    echo "nginx_ok=$nginx_ok"
    echo "php_ok=$php_ok"
    echo "php_ext_ok=$php_ext_ok"
    echo "missing_requirements=${missing_requirements[*]}"
}
install_dependencies() {
    local requirements=("$@")
    if [[ ${#requirements[@]} -eq 0 ]]; then
        log_success "All dependencies are satisfied"
        return 0
    fi
    log_warning "Missing dependencies: ${requirements[*]}"
    echo
    read -p "Install missing dependencies? (y/n): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        log_error "Cannot continue without dependencies"
        return 1
    fi
    log_info "Installing dependencies..."
    sudo apt update
    for service in apache2 lighttpd; do
        if systemctl is-active --quiet "$service"; then
            log_warning "Stopping $service..."
            sudo systemctl stop "$service"
            sudo systemctl disable "$service"
        fi
    done
    local needs_php_restart=false
    for req in "${requirements[@]}"; do
        case "$req" in
            "nginx"|"nginx-upgrade")
                log_info "Installing/upgrading Nginx..."
                sudo apt install -y nginx
                sudo systemctl enable nginx
                sudo systemctl start nginx
                ;;
            "php"|"php-upgrade")
                log_info "Installing/upgrading PHP and PHP-FPM..."
                sudo apt install -y php-fpm php-cli
                needs_php_restart=true
                local php_service=$(systemctl list-units --type=service | grep -E "php[0-9.]*-fpm" | awk '{print $1}' | head -n1)
                if [[ -n "$php_service" ]]; then
                    sudo systemctl enable "$php_service"
                    sudo systemctl start "$php_service"
                fi
                ;;
            "sqlite3")
                log_info "Installing SQLite3 and libsqlite3..."
                sudo apt install -y sqlite3 libsqlite3-0 libsqlite3-dev
                ;;
            "php-extensions")
                log_info "Installing PHP extensions (including SQLite3)..."
                sudo apt install -y php-mbstring php-sqlite3 php-zip php-curl php-json
                needs_php_restart=true
                ;;
        esac
    done
    if [[ "$needs_php_restart" == "true" ]]; then
        local php_service=$(systemctl list-units --type=service | grep -E "php[0-9.]*-fpm" | awk '{print $1}' | head -n1)
        if [[ -n "$php_service" ]]; then
            log_info "Restarting PHP-FPM service..."
            sudo systemctl restart "$php_service"
        fi
    fi
    if dpkg -l | grep -q apache2; then
        log_info "Removing unwanted Apache2..."
        sudo apt remove -y apache2* libapache2* || true
        sudo apt autoremove -y
    fi
    if command -v sqlite3 >/dev/null 2>&1; then
        local sqlite_version=$(sqlite3 --version | awk '{print $1}')
        log_success "SQLite3 $sqlite_version installed successfully"
    fi
    if php -m | grep -q "^sqlite3$"; then
        log_success "PHP SQLite3 extension installed successfully"
    else
        log_warning "PHP SQLite3 extension may not be loaded correctly"
    fi
    log_success "Dependencies installed successfully"
    return 0
}
create_nginx_config() {
    local web_path="$1"
    log_info "Creating Nginx configuration..."
    local temp_config=$(mktemp)
    if ! curl -s -L -o "$temp_config" "$REPO_BASE_URL/5q12-indexer.conf/"; then
        log_error "Failed to download Nginx config template"
        rm -f "$temp_config"
        return 1
    fi
    sed "s|{WEB_PATH}|$web_path|g" "$temp_config" | sudo tee "$NGINX_CONFIG_PATH" > /dev/null
    rm -f "$temp_config"
    sudo ln -sf "$NGINX_CONFIG_PATH" "$NGINX_ENABLED_PATH"
    if ! sudo nginx -t; then
        log_error "Nginx configuration test failed"
        return 1
    fi
    sudo systemctl reload nginx
    log_success "Nginx configuration created and enabled"
    log_info "Config file: $NGINX_CONFIG_PATH"
    return 0
}
create_script_marker() {
    local install_path="$1"
    log_info "Creating script installation marker..."
    local marker_file="$install_path/.indexer_files/.script"
    local marker_dir=$(dirname "$marker_file")
    if [[ ! -d "$marker_dir" ]]; then
        sudo mkdir -p "$marker_dir"
        sudo chown www-data:www-data "$marker_dir"
        sudo chmod 755 "$marker_dir"
    fi
    sudo touch "$marker_file"
    sudo chown www-data:www-data "$marker_file"
    sudo chmod 644 "$marker_file"
    log_success "Script marker created: $marker_file"
}
install_indexer() {
    local install_path="$1"
    if [[ -z "$install_path" ]]; then
        log_error "Installation path is required"
        return 1
    fi
    if [[ ! "$install_path" = /* ]]; then
        install_path="$(pwd)/$install_path"
    fi
    log_info "Installing indexer to: $install_path"
    if [[ ! -d "$install_path" ]]; then
        sudo mkdir -p "$install_path"
    fi
    sudo chown -R www-data:www-data "$install_path"
    sudo chmod 755 "$install_path"
    if ! download_indexer_files "$install_path"; then
        log_error "Failed to download indexer files"
        return 1
    fi
    create_script_marker "$install_path"
    local config_file="$install_path/.indexer_files/config.json"
    if [[ -f "$config_file" ]]; then
        sudo chown www-data:www-data "$config_file"
        sudo chmod 644 "$config_file"
        log_success "Default config.json installed"
    else
        log_warning "config.json not found after download"
    fi
    log_info "Testing indexer installation..."
    if [[ -f "$install_path/index.php" ]]; then
        if (cd "$install_path" && sudo -u www-data php -l index.php > /dev/null 2>&1); then
            log_success "Indexer installation test passed"
        else
            log_warning "PHP syntax check failed, but installation may still work"
        fi
    else
        log_error "index.php not found after download"
        return 1
    fi
    log_info "Checking system dependencies..."
    for pass in 1 2; do
        if [[ $pass -eq 2 ]]; then
            log_info "Re-checking dependencies to ensure everything is installed..."
        fi
        local req_output=$(check_system_requirements)
        local nginx_ok=$(echo "$req_output" | grep "nginx_ok=" | cut -d= -f2)
        local php_ok=$(echo "$req_output" | grep "php_ok=" | cut -d= -f2)
        local php_ext_ok=$(echo "$req_output" | grep "php_ext_ok=" | cut -d= -f2)
        local missing_req=$(echo "$req_output" | grep "missing_requirements=" | cut -d= -f2-)
        if [[ -n "$missing_req" && "$missing_req" != "" ]]; then
            if [[ $pass -eq 2 ]]; then
                log_info "Installing remaining dependencies from second pass..."
            fi
            if ! install_dependencies $missing_req; then
                log_error "Failed to install dependencies"
                return 1
            fi
        else
            if [[ $pass -eq 2 ]]; then
                log_success "All dependencies verified on second pass"
            fi
            break
        fi
    done
    if ! create_nginx_config "$install_path"; then
        log_error "Failed to create Nginx configuration"
        return 1
    fi
    local version=$(get_repo_version)
    local install_date=$(date '+%Y-%m-%d %H:%M:%S')
    save_config "$version" "$install_path" "$install_date" "$nginx_ok" "$php_ok" "$php_ext_ok"
    log_success "Installation completed successfully!"
    log_info "Indexer installed at: $install_path"
    log_info "Version: $version"
    log_info "Access URL: http://localhost:5012"
    return 0
}
smart_update_files() {
    local install_path="$1"
    log_info "Performing smart update..."
    local repo_file=$(mktemp)
    if ! curl -s -L "$REPO_LIST_URL" > "$repo_file"; then
        log_error "Failed to fetch repository file list"
        rm -f "$repo_file"
        return 1
    fi
    local always_replace=(
        "index.php"
        ".indexer_files/config-reference.txt"
        ".indexer_files/local_api"
        ".indexer_files/local_api/style"
    )
    log_info "Removing files/directories that will be replaced..."
    for item in "${always_replace[@]}"; do
        local full_path="$install_path/$item"
        if [[ -e "$full_path" ]]; then
            sudo rm -rf "$full_path"
            log_info "Removed: $item"
        fi
    done
    local updated_files=0
    local skipped_files=0
    while IFS= read -r line; do
        if [[ $line =~ ^VERSION= ]] || [[ -z "$line" ]]; then
            continue
        fi
        if [[ $line == *\"*\"* ]] && [[ $(echo "$line" | grep -o '"' | wc -l) -eq 4 ]]; then
            local filename=$(echo "$line" | cut -d'"' -f2)
            local fileurl=$(echo "$line" | cut -d'"' -f4)
            if ! should_download_file "$fileurl" "$filename"; then
                continue
            fi
            local local_path=""
            if [[ $fileurl =~ /indexer_files/(.+)/ ]]; then
                local_path=".indexer_files/${BASH_REMATCH[1]}"
            elif [[ $filename == "index.php" ]]; then
                local_path="$filename"
            else
                local_path="$filename"
            fi
            local full_path="$install_path/$local_path"
            local dir_path=$(dirname "$full_path")
            local should_download=false
            local reason=""
            for item in "${always_replace[@]}"; do
                if [[ "$local_path" == "$item" ]] || [[ "$local_path" == "$item"/* ]]; then
                    should_download=true
                    reason="always replaced"
                    break
                fi
            done
            if [[ "$should_download" == "false" ]] && [[ ! -f "$full_path" ]]; then
                should_download=true
                reason="missing file"
            fi
            if [[ "$should_download" == "true" ]]; then
                if [[ ! -d "$dir_path" ]]; then
                    sudo mkdir -p "$dir_path"
                    sudo chown www-data:www-data "$dir_path"
                    sudo chmod 755 "$dir_path"
                fi
                log_info "Updating: $filename ($reason)"
                if curl -s -L -o "$full_path" "$fileurl"; then
                    sudo chown www-data:www-data "$full_path"
                    sudo chmod 644 "$full_path"
                    ((updated_files++))
                else
                    log_warning "Failed to download: $filename"
                fi
            else
                log_info "Skipping: $filename (already exists)"
                ((skipped_files++))
            fi
        fi
    done < "$repo_file"
    rm -f "$repo_file"
    create_script_marker "$install_path"
    log_success "Update completed: $updated_files files updated, $skipped_files files skipped"
    return 0
}
update_indexer() {
    load_config
    if ! update_install_script; then
        log_warning "Failed to update install script, continuing with indexer update..."
    fi
    if [[ -z "$INDEXER_PATH" ]]; then
        log_error "No installation found. Use 'install' command first."
        return 1
    fi
    if [[ ! -d "$INDEXER_PATH" ]]; then
        log_error "Installation directory not found: $INDEXER_PATH"
        return 1
    fi
    local current_version="${INDEXER_VERSION:-unknown}"
    local latest_version=$(get_repo_version)
    log_info "Current version: $current_version"
    log_info "Latest version: $latest_version"
    if [[ "$current_version" == "$latest_version" && "$current_version" != "unknown" ]]; then
        log_success "Already up to date"
        return 0
    fi
    if ! smart_update_files "$INDEXER_PATH"; then
        log_error "Failed to update files"
        return 1
    fi
    if ! handle_config_json_update "$INDEXER_PATH"; then
        log_error "Failed to update configuration"
        return 1
    fi
    create_nginx_config "$INDEXER_PATH"
    local update_date=$(date '+%Y-%m-%d %H:%M:%S')
    save_config "$latest_version" "$INDEXER_PATH" "$update_date" "${NGINX_INSTALLED:-true}" "${PHP_INSTALLED:-true}" "${PHP_EXTENSIONS_INSTALLED:-true}"
    log_success "Updated from $current_version to $latest_version"
    log_info "Your existing configuration has been preserved and enhanced with new fields"
    return 0
}
update_install_script() {
    log_info "Checking for install script updates..."
    local script_url="$REPO_BASE_URL/install.sh/"
    local temp_script=$(mktemp)
    local current_script="$CONFIG_DIR/install.sh"
    if ! curl -s -L -o "$temp_script" "$script_url"; then
        log_error "Failed to download latest install script"
        rm -f "$temp_script"
        return 1
    fi
    if ! bash -n "$temp_script" 2>/dev/null; then
        log_error "Downloaded install script has syntax errors"
        rm -f "$temp_script"
        return 1
    fi
    local current_version=$(grep -oP 'Installer version: \K[0-9.]+' "$current_script" 2>/dev/null || echo "unknown")
    local new_version=$(grep -oP 'Installer version: \K[0-9.]+' "$temp_script" 2>/dev/null || echo "unknown")
    log_info "Current install script version: $current_version"
    log_info "Latest install script version: $new_version"
    if [[ "$current_version" == "$new_version" && "$current_version" != "unknown" ]]; then
        log_success "Install script is already up to date"
        rm -f "$temp_script"
        return 0
    fi
    sudo cp "$temp_script" "$current_script"
    sudo chmod +x "$current_script"
    if [[ -L "$SYMLINK_PATH" ]]; then
        sudo ln -sf "$current_script" "$SYMLINK_PATH"
    fi
    rm -f "$temp_script"
    log_success "Install script updated from $current_version to $new_version"
    log_warning "Please re-run your update command to use the new script version"
    return 0
}
show_version() {
    load_config
    local current_version="${INDEXER_VERSION:-not installed}"
    local latest_version=$(get_repo_version)
    echo "5q12's Indexer Version Information"
    echo "=================================="
    echo "Installed version: $current_version"
    echo "Latest version: $latest_version"
    if [[ -n "$INDEXER_PATH" ]]; then
        echo "Installation path: $INDEXER_PATH"
    fi
    if [[ -n "$INDEXER_INSTALL_DATE" ]]; then
        echo "Installation date: $INDEXER_INSTALL_DATE"
    fi
    if [[ "$current_version" != "not installed" && "$current_version" != "$latest_version" ]]; then
        echo
        log_warning "Update available! Run '$SCRIPT_NAME update' to upgrade."
    elif [[ "$current_version" == "$latest_version" && "$current_version" != "not installed" ]]; then
        echo
        log_success "You have the latest version installed."
    fi
}
setup_script() {
    if check_root; then
        log_info "Setting up system-wide script access..."
        sudo mkdir -p "$CONFIG_DIR"
        sudo cp "$0" "$CONFIG_DIR/install.sh"
        sudo chmod +x "$CONFIG_DIR/install.sh"
        sudo ln -sf "$CONFIG_DIR/install.sh" "$SYMLINK_PATH"
        log_success "Script installed! You can now use '$SCRIPT_NAME <command>'"
    fi
}
show_usage() {
    echo "5q12's Indexer Installation Script v1.2.0-r2"
    echo "Usage: $SCRIPT_NAME <command> [options]"
    echo
    echo "Commands:"
    echo "  install <path>                  (Install indexer to specified directory)"
    echo "  install <path> -source -<path>  (Download source files from repository)"
    echo "  update                          (Update existing installation)"
    echo "  version | v                     (Show version information)"
    echo "  help | -h                       (Show this help message)"
    echo
    echo "Examples:"
    echo "  $SCRIPT_NAME install /var/www/html/indexer (install to folder /var/www/html/indexer)"
    echo "  $SCRIPT_NAME install /test                 (install to folder /test)"
    echo "  $SCRIPT_NAME update                        (update to latest version)"
    echo "  $SCRIPT_NAME version                       (see currently installed version)"
    echo "  $SCRIPT_NAME install /var/www/html/indexer -source -all              (all files)"
    echo "  $SCRIPT_NAME install /var/www/html/indexer -source -docker           (docker dir)"
    echo "  $SCRIPT_NAME install /var/www/html/indexer -source -docker/config    (nested dir)"
    echo "  $SCRIPT_NAME install /var/www/html/indexer -source -index.php        (single file)"
}
main() {
    local command="$1"
    local install_path="$2"
    if [[ ! -f "$SYMLINK_PATH" ]]; then
        setup_script
    fi
    case "$command" in
        "install")
            if [[ -z "$install_path" ]]; then
                log_error "Installation path is required"
                echo "Usage: $SCRIPT_NAME install <path>"
                exit 1
            fi
            if ! check_root; then
                log_error "Installation requires root privileges. Please run with sudo."
                exit 1
            fi
            local use_source=false
            for arg in "$@"; do
                if [[ "$arg" == "-source" ]]; then
                    use_source=true
                    break
                fi
            done
            if [[ "$use_source" == "true" ]]; then
                if install_source "$install_path" "$@"; then
                    log_success "Source installation completed successfully!"
                else
                    log_error "Source installation failed"
                    exit 1
                fi
            else
                if install_indexer "$install_path"; then
                    log_success "Installation completed successfully!"
                else
                    log_error "Installation failed"
                    exit 1
                fi
            fi
            ;;
        "update")
            if ! check_root; then
                log_error "Update requires root privileges. Please run with sudo."
                exit 1
            fi
            if update_indexer; then
                log_success "Update completed successfully!"
            else
                log_error "Update failed"
                exit 1
            fi
            ;;
        "version"|"v")
            show_version
            ;;
        "help"|"-h"|"")
            show_usage
            ;;
        *)
            log_error "Unknown command: $command"
            show_usage
            exit 1
            ;;
    esac
}
main "$@"