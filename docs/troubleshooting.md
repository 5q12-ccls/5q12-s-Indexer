# Troubleshooting Guide

## Table of Contents
- [Quick Fixes](#quick-fixes)
- [Installation Issues](#installation-issues)
- [Performance Problems](#performance-problems)
- [Display & Interface Issues](#display--interface-issues)
- [File Access Problems](#file-access-problems)
- [Configuration Issues](#configuration-issues)
- [Security & Permission Errors](#security--permission-errors)
- [Network & API Issues](#network--api-issues)
- [Diagnostic Tools](#diagnostic-tools)
- [Getting Help](#getting-help)

## Quick Fixes

### Most Common Issues (Try these first)

#### Indexer Not Loading
```bash
# Check PHP is working
echo "<?php phpinfo(); ?>" > test.php
# Access test.php in browser

# Check file permissions
chmod 644 index.php
ls -la index.php
```

#### Files Not Appearing
```bash
# Clear cache
rm -rf .indexer_files/index_cache/*

# Check configuration
cat .indexer_files/config.json | python -m json.tool
```

#### Performance Issues
```json
{
  "main": {
    "cache_type": "sqlite"
  }
}
```

#### Icons Not Loading
```json
{
  "main": {
    "local_icons": true
  }
}
```

## Installation Issues

### Indexer Not Loading

#### Symptom: Blank page or "file not found"

**Diagnostic steps:**
1. **Check PHP installation:**
   ```bash
   php --version
   which php
   ```

2. **Test PHP processing:**
   ```php
   <?php echo "PHP is working: " . phpinfo(); ?>
   ```

3. **Check web server error logs:**
   ```bash
   # Apache
   tail -f /var/log/apache2/error.log
   
   # Nginx  
   tail -f /var/log/nginx/error.log
   ```

4. **Verify file permissions:**
   ```bash
   ls -la index.php
   # Should show: -rw-r--r-- (644)
   ```

**Solutions:**
- Install PHP if missing: `apt-get install php`
- Fix permissions: `chmod 644 index.php`
- Enable PHP in web server configuration
- Check DocumentRoot is correct

### Configuration Directory Creation Failed

#### Symptom: Error about `.indexer_files` directory

**Solutions:**
```bash
# Check write permissions
ls -ld .

# Fix permissions
chmod 755 .

# Create manually if needed
mkdir -p .indexer_files/{zip_cache,index_cache,icons,local_api}
chmod -R 755 .indexer_files
```

### Missing PHP Extensions

#### Symptom: Fatal errors about missing functions

**Check extensions:**
```bash
php -m | grep -E "(sqlite|zip|json)"
```

**Install missing extensions:**
```bash
# Ubuntu/Debian
sudo apt-get install php-sqlite3 php-zip php-curl

# CentOS/RHEL
sudo yum install php-sqlite3 php-zip php-curl

# Check loaded extensions
php -i | grep -E "(sqlite|zip)"
```

## Performance Problems

### Slow Directory Loading

#### Symptom: Long loading times for file listings

**Solutions:**

1. **Enable SQLite caching:**
   ```json
   {
     "main": {
       "cache_type": "sqlite"
     }
   }
   ```

2. **Verify SQLite is available:**
   ```bash
   php -m | grep sqlite3
   ```

3. **Clear existing cache:**
   ```bash
   rm -rf .indexer_files/index_cache/*
   ```

4. **Increase PHP limits:**
   ```ini
   ; In php.ini
   memory_limit = 256M
   max_execution_time = 60
   ```

### Memory Limit Errors

#### Symptom: "Fatal error: Allowed memory size exhausted"

**Solutions:**
```ini
; Increase in php.ini
memory_limit = 512M
max_execution_time = 300

; Or for specific directory in .htaccess
php_value memory_limit 512M
php_value max_execution_time 300
```

### Timeout Errors

#### Symptom: "Maximum execution time exceeded"

**Solutions:**
```ini
; In php.ini
max_execution_time = 300
set_time_limit = 300

; For large directories
max_input_time = 300
```

## Display & Interface Issues

### Icons Not Loading

#### Symptom: Emoji icons instead of file type icons

**Solutions:**

1. **Enable local icons:**
   ```json
   {
     "main": {
       "local_icons": true
     }
   }
   ```

2. **Check icon directory:**
   ```bash
   ls -la .indexer_files/icons/
   ```

3. **Test icon accessibility:**
   ```bash
   curl -I https://yourdomain.com/.indexer_files/icons/folder.png
   ```

4. **Manual icon download (if API disabled):**
   ```bash
   cd .indexer_files/icons/
   wget https://api.indexer.ccls.icu/icons/folder.png
   ```

### Interface Display Problems

#### Symptom: Broken layout, missing styles

**Solutions:**

1. **Check CSS loading:**
   - View page source
   - Verify stylesheet URL is accessible
   - Check browser console for 404 errors

2. **Clear browser cache:**
   ```
   Ctrl+Shift+Delete (Windows)
   Cmd+Shift+Delete (Mac)
   ```

3. **Test with different browser:**
   - Chrome, Firefox, Safari, Edge
   - Disable browser extensions

4. **Enable local resources:**
   ```json
   {
     "main": {
       "disable_api": true,
       "local_icons": true
     }
   }
   ```

### Mobile Display Issues

#### Symptom: Poor mobile interface, unresponsive design

**Solutions:**
1. **Update browser** to latest version
2. **Clear mobile browser cache**
3. **Try different mobile browser** (Chrome, Firefox, Safari)
4. **Check viewport settings** - should be automatic
5. **Test on different mobile device**

## File Access Problems

### Files Not Appearing

#### Symptom: Expected files don't show in listings

**Diagnostic checklist:**

1. **Check file permissions:**
   ```bash
   ls -la filename.ext
   # Should be readable: -rw-r--r--
   ```

2. **Verify extension configuration:**
   ```json
   {
     "exclusions": {
       "index_php": true  // true = show, false = hide
     }
   }
   ```

3. **Check deny list:**
   ```json
   {
     "main": {
       "deny_list": "*.php, admin, logs"
     }
   }
   ```

4. **Hidden files:**
   ```json
   {
     "main": {
       "index_hidden": true  // Show files starting with "."
     }
   }
   ```

**Solutions:**
- Fix file permissions: `chmod 644 filename.ext`
- Update configuration to include file type
- Remove from deny list or add to allow list
- Enable hidden file indexing if needed

### Cannot Access Directories

#### Symptom: Folders appear but clicking produces errors

**Solutions:**
1. **Check directory permissions:**
   ```bash
   ls -ld /path/to/folder
   # Should have execute permission: drwxr-xr-x
   chmod 755 folder/
   ```

2. **Verify folder indexing:**
   ```json
   {
     "exclusions": {
       "index_folders": true
     }
   }
   ```

3. **Check path security:**
   - Ensure no `../` or `./` in path
   - Verify no special characters causing issues

### Download Problems

#### Symptom: Download buttons missing or not working

**Solutions:**

1. **Check download settings:**
   ```json
   {
     "main": {
       "disable_file_downloads": false,
       "disable_folder_downloads": false
     }
   }
   ```

2. **Verify ZIP extension:**
   ```bash
   php -m | grep zip
   # If missing: apt-get install php-zip
   ```

3. **Check disk space:**
   ```bash
   df -h .indexer_files/zip_cache/
   ```

4. **Test with small file first**

#### Large File Download Issues

**Solutions:**
```ini
; In php.ini
max_execution_time = 600
memory_limit = 1024M
post_max_size = 2G
upload_max_filesize = 2G

; Check current limits
php -i | grep -E "(max_execution_time|memory_limit|post_max_size)"
```

## Configuration Issues

### Configuration Not Taking Effect

#### Symptom: Changes to config.json don't work

**Solutions:**

1. **Validate JSON syntax:**
   ```bash
   php -r "json_decode(file_get_contents('.indexer_files/config.json')); echo 'Valid JSON\n';"
   ```

2. **Clear cache:**
   ```bash
   rm -rf .indexer_files/index_cache/*
   ```

3. **Check file permissions:**
   ```bash
   chmod 644 .indexer_files/config.json
   ```

4. **Look for backup files:**
   ```bash
   ls -la .indexer_files/config.json.backup.*
   ```

### API Updates Overwriting Settings

#### Symptom: Manual changes get reset

**Solutions:**
1. **Disable API updates:**
   ```json
   {
     "main": {
       "disable_api": true
     }
   }
   ```

2. **Backup before changes:**
   ```bash
   cp .indexer_files/config.json .indexer_files/config.json.manual
   ```

3. **Check update logs:**
   ```bash
   cat .indexer_files/config_updates.log
   ```

### Invalid Configuration

#### Symptom: Indexer uses defaults despite config file

**Solutions:**
```bash
# Check for JSON syntax errors
python -m json.tool .indexer_files/config.json

# Create minimal working config
cat > .indexer_files/config.json << 'EOF'
{
  "version": "1.0",
  "main": {
    "cache_type": "json"
  },
  "exclusions": {
    "index_folders": true
  },
  "viewable_files": {
    "view_txt": true
  }
}
EOF
```

## Security & Permission Errors

### Permission Denied Errors

#### Symptom: Various "Permission denied" errors

**Solutions:**

1. **Fix web server permissions:**
   ```bash
   # For Apache (www-data)
   chown -R www-data:www-data .indexer_files/
   
   # For Nginx
   chown -R nginx:nginx .indexer_files/
   ```

2. **Set correct file modes:**
   ```bash
   find .indexer_files/ -type f -exec chmod 644 {} \;
   find .indexer_files/ -type d -exec chmod 755 {} \;
   ```

3. **Check SELinux (if applicable):**
   ```bash
   # Check status
   sestatus
   
   # Allow HTTP network connections
   setsebool -P httpd_can_network_connect 1
   
   # Set correct context
   restorecon -R .indexer_files/
   ```

### Access Denied Errors

#### Symptom: "Access denied - path traversal detected"

**Cause:** Security system detecting potential attacks

**Solutions:**
1. **Check URL for invalid characters:**
   - Remove `../` or `./` from URLs
   - Avoid encoded traversal attempts

2. **Use clean navigation:**
   - Navigate through interface instead of editing URLs
   - Use breadcrumbs for navigation

3. **Check for symlink issues:**
   ```bash
   ls -la | grep "^l"  # Find symlinks
   ```

### ZIP Creation Failures

#### Symptom: "Failed to create zip file"

**Solutions:**

1. **Check ZIP extension:**
   ```bash
   php -m | grep zip
   # Install if missing: apt-get install php-zip
   ```

2. **Verify disk space:**
   ```bash
   df -h .indexer_files/
   ```

3. **Check temp directory permissions:**
   ```bash
   chmod 755 .indexer_files/zip_cache/
   ls -ld .indexer_files/zip_cache/
   ```

4. **Test ZIP functionality:**
   ```php
   <?php
   $zip = new ZipArchive();
   $result = $zip->open('/tmp/test.zip', ZipArchive::CREATE);
   echo "ZIP test result: " . $result . "\n";
   ?>
   ```

## Network & API Issues

### API Connection Failures

#### Symptom: Cannot connect to API, configuration not downloading

**Solutions:**

1. **Test API connectivity:**
   ```bash
   curl -I https://api.indexer.ccls.icu/api.php?action=status
   ```

2. **Check PHP URL functions:**
   ```php
   <?php
   echo "allow_url_fopen: " . (ini_get('allow_url_fopen') ? 'enabled' : 'disabled') . "\n";
   echo "curl available: " . (function_exists('curl_version') ? 'yes' : 'no') . "\n";
   ?>
   ```

3. **Enable offline mode:**
   ```json
   {
     "main": {
       "disable_api": true
     }
   }
   ```

4. **Check firewall rules:**
   ```bash
   # Test outbound HTTPS
   telnet api.indexer.ccls.icu 443
   ```

### SSL/TLS Issues

#### Symptom: SSL certificate errors when accessing API

**Solutions:**
```php
// Temporary workaround (not recommended for production)
$context = stream_context_create([
    "ssl" => [
        "verify_peer" => false,
        "verify_peer_name" => false,
    ],
]);
```

**Better solution:**
1. Update CA certificates: `apt-get update && apt-get install ca-certificates`
2. Update PHP: Use recent PHP version with updated SSL support

## Diagnostic Tools

### System Information Collection

**Run this diagnostic script:**
```bash
#!/bin/bash
echo "=== 5q12's Indexer Diagnostics ==="
echo "Date: $(date)"
echo ""

echo "=== System Information ==="
echo "OS: $(uname -a)"
echo "PHP Version: $(php --version | head -1)"
echo "Web Server: $(ps aux | grep -E '(apache|nginx|httpd)' | head -1)"
echo ""

echo "=== PHP Extensions ==="
php -m | grep -E "(sqlite|zip|json|curl|gd)"
echo ""

echo "=== File Permissions ==="
ls -la index.php .indexer_files/
echo ""

echo "=== Disk Space ==="
df -h .
echo ""

echo "=== Configuration ==="
if [ -f .indexer_files/config.json ]; then
    echo "Config exists: YES"
    cat .indexer_files/config.json | python -m json.tool > /dev/null 2>&1
    if [ $? -eq 0 ]; then
        echo "Config valid: YES"
    else
        echo "Config valid: NO - JSON syntax error"
    fi
else
    echo "Config exists: NO"
fi
echo ""

echo "=== Cache Status ==="
if [ -d .indexer_files/index_cache ]; then
    echo "Cache dir exists: YES"
    echo "Cache files: $(find .indexer_files/index_cache -type f | wc -l)"
else
    echo "Cache dir exists: NO"
fi
echo ""

echo "=== Error Logs (last 10 lines) ==="
if [ -f /var/log/apache2/error.log ]; then
    tail -10 /var/log/apache2/error.log
elif [ -f /var/log/nginx/error.log ]; then
    tail -10 /var/log/nginx/error.log
else
    echo "No standard error logs found"
fi
```

### Configuration Validator

**Create config validator:**
```php
<?php
function validateIndexerConfig($configFile = '.indexer_files/config.json') {
    echo "=== Configuration Validator ===\n";
    
    if (!file_exists($configFile)) {
        echo "ERROR: Configuration file not found\n";
        return false;
    }
    
    $content = file_get_contents($configFile);
    $config = json_decode($content, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "ERROR: Invalid JSON - " . json_last_error_msg() . "\n";
        return false;
    }
    
    echo "✓ JSON syntax valid\n";
    
    // Check required sections
    $required = ['version', 'main', 'exclusions', 'viewable_files'];
    foreach ($required as $section) {
        if (!isset($config[$section])) {
            echo "WARNING: Missing section '$section'\n";
        } else {
            echo "✓ Section '$section' present\n";
        }
    }
    
    // Check main settings
    if (isset($config['main'])) {
        $mainDefaults = [
            'cache_type' => 'json',
            'local_icons' => false,
            'disable_api' => false
        ];
        
        foreach ($mainDefaults as $key => $default) {
            if (!isset($config['main'][$key])) {
                echo "INFO: Using default for main.$key = $default\n";
            } else {
                echo "✓ Setting main.$key = " . json_encode($config['main'][$key]) . "\n";
            }
        }
    }
    
    echo "Configuration validation complete\n";
    return true;
}

validateIndexerConfig();
?>
```

### Performance Test

**Test directory loading performance:**
```php
<?php
function testPerformance($directory = '.') {
    $start = microtime(true);
    
    $files = scandir($directory);
    $count = 0;
    
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        $count++;
        $filePath = $directory . '/' . $file;
        $size = is_dir($filePath) ? 0 : filesize($filePath);
        $modified = filemtime($filePath);
    }
    
    $end = microtime(true);
    $time = round(($end - $start) * 1000, 2);
    
    echo "Performance Test Results:\n";
    echo "Directory: $directory\n";
    echo "Files processed: $count\n";
    echo "Time taken: {$time}ms\n";
    echo "Average per file: " . round($time / max($count, 1), 2) . "ms\n";
}

testPerformance();
?>
```

## Getting Help

### Information to Collect

**When seeking help, provide:**

1. **Environment Details:**
   ```bash
   uname -a
   php --version
   apache2 -v  # or nginx -v
   ```

2. **Error Information:**
   - Exact error messages
   - Steps to reproduce
   - When issue started
   - Recent changes

3. **Configuration:**
   ```bash
   cat .indexer_files/config.json | python -m json.tool
   ls -la .indexer_files/
   ```

4. **Logs:**
   ```bash
   tail -50 /var/log/apache2/error.log
   tail -20 .indexer_files/config_updates.log
   ```

### Common Solutions Summary

| Problem | Quick Fix |
|---------|-----------|
| Not loading | Check PHP installation and permissions |
| Files missing | Check configuration exclusions and deny list |
| Slow performance | Enable SQLite caching |
| Icons missing | Enable local icons |
| Downloads fail | Check ZIP extension and permissions |
| Config ignored | Validate JSON syntax and clear cache |
| Permission errors | Fix file ownership and permissions |
| API issues | Enable offline mode |

### Support Resources

1. **GitHub Repository** - Check for known issues and updates
2. **Documentation** - Review related guides:
   - [Installation Guide](installation.md) - Setup issues
   - [Configuration Guide](configuration.md) - Settings problems
   - [Security Guide](security.md) - Permission and access issues
3. **Community** - Search for similar issues
4. **Professional Help** - Consider system administrator assistance for complex server issues

### Recovery Procedures

**Complete reset if all else fails:**
```bash
# Backup current state
cp -r .indexer_files .indexer_files.backup

# Remove configuration
rm -rf .indexer_files

# Access indexer to reinitialize
curl https://yourdomain.com/path/to/indexer/

# Restore custom settings if needed
# Edit .indexer_files/config.json with your requirements
```

This troubleshooting guide provides systematic approaches to diagnose and resolve the most common issues with 5q12's Indexer.# Troubleshooting Guide

## Overview

This guide addresses common issues encountered when using 5q12's Indexer, providing step-by-step solutions and diagnostic procedures.

## Initial Setup Issues

### Indexer Not Loading

**Symptom:** Blank page or "file not found" error when accessing the indexer.

**Possible Causes:**
- PHP not enabled or configured
- File permissions incorrect
- Web server misconfiguration

**Solutions:**

1. **Verify PHP Installation:**
   ```bash
   php --version
   ```
   If PHP is not installed, install it through your package manager.

2. **Check File Permissions:**
   ```bash
   chmod 644 index.php
   ls -la index.php
   ```
   File should be readable by web server user.

3. **Test PHP Processing:**
   Create a test file `phpinfo.php`:
   ```php
   <?php phpinfo(); ?>
   ```
   If this doesn't load, PHP processing is not working.

4. **Check Web Server Error Logs:**
   ```bash
   # Apache
   tail -f /var/log/apache2/error.log
   
   # Nginx
   tail -f /var/log/nginx/error.log
   ```

### Configuration Directory Creation Fails

**Symptom:** Error about inability to create `.indexer_files` directory.

**Cause:** Insufficient write permissions in the web directory.

**Solution:**
```bash
# Check current permissions
ls -la

# Make directory writable
chmod 755 .
mkdir .indexer_files
chmod 755 .indexer_files

# Or create manually with proper permissions
mkdir -p .indexer_files/{zip_cache,index_cache,icons,local_api}
chmod -R 755 .indexer_files
```

### API Connection Errors

**Symptom:** Error messages about API connectivity or configuration download failures.

**Solutions:**

1. **Check Internet Connectivity:**
   ```bash
   curl -I https://api.indexer.ccls.icu
   ```

2. **Enable Offline Mode:**
   Manually create `config.json` with `"disable_api": true`:
   ```json
   {
     "version": "1.0",
     "main": {
       "disable_api": true
     }
   }
   ```

3. **Check Firewall Rules:**
   Ensure outbound HTTPS (port 443) is allowed.

4. **Verify PHP URL Functions:**
   ```php
   <?php
   var_dump(function_exists('file_get_contents'));
   var_dump(ini_get('allow_url_fopen'));
   ?>
   ```

## Performance Issues

### Slow Directory Loading

**Symptom:** Long loading times for directories with many files.

**Solutions:**

1. **Enable SQLite Caching:**
   ```json
   {
     "main": {
       "cache_type": "sqlite"
     }
   }
   ```

2. **Check SQLite Extension:**
   ```bash
   php -m | grep sqlite
   ```
   If missing, install: `apt-get install php-sqlite3`

3. **Clear Existing Cache:**
   ```bash
   rm -rf .indexer_files/index_cache/*
   ```

4. **Increase PHP Memory Limit:**
   In `php.ini`:
   ```ini
   memory_limit = 256M
   max_execution_time = 60
   ```

### Icons Not Loading

**Symptom:** File type icons not displaying, showing emoji fallbacks.

**Solutions:**

1. **Enable Local Icons:**
   ```json
   {
     "main": {
       "local_icons": true
     }
   }
   ```

2. **Check Icon Directory:**
   ```bash
   ls -la .indexer_files/icons/
   ```

3. **Manually Download Icons:**
   If API is disabled, download icon files manually to `.indexer_files/icons/`

4. **Verify Web Access to Icons:**
   Test icon URL directly: `https://yourdomain.com/.indexer_files/icons/folder.png`

## File Display Issues

### Files Not Appearing

**Symptom:** Expected files don't show in directory listing.

**Diagnostic Steps:**

1. **Check File Permissions:**
   ```bash
   ls -la filename.ext
   ```
   Ensure file is readable by web server.

2. **Verify Extension Settings:**
   Check if file type is enabled in configuration:
   ```json
   {
     "exclusions": {
       "index_php": true
     }
   }
   ```

3. **Check Deny List:**
   Verify file isn't in deny list:
   ```json
   {
     "main": {
       "deny_list": "*.php, admin, logs"
     }
   }
   ```

4. **Hidden Files:**
   For files starting with `.`, check:
   ```json
   {
     "main": {
       "index_hidden": true
     }
   }
   ```

### Folders Not Accessible

**Symptom:** Folders appear but clicking them results in errors.

**Solutions:**

1. **Check Directory Permissions:**
   ```bash
   ls -ld /path/to/folder
   ```
   Directory needs execute permission: `chmod 755 folder`

2. **Verify Folder Indexing:**
   ```json
   {
     "exclusions": {
       "index_folders": true
     }
   }
   ```

3. **Check Path Security:**
   Ensure path doesn't contain traversal attempts or restricted characters.

## Download Issues

### Download Buttons Missing

**Symptom:** No "DL" or "ZIP" buttons visible.

**Cause:** Downloads disabled in configuration.

**Solution:**
```json
{
  "main": {
    "disable_file_downloads": false,
    "disable_folder_downloads": false
  }
}
```

### Download Failures

**Symptom:** Download button present but downloads fail or produce errors.

**Solutions:**

1. **Check File Permissions:**
   ```bash
   ls -la filename.ext
   ```

2. **Verify ZIP Extension:**
   ```bash
   php -m | grep zip
   ```
   Install if missing: `apt-get install php-zip`

3. **Check Disk Space:**
   ```bash
   df -h .indexer_files/zip_cache/
   ```

4. **Verify Temporary Directory:**
   ```bash
   ls -la .indexer_files/zip_cache/
   chmod 755 .indexer_files/zip_cache/
   ```

### Large File Download Issues

**Symptom:** Large files fail to download or timeout.

**Solutions:**

1. **Increase PHP Limits:**
   ```ini
   max_execution_time = 300
   memory_limit = 512M
   post_max_size = 1G
   upload_max_filesize = 1G
   ```

2. **Check Web Server Timeouts:**
   ```apache
   # Apache
   Timeout 300
   ```

3. **Monitor Server Resources:**
   ```bash
   top
   df -h
   ```

## Viewing Issues

### Files Not Viewable in Browser

**Symptom:** Files download instead of displaying in browser.

**Solutions:**

1. **Check Viewable Files Configuration:**
   ```json
   {
     "viewable_files": {
       "view_pdf": true,
       "view_png": true,
       "view_txt": true
     }
   }
   ```

2. **Verify MIME Type Support:**
   Browser may not support the file type for viewing.

3. **Check File Size:**
   Very large files may not be suitable for browser viewing.

### Incorrect File Display

**Symptom:** Files display with wrong formatting or as binary data.

**Solutions:**

1. **Check File Extension Mapping:**
   Ensure correct extension is configured in `extensionMap.json`

2. **Verify File Encoding:**
   For text files, ensure UTF-8 encoding:
   ```bash
   file -i filename.txt
   ```

3. **Browser Compatibility:**
   Some file types require specific browser support or plugins.

## Configuration Issues

### Configuration Not Taking Effect

**Symptom:** Changes to `config.json` don't appear to work.

**Solutions:**

1. **Validate JSON Syntax:**
   ```bash
   php -r "json_decode(file_get_contents('.indexer_files/config.json')); echo 'Valid JSON';"
   ```

2. **Clear Cache:**
   ```bash
   rm -rf .indexer_files/index_cache/*
   ```

3. **Check File Permissions:**
   ```bash
   chmod 644 .indexer_files/config.json
   ```

4. **Verify Configuration Backup:**
   Check if automatic updates overwrote manual changes:
   ```bash
   ls -la .indexer_files/config.json.backup.*
   ```

### API Updates Overwriting Settings

**Symptom:** Manual configuration changes get reset automatically.

**Solutions:**

1. **Disable API Updates:**
   ```json
   {
     "main": {
       "disable_api": true
     }
   }
   ```

2. **Backup Before Changes:**
   ```bash
   cp .indexer_files/config.json .indexer_files/config.json.manual
   ```

3. **Check Update Logs:**
   ```bash
   cat .indexer_files/config_updates.log
   ```

## Error Messages

### "Directory not found" Error

**Symptom:** Error 404 when accessing specific directories.

**Solutions:**

1. **Check Path Exists:**
   ```bash
   ls -la /path/to/directory
   ```

2. **Verify URL Encoding:**
   Special characters in URLs may need proper encoding.

3. **Check Permissions:**
   ```bash
   ls -ld /path/to/directory
   chmod 755 /path/to/directory
   ```

### "Access denied - path traversal detected"

**Symptom:** Security error when accessing certain paths.

**Cause:** Security system detecting potential path traversal attack.

**Solutions:**

1. **Check URL for Invalid Characters:**
   - Remove `../` or `./` from URLs
   - Avoid encoded traversal attempts

2. **Use Clean Paths:**
   Navigate through the interface rather than manually editing URLs.

3. **Check Symlinks:**
   Ensure symbolic links don't point outside allowed directories.

### "Failed to create zip file"

**Symptom:** Error when attempting to download folders as ZIP.

**Solutions:**

1. **Check ZIP Extension:**
   ```bash
   php -m | grep zip
   ```

2. **Verify Disk Space:**
   ```bash
   df -h .indexer_files/
   ```

3. **Check Permissions:**
   ```bash
   chmod 755 .indexer_files/zip_cache/
   ```

4. **Test ZIP Creation:**
   ```php
   <?php
   $zip = new ZipArchive();
   $result = $zip->open('/tmp/test.zip', ZipArchive::CREATE);
   var_dump($result);
   ?>
   ```

## Browser-Specific Issues

### Internet Explorer/Edge Legacy Issues

**Symptoms:** Interface display problems, functionality not working.

**Solutions:**

1. **Update Browser:**
   Use modern browser versions (Chrome, Firefox, Safari, Edge Chromium).

2. **Enable JavaScript:**
   Ensure JavaScript is enabled in browser settings.

3. **Clear Browser Cache:**
   ```
   Ctrl+Shift+Delete (Windows)
   Cmd+Shift+Delete (Mac)
   ```

### Mobile Browser Issues

**Symptoms:** Poor display on mobile devices, touch interface problems.

**Solutions:**

1. **Check Viewport:**
   Ensure responsive design is working properly.

2. **Test Different Mobile Browsers:**
   Chrome, Safari, Firefox mobile.

3. **Check Network Speed:**
   Mobile networks may cause timeout issues.

## Server Environment Issues

### Memory Limit Errors

**Symptom:** "Fatal error: Allowed memory size exhausted"

**Solutions:**

1. **Increase PHP Memory Limit:**
   ```ini
   memory_limit = 512M
   ```

2. **Optimize Large Directory Handling:**
   ```json
   {
     "main": {
       "cache_type": "sqlite"
     }
   }
   ```

3. **Check for Memory Leaks:**
   Monitor memory usage during operation.

### Timeout Errors

**Symptom:** "Maximum execution time exceeded"

**Solutions:**

1. **Increase Execution Time:**
   ```ini
   max_execution_time = 300
   ```

2. **Optimize Directory Processing:**
   Use caching and limit directory depth.

3. **Check Server Load:**
   ```bash
   top
   htop
   ```

### File Permission Errors

**Symptom:** Various "Permission denied" errors.

**Solutions:**

1. **Fix Web Server Permissions:**
   ```bash
   chown -R www-data:www-data .indexer_files/
   ```

2. **Set Correct File Modes:**
   ```bash
   find .indexer_files/ -type f -exec chmod 644 {} \;
   find .indexer_files/ -type d -exec chmod 755 {} \;
   ```

3. **Check SELinux (if applicable):**
   ```bash
   setsebool -P httpd_can_network_connect 1
   ```

## Debugging Procedures

### Enable Debug Mode

Add debug information to troubleshoot issues:

```php
// Add to top of index.php temporarily
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '.indexer_files/debug.log');
```

### Collect Diagnostic Information

1. **System Information:**
   ```bash
   php --version
   php -m
   ls -la .indexer_files/
   df -h
   ```

2. **Configuration Status:**
   ```bash
   cat .indexer_files/config.json
   ls -la .indexer_files/config.json*
   ```

3. **Error Logs:**
   ```bash
   tail -50 /var/log/apache2/error.log
   tail -50 .indexer_files/debug.log
   ```

### Test API Connectivity

```bash
# Test API endpoints
curl -I https://api.indexer.ccls.icu/api.php?action=status
curl https://api.indexer.ccls.icu/api.php?action=config
```

### Validate Configuration

```php
<?php
$config = json_decode(file_get_contents('.indexer_files/config.json'), true);
if (json_last_error() === JSON_ERROR_NONE) {
    echo "Configuration valid\n";
    print_r($config);
} else {
    echo "JSON Error: " . json_last_error_msg() . "\n";
}
?>
```

## Recovery Procedures

### Complete Reset

If all else fails, reset the indexer:

1. **Backup Current State:**
   ```bash
   cp -r .indexer_files .indexer_files.backup
   ```

2. **Remove Configuration:**
   ```bash
   rm -rf .indexer_files
   ```

3. **Reload Indexer:**
   Access in browser to reinitialize.

### Restore from Backup

```bash
# Restore configuration
cp .indexer_files/config.json.backup.[timestamp] .indexer_files/config.json

# Restore full directory
rm -rf .indexer_files
mv .indexer_files.backup .indexer_files
```

### Manual Configuration Recreation

Create minimal working configuration:

```json
{
  "version": "1.0",
  "main": {
    "cache_type": "json",
    "local_icons": false,
    "disable_api": false,
    "disable_file_downloads": false,
    "disable_folder_downloads": false,
    "index_hidden": false,
    "index_all": false,
    "deny_list": "",
    "allow_list": ""
  },
  "exclusions": {
    "index_folders": true,
    "index_txt": true,
    "index_php": true
  },
  "viewable_files": {
    "view_txt": true,
    "view_php": true
  }
}
```

## Getting Help

### Information to Collect

When seeking help, provide:

1. **Environment Details:**
   - Operating system and version
   - Web server type and version
   - PHP version and extensions
   - Browser type and version

2. **Error Information:**
   - Exact error messages
   - Steps to reproduce
   - When the issue started
   - Recent changes made

3. **Configuration:**
   - Relevant parts of `config.json`
   - Web server configuration
   - File permissions

4. **Logs:**
   - Web server error logs
   - PHP error logs
   - Indexer debug logs

### Support Resources

- Check GitHub repository for known issues
- Review documentation for configuration options
- Test in different environment if possible
- Consider professional system administration help for complex server issues

This troubleshooting guide covers the most common issues encountered with 5q12's Indexer and provides systematic approaches to diagnosis and resolution.