#!/bin/sh

echo "Starting 5q12's Indexer..."

# Set timezone if provided
if [ ! -z "$TZ" ]; then
    echo "Setting timezone to $TZ"
    ln -sf /usr/share/zoneinfo/$TZ /etc/localtime
    echo $TZ > /etc/timezone
fi

# Ensure mount point directories exist
mkdir -p /config
mkdir -p /files

# Ensure required config subdirectories exist
echo "Ensuring config subdirectories exist..."
mkdir -p /config/zip_cache
mkdir -p /config/index_cache
mkdir -p /config/icons
mkdir -p /config/favicon

# CRITICAL: Always force-refresh local_api directory on every startup
# This ensures backend files are always compatible with current index.php
echo "Force-refreshing local_api directory (critical backend files)..."
rm -rf /config/local_api

if [ -d "/app/default-config/local_api" ]; then
    cp -r /app/default-config/local_api /config/
    echo "✓ local_api directory refreshed from defaults"
else
    # Fallback: create minimal structure if default not found
    mkdir -p /config/local_api/style
    echo "⚠ Created minimal local_api structure (default not found)"
fi

# CRITICAL: Always force-refresh php directory on every startup
# This ensures PHP class files are always compatible with current index.php
echo "Force-refreshing php directory (critical PHP class files)..."
rm -rf /config/php

if [ -d "/app/default-config/php" ]; then
    cp -r /app/default-config/php /config/
    echo "✓ php directory refreshed from defaults"
else
    # Fallback: create minimal structure if default not found
    mkdir -p /config/php
    echo "⚠ Created minimal php structure (default not found)"
fi

# Handle config.json with version checking and field merging
echo "Checking config.json version and updating if needed..."

# Function to merge JSON configs using jq-like shell operations
merge_config_json() {
    existing_config="$1"
    default_config="$2"
    output_config="$3"
    
    # Create a temporary script to merge configs
    cat > /tmp/merge_config.php << 'MERGE_EOF'
<?php
$existing = json_decode(file_get_contents($argv[1]), true);
$default = json_decode(file_get_contents($argv[2]), true);

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
file_put_contents($argv[3], json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "Config merge completed successfully\n";
MERGE_EOF

    # Run the merge script
    php /tmp/merge_config.php "$existing_config" "$default_config" "$output_config"
    merge_result=$?
    
    # Clean up temporary script
    rm -f /tmp/merge_config.php
    
    return $merge_result
}

if [ -f "/app/default-config/config.json" ]; then
    if [ -f "/config/config.json" ]; then
        echo "Existing config.json found, checking for updates..."
        
        # Create backup of existing config
        cp /config/config.json /config/config.json.backup
        
        # Attempt to merge configs
        if merge_config_json "/config/config.json" "/app/default-config/config.json" "/config/config.json.new"; then
            # Replace the existing config with merged version
            mv /config/config.json.new /config/config.json
            echo "✓ Config.json updated with missing fields and latest version"
        else
            echo "⚠ Config merge failed, keeping existing config.json"
            # Restore backup if merge failed
            if [ -f "/config/config.json.backup" ]; then
                mv /config/config.json.backup /config/config.json
            fi
        fi
        
        # Clean up any leftover files
        rm -f /config/config.json.new /config/config.json.backup
    else
        echo "No existing config.json, copying default..."
        cp /app/default-config/config.json /config/config.json
        echo "✓ Default config.json copied"
    fi
else
    echo "⚠ No default config.json found"
    
    # Create minimal config.json if neither exists
    if [ ! -f "/config/config.json" ]; then
        echo "ERROR: No config.json found and no default available!"
        echo "This should not happen in a properly built container."
        exit 1
    fi
fi

# Process environment variables and update config.json
echo "Processing environment variable overrides..."

# Enhanced debugging - check multiple environment sources
echo "=== DEBUG: Environment Variable Sources ==="
echo "1. Shell environment (env | grep INDEXER_):"
env | grep "^INDEXER_" | sort || echo "  No INDEXER_* variables found in shell env"

echo "2. /proc/self/environ:"
if [ -f "/proc/self/environ" ]; then
    cat /proc/self/environ | tr '\0' '\n' | grep "^INDEXER_" | sort || echo "  No INDEXER_* variables found in /proc/self/environ"
else
    echo "  /proc/self/environ not available"
fi

echo "3. s6 environment files:"
if [ -d "/var/run/s6/container_environment" ]; then
    ls -la /var/run/s6/container_environment/ | grep "INDEXER_" || echo "  No INDEXER_* files found"
    for envfile in /var/run/s6/container_environment/INDEXER_*; do
        if [ -f "$envfile" ]; then
            echo "  $(basename "$envfile")=$(cat "$envfile")"
        fi
    done
else
    echo "  s6 environment directory not found"
fi
echo "=== END DEBUG ==="

if [ -f "/config/config.json" ]; then
    # Create environment file for s6-overlay to ensure variables are available
    echo "Creating environment backup file..."
    env | grep "^INDEXER_" > /tmp/indexer_env_vars.txt || echo "# No INDEXER vars found" > /tmp/indexer_env_vars.txt
    
    # Source the environment file to ensure variables are available in this shell
    if [ -s /tmp/indexer_env_vars.txt ] && [ "$(head -1 /tmp/indexer_env_vars.txt)" != "# No INDEXER vars found" ]; then
        echo "Sourcing environment variables..."
        # Convert env output to shell variable assignments
        sed 's/^/export /' /tmp/indexer_env_vars.txt > /tmp/indexer_env_source.sh
        . /tmp/indexer_env_source.sh
        echo "Environment variables sourced successfully"
    fi
    
    # Create a PHP script to handle environment variable processing with better error handling
    cat > /tmp/process_env_config.php << 'ENV_EOF'
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$configFile = '/config/config.json';
$config = json_decode(file_get_contents($configFile), true);

if (!$config) {
    echo "Error: Could not parse config.json\n";
    exit(1);
}

echo "Config file loaded successfully\n";

// Function to get environment variable with multiple fallback methods
function getEnvironmentVar($varName) {
    // Method 1: getenv()
    $value = getenv($varName);
    if ($value !== false) {
        echo "Found $varName via getenv(): $value\n";
        return $value;
    }
    
    // Method 2: $_ENV array
    if (isset($_ENV[$varName])) {
        echo "Found $varName via \$_ENV: {$_ENV[$varName]}\n";
        return $_ENV[$varName];
    }
    
    // Method 3: $_SERVER array
    if (isset($_SERVER[$varName])) {
        echo "Found $varName via \$_SERVER: {$_SERVER[$varName]}\n";
        return $_SERVER[$varName];
    }
    
    // Method 4: Read from file if available
    $envFile = "/tmp/indexer_env_vars.txt";
    if (file_exists($envFile)) {
        $envLines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($envLines as $line) {
            if (strpos($line, $varName . '=') === 0) {
                $value = substr($line, strlen($varName) + 1);
                echo "Found $varName via env file: $value\n";
                return $value;
            }
        }
    }
    
    // Method 5: s6-overlay environment files
    $s6EnvFile = "/var/run/s6/container_environment/$varName";
    if (file_exists($s6EnvFile)) {
        $value = trim(file_get_contents($s6EnvFile));
        echo "Found $varName via s6 env file: $value\n";
        return $value;
    }
    
    return false;
}

// Function to get all INDEXER_* environment variables
function getAllIndexerVars() {
    $allVars = [];
    
    // Known environment variables to check
    $knownVars = [
        'INDEXER_ACCESS_URL', 'INDEXER_CACHE_TYPE', 'INDEXER_ICON_TYPE',
        'INDEXER_DISABLE_FILE_DOWNLOADS', 'INDEXER_DISABLE_FOLDER_DOWNLOADS',
        'INDEXER_INDEX_HIDDEN', 'INDEXER_INDEX_ALL', 'INDEXER_DENY_LIST', 'INDEXER_ALLOW_LIST'
    ];
    
    foreach ($knownVars as $var) {
        $value = getEnvironmentVar($var);
        if ($value !== false) {
            $allVars[$var] = $value;
        }
    }
    
    // Check env file for any other INDEXER_ variables
    $envFile = "/tmp/indexer_env_vars.txt";
    if (file_exists($envFile)) {
        $envLines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($envLines as $line) {
            if (strpos($line, 'INDEXER_') === 0 && strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                if (!isset($allVars[$key])) {
                    $allVars[$key] = $value;
                    echo "Found additional $key via env file: $value\n";
                }
            }
        }
    }
    
    // Check s6-overlay environment directory
    $s6EnvDir = "/var/run/s6/container_environment";
    if (is_dir($s6EnvDir)) {
        $files = scandir($s6EnvDir);
        foreach ($files as $file) {
            if (strpos($file, 'INDEXER_') === 0) {
                $filePath = "$s6EnvDir/$file";
                if (is_file($filePath)) {
                    $value = trim(file_get_contents($filePath));
                    if (!isset($allVars[$file])) {
                        $allVars[$file] = $value;
                        echo "Found additional $file via s6 env dir: $value\n";
                    }
                }
            }
        }
    }
    
    return $allVars;
}

// Function to normalize filetype names for config lookup
function normalizeFiletypeName($envFiletype) {
    // Convert to lowercase first
    $normalized = strtolower($envFiletype);
    
    // Special mappings for known problematic cases
    $specialMappings = [
        'non-descript-files' => 'non-descript-files',
        'non_descript_files' => 'non-descript-files',
    ];
    
    if (isset($specialMappings[$normalized])) {
        return $specialMappings[$normalized];
    }
    
    // Default: replace underscores with hyphens and hyphens with underscores to try both
    return $normalized;
}

// Mapping of environment variables to config keys
$envMappings = [
    'INDEXER_ACCESS_URL' => ['main', 'access_url'],
    'INDEXER_CACHE_TYPE' => ['main', 'cache_type'], 
    'INDEXER_ICON_TYPE' => ['main', 'icon_type'],
    'INDEXER_DISABLE_FILE_DOWNLOADS' => ['main', 'disable_file_downloads'],
    'INDEXER_DISABLE_FOLDER_DOWNLOADS' => ['main', 'disable_folder_downloads'],
    'INDEXER_INDEX_HIDDEN' => ['main', 'index_hidden'],
    'INDEXER_INDEX_ALL' => ['main', 'index_all'],
    'INDEXER_DENY_LIST' => ['main', 'deny_list'],
    'INDEXER_ALLOW_LIST' => ['main', 'allow_list']
];

$changes = 0;

// Get all environment variables
$allEnvVars = getAllIndexerVars();

echo "Found " . count($allEnvVars) . " INDEXER_* environment variables total\n";

// Process main config mappings
foreach ($envMappings as $envVar => $configPath) {
    $value = isset($allEnvVars[$envVar]) ? $allEnvVars[$envVar] : false;
    if ($value !== false) {
        // Ensure the config section exists
        if (!isset($config[$configPath[0]])) {
            $config[$configPath[0]] = [];
        }
        
        $originalValue = $config[$configPath[0]][$configPath[1]] ?? null;
        
        // Convert string values to appropriate types
        if (in_array($envVar, ['INDEXER_DISABLE_FILE_DOWNLOADS', 'INDEXER_DISABLE_FOLDER_DOWNLOADS', 'INDEXER_INDEX_HIDDEN', 'INDEXER_INDEX_ALL'])) {
            $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }
        
        if ($originalValue !== $value) {
            $config[$configPath[0]][$configPath[1]] = $value;
            echo "✓ Updated {$configPath[0]}.{$configPath[1]}: " . json_encode($originalValue) . " -> " . json_encode($value) . "\n";
            $changes++;
        } else {
            echo "  {$configPath[0]}.{$configPath[1]} already set to: " . json_encode($value) . "\n";
        }
    }
}

// Process dynamic filetype settings
foreach ($allEnvVars as $envVar => $value) {
    // Handle index_filetype settings
    if (preg_match('/^INDEXER_INDEX_FILETYPE_([A-Z0-9_-]+)$/', $envVar, $matches)) {
        $rawFiletype = $matches[1];
        $filetype = normalizeFiletypeName($rawFiletype);
        $configKey = "index_$filetype";
        
        // Ensure exclusions section exists
        if (!isset($config['exclusions'])) {
            $config['exclusions'] = [];
        }
        
        // Try the normalized version first, then try with underscores if that doesn't work
        $possibleKeys = [$configKey];
        if (strpos($filetype, '-') !== false) {
            $possibleKeys[] = "index_" . str_replace('-', '_', $filetype);
        }
        if (strpos($filetype, '_') !== false) {
            $possibleKeys[] = "index_" . str_replace('_', '-', $filetype);
        }
        
        $keyFound = false;
        foreach ($possibleKeys as $tryKey) {
            if (isset($config['exclusions'][$tryKey])) {
                $boolValue = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                $originalValue = $config['exclusions'][$tryKey];
                
                if ($originalValue !== $boolValue) {
                    $config['exclusions'][$tryKey] = $boolValue;
                    echo "✓ Updated exclusions.$tryKey: " . json_encode($originalValue) . " -> " . json_encode($boolValue) . "\n";
                    $changes++;
                }
                $keyFound = true;
                break;
            }
        }
        
        if (!$keyFound) {
            echo "⚠ Warning: Unknown filetype '$filetype' (raw: '$rawFiletype') for indexing (env: $envVar)\n";
            echo "  Tried keys: " . implode(', ', $possibleKeys) . "\n";
            if (isset($config['exclusions'])) {
                $availableKeys = array_filter(array_keys($config['exclusions']), function($k) { return strpos($k, 'index_') === 0; });
                echo "  Available index keys: " . implode(', ', $availableKeys) . "\n";
            }
        }
    }
    
    // Handle view_filetype settings  
    if (preg_match('/^INDEXER_VIEW_FILETYPE_([A-Z0-9_-]+)$/', $envVar, $matches)) {
        $rawFiletype = $matches[1];
        $filetype = normalizeFiletypeName($rawFiletype);
        $configKey = "view_$filetype";
        
        // Ensure viewable_files section exists
        if (!isset($config['viewable_files'])) {
            $config['viewable_files'] = [];
        }
        
        // Try the normalized version first, then try with underscores if that doesn't work
        $possibleKeys = [$configKey];
        if (strpos($filetype, '-') !== false) {
            $possibleKeys[] = "view_" . str_replace('-', '_', $filetype);
        }
        if (strpos($filetype, '_') !== false) {
            $possibleKeys[] = "view_" . str_replace('_', '-', $filetype);
        }
        
        $keyFound = false;
        foreach ($possibleKeys as $tryKey) {
            if (isset($config['viewable_files'][$tryKey])) {
                $boolValue = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                $originalValue = $config['viewable_files'][$tryKey];
                
                if ($originalValue !== $boolValue) {
                    $config['viewable_files'][$tryKey] = $boolValue;
                    echo "✓ Updated viewable_files.$tryKey: " . json_encode($originalValue) . " -> " . json_encode($boolValue) . "\n";
                    $changes++;
                }
                $keyFound = true;
                break;
            }
        }
        
        if (!$keyFound) {
            echo "⚠ Warning: Unknown filetype '$filetype' (raw: '$rawFiletype') for viewing (env: $envVar)\n";
            echo "  Tried keys: " . implode(', ', $possibleKeys) . "\n";
            if (isset($config['viewable_files'])) {
                $availableKeys = array_filter(array_keys($config['viewable_files']), function($k) { return strpos($k, 'view_') === 0; });
                echo "  Available view keys: " . implode(', ', $availableKeys) . "\n";
            }
        }
    }
}

if ($changes > 0) {
    // Write updated config back to file
    $result = file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    if ($result === false) {
        echo "Error: Could not write updated config.json\n";
        exit(1);
    }
    echo "✓ Applied $changes environment variable overrides to config.json\n";
} else {
    echo "No environment variable changes applied (all values already correct or no vars found)\n";
}

// Final debug output
echo "\n=== Final Environment Variables Summary ===\n";
foreach ($allEnvVars as $key => $value) {
    echo "  $key = $value\n";
}
echo "=== End Summary ===\n";
?>
ENV_EOF

    # Run the environment processing script with verbose output
    echo "Running PHP environment processing script..."
    php /tmp/process_env_config.php
    env_result=$?
    
    # Clean up temporary files
    rm -f /tmp/process_env_config.php /tmp/indexer_env_vars.txt /tmp/indexer_env_source.sh
    
    if [ $env_result -eq 0 ]; then
        echo "✓ Environment variable processing completed successfully"
    else
        echo "⚠ Warning: Environment variable processing failed with exit code $env_result"
    fi
else
    echo "Warning: config.json not found, skipping environment variable processing"
fi

# Handle other configuration files - preserve existing, copy missing
if [ -d "/app/default-config" ]; then
    echo "Checking for other missing configuration files..."
    
    # Create a temporary file list to avoid subshell issues with the while loop
    temp_file_list="/tmp/default_config_files.txt"
    find /app/default-config -type f > "$temp_file_list"
    
    # Read the file list line by line
    while IFS= read -r src_file; do
        # Skip empty lines
        [ -z "$src_file" ] && continue
        
        # Get relative path from default config root
        rel_path="${src_file#/app/default-config/}"
        dst_file="/config/$rel_path"
        
        # Skip files we've already handled
        case "$rel_path" in
            local_api/*|php/*|config.json)
                continue
                ;;
        esac
        
        # Copy file if it doesn't exist in destination
        if [ ! -f "$dst_file" ]; then
            echo "Copying missing file: $rel_path"
            # Ensure destination directory exists
            dst_dir="$(dirname "$dst_file")"
            mkdir -p "$dst_dir"
            
            # Copy the file and verify
            if cp "$src_file" "$dst_file"; then
                echo "  ✓ Successfully copied: $rel_path"
                chown www-data:www-data "$dst_file"
            else
                echo "  ✗ Failed to copy: $rel_path"
            fi
        fi
    done < "$temp_file_list"
    
    # Clean up temp file
    rm -f "$temp_file_list"
    
    echo "✓ Configuration files check completed"
else
    echo "⚠ Warning: No default config found at /app/default-config"
fi

# Ensure critical directories exist with proper ownership - POSIX compliant version
critical_dirs="/config/zip_cache /config/index_cache /config/icons /config/favicon /config/local_api /config/php"

for dir in $critical_dirs; do
    if [ ! -d "$dir" ]; then
        echo "Creating missing critical directory: $dir"
        mkdir -p "$dir"
    fi
    chown www-data:www-data "$dir"
done

# Remove existing symlinks/directories if they exist and aren't symlinks
echo "Setting up symlinks..."
if [ -d "/www/indexer/.indexer_files" ] && [ ! -L "/www/indexer/.indexer_files" ]; then
    rm -rf /www/indexer/.indexer_files
fi
if [ -d "/www/indexer/files" ] && [ ! -L "/www/indexer/files" ]; then
    rm -rf /www/indexer/files
fi

# Remove existing symlinks to recreate them
if [ -L "/www/indexer/.indexer_files" ]; then
    rm /www/indexer/.indexer_files
fi
if [ -L "/www/indexer/files" ]; then
    rm /www/indexer/files
fi

# Create symlinks
ln -sf /config /www/indexer/.indexer_files
ln -sf /files /www/indexer/files

echo "Symlinks created:"
echo "  /www/indexer/.indexer_files -> /config"
echo "  /www/indexer/files -> /files"

# Set proper ownership for all directories
echo "Setting proper ownership..."
chown -R www-data:www-data /config
chown -R www-data:www-data /files
chown -R www-data:www-data /www/indexer

# Verify symlinks
echo "Verifying symlinks..."
if [ -L "/www/indexer/.indexer_files" ]; then
    echo "✓ .indexer_files symlink created successfully"
else
    echo "✗ Failed to create .indexer_files symlink"
    exit 1
fi

if [ -L "/www/indexer/files" ]; then
    echo "✓ files symlink created successfully"
else
    echo "✗ Failed to create files symlink"
    exit 1
fi

# Test nginx configuration
echo "Testing nginx configuration..."
if nginx -t; then
    echo "✓ Nginx configuration test passed"
else
    echo "✗ Nginx configuration test failed!"
    exit 1
fi

# Test PHP-FPM configuration
echo "Testing PHP-FPM configuration..."
if php-fpm -t; then
    echo "✓ PHP-FPM configuration test passed"
else
    echo "✗ PHP-FPM configuration test failed!"
    exit 1
fi

# Display container information
echo ""
echo "5q12's Indexer Container Information:"
echo "====================================="
echo "Environment: Docker with S6-Overlay"
echo "Index.php location: /www/indexer/index.php"
echo "Config directory: /config (mounted)"
echo "Files directory: /files (mounted)"
echo "Nginx port: 5012"
echo "Version: 1.1.19"
echo ""
echo "Volume mounts should be:"
echo "  -v /host/config:/config"
echo "  -v /host/files:/files"
echo ""
echo "Configuration status:"
if [ -f "/config/config.json" ]; then
    # Try to extract version from config.json
    if command -v php >/dev/null 2>&1; then
        current_version=$(php -r "
            \$config = json_decode(file_get_contents('/config/config.json'), true);
            echo isset(\$config['version']) ? \$config['version'] : 'unknown';
        " 2>/dev/null || echo "unknown")
        echo "  ✓ config.json: Present (version: $current_version)"
    else
        echo "  ✓ config.json: Present"
    fi
else
    echo "  ✗ config.json: Missing"
fi

if [ -d "/config/local_api" ]; then
    echo "  ✓ local_api: Refreshed"
else
    echo "  ✗ local_api: Missing"
fi

if [ -d "/config/php" ]; then
    echo "  ✓ php: Refreshed"
else
    echo "  ✗ php: Missing"
fi

if [ -d "/config/zip_cache" ]; then
    echo "  ✓ zip_cache: Ready"
fi

if [ -d "/config/index_cache" ]; then
    echo "  ✓ index_cache: Ready"
fi

echo ""
echo "✓ 5q12's Indexer initialization completed successfully!"
echo "Starting services: nginx + php-fpm"