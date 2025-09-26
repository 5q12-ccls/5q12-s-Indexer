# Troubleshooting Guide

## Table of Contents
- [Quick Fixes](#quick-fixes)
- [Installation Issues](#installation-issues)
- [Performance Problems](#performance-problems)
- [Display & Interface Issues](#display--interface-issues)
- [File Access Problems](#file-access-problems)
- [Configuration Issues](#configuration-issues)
- [Security & Permission Errors](#security--permission-errors)
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
# Check configuration exists
ls -la .indexer_files/config.json

# Create basic configuration if missing
mkdir -p .indexer_files
cat > .indexer_files/config.json << 'EOF'
{
  "version": "1.0",
  "main": {
    "cache_type": "json",
    "local_icons": true
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

# Clear cache
rm -rf .indexer_files/index_cache/*
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
Icons are now always local - check if `.indexer_files/icons/` directory exists and contains icon files.

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
   <?php echo "PHP is working: " . phpversion(); ?>
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

### Configuration Directory Missing

#### Symptom: Error about `.indexer_files` directory or configuration

**Solutions:**
```bash
# Create configuration structure manually
mkdir -p .indexer_files/{zip_cache,index_cache,icons}
chmod -R 755 .indexer_files

# Create basic configuration
cat > .indexer_files/config.json << 'EOF'
{
  "version": "1.0",
  "main": {
    "cache_type": "sqlite",
    "local_icons": true,
    "disable_file_downloads": false,
    "disable_folder_downloads": false,
    "index_hidden": false,
    "deny_list": "",
    "allow_list": ""
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

chmod 644 .indexer_files/config.json
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

#### Symptom: No icons or generic icons only

**Solutions:**

1. **Check icon directory exists:**
   ```bash
   ls -la .indexer_files/icons/
   ```

2. **Check configuration:**
   ```json
   {
     "main": {
       "icon_type": "default",
       "local_icons": true
     }
   }
   ```

3. **Create basic icons manually:**
   ```bash
   mkdir -p .indexer_files/icons
   # Add icon files as needed for your file types
   ```

4. **Use emoji fallback:**
   ```json
   {
     "main": {
       "icon_type": "emoji"
     }
   }
   ```

### Interface Display Problems

#### Symptom: Broken layout, missing styles

**Solutions:**

1. **Check configuration exists:**
   ```bash
   ls -la .indexer_files/config.json
   ```

2. **Clear browser cache:**
   ```
   Ctrl+Shift+Delete (Windows)
   Cmd+Shift+Delete (Mac)
   ```

3. **Test with different browser:**
   - Chrome, Firefox, Safari, Edge
   - Disable browser extensions

4. **Create minimal configuration:**
   ```json
   {
     "version": "1.0",
     "main": {
       "cache_type": "json",
       "icon_type": "emoji"
     }
   }
   ```

### Mobile Display Issues

#### Symptom: Poor mobile interface, unresponsive design

**Solutions:**
1. **Update browser** to latest version
2. **Clear mobile browser cache**
3. **Try different mobile browser** (Chrome, Firefox, Safari)
4. **Test on different mobile device**

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

### Configuration Not Found

#### Symptom: Default behavior, no configuration applied

**Cause:** Configuration file missing or not readable.

**Solutions:**

1. **Check if configuration exists:**
   ```bash
   ls -la .indexer_files/config.json
   ```

2. **Create basic configuration:**
   ```bash
   mkdir -p .indexer_files
   cat > .indexer_files/config.json << 'EOF'
   {
     "version": "1.0",
     "main": {
       "cache_type": "sqlite",
       "local_icons": true
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
   chmod 644 .indexer_files/config.json
   ```

### Configuration Not Taking Effect

#### Symptom: Changes to config.json don't work

**Solutions:**

1. **Validate JSON syntax:**
   ```bash
   python -m json.tool .indexer_files/config.json
   ```

2. **Clear cache:**
   ```bash
   rm -rf .indexer_files/index_cache/*
   ```

3. **Check file permissions:**
   ```bash
   chmod 644 .indexer_files/config.json
   ```

4. **Restart web server:**
   ```bash
   sudo systemctl restart apache2
   # or
   sudo systemctl restart nginx
   ```

### Invalid Configuration

#### Symptom: Indexer uses defaults despite config file

**Solutions:**
```bash
# Check for JSON syntax errors
python -m json.tool .indexer_files/config.json

# Fix common JSON issues:
# - Missing commas
# - Trailing commas
# - Unquoted keys
# - Invalid values

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
ls -la index.php .indexer_files/ 2>/dev/null || echo "Configuration directory missing"
echo ""

echo "=== Disk Space ==="
df -h .
echo ""

echo "=== Configuration ==="
if [ -f .indexer_files/config.json ]; then
    echo "Config exists: YES"
    python -m json.tool .indexer_files/config.json > /dev/null 2>&1
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
    echo "Cache files: $(find .indexer_files/index_cache -type f 2>/dev/null | wc -l)"
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
        echo "Create configuration with:\n";
        echo "mkdir -p .indexer_files\n";
        echo 'cat > .indexer_files/config.json << \'EOF\'' . "\n";
        echo '{"version":"1.0","main":{"cache_type":"json"},"exclusions":{"index_folders":true},"viewable_files":{"view_txt":true}}' . "\n";
        echo "EOF\n";
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
            'local_icons' => true,
            'disable_file_downloads' => false
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
   tail -20 .indexer_files/debug.log  # if exists
   ```

### Common Solutions Summary

| Problem | Quick Fix |
|---------|-----------|
| Not loading | Check PHP installation and permissions |
| Files missing | Create configuration file manually |
| Slow performance | Enable SQLite caching |
| Icons missing | Set icon_type to "emoji" or create local icons |
| Downloads fail | Check ZIP extension and permissions |
| Config ignored | Validate JSON syntax and clear cache |
| Permission errors | Fix file ownership and permissions |
| Configuration missing | Create .indexer_files/config.json manually |

### Configuration Template

**Minimal working configuration:**
```json
{
  "version": "1.0",
  "main": {
    "cache_type": "sqlite",
    "local_icons": true,
    "disable_file_downloads": false,
    "disable_folder_downloads": false,
    "index_hidden": false,
    "deny_list": "",
    "allow_list": ""
  },
  "exclusions": {
    "index_folders": true,
    "index_txt": true,
    "index_php": true,
    "index_js": true,
    "index_html": true,
    "index_json": true,
    "index_png": true,
    "index_jpg": true,
    "index_pdf": true,
    "index_zip": true
  },
  "viewable_files": {
    "view_txt": true,
    "view_php": true,
    "view_js": true,
    "view_html": true,
    "view_json": true,
    "view_png": true,
    "view_jpg": true,
    "view_pdf": true
  }
}
```

### Recovery Procedures

**Complete reset if all else fails:**
```bash
# Backup current state
cp -r .indexer_files .indexer_files.backup 2>/dev/null || echo "No existing config"

# Remove configuration
rm -rf .indexer_files

# Create fresh configuration
mkdir -p .indexer_files/{zip_cache,index_cache,icons}
# Copy the minimal configuration template above to .indexer_files/config.json
chmod 644 .indexer_files/config.json
chmod -R 755 .indexer_files/

# Access indexer to test
curl -I https://yourdomain.com/path/to/indexer/
```

### Support Resources

1. **Repository** - Check for updates and documentation at https://ccls.icu/src/repositories/5q12-indexer/
2. **Documentation** - Review related guides:
   - [Installation Guide](installation.md) - Setup issues
   - [Configuration Guide](configuration.md) - Settings problems
   - [Security Guide](security.md) - Permission and access issues
3. **System Administrator** - Consider professional help for complex server issues

This troubleshooting guide provides systematic approaches to diagnose and resolve the most common issues with 5q12's Indexer in its current offline-only configuration.