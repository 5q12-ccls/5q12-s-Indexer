# API Reference

## Table of Contents
- [Overview](#overview)
- [Base URL & Authentication](#base-url--authentication)
- [Configuration Endpoints](#configuration-endpoints)
- [Extension Management](#extension-management)
- [Icon Management](#icon-management)
- [System Information](#system-information)
- [Direct Resource Access](#direct-resource-access)
- [Error Handling](#error-handling)
- [Client Implementation](#client-implementation)

## Overview

The 5q12's Indexer API provides configuration management, icon delivery, and resource updates for the indexer system. The API uses RESTful endpoints with JSON responses.

### API Features
- **Configuration Management**: Version checking and updates
- **Extension Mappings**: File type to configuration mappings
- **Icon System**: File type icons and metadata
- **Resource Delivery**: Stylesheets, fonts, and static files
- **System Status**: Health checks and diagnostics

## Base URL & Authentication

### Base URL
```
https://api.indexer.ccls.icu
```

### Authentication
- **No authentication required** for current version
- **Rate limiting**: Reasonable limits for normal usage
- **HTTPS required**: All requests must use HTTPS

### Request Headers
```http
User-Agent: 5q12-Indexer/1.0
Accept: application/json
```

## Configuration Endpoints

### Get Configuration
Get the complete configuration file with all settings.

```http
GET /api.php?action=config
```

**Response:**
```json
{
  "success": true,
  "config": {
    "version": "1.0",
    "main": { /* main settings */ },
    "exclusions": { /* file type indexing */ },
    "viewable_files": { /* file type viewing */ }
  },
  "version": "1.0",
  "last_modified": 1640995200
}
```

**Use cases:**
- Initial configuration download
- Manual configuration updates
- Backup/restore operations

### Version Check
Check if a configuration update is available.

```http
GET /api.php?action=versionCheck&current_version={version}
```

**Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `current_version` | string | Yes | Current configuration version |

**Response (Update Available):**
```json
{
  "success": true,
  "current_version": "1.0",
  "latest_version": "1.1",
  "update_needed": true,
  "message": "Update available: 1.0 -> 1.1",
  "config_url": "https://api.indexer.ccls.icu/api.php?action=config",
  "timestamp": "2025-01-10 15:30:00"
}
```

**Response (No Update):**
```json
{
  "success": true,
  "current_version": "1.0",
  "latest_version": "1.0",
  "update_needed": false,
  "message": "You have the latest version: 1.0",
  "timestamp": "2025-01-10 15:30:00"
}
```

## Extension Management

### Get Extension Mappings
Returns file extension to configuration key mappings.

```http
GET /api.php?action=extensionMappings&type={type}
```

**Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `type` | string | No | `"all"`, `"indexing"`, or `"viewing"` (default: `"all"`) |

**Response:**
```json
{
  "success": true,
  "mappings": {
    "indexing": {
      "php": "index_php",
      "js": "index_js",
      "py": "index_py"
    },
    "viewing": {
      "php": "view_php",
      "js": "view_js",
      "py": "view_py"
    }
  },
  "indexing_count": 200,
  "viewing_count": 180
}
```

**Type-specific responses:**
```http
GET /api.php?action=extensionMappings&type=indexing
```
Returns only indexing mappings with `indexing_count`.

```http
GET /api.php?action=extensionMappings&type=viewing
```
Returns only viewing mappings with `viewing_count`.

### Get Extension Setting
Get the configuration key for a specific file extension.

```http
GET /api.php?action=getExtensionSetting&extension={ext}&type={type}
```

**Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `extension` | string | Yes | File extension (without dot) |
| `type` | string | No | `"indexing"` or `"viewing"` (default: `"indexing"`) |

**Response:**
```json
{
  "success": true,
  "extension": "php",
  "type": "indexing",
  "setting": "index_php",
  "found": true
}
```

**Not found response:**
```json
{
  "success": true,
  "extension": "unknownext",
  "type": "indexing",
  "setting": null,
  "found": false
}
```

## Icon Management

### List All Icons
Get a complete list of available icon files.

```http
GET /api.php?action=icons
```

**Response:**
```json
{
  "success": true,
  "icons": [
    {
      "filename": "php.png",
      "url": "https://api.indexer.ccls.icu/icons/php.png",
      "size": 2048,
      "last_modified": 1640995200
    },
    {
      "filename": "folder.png",
      "url": "https://api.indexer.ccls.icu/icons/folder.png",
      "size": 1534,
      "last_modified": 1640995200
    }
  ],
  "count": 150
}
```

### Get Specific Icon
Get information about a specific icon file.

```http
GET /api.php?action=icon&name={filename}
```

**Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `name` | string | Yes | Icon filename (e.g., "php.png") |

**Response:**
```json
{
  "success": true,
  "icon": {
    "filename": "php.png",
    "url": "https://api.indexer.ccls.icu/icons/php.png",
    "size": 2048,
    "last_modified": 1640995200
  }
}
```

### Find Icon for File Type
Find the appropriate icon for a file type or folder.

```http
GET /api.php?action=findIcon&type={type}&extension={ext}
```

**Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `type` | string | Yes | `"file"` or `"folder"` |
| `extension` | string | Conditional | Required for files, ignored for folders |

**Response:**
```json
{
  "success": true,
  "type": "file",
  "extension": "php",
  "icon": {
    "filename": "php.png",
    "url": "https://api.indexer.ccls.icu/icons/php.png",
    "path": "/path/to/icons/php.png",
    "size": 2048,
    "last_modified": 1640995200
  }
}
```

**Folder example:**
```http
GET /api.php?action=findIcon&type=folder
```

### Get Icon Mappings
Get the complete mapping of extensions to icon filenames.

```http
GET /api.php?action=iconMappings
```

**Response:**
```json
{
  "success": true,
  "mappings": {
    "php": "php.png",
    "js": "js.png",
    "py": "python.png",
    "folder": "folder.png"
  },
  "count": 150
}
```

## System Information

### API Status
Get API health and status information.

```http
GET /api.php?action=status
```

**Response:**
```json
{
  "success": true,
  "service": "Indexer API",
  "version": "1.0",
  "timestamp": "2025-01-10 15:30:00",
  "config_exists": true,
  "icons_dir_exists": true,
  "icon_mappings_exists": true,
  "extension_mappings_exists": true,
  "icon_count": 150,
  "mapping_count": 200,
  "extension_mapping_count": 380,
  "config_last_modified": 1640995200,
  "mappings_last_modified": 1640995200
}
```

### API Information
Get general API information and available endpoints.

```http
GET /api.php
```

**Response:**
```json
{
  "success": true,
  "service": "Indexer API",
  "version": "1.0",
  "description": "API for the custom indexer project",
  "endpoints": {
    "versionCheck": {
      "url": "?action=versionCheck&current_version={version}",
      "description": "Check if a config update is available",
      "method": "GET"
    },
    "config": {
      "url": "?action=config",
      "description": "Get configuration file",
      "method": "GET"
    }
  },
  "icon_base_url": "https://api.indexer.ccls.icu/icons/",
  "timestamp": "2025-01-10 15:30:00"
}
```

## Direct Resource Access

### Icon Files
Direct access to icon files for display in the indexer.

```http
GET /icons/{filename}
```

**Examples:**
```
GET /icons/php.png
GET /icons/folder.png
GET /icons/text.png
```

**Response:** Binary image data with appropriate MIME type.

### Stylesheets
Access to CSS stylesheets and web fonts.

```http
GET /style/{filename}
```

**Examples:**
```
GET /style/8d9f7fa8de5d3bac302028ab474b30b4.css
GET /style/latin-400.woff2
```

### Configuration Files
Direct access to configuration and mapping files.

```http
GET /{filename}
```

**Examples:**
```
GET /config.json
GET /extensionMap.json
GET /icons.json
```

## Error Handling

### Error Response Format
All API endpoints return errors in a consistent format:

```json
{
  "error": "Error message description",
  "status": 400
}
```

### HTTP Status Codes

| Code | Description | Common Causes |
|------|-------------|---------------|
| **200** | OK | Successful request |
| **400** | Bad Request | Missing/invalid parameters |
| **404** | Not Found | Resource doesn't exist |
| **500** | Internal Server Error | Server-side error |

### Common Error Examples

**Missing Parameters:**
```json
{
  "error": "Extension parameter is required",
  "status": 400
}
```

**Resource Not Found:**
```json
{
  "error": "Icon not found",
  "status": 404
}
```

**Invalid Configuration:**
```json
{
  "error": "Invalid configuration file",
  "status": 500
}
```

## Client Implementation

### Caching Strategy

The indexer implements intelligent caching:

| Endpoint | Cache Duration | Use Case |
|----------|----------------|----------|
| Configuration | 1 hour | Regular updates |
| Version checks | 15 minutes | Frequent checks |
| Extension mappings | 24 hours | Rarely change |
| Icon data | 24 hours | Static resources |

### Timeout Recommendations

| Request Type | Timeout | Reason |
|--------------|---------|---------|
| Configuration | 30 seconds | Large payload |
| Icons | 15 seconds | Binary data |
| Version checks | 10 seconds | Quick response needed |

### PHP Example

```php
<?php
function callIndexerAPI($endpoint, $params = []) {
    $baseUrl = 'https://api.indexer.ccls.icu/api.php';
    $url = $baseUrl . '?action=' . $endpoint;
    
    if (!empty($params)) {
        $url .= '&' . http_build_query($params);
    }
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 30,
            'user_agent' => '5q12-Indexer/1.0'
        ]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        return null; // Handle error appropriately
    }
    
    $data = json_decode($response, true);
    
    if (isset($data['error'])) {
        error_log("API Error: " . $data['error']);
        return null;
    }
    
    return $data;
}

// Usage examples
$config = callIndexerAPI('config');
$versionCheck = callIndexerAPI('versionCheck', ['current_version' => '1.0']);
$iconInfo = callIndexerAPI('findIcon', ['type' => 'file', 'extension' => 'php']);
?>
```

### JavaScript Example

```javascript
class IndexerAPI {
    constructor(baseUrl = 'https://api.indexer.ccls.icu') {
        this.baseUrl = baseUrl;
    }
    
    async call(endpoint, params = {}) {
        const url = new URL(`${this.baseUrl}/api.php`);
        url.searchParams.set('action', endpoint);
        
        Object.keys(params).forEach(key => {
            url.searchParams.set(key, params[key]);
        });
        
        try {
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'User-Agent': '5q12-Indexer/1.0'
                },
                timeout: 30000
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.error) {
                throw new Error(data.error);
            }
            
            return data;
            
        } catch (error) {
            console.error('API call failed:', error);
            return null;
        }
    }
    
    // Convenience methods
    async getConfig() {
        return this.call('config');
    }
    
    async checkVersion(currentVersion) {
        return this.call('versionCheck', { current_version: currentVersion });
    }
    
    async findIcon(type, extension = '') {
        return this.call('findIcon', { type, extension });
    }
}

// Usage
const api = new IndexerAPI();
const config = await api.getConfig();
const versionCheck = await api.checkVersion('1.0');
```

### Error Handling Best Practices

1. **Always check for errors** in API responses
2. **Implement fallback behavior** for failed requests
3. **Cache successful responses** to reduce API calls
4. **Log errors appropriately** for debugging
5. **Handle network timeouts** gracefully

### Rate Limiting Considerations

- **Normal usage**: No strict limits
- **Bulk requests**: Should be avoided
- **Cache responses**: Locally when possible
- **Respect server resources**: Don't make unnecessary calls

---

**Related Documentation:**
- [Configuration Guide](configuration.md) - Understanding configuration structure
- [Installation Guide](installation.md) - API integration setup