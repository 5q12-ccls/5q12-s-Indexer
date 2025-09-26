# Security Guide

## Table of Contents
- [Overview](#overview)
- [Built-in Security Features](#built-in-security-features)
- [Access Control Configuration](#access-control-configuration)
- [Web Server Security](#web-server-security)
- [File System Security](#file-system-security)
- [Network Security](#network-security)
- [Monitoring & Logging](#monitoring--logging)
- [Incident Response](#incident-response)
- [Security Best Practices](#security-best-practices)

## Overview

Security for 5q12's Indexer involves multiple layers: built-in protections, configuration controls, web server hardening, and monitoring. This guide covers comprehensive security implementation.

### Security Principles
- **Defense in Depth**: Multiple security layers
- **Least Privilege**: Minimum necessary access
- **Security by Default**: Safe default configurations
- **Monitoring**: Continuous security awareness

## Built-in Security Features

### Path Traversal Protection

**Automatic protection against directory traversal attacks:**

```php
// Built-in security checks
if (strpos($downloadPath, '../') !== false || strpos($downloadPath, './') !== false) {
    http_response_code(403);
    die('Access denied - invalid filename');
}
```

**Protected against:**
- `../` escape sequences
- `./` relative path references  
- NULL byte injection (`%00`)
- Symlink exploitation
- Encoded traversal attempts

### File Type Security

**Default security exclusions:**

```json
{
  "exclusions": {
    "index_key": false,           // Cryptographic keys
    "index_secret": false,        // Secret files  
    "index_passwd": false,        // Password files
    "index_rsa": false,           // SSH private keys
    "index_authorized_keys": false,
    "index_known_hosts": false,
    "index_jks": false,           // Java keystores
    "index_keystore": false,
    "index_p12": false,           // PKCS#12 certificates
    "index_pfx": false
  }
}
```

### Hidden File Protection

**Hidden files excluded by default:**

```json
{
  "main": {
    "index_hidden": false  // Hides .htaccess, .env, .git/, etc.
  }
}
```

**Hidden items include:**
- `.htaccess` - Web server configuration
- `.env` - Environment variables
- `.git/` - Version control data
- `.ssh/` - SSH configuration
- `.DS_Store` - System files

## Access Control Configuration

### Deny List Implementation

**Block sensitive directories and files:**

```json
{
  "main": {
    "deny_list": "admin, private, .git, .env*, config/secrets*, logs, uploads/.exe*"
  }
}
```

### Common Security Patterns

#### High-Security Environment
```json
{
  "main": {
    "cache_type": "sqlite",
    "disable_file_downloads": true,
    "disable_folder_downloads": true,
    "index_hidden": false,
    "deny_list": "admin, config, logs, .git, .env*, .htaccess, *.key, *.pem, private/*"
  },
  "exclusions": {
    "index_php": false,
    "index_key": false,
    "index_secret": false,
    "index_passwd": false
  },
  "viewable_files": {
    "view_php": false,
    "view_config": false,
    "view_env": false,
    "view_key": false
  }
}
```

#### Public File Server
```json
{
  "main": {
    "deny_list": "admin, private, .htaccess, config, system, .exe*, .bat*, .cmd*"
  },
  "exclusions": {
    "index_exe": false,
    "index_dll": false,
    "index_bat": false,
    "index_cmd": false
  }
}
```

### Download Controls

**Disable downloads for security:**

```json
{
  "main": {
    "disable_file_downloads": true,
    "disable_folder_downloads": true
  }
}
```

**Use cases:**
- Read-only environments
- Content protection
- Bandwidth conservation
- Compliance requirements

## Web Server Security

### Apache Security Configuration

#### Protect Configuration Files
```apache
# In .indexer_files/.htaccess
<Files "config.json">
    Require all denied
</Files>

<Files "*.log">
    Require all denied
</Files>

<Files "*.backup">
    Require all denied
</Files>
```

#### Prevent PHP Execution in Sensitive Areas
```apache
# In uploads/.htaccess
<Files "*.php">
    Require all denied
</Files>

<Files "*.phtml">
    Require all denied
</Files>

php_flag engine off
```

#### Hide Sensitive Directories
```apache
# In root .htaccess
<DirectoryMatch "^\.|\/\.">
    Require all denied
</DirectoryMatch>

<Directory ".indexer_files">
    <Files "*.json">
        Require all denied
    </Files>
    <Files "*.log">
        Require all denied
    </Files>
</Directory>
```

#### Security Headers
```apache
# Add security headers
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"
Header always set Referrer-Policy strict-origin-when-cross-origin
Header always set Content-Security-Policy "default-src 'self'"
```

### Nginx Security Configuration

#### Main Configuration
```nginx
server {
    listen 443 ssl http2;
    server_name files.yourdomain.com;
    root /var/www/html/files;

    # SSL Configuration
    ssl_certificate /path/to/certificate.pem;
    ssl_certificate_key /path/to/private.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512;

    # Security headers
    add_header X-Frame-Options DENY always;
    add_header X-Content-Type-Options nosniff always;
    add_header X-XSS-Protection "1; mode=block" always;

    # Hide sensitive files and directories
    location ~ /\. {
        deny all;
        access_log off;
        log_not_found off;
    }

    location ~* \.(json|log|backup)$ {
        deny all;
        access_log off;
    }

    # Prevent PHP execution in uploads
    location /uploads/ {
        location ~ \.php$ {
            deny all;
        }
    }

    # Main indexer location
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}

# Redirect HTTP to HTTPS
server {
    listen 80;
    server_name files.yourdomain.com;
    return 301 https://$server_name$request_uri;
}
```

## File System Security

### File Permissions

**Set restrictive permissions:**

```bash
# Indexer file
chmod 644 index.php

# Configuration directory
chmod 755 .indexer_files/
chmod 644 .indexer_files/config.json
chmod 755 .indexer_files/cache/
chmod 755 .indexer_files/zip_cache/

# Remove world write permissions
find .indexer_files/ -type f -exec chmod 644 {} \;
find .indexer_files/ -type d -exec chmod 755 {} \;

# Secure log files
chmod 640 .indexer_files/*.log
```

### Ownership Configuration

**Set appropriate ownership:**

```bash
# Linux/Unix systems
chown -R www-data:www-data .indexer_files/
chown www-data:www-data index.php

# Ensure proper group permissions
chgrp -R www-data .indexer_files/

# Prevent other users from reading sensitive files
chmod 750 .indexer_files/
```

### Directory Security

**Secure sensitive directories:**

```bash
# Create protected directories
mkdir -p {admin,private,config}/.protected
echo "deny from all" > admin/.htaccess
echo "deny from all" > private/.htaccess
echo "deny from all" > config/.htaccess

# Set restrictive permissions
chmod 700 admin/ private/ config/
```

## Network Security

### HTTPS Configuration

**Force HTTPS for all connections:**

```apache
# Apache .htaccess
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Add HSTS header
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"
```

### IP Access Restrictions

#### Allow Specific Networks
```apache
# Apache
<RequireAll>
    Require ip 192.168.1.0/24
    Require ip 10.0.0.0/8
    Require ip 172.16.0.0/12
</RequireAll>
```

```nginx
# Nginx
allow 192.168.1.0/24;
allow 10.0.0.0/8;
allow 172.16.0.0/12;
deny all;
```

#### Block Suspicious IPs
```bash
# Using fail2ban
[indexer-bruteforce]
enabled = true
port = http,https
filter = indexer-bruteforce
logpath = /var/log/apache2/access.log
maxretry = 5
bantime = 3600
```

### Firewall Configuration

**Configure server firewall:**

```bash
# UFW (Ubuntu Firewall)
ufw default deny incoming
ufw default allow outgoing
ufw allow from 192.168.1.0/24 to any port 22
ufw allow from 192.168.1.0/24 to any port 80
ufw allow from 192.168.1.0/24 to any port 443
ufw enable

# iptables
iptables -A INPUT -s 192.168.1.0/24 -p tcp --dport 80 -j ACCEPT
iptables -A INPUT -s 192.168.1.0/24 -p tcp --dport 443 -j ACCEPT
iptables -A INPUT -p tcp --dport 80 -j DROP
iptables -A INPUT -p tcp --dport 443 -j DROP
```

## Monitoring & Logging

### Access Logging

**Enhanced Apache logging:**

```apache
# Custom log format with security details
LogFormat "%h %l %u %t \"%r\" %>s %O \"%{Referer}i\" \"%{User-Agent}i\" %D %{X-Forwarded-For}i" security
CustomLog /var/log/apache2/indexer_security.log security
```

### Security Monitoring

**Monitor for suspicious activity:**

```bash
#!/bin/bash
# Security monitoring script

LOG_FILE="/var/log/apache2/access.log"
ALERT_EMAIL="admin@yourdomain.com"

# Monitor for path traversal attempts
grep -E "\.\.\/|%2e%2e|%252e" $LOG_FILE | tail -10 | \
    mail -s "Path Traversal Attempt Detected" $ALERT_EMAIL

# Monitor for large downloads
awk '$10 > 100000000 {print $0}' $LOG_FILE | tail -5 | \
    mail -s "Large Download Detected" $ALERT_EMAIL

# Monitor for rapid requests from single IP
awk '{print $1}' $LOG_FILE | sort | uniq -c | sort -nr | head -10 | \
    awk '$1 > 1000 {print "High request volume from " $2 ": " $1 " requests"}' | \
    mail -s "High Request Volume Detected" $ALERT_EMAIL
```

### Log Analysis

**Regular security analysis:**

```bash
# Daily security report
#!/bin/bash
LOG_FILE="/var/log/apache2/access.log"
REPORT_DATE=$(date +%Y-%m-%d)

echo "Security Report for $REPORT_DATE" > /tmp/security_report.txt

# Failed requests
echo "=== Failed Requests ===" >> /tmp/security_report.txt
grep " 403 \| 404 \| 500 " $LOG_FILE | wc -l >> /tmp/security_report.txt

# Suspicious user agents
echo "=== Suspicious User Agents ===" >> /tmp/security_report.txt
grep -i "bot\|crawler\|scanner" $LOG_FILE | cut -d'"' -f6 | sort | uniq -c | sort -nr | head -10 >> /tmp/security_report.txt

# Geographic analysis (if GeoIP available)
echo "=== Top Countries ===" >> /tmp/security_report.txt
# Add GeoIP analysis here

# Send report
mail -s "Daily Security Report" admin@yourdomain.com < /tmp/security_report.txt
```

## Incident Response

### Security Breach Response Plan

#### Immediate Actions (First 15 minutes)
1. **Isolate the system:**
   ```bash
   # Temporarily disable indexer
   mv index.php index.php.disabled
   
   # Or block all access
   echo "deny from all" > .htaccess
   ```

2. **Preserve evidence:**
   ```bash
   # Copy current logs
   cp /var/log/apache2/access.log /tmp/incident_$(date +%s).log
   cp .indexer_files/*.log /tmp/
   ```

3. **Assess damage:**
   ```bash
   # Check for unauthorized file modifications
   find . -type f -mtime -1 -ls
   
   # Check running processes
   ps aux | grep -E "php|apache|nginx"
   ```

#### Investigation Phase (First hour)
1. **Log analysis:**
   ```bash
   # Find attack vectors
   grep -E "\.\.\/|%2e%2e|%252e" /var/log/apache2/access.log
   
   # Identify attacker IPs
   grep " 403 \| 404 " /var/log/apache2/access.log | awk '{print $1}' | sort | uniq -c | sort -nr
   ```

2. **File integrity check:**
   ```bash
   # Compare with backups
   diff -r backup/ current/
   
   # Check for webshells
   find . -name "*.php" -exec grep -l "eval\|base64_decode\|exec" {} \;
   ```

#### Recovery Phase
1. **Clean and restore:**
   ```bash
   # Restore from clean backup
   rm -rf compromised_files/
   cp -r backup/ current/
   
   # Update permissions
   chmod -R 644 *.php
   chmod -R 755 .indexer_files/
   ```

2. **Strengthen security:**
   ```bash
   # Update deny list
   vim .indexer_files/config.json
   
   # Add IP blocks
   echo "deny from attacker.ip.address" >> .htaccess
   ```

### Backup Security

**Secure backup procedures:**

```bash
#!/bin/bash
# Secure backup script

BACKUP_DIR="/secure/backups"
DATE=$(date +%Y%m%d_%H%M%S)

# Create encrypted backup
tar -czf - .indexer_files/ index.php | \
    gpg --cipher-algo AES256 --compress-algo 1 --symmetric \
    --output "$BACKUP_DIR/indexer_backup_$DATE.tar.gz.gpg"

# Verify backup integrity
gpg --decrypt "$BACKUP_DIR/indexer_backup_$DATE.tar.gz.gpg" | \
    tar -tz > /dev/null && echo "Backup verification: OK"

# Clean old backups (keep 30 days)
find "$BACKUP_DIR" -name "indexer_backup_*.tar.gz.gpg" -mtime +30 -delete
```

## Security Best Practices

### Development Security

#### Secure Coding Practices
1. **Input validation** - Validate all user inputs
2. **Output encoding** - Encode output data appropriately  
3. **Error handling** - Don't expose system information
4. **Resource limits** - Implement appropriate limits

#### Security Testing
```bash
# Test for common vulnerabilities
curl "https://yoursite.com/path/../../../etc/passwd"
curl "https://yoursite.com/path/%2e%2e%2f%2e%2e%2fetc%2fpasswd"
curl "https://yoursite.com/path/..%2f..%2f..%2fetc%2fpasswd"
```

### Deployment Security

#### Environment Hardening
1. **Remove unnecessary software** and services
2. **Update regularly** - OS, PHP, web server
3. **Use security frameworks** - ModSecurity, fail2ban
4. **Implement monitoring** - Real-time alerts

#### Configuration Management
```bash
# Secure PHP configuration
sed -i 's/expose_php = On/expose_php = Off/' /etc/php/8.1/apache2/php.ini
sed -i 's/display_errors = On/display_errors = Off/' /etc/php/8.1/apache2/php.ini
sed -i 's/;disable_functions =/disable_functions = exec,shell_exec,system,passthru/' /etc/php/8.1/apache2/php.ini
```

### Ongoing Security

#### Regular Security Tasks
1. **Weekly log review** - Analyze access patterns
2. **Monthly updates** - Apply security patches
3. **Quarterly audits** - Review configurations
4. **Annual testing** - Penetration testing

#### Security Checklist
- [ ] HTTPS enabled and enforced
- [ ] Security headers configured
- [ ] File permissions properly set
- [ ] Sensitive files hidden/protected
- [ ] Regular backups created and tested
- [ ] Monitoring and alerting active
- [ ] Access logs reviewed regularly
- [ ] Updates applied promptly

This security guide provides comprehensive protection for 5q12's Indexer while maintaining functionality and performance.

---

**Related Documentation:**
- [Configuration Guide](configuration.md) - Security-focused settings
- [Installation Guide](installation.md) - Secure deployment procedures