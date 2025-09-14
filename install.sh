#!/bin/bash

# 5q12's Indexer Installation Script
# Installer version: 1.0.0

set -e
SCRIPT_NAME="5q12-index"
CONFIG_DIR="/etc/5q12-indexer"
CONFIG_FILE="$CONFIG_DIR/indexer.conf"
BACKUP_DIR="$CONFIG_DIR/backups"
SYMLINK_PATH="/usr/local/bin/$SCRIPT_NAME"
NGINX_CONFIG_PATH="/etc/nginx/sites-available/5q12-indexer.conf"
NGINX_ENABLED_PATH="/etc/nginx/sites-enabled/5q12-indexer.conf"
INDEX_URL="https://github.com/5q12-ccls/5q12-s-Indexer/raw/main/index.php"
NGINX_CONFIG_URL="https://github.com/5q12-ccls/5q12-s-Indexer/raw/main/5q12-indexer.conf"
VERSION_URL="https://api.indexer.ccls.icu/version.txt"
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
get_latest_version() {
    curl -s "$VERSION_URL" 2>/dev/null | tr -d '\n\r' || echo "unknown"
}
get_installation_config() {
    if [[ -f "$CONFIG_FILE" ]]; then
        source "$CONFIG_FILE" 2>/dev/null || true
    fi
}
get_current_version() {
    get_installation_config
    if [[ -n "$INDEXER_VERSION" ]]; then
        echo "$INDEXER_VERSION"
    else
        echo "not installed"
    fi
}
get_installation_path() {
    get_installation_config
    if [[ -n "$INDEXER_PATH" ]]; then
        echo "$INDEXER_PATH"
    else
        echo ""
    fi
}
save_installation_config() {
    local version="$1"
    local path="$2"
    local install_date="$3"
    sudo mkdir -p "$CONFIG_DIR"
    sudo mkdir -p "$BACKUP_DIR"
    cat << EOF | sudo tee "$CONFIG_FILE" > /dev/null
INDEXER_VERSION="$version"
INDEXER_PATH="$path"
INDEXER_INSTALL_DATE="$install_date"
INDEXER_NGINX_CONFIG="$NGINX_CONFIG_PATH"
INDEXER_SCRIPT_VERSION="1.0.0"
EOF
    log_info "Installation config saved to $CONFIG_FILE"
}
check_requirements() {
    local missing_requirements=()
    local nginx_ok=false
    local php_ok=false
    log_info "Checking system requirements..."
    if command -v nginx >/dev/null 2>&1; then
        local nginx_version=$(nginx -v 2>&1 | grep -oP 'nginx/\K[0-9.]+')
        local required_version="1.14"
        if [[ $(printf '%s\n' "$required_version" "$nginx_version" | sort -V | head -n1) = "$required_version" ]]; then
            log_success "Nginx $nginx_version found (>= $required_version required)"
            nginx_ok=true
        else
            log_warning "Nginx $nginx_version found but version >= $required_version required"
            missing_requirements+=("nginx-upgrade")
        fi
    else
        log_warning "Nginx not found"
        missing_requirements+=("nginx")
    fi
    if command -v php >/dev/null 2>&1; then
        local php_version=$(php -v | grep -oP 'PHP \K[0-9.]+' | head -n1)
        local required_php="8.3"
        if [[ $(printf '%s\n' "$required_php" "$php_version" | sort -V | head -n1) = "$required_php" ]]; then
            log_success "PHP $php_version found (>= $required_php required)"
            php_ok=true
        else
            log_warning "PHP $php_version found but version >= $required_php required"
            missing_requirements+=("php-upgrade")
        fi
    else
        log_warning "PHP not found"
        missing_requirements+=("php")
        php_ok=false
    fi
    echo "${missing_requirements[@]}"
}
check_and_install_php_extensions() {
    log_info "Checking PHP extensions..."
    local required_extensions=("json" "fileinfo" "mbstring" "sqlite3" "zip" "curl" "openssl")
    local missing_extensions=()
    for ext in "${required_extensions[@]}"; do
        if ! php -m | grep -q "^$ext$"; then
            missing_extensions+=("$ext")
        fi
    done
    if [[ ${#missing_extensions[@]} -eq 0 ]]; then
        log_success "All required PHP extensions found"
        return 0
    fi
    log_warning "Missing PHP extensions: ${missing_extensions[*]}"
    log_info "Installing missing PHP extensions..."
    sudo apt install -y php8.3-mbstring php8.3-sqlite3 php8.3-zip php8.3-curl
    sudo systemctl restart php8.3-fpm || sudo systemctl restart php-fpm
    log_info "Verifying PHP extensions..."
    local still_missing=()
    for ext in "${required_extensions[@]}"; do
        if ! php -m | grep -q "^$ext$"; then
            still_missing+=("$ext")
        fi
    done
    if [[ ${#still_missing[@]} -eq 0 ]]; then
        log_success "All PHP extensions now available"
    else
        log_warning "Some extensions still missing: ${still_missing[*]}"
        log_info "These may be included under different names or already available"
    fi
}
install_requirements() {
    local requirements=("$@")
    if [[ ${#requirements[@]} -eq 0 ]]; then
        log_success "All requirements are already satisfied"
        return 0
    fi
    log_warning "Missing requirements detected: ${requirements[*]}"
    echo
    read -p "Would you like to install the missing requirements? (y/n): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        log_error "Installation cannot continue without required dependencies"
        exit 1
    fi
    log_info "Installing missing requirements..."
    sudo apt update
    log_info "Checking for conflicting web servers..."
    if systemctl is-active --quiet apache2; then
        log_warning "Apache2 is running, stopping and disabling it..."
        sudo systemctl stop apache2
        sudo systemctl disable apache2
    fi
    if systemctl is-active --quiet lighttpd; then
        log_warning "Lighttpd is running, stopping and disabling it..."
        sudo systemctl stop lighttpd
        sudo systemctl disable lighttpd
    fi
    for req in "${requirements[@]}"; do
        case $req in
            "nginx"|"nginx-upgrade")
                log_info "Installing/upgrading Nginx..."
                sudo apt install -y nginx
                sudo systemctl enable nginx
                sudo systemctl start nginx
                ;;
            "php"|"php-upgrade")
                log_info "Installing/upgrading PHP and PHP-FPM (without Apache2)..."
                sudo apt install -y php-fpm php-cli
                sudo systemctl enable php8.3-fpm || sudo systemctl enable php-fpm
                sudo systemctl start php8.3-fpm || sudo systemctl start php-fpm
                ;;
        esac
    done
    if dpkg -l | grep -q apache2; then
        log_info "Removing Apache2 that was installed as dependency..."
        sudo apt remove -y apache2 apache2-bin apache2-data apache2-utils libapache2-mod-php8.3 || true
        sudo apt autoremove -y || true
    fi
    log_success "Requirements installation completed"
}
create_nginx_config() {
    local web_path="$1"
    log_info "Creating Nginx configuration..."
    local temp_config=$(mktemp)
    if ! curl -s -L -o "$temp_config" "$NGINX_CONFIG_URL"; then
        log_error "Failed to download nginx config template"
        rm -f "$temp_config"
        exit 1
    fi
    sed "s|{WEB_PATH}|$web_path|g" "$temp_config" | sudo tee "$NGINX_CONFIG_PATH" > /dev/null
    rm -f "$temp_config"
    sudo ln -sf "$NGINX_CONFIG_PATH" "$NGINX_ENABLED_PATH"
    if ! sudo nginx -t; then
        log_error "Nginx configuration test failed"
        log_info "Config file content:"
        sudo cat "$NGINX_CONFIG_PATH"
        exit 1
    fi
    sudo systemctl reload nginx
    log_success "Nginx configuration created and enabled"
    log_info "Document root: $web_path"
    log_info "Config file: $NGINX_CONFIG_PATH"
}
install_indexer() {
    local install_path="$1"
    if [[ -z "$install_path" ]]; then
        log_error "Installation path is required"
        exit 1
    fi
    if [[ ! "$install_path" = /* ]]; then
        install_path="$(pwd)/$install_path"
    fi
    if [[ ! -d "$install_path" ]]; then
        log_info "Creating directory: $install_path"
        sudo mkdir -p "$install_path"
    fi
    sudo chown -R www-data:www-data "$install_path"
    sudo chmod 755 "$install_path"
    log_info "Downloading index.php..."
    if ! sudo curl -s -L -o "$install_path/index.php" "$INDEX_URL"; then
        log_error "Failed to download index.php"
        exit 1
    fi
    sudo chmod 644 "$install_path/index.php"
    sudo chown www-data:www-data "$install_path/index.php"
    log_success "Index.php downloaded to $install_path"
    log_info "Initializing indexer..."
    log_info "Initializing indexer configuration..."
    if ! (cd "$install_path" && sudo -u www-data php index.php > /dev/null 2>&1); then
        sudo chmod 755 "$install_path"
        if ! (cd "$install_path" && sudo -u www-data php index.php > /dev/null 2>&1); then
            log_warning "Could not initialize via PHP, indexer will auto-initialize on first web access"
        fi
    fi
    log_success "Indexer initialized successfully"
    local latest_version=$(get_latest_version)
    local install_date=$(date '+%Y-%m-%d %H:%M:%S')
    save_installation_config "$latest_version" "$install_path" "$install_date"
    log_success "Installation completed!"
    log_info "Indexer installed at: $install_path"
    log_info "Version: $latest_version"
    log_info "Nginx config: $NGINX_CONFIG_PATH"
    log_info "Access URL: http://localhost:5012"
    echo "$install_path"
}
update_indexer() {
    log_info "Checking for updates..."
    local current_version=$(get_current_version)
    local latest_version=$(get_latest_version)
    local install_path=$(get_installation_path)
    if [[ "$current_version" == "not installed" ]]; then
        log_error "No installation found. Use 'install' command first."
        exit 1
    fi
    if [[ -z "$install_path" ]]; then
        log_error "Installation path not found in config. Please reinstall."
        exit 1
    fi
    if [[ ! -d "$install_path" ]]; then
        log_error "Installation directory '$install_path' no longer exists. Please reinstall."
        exit 1
    fi
    if [[ ! -f "$install_path/index.php" ]]; then
        log_error "Index.php not found at '$install_path'. Please reinstall."
        exit 1
    fi
    if [[ "$current_version" == "$latest_version" ]]; then
        log_success "Already up to date (version $current_version)"
        return 0
    fi
    log_info "Current version: $current_version"
    log_info "Latest version: $latest_version"
    log_info "Installation path: $install_path"
    sudo mkdir -p "$BACKUP_DIR"
    local backup_name="index.php.backup.$(date +%Y%m%d_%H%M%S)"
    local backup_path="$BACKUP_DIR/$backup_name"
    sudo cp "$install_path/index.php" "$backup_path"
    log_info "Created backup: $backup_path"
    log_info "Downloading latest version..."
    if ! sudo curl -s -L -o "$install_path/index.php" "$INDEX_URL"; then
        log_error "Failed to download latest version"
        log_info "Restoring backup..."
        sudo cp "$backup_path" "$install_path/index.php"
        exit 1
    fi
    sudo chmod 644 "$install_path/index.php"
    sudo chown www-data:www-data "$install_path/index.php"
    local update_date=$(date '+%Y-%m-%d %H:%M:%S')
    save_installation_config "$latest_version" "$install_path" "$update_date"
    log_success "Updated from $current_version to $latest_version"
    log_info "Backup available at: $backup_path"
}
show_version() {
    local current_version=$(get_current_version)
    local latest_version=$(get_latest_version)
    local install_path=$(get_installation_path)
    echo "5q12's Indexer Version Information"
    echo "=================================="
    echo "Installed version: $current_version"
    echo "Latest version: $latest_version"
    if [[ -n "$install_path" ]]; then
        echo "Installation path: $install_path"
    fi
    if [[ -f "$CONFIG_FILE" ]]; then
        get_installation_config
        if [[ -n "$INDEXER_INSTALL_DATE" ]]; then
            echo "Installation date: $INDEXER_INSTALL_DATE"
        fi
    fi
    if [[ "$current_version" != "not installed" && "$current_version" != "$latest_version" ]]; then
        echo
        log_warning "Update available! Run '$SCRIPT_NAME update' to upgrade."
    elif [[ "$current_version" == "$latest_version" ]]; then
        echo
        log_success "You have the latest version installed."
    fi
}
setup_script() {
    log_info "Setting up system-wide script access..."
    sudo mkdir -p "$CONFIG_DIR"
    sudo mkdir -p "$BACKUP_DIR"
    sudo cp "$0" "$CONFIG_DIR/install.sh"
    sudo chmod +x "$CONFIG_DIR/install.sh"
    sudo ln -sf "$CONFIG_DIR/install.sh" "$SYMLINK_PATH"
    log_success "Script installed! You can now use '$SCRIPT_NAME <command>'"
}
show_usage() {
    echo "5q12's Indexer Installation Script"
    echo "Usage: $SCRIPT_NAME <command> [options]"
    echo
    echo "Commands:"
    echo "  install <path>    Install indexer to specified directory"
    echo "  update            Update existing installation"
    echo "  version | v       Show version information"
    echo "  help | -h         Show this help message"
    echo
    echo "Examples:"
    echo "  $SCRIPT_NAME install /var/www/html/files"
    echo "  $SCRIPT_NAME install www"
    echo "  $SCRIPT_NAME update"
    echo "  $SCRIPT_NAME version"
}
main() {
    local command="$1"
    local install_path="$2"
    if [[ ! -f "$SYMLINK_PATH" ]]; then
        if check_root; then
            setup_script
        else
            log_warning "Run with sudo to enable system-wide access"
        fi
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
            local missing_req=($(check_requirements))
            install_requirements "${missing_req[@]}"
            check_and_install_php_extensions
            local absolute_path
            absolute_path=$(install_indexer "$install_path" | tail -1)
            create_nginx_config "$absolute_path"
            log_info "Final system verification:"
            log_info "PHP Extensions: $(php -m | grep -E 'json|fileinfo|mbstring|sqlite3|zip|curl|openssl' | tr '\n' ' ')"
            ;;
        "update")
            if ! check_root; then
                log_error "Update requires root privileges. Please run with sudo."
                exit 1
            fi
            update_indexer
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