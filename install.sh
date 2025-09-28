#!/bin/bash

# 5q12's Indexer Installation Script
# Installer version: 3.0.0

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
INDEXER_SCRIPT_VERSION="3.0.0"
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
    
    # Download index.php only from main root
    if [[ "$filename" == "index.php" && "$file_url" == "https://ccls.icu/src/repositories/5q12-indexer/main/index.php/" ]]; then
        return 0
    fi
    
    # DO NOT download 5q12-indexer.conf to installation directory
    # It should only be placed in nginx sites-available directory
    if [[ "$filename" == "5q12-indexer.conf" ]]; then
        return 1
    fi
    
    # Download all files from indexer_files/ directory and subdirectories
    if [[ "$file_url" == *"/main/indexer_files/"* ]]; then
        return 0
    fi
    
    return 1
}

# Function to merge JSON configs using PHP (mirrors entrypoint.sh approach)
merge_config_json() {
    local existing_config="$1"
    local default_config="$2"
    local output_config="$3"
    
    # Create a temporary PHP script to merge configs
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

    # Run the merge script
    php "$merge_script" "$existing_config" "$default_config" "$output_config"
    local merge_result=$?
    
    # Clean up temporary script
    rm -f "$merge_script"
    
    return $merge_result
}

# Enhanced function to handle config.json updates during installation/update
handle_config_json_update() {
    local install_path="$1"
    
    local config_dir="$install_path/.indexer_files"
    local current_config="$config_dir/config.json"
    local temp_default_config=$(mktemp)
    
    log_info "Handling config.json update with advanced merging..."
    
    # Ensure config directory exists
    if [[ ! -d "$config_dir" ]]; then
        sudo mkdir -p "$config_dir"
        sudo chown www-data:www-data "$config_dir"
        sudo chmod 755 "$config_dir"
    fi
    
    # Download the latest default config
    local default_config_url="$REPO_BASE_URL/indexer_files/config.json/"
    if ! curl -s -L -o "$temp_default_config" "$default_config_url"; then
        log_error "Failed to download default config.json"
        rm -f "$temp_default_config"
        return 1
    fi
    
    # Handle different scenarios
    if [[ -f "$current_config" ]]; then
        # We have existing config - merge with new default
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
        # No existing config - use new default
        log_info "No existing config found, using new default..."
        sudo cp "$temp_default_config" "$current_config"
        sudo chown www-data:www-data "$current_config"
        sudo chmod 644 "$current_config"
        log_success "Default config.json installed"
    fi
    
    # Clean up temp file
    rm -f "$temp_default_config"
    
    # Verify the final config is valid JSON
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
    
    # Create temp file for repo data
    local repo_file=$(mktemp)
    if ! curl -s -L "$REPO_LIST_URL" > "$repo_file"; then
        log_error "Failed to fetch repository file list"
        rm -f "$repo_file"
        return 1
    fi

    # Debug: Show first few lines of repo file
    log_info "Repository file first 10 lines:"
    head -n 10 "$repo_file" | while read line; do
        log_info "REPO LINE: $line"
    done
    
    log_info "Processing file list from repository..."
    
    local total_files=0
    local downloaded_files=0
    local failed_downloads=()
    
    # First pass: count files to download
    while IFS= read -r line; do
        # Skip version line
        if [[ $line =~ ^VERSION= ]]; then
            continue
        fi
        
        # Skip empty lines
        if [[ -z "$line" ]]; then
            continue
        fi
        
        # Check if line has two quoted parts (filename and URL)
        if [[ $line == *\"*\"* ]] && [[ $(echo "$line" | grep -o '"' | wc -l) -eq 4 ]]; then
            # Extract filename and URL
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
    
    # Second pass: download files
    while IFS= read -r line; do
        # Skip version line and empty lines
        if [[ $line =~ ^VERSION= ]] || [[ -z "$line" ]]; then
            continue
        fi
        
        # Check if line has two quoted parts
        if [[ $line == *\"*\"* ]] && [[ $(echo "$line" | grep -o '"' | wc -l) -eq 4 ]]; then
            local filename=$(echo "$line" | cut -d'"' -f2)
            local fileurl=$(echo "$line" | cut -d'"' -f4)
            
            if ! should_download_file "$fileurl" "$filename"; then
                continue
            fi
            
            # Determine local path
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
            
            # Create directory if needed
            if [[ ! -d "$dir_path" ]]; then
                sudo mkdir -p "$dir_path"
                sudo chown www-data:www-data "$dir_path"
                sudo chmod 755 "$dir_path"
            fi
            
            # Download file
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
    
    # Create files directory for user content
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

check_system_requirements() {
    log_info "Checking system requirements..."
    
    local nginx_ok=false
    local php_ok=false
    local php_ext_ok=false
    local missing_requirements=()
    
    # Check Nginx
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
    
    # Check PHP
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
    
    # Check SQLite3 command line tool
    if ! command -v sqlite3 >/dev/null 2>&1; then
        log_warning "SQLite3 not found"
        missing_requirements+=("sqlite3")
    else
        log_success "SQLite3 found"
    fi
    
    # Check PHP extensions if PHP is available
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
    
    # Return status
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
    
    # Stop conflicting web servers
    for service in apache2 lighttpd; do
        if systemctl is-active --quiet "$service"; then
            log_warning "Stopping $service..."
            sudo systemctl stop "$service"
            sudo systemctl disable "$service"
        fi
    done
    
    # Install each requirement
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
                # Find the actual PHP-FPM service name
                local php_service=$(systemctl list-units --type=service | grep -E "php[0-9.]*-fpm" | awk '{print $1}' | head -n1)
                if [[ -n "$php_service" ]]; then
                    sudo systemctl enable "$php_service"
                    sudo systemctl start "$php_service"
                fi
                ;;
            "sqlite3")
                log_info "Installing SQLite3..."
                sudo apt install -y sqlite3
                ;;
            "php-extensions")
                log_info "Installing PHP extensions..."
                sudo apt install -y php-mbstring php-sqlite3 php-zip php-curl
                local php_service=$(systemctl list-units --type=service | grep -E "php[0-9.]*-fpm" | awk '{print $1}' | head -n1)
                if [[ -n "$php_service" ]]; then
                    sudo systemctl restart "$php_service"
                fi
                ;;
        esac
    done
    
    # Clean up Apache if it was installed as dependency
    if dpkg -l | grep -q apache2; then
        log_info "Removing unwanted Apache2..."
        sudo apt remove -y apache2* libapache2* || true
        sudo apt autoremove -y
    fi
    
    log_success "Dependencies installed successfully"
    return 0
}

create_nginx_config() {
    local web_path="$1"
    
    log_info "Creating Nginx configuration..."
    
    # Download nginx config template
    local temp_config=$(mktemp)
    if ! curl -s -L -o "$temp_config" "$REPO_BASE_URL/5q12-indexer.conf/"; then
        log_error "Failed to download Nginx config template"
        rm -f "$temp_config"
        return 1
    fi
    
    # Replace path placeholder and install
    sed "s|{WEB_PATH}|$web_path|g" "$temp_config" | sudo tee "$NGINX_CONFIG_PATH" > /dev/null
    rm -f "$temp_config"
    
    # Enable the site
    sudo ln -sf "$NGINX_CONFIG_PATH" "$NGINX_ENABLED_PATH"
    
    # Test configuration
    if ! sudo nginx -t; then
        log_error "Nginx configuration test failed"
        return 1
    fi
    
    # Reload nginx
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
    
    # Ensure the .indexer_files directory exists
    if [[ ! -d "$marker_dir" ]]; then
        sudo mkdir -p "$marker_dir"
        sudo chown www-data:www-data "$marker_dir"
        sudo chmod 755 "$marker_dir"
    fi
    
    # Create empty .script file
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
    
    # Convert to absolute path
    if [[ ! "$install_path" = /* ]]; then
        install_path="$(pwd)/$install_path"
    fi
    
    log_info "Installing indexer to: $install_path"
    
    # Create installation directory
    if [[ ! -d "$install_path" ]]; then
        sudo mkdir -p "$install_path"
    fi
    
    sudo chown -R www-data:www-data "$install_path"
    sudo chmod 755 "$install_path"
    
    # Download all indexer files (includes default config.json)
    if ! download_indexer_files "$install_path"; then
        log_error "Failed to download indexer files"
        return 1
    fi
    
    # Create script installation marker
    create_script_marker "$install_path"
    
    # For fresh installation, just ensure the config file has proper permissions
    local config_file="$install_path/.indexer_files/config.json"
    if [[ -f "$config_file" ]]; then
        sudo chown www-data:www-data "$config_file"
        sudo chmod 644 "$config_file"
        log_success "Default config.json installed"
    else
        log_warning "config.json not found after download"
    fi
    
    # Test the installation
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
    
    # Now check system requirements
    log_info "Checking system dependencies..."
    local req_output=$(check_system_requirements)
    
    # Parse the output
    local nginx_ok=$(echo "$req_output" | grep "nginx_ok=" | cut -d= -f2)
    local php_ok=$(echo "$req_output" | grep "php_ok=" | cut -d= -f2)
    local php_ext_ok=$(echo "$req_output" | grep "php_ext_ok=" | cut -d= -f2)
    local missing_req=$(echo "$req_output" | grep "missing_requirements=" | cut -d= -f2-)
    
    # Install missing dependencies
    if [[ -n "$missing_req" && "$missing_req" != "" ]]; then
        if ! install_dependencies $missing_req; then
            log_error "Failed to install dependencies"
            return 1
        fi
    fi
    
    # Create nginx configuration
    if ! create_nginx_config "$install_path"; then
        log_error "Failed to create Nginx configuration"
        return 1
    fi
    
    # Save configuration
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
    
    # Get repo data
    local repo_file=$(mktemp)
    if ! curl -s -L "$REPO_LIST_URL" > "$repo_file"; then
        log_error "Failed to fetch repository file list"
        rm -f "$repo_file"
        return 1
    fi
    
    # Files/dirs to always replace completely
    local always_replace=(
        "index.php"
        ".indexer_files/config-reference.txt"
        ".indexer_files/local_api"
        ".indexer_files/local_api/style"
    )
    
    # Remove always-replace items first
    log_info "Removing files/directories that will be replaced..."
    for item in "${always_replace[@]}"; do
        local full_path="$install_path/$item"
        if [[ -e "$full_path" ]]; then
            sudo rm -rf "$full_path"
            log_info "Removed: $item"
        fi
    done
    
    # Process all files from repo
    local updated_files=0
    local skipped_files=0
    
    while IFS= read -r line; do
        # Skip version line and empty lines
        if [[ $line =~ ^VERSION= ]] || [[ -z "$line" ]]; then
            continue
        fi
        
        # Process file lines
        if [[ $line == *\"*\"* ]] && [[ $(echo "$line" | grep -o '"' | wc -l) -eq 4 ]]; then
            local filename=$(echo "$line" | cut -d'"' -f2)
            local fileurl=$(echo "$line" | cut -d'"' -f4)
            
            if ! should_download_file "$fileurl" "$filename"; then
                continue
            fi
            
            # Determine local path
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
            
            # Check if this should always be replaced or is missing
            local should_download=false
            local reason=""
            
            # Check if it's in always-replace list
            for item in "${always_replace[@]}"; do
                if [[ "$local_path" == "$item" ]] || [[ "$local_path" == "$item"/* ]]; then
                    should_download=true
                    reason="always replaced"
                    break
                fi
            done
            
            # If not always replaced, check if missing
            if [[ "$should_download" == "false" ]] && [[ ! -f "$full_path" ]]; then
                should_download=true
                reason="missing file"
            fi
            
            if [[ "$should_download" == "true" ]]; then
                # Create directory if needed
                if [[ ! -d "$dir_path" ]]; then
                    sudo mkdir -p "$dir_path"
                    sudo chown www-data:www-data "$dir_path"
                    sudo chmod 755 "$dir_path"
                fi
                
                # Download file
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
    
    # Ensure script marker exists after update
    create_script_marker "$install_path"
    
    log_success "Update completed: $updated_files files updated, $skipped_files files skipped"
    return 0
}

update_indexer() {
    load_config
    
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
    
    # Download new files with smart updating
    if ! smart_update_files "$INDEXER_PATH"; then
        log_error "Failed to update files"
        return 1
    fi
    
    # Handle config updates with enhanced method
    if ! handle_config_json_update "$INDEXER_PATH"; then
        log_error "Failed to update configuration"
        return 1
    fi
    
    # Update nginx config
    create_nginx_config "$INDEXER_PATH"
    
    # Update configuration
    local update_date=$(date '+%Y-%m-%d %H:%M:%S')
    save_config "$latest_version" "$INDEXER_PATH" "$update_date" "${NGINX_INSTALLED:-true}" "${PHP_INSTALLED:-true}" "${PHP_EXTENSIONS_INSTALLED:-true}"
    
    log_success "Updated from $current_version to $latest_version"
    log_info "Your existing configuration has been preserved and enhanced with new fields"
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
    echo "5q12's Indexer Installation Script v3.0.0"
    echo "Usage: $SCRIPT_NAME <command> [options]"
    echo
    echo "Commands:"
    echo "  install <path>    Install indexer to specified directory"
    echo "  update            Update existing installation"
    echo "  version | v       Show version information"
    echo "  help | -h         Show this help message"
    echo
    echo "Examples:"
    echo "  $SCRIPT_NAME install /var/www/html/indexer"
    echo "  $SCRIPT_NAME install /test"
    echo "  $SCRIPT_NAME update"
    echo "  $SCRIPT_NAME version"
}

main() {
    local command="$1"
    local install_path="$2"
    
    # Setup script if not already done
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
            
            if install_indexer "$install_path"; then
                log_success "Installation completed successfully!"
            else
                log_error "Installation failed"
                exit 1
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