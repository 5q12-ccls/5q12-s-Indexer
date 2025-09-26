# Manual Installation Guide

## Table of Contents
- [Overview](#overview)
- [System Requirements](#system-requirements)
- [Installation Methods](#installation-methods)
- [Web Server Configuration](#web-server-configuration)
- [Initial Setup](#initial-setup)
- [Troubleshooting Installation](#troubleshooting-installation)
- [Performance Optimization](#performance-optimization)

## Overview

Manual installation gives you complete control over the installation process and is suitable for advanced users who want to customize their setup or work with non-standard environments.

### When to Use Manual Installation
- Custom server configurations
- Non-Debian distributions
- Integration with existing web server setups
- Learning how the system works
- Environments where automated scripts can't be used

## System Requirements

### Minimum Requirements

| Component | Requirement |
|-----------|-------------|
| **PHP** | 8.3+ |
| **Web Server** | Apache, Nginx, IIS, or any PHP-compatible server |
| **Memory** | 64MB PHP memory limit |
| **Disk Space** | 50MB for cache and temporary files |
| **Extensions** | `json` (usually enabled by default) |

### Recommended Requirements

| Component | Requirement | Benefit |
|-----------|-------------|---------|
| **PHP** | 8.3+ | Better performance and security |
| **Memory** | 128MB+ | Handle large directories efficiently |
| **Disk Space** | 200MB+ | Optimal caching and icon storage |
| **SQLite3** | Extension enabled | 5-10x faster caching |
| **ZipArchive** | Extension enabled | Folder download functionality |

### Required PHP Extensions

**Core Extensions (Usually Enabled)**
- `json` - Configuration file handling
- `curl` or `allow_url_fopen` - API communication

**Optional Extensions**  
- `sqlite3` - High-performance caching (highly recommended)
- `zip` - Folder download as ZIP archives
- `gd` or `imagick` - Enhanced icon handling

### Checking Your Environment

**Check PHP version:**
```bash
php --version
```

**Check available extensions:**
```bash
php -m | grep -E "(sqlite|zip|json|curl)"
```

**Check PHP configuration:**
```php
<?php phpinfo(); ?>
```

## Installation

### Method 1: CCLS repository

```bash
# Download indexer repository
wget https://ccls.icu/src/repositories/5q12-indexer/main/?download=archive -O 5q12-indexer.zip

# Move to desired location
sudo mv main/* main/.* /var/www/html/ 2>/dev/null
```

### Method 2: GitHub

```bash
# Clone repository
git clone https://github.com/5q12-ccls/5q12-s-Indexer.git
cd repo

# Copy to web directory
cp repo/ /var/www/html/dev/
```

## Web Server Configuration

### Apache Configuration

**Basic .htaccess example:**
```apache
# Allow indexer execution
<Files "index.php">
    Require all granted
</Files>

# Optional: Hide sensitive files
<Files "config.json">
    Require all denied
</Files>

# Optional: Custom error handling
ErrorDocument 404 /index.php
```

**Virtual host example:**
```apache
<VirtualHost *:80>
    ServerName files.yourdomain.com
    DocumentRoot /var/www/html/files
    
    <Directory "/var/www/html/files">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

### Nginx Configuration

**Basic server block:**
```nginx
server {
    listen 80;
    server_name files.yourdomain.com;
    root /var/www/html/files;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Security: Hide sensitive files
    location ~ /\.(indexer_files|git|env) {
        deny all;
    }
}
```

### IIS Configuration (Windows)

**web.config example:**
```xml
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
    <system.webServer>
        <defaultDocument>
            <files>
                <clear />
                <add value="index.php" />
            </files>
        </defaultDocument>
        
        <rewrite>
            <rules>
                <rule name="Indexer" stopProcessing="true">
                    <match url=".*" />
                    <conditions>
                        <add input="{REQUEST_FILENAME}" matchType="IsFile" negate="true" />
                        <add input="{REQUEST_FILENAME}" matchType="IsDirectory" negate="true" />
                    </conditions>
                    <action type="Rewrite" url="index.php" />
                </rule>
            </rules>
        </rewrite>
    </system.webServer>
</configuration>
```

### Manual Setup

```bash
# Create directory structure
mkdir -p .indexer_files/{zip_cache,index_cache,icons,local_api}

# Set permissions
chmod 755 .indexer_files
chmod 755 .indexer_files/*

# Create basic configuration
cat > .indexer_files/config.json << 'EOF'
{
  "version": "1.1.10",
  "main": {
    "cache_type": "json",
    "disable_api": false
  },
  "exclusions": {
    "index_folders": true,
    "index_txt": true
  },
  "viewable_files": {
    "view_txt": true
  }
}
EOF
```

### Verification Steps

1. **Check file permissions:**
   ```bash
   ls -la index.php .indexer_files/
   ```

2. **Test web access:**
   ```bash
   curl -I https://yourdomain.com/path/to/indexer/
   ```

3. **Verify configuration:**
   ```bash
   cat .indexer_files/config.json | python -m json.tool
   ```

## Troubleshooting Installation

### Common Issues

#### Permission Denied Errors
```bash
# Fix file permissions
chmod 644 index.php
chmod -R 755 .indexer_files/

# Fix ownership (Linux/Unix)
chown -R www-data:www-data .indexer_files/
```

#### PHP Extension Missing
```bash
# Ubuntu/Debian
sudo apt-get install php-sqlite3 php-zip

# CentOS/RHEL
sudo yum install php-sqlite3 php-zip

# Or compile with extensions
./configure --with-sqlite3 --with-zip
```

#### White Screen/No Output
1. Check PHP error logs
2. Enable error reporting temporarily:
   ```php
   <?php
   error_reporting(E_ALL);
   ini_set('display_errors', 1);
   ?>
   ```

#### API Connection Failures
1. **Test connectivity:**
   ```bash
   curl -I https://api.indexer.ccls.icu
   ```

2. **Enable offline mode:**
   ```json
   {"main": {"disable_api": true}}
   ```

3. **Check firewall rules:**
   ```bash
   # Allow outbound HTTPS
   ufw allow out 443
   ```

### Debug Information

**Collect system info:**
```bash
php --version
php -m
uname -a
df -h
ls -la .indexer_files/
```

**Check error logs:**
```bash
tail -f /var/log/apache2/error.log
tail -f /var/log/nginx/error.log
tail -f .indexer_files/debug.log
```

## Performance Optimization

### PHP Configuration

**Optimize php.ini:**
```ini
; Memory and execution
memory_limit = 256M
max_execution_time = 60

; File uploads (for large files)
post_max_size = 100M
upload_max_filesize = 100M

; OPcache (recommended)
opcache.enable = 1
opcache.memory_consumption = 128
opcache.max_accelerated_files = 4000
```

### Indexer Configuration

**High-performance settings:**
```json
{
  "main": {
    "cache_type": "sqlite",
  }
}
```

### Web Server Optimization

**Apache (.htaccess):**
```apache
# Enable compression
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/css text/javascript application/javascript
</IfModule>

# Enable caching
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/png "access plus 1 month"
    ExpiresByType text/css "access plus 1 week"
</IfModule>
```

**Nginx:**
```nginx
# Enable gzip compression
gzip on;
gzip_types text/css application/javascript image/png;

# Enable caching
location ~* \.(png|css|js)$ {
    expires 1M;
    add_header Cache-Control "public, immutable";
}
```

## Security Considerations

### File Permissions
```bash
# Secure file permissions
chmod 644 index.php
chmod 755 .indexer_files/
find .indexer_files/ -type f -exec chmod 644 {} \;
find .indexer_files/ -type d -exec chmod 755 {} \;
```

### Web Server Security
```apache
# Apache - Hide sensitive files
<Files ".indexer_files/*">
    Require all denied
</Files>
```

```nginx
# Nginx - Hide sensitive files
location ~ /\.indexer_files {
    deny all;
}
```

### Network Security
- Enable HTTPS
- Use firewall rules
- Restrict access by IP if needed
- Regular security updates

---


**Next Steps:** After installation, see the [Configuration Guide](configuration.md) to customize your indexer settings.
