# Automated Script Installation

## Overview

The automated installation script is the easiest way to install 5q12's Indexer. It handles all dependencies, configuration, and setup automatically.

### Features
- **Automatic dependency installation** - Installs Nginx, PHP, and required extensions
- **Conflict resolution** - Stops and disables conflicting web servers (Apache2, Lighttpd)
- **Nginx configuration** - Creates optimized configuration for the indexer
- **PHP optimization** - Installs and configures PHP-FPM with required extensions
- **System integration** - Sets up system-wide command access
- **Update management** - Built-in update checking and upgrading
- **Configuration creation** - Creates basic configuration files

### Compatibility

**Tested and Supported:**
- Ubuntu Server 20.04 LTS
- Ubuntu Server 22.04 LTS
- Ubuntu Server 24.04 LTS

**May Work (Untested):**
- Other Debian-based distributions
- Arch Linux (with manual package management adjustments)
- Other systemd-based distributions

**Not Supported:**
- CentOS/RHEL (different package manager)
- Alpine Linux (different init system)
- Windows/macOS

## Installation

### Step 1: Download the Script

```bash
# Download the installation script
wget https://ccls.icu/src/repositories/5q12-indexer/main/install.sh

# Make it executable
chmod +x install.sh
```

### Step 2: Run Installation

```bash
# Install to a web directory
sudo ./install.sh install /var/www/html/files

# Or install to a relative path
sudo ./install.sh install files

# Or install to home directory
sudo ./install.sh install ~/public_html/files
```

**Installation Path Examples:**
- `/var/www/html/files` - Standard web server location
- `/home/user/www/files` - User home directory
- `./files` - Current directory (converts to absolute path)

### Step 3: Access Your Indexer

After installation, your indexer will be available at:
```
http://your-server-ip:5012
```

Or if running locally:
```
http://localhost:5012
```

## System Requirements

### Required
- **Operating System**: Debian-based Linux distribution
- **Memory**: 512MB RAM minimum (1GB+ recommended)
- **Disk Space**: 100MB free space
- **Network**: Internet connection for package downloads
- **Privileges**: sudo/root access

### Automatically Installed
- **Nginx** (latest stable)
- **PHP 8.3** with PHP-FPM
- **PHP Extensions**: json, fileinfo, mbstring, sqlite3, zip, curl, openssl

## Command Reference

Once installed, you can use the `5q12-index` command system-wide:

### Installation
```bash
# Install to specified directory
sudo 5q12-index install /path/to/directory
```

### Update Management
```bash
# Check for updates
5q12-index version

# Update to latest version
sudo 5q12-index update
```

### Configuration Management
```bash
# Create default configuration
sudo 5q12-index create-config /path/to/installation

# Validate configuration
5q12-index validate-config /path/to/installation
```

### Help
```bash
# Show help information
5q12-index help
```

## Installation Process Details

### What the Script Does

1. **System Check**
   - Verifies OS compatibility
   - Checks for existing installations
   - Validates system requirements

2. **Dependency Management**
   - Updates package lists
   - Installs Nginx and PHP-FPM
   - Removes conflicting web servers
   - Installs required PHP extensions

3. **Configuration**
   - Creates optimized Nginx configuration
   - Sets up PHP-FPM integration
   - Configures proper file permissions
   - Creates system directories

4. **Indexer Setup**
   - Downloads latest index.php from repository
   - Creates basic configuration file
   - Sets proper ownership (www-data)
   - Initializes cache directories
   - Creates management symlinks

5. **Service Management**
   - Enables and starts Nginx
   - Enables and starts PHP-FPM
   - Tests configuration validity
   - Reloads services

### Generated Files and Directories

**System Configuration:**
- `/etc/5q12-indexer/indexer.conf` - Installation configuration
- `/etc/5q12-indexer/backups/` - Backup directory
- `/etc/nginx/sites-available/5q12-indexer.conf` - Nginx configuration
- `/usr/local/bin/5q12-index` - System command symlink

**Indexer Files:**
- `{install-path}/index.php` - Main indexer file
- `{install-path}/.indexer_files/` - Configuration and cache directories
- `{install-path}/.indexer_files/config.json` - Basic configuration

## Troubleshooting

### Permission Issues
```bash
# Fix ownership if needed
sudo chown -R www-data:www-data /path/to/installation

# Fix permissions
sudo chmod 755 /path/to/installation
sudo chmod 644 /path/to/installation/index.php
```

### Service Issues
```bash
# Check service status
sudo systemctl status nginx
sudo systemctl status php8.3-fpm

# Restart services
sudo systemctl restart nginx
sudo systemctl restart php8.3-fpm

# Check nginx configuration
sudo nginx -t
```

### Network Issues
```bash
# Check if port 5012 is open
sudo netstat -tlnp | grep :5012

# Check firewall (if applicable)
sudo ufw status
sudo ufw allow 5012
```

### PHP Extension Issues
```bash
# Check installed extensions
php -m | grep -E 'json|sqlite3|zip|curl'

# Install missing extensions manually
sudo apt install php8.3-sqlite3 php8.3-zip php8.3-curl php8.3-mbstring
```

### Configuration Issues
```bash
# Check configuration exists
ls -la /path/to/installation/.indexer_files/config.json

# Validate configuration
python -m json.tool /path/to/installation/.indexer_files/config.json

# Recreate configuration if needed
sudo 5q12-index create-config /path/to/installation
```

### Common Error Solutions

**"Port 5012 already in use"**
- Check for other services using the port
- Modify nginx configuration to use different port

**"Permission denied"**
- Ensure running with sudo
- Check directory permissions
- Verify www-data user exists

**"Package not found"**
- Update package lists: `sudo apt update`
- Check internet connection
- Verify distribution compatibility

**"Configuration file not found"**
- Run: `sudo 5q12-index create-config /path/to/installation`
- Check file permissions: `ls -la .indexer_files/config.json`

## Manual Cleanup

If you need to completely remove the installation:

```bash
# Remove nginx configuration
sudo rm -f /etc/nginx/sites-available/5q12-indexer.conf
sudo rm -f /etc/nginx/sites-enabled/5q12-indexer.conf

# Remove system files
sudo rm -rf /etc/5q12-indexer
sudo rm -f /usr/local/bin/5q12-index

# Remove indexer files (replace with your path)
sudo rm -rf /path/to/your/installation

# Reload nginx
sudo systemctl reload nginx
```

## Advanced Configuration

### Custom Port
Edit `/etc/nginx/sites-available/5q12-indexer.conf` and change:
```nginx
listen 5012;
```
To your desired port, then reload nginx:
```bash
sudo systemctl reload nginx
```

### SSL/HTTPS Setup
Add SSL certificate configuration to your nginx config:
```nginx
listen 443 ssl;
ssl_certificate /path/to/certificate.pem;
ssl_certificate_key /path/to/private.key;
```

### Custom PHP Settings
Edit `/etc/php/8.3/fpm/php.ini` for global changes or create pool-specific configuration.

## Next Steps

After successful installation:

1. **Review the configuration** - Edit `.indexer_files/config.json` as needed
2. **Secure your installation** - See [Security Guide](security.md)
3. **Customize settings** - See [Configuration Guide](configuration.md)
4. **Set up backups** - Regular backups of your files and configuration

---

**Related Documentation:**
- [Configuration Guide](configuration.md) - Customize indexer settings
- [Security Guide](security.md) - Secure your installation
- [Troubleshooting Guide](troubleshooting.md) - Common issues and solutions