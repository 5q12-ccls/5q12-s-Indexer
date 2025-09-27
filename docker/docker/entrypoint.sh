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
    local existing_config="$1"
    local default_config="$2"
    local output_config="$3"
    
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
    local merge_result=$?
    
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

if [ -f "/config/config.json" ]; then
    # Create a PHP script to handle environment variable processing
    cat > /tmp/process_env_config.php << 'ENV_EOF'
<?php
$configFile = '/config/config.json';
$config = json_decode(file_get_contents($configFile), true);

if (!$config) {
    echo "Error: Could not parse config.json\n";
    exit(1);
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

// Process main config mappings
foreach ($envMappings as $envVar => $configPath) {
    $value = getenv($envVar);
    if ($value !== false) {
        $originalValue = $config[$configPath[0]][$configPath[1]] ?? null;
        
        // Convert string values to appropriate types
        if (in_array($envVar, ['INDEXER_DISABLE_FILE_DOWNLOADS', 'INDEXER_DISABLE_FOLDER_DOWNLOADS', 'INDEXER_INDEX_HIDDEN', 'INDEXER_INDEX_ALL'])) {
            $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }
        
        if ($originalValue !== $value) {
            $config[$configPath[0]][$configPath[1]] = $value;
            echo "Updated {$configPath[0]}.{$configPath[1]}: " . json_encode($originalValue) . " -> " . json_encode($value) . "\n";
            $changes++;
        }
    }
}

// Process dynamic filetype settings
foreach ($_ENV as $envVar => $value) {
    // Handle index_filetype settings
    if (preg_match('/^INDEXER_INDEX_FILETYPE_([A-Z0-9_]+)$/', $envVar, $matches)) {
        $filetype = strtolower($matches[1]);
        $configKey = "index_$filetype";
        
        if (isset($config['exclusions'][$configKey])) {
            $boolValue = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            $originalValue = $config['exclusions'][$configKey];
            
            if ($originalValue !== $boolValue) {
                $config['exclusions'][$configKey] = $boolValue;
                echo "Updated exclusions.$configKey: " . json_encode($originalValue) . " -> " . json_encode($boolValue) . "\n";
                $changes++;
            }
        } else {
            echo "Warning: Unknown filetype '$filetype' for indexing (env: $envVar)\n";
        }
    }
    
    // Handle view_filetype settings  
    if (preg_match('/^INDEXER_VIEW_FILETYPE_([A-Z0-9_]+)$/', $envVar, $matches)) {
        $filetype = strtolower($matches[1]);
        $configKey = "view_$filetype";
        
        if (isset($config['viewable_files'][$configKey])) {
            $boolValue = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            $originalValue = $config['viewable_files'][$configKey];
            
            if ($originalValue !== $boolValue) {
                $config['viewable_files'][$configKey] = $boolValue;
                echo "Updated viewable_files.$configKey: " . json_encode($originalValue) . " -> " . json_encode($boolValue) . "\n";
                $changes++;
            }
        } else {
            echo "Warning: Unknown filetype '$filetype' for viewing (env: $envVar)\n";
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
    echo "Applied $changes environment variable overrides to config.json\n";
} else {
    echo "No environment variable overrides found\n";
}
ENV_EOF

    # Run the environment processing script
    php /tmp/process_env_config.php
    
    # Clean up temporary script
    rm -f /tmp/process_env_config.php
    
    echo "Environment variable processing completed"
else
    echo "Warning: config.json not found, skipping environment variable processing"
fi

# Handle other configuration files - preserve existing, copy missing
if [ -d "/app/default-config" ]; then
    echo "Checking for other missing configuration files..."
    
    # Use find to iterate through all files in default config
    find /app/default-config -type f | while read -r src_file; do
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
            mkdir -p "$(dirname "$dst_file")"
            cp "$src_file" "$dst_file"
        fi
    done
    
    echo "✓ Configuration files check completed"
else
    echo "⚠ Warning: No default config found at /app/default-config"
fi

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
nginx -t

if [ $? -ne 0 ]; then
    echo "✗ Nginx configuration test failed!"
    exit 1
fi

echo "✓ Nginx configuration test passed"

# Test PHP-FPM configuration
echo "Testing PHP-FPM configuration..."
php-fpm -t

if [ $? -ne 0 ]; then
    echo "✗ PHP-FPM configuration test failed!"
    exit 1
fi

echo "✓ PHP-FPM configuration test passed"

# Display container information
echo ""
echo "5q12's Indexer Container Information:"
echo "====================================="
echo "Environment: Docker"
echo "Index.php location: /www/indexer/index.php"
echo "Config directory: /config (mounted)"
echo "Files directory: /files (mounted)"
echo "Nginx port: 5012"
echo "Version: 1.1.18"
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
echo "Starting services..."

# Execute the main command (supervisord)
exec "$@"