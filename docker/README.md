# 5q12's Indexer - Docker Image

A PHP-Based file browser with sorting, filtering, download, icons and caching. Configurable indexing with intelligent configuration management and environment variable support.

## Quick Start

```bash
# Create docker-compose.yml
cat > docker-compose.yml << EOF
services:
  5q12-indexer:
    image: 5q12/5q12-indexer:latest
    container_name: 5q12-indexer
    restart: unless-stopped
    ports:
      - "5012:5012"  # Access the indexer on port 5012
    environment:
      - TZ=Etc/UTC   # Set your timezone (optional)
    volumes:
      # Configuration directory - stores settings and cache
      - /example_host_path/config:/config
      
      # Files directory - mount your content here to index
      - /example_host_path/files:/files
EOF

# Start the indexer
docker compose up -d
```

Access at: `http://localhost:5012`

**Important:** Replace `/example_host_path/` with your actual host directories. The `/config` volume persists settings and cache, while `/files` contains the content you want to browse.

## What's Included

- **Alpine Linux** base image for small size
- **PHP 8.3-FPM** with required extensions
- **Nginx** web server with optimized configuration
- **Supervisor** for process management
- **SQLite** support for high-performance caching
- **ZIP** support for folder downloads
- **Smart config management** with version-aware updates
- **Environment variable configuration** for Docker-native setup

## Supported Tags

- `latest` - Latest stable release (v1.1.18)
- `1.1.18` - Specific release version

## Volumes

| Path | Purpose | Required | Notes |
|------|---------|----------|--------|
| `/config` | Configuration, cache, and local resources | Yes | Auto-managed, preserves user settings |
| `/files` | Content directory to browse and index | Yes | Supports dot folders/files |

## Environment Variables

### Basic Configuration

| Variable | Default | Description |
|----------|---------|-------------|
| `TZ` | `Etc/UTC` | Container timezone |

### Main Settings

| Variable | Default | Description |
|----------|---------|-------------|
| `INDEXER_ACCESS_URL` | `""` | Base URL for generating absolute links |
| `INDEXER_CACHE_TYPE` | `sqlite` | Cache type (`sqlite` or `json`) |
| `INDEXER_ICON_TYPE` | `default` | Icon display (`default`, `minimal`, `emoji`, `disabled`) |
| `INDEXER_DISABLE_FILE_DOWNLOADS` | `false` | Disable individual file downloads |
| `INDEXER_DISABLE_FOLDER_DOWNLOADS` | `false` | Disable folder ZIP downloads |
| `INDEXER_INDEX_HIDDEN` | `false` | Index hidden files/folders (starting with `.`) |
| `INDEXER_INDEX_ALL` | `false` | Index all files regardless of other settings |
| `INDEXER_DENY_LIST` | `""` | Comma-separated list of paths to deny |
| `INDEXER_ALLOW_LIST` | `""` | Comma-separated list of paths to allow |

### File Type Configuration

Configure indexing and viewing for any file type using these patterns:

- `INDEXER_INDEX_FILETYPE_{TYPE}=true/false` - Whether to show files of this type in listings
- `INDEXER_VIEW_FILETYPE_{TYPE}=true/false` - Whether files of this type can be viewed in browser

Examples:
```yaml
environment:
  # Disable PHP file indexing and viewing
  - INDEXER_INDEX_FILETYPE_PHP=false
  - INDEXER_VIEW_FILETYPE_PHP=false
  
  # Enable JavaScript viewing but disable Markdown
  - INDEXER_VIEW_FILETYPE_JS=true
  - INDEXER_INDEX_FILETYPE_MD=false
  - INDEXER_VIEW_FILETYPE_MD=false
  
  # Configure image types
  - INDEXER_INDEX_FILETYPE_PNG=true
  - INDEXER_VIEW_FILETYPE_PNG=true
```

## Port

The container exposes port `5012` for the web interface.

## Configuration Management

The indexer features intelligent configuration management:

- **Automatic Updates**: Missing config fields are added when updating containers
- **Environment Override**: Environment variables take precedence over config.json
- **Setting Preservation**: Your customizations are never lost during updates
- **Version Tracking**: Config version automatically matches container version
- **Dynamic Filetype Support**: Add support for new file types without rebuilding

## Example Configurations

### Basic File Server
```yaml
services:
  5q12-indexer:
    image: 5q12/5q12-indexer:latest
    container_name: file-server
    restart: unless-stopped
    ports:
      - "8080:5012"
    volumes:
      - ./config:/config
      - ./public-files:/files
```

### Secure Document Server
```yaml
services:
  5q12-indexer:
    image: 5q12/5q12-indexer:latest
    container_name: secure-docs
    restart: unless-stopped
    ports:
      - "5012:5012"
    environment:
      - TZ=America/New_York
      - INDEXER_ACCESS_URL=https://docs.example.com
      - INDEXER_ICON_TYPE=minimal
      - INDEXER_INDEX_HIDDEN=false
      - INDEXER_DENY_LIST=admin,logs,.git,private/*
      - INDEXER_INDEX_FILETYPE_PHP=false
      - INDEXER_INDEX_FILETYPE_SH=false
      - INDEXER_VIEW_FILETYPE_PDF=true
      - INDEXER_VIEW_FILETYPE_MD=true
    volumes:
      - ./config:/config
      - ./documents:/files:ro
```

### Media Server with Restrictions
```yaml
services:
  5q12-indexer:
    image: 5q12/5q12-indexer:latest
    container_name: media-server
    restart: unless-stopped
    ports:
      - "5012:5012"
    environment:
      - TZ=Etc/UTC
      - INDEXER_CACHE_TYPE=sqlite
      - INDEXER_DISABLE_FILE_DOWNLOADS=true
      - INDEXER_DISABLE_FOLDER_DOWNLOADS=true
      - INDEXER_INDEX_FILETYPE_MP4=true
      - INDEXER_VIEW_FILETYPE_MP4=true
      - INDEXER_INDEX_FILETYPE_MP3=true
      - INDEXER_VIEW_FILETYPE_MP3=true
      - INDEXER_INDEX_FILETYPE_JPG=true
      - INDEXER_VIEW_FILETYPE_JPG=true
    volumes:
      - ./config:/config
      - /mnt/media:/files:ro
```

### Development Environment
```yaml
services:
  5q12-indexer:
    image: 5q12/5q12-indexer:latest
    container_name: dev-files
    restart: unless-stopped
    ports:
      - "5012:5012"
    environment:
      - TZ=America/New_York
      - INDEXER_INDEX_HIDDEN=true
      - INDEXER_INDEX_ALL=false
      - INDEXER_ALLOW_LIST=src/*,docs/*,README.md
      - INDEXER_VIEW_FILETYPE_JS=true
      - INDEXER_VIEW_FILETYPE_TS=true
      - INDEXER_VIEW_FILETYPE_JSON=true
      - INDEXER_VIEW_FILETYPE_MD=true
      - INDEXER_INDEX_FILETYPE_PHP=true
      - INDEXER_VIEW_FILETYPE_PHP=false
    volumes:
      - ./config:/config
      - ./project:/files
```

### Production Setup
```yaml
services:
  5q12-indexer:
    image: 5q12/5q12-indexer:1.1.18  # Pin version
    container_name: 5q12-indexer-prod
    restart: unless-stopped
    ports:
      - "5012:5012"
    environment:
      - TZ=Etc/UTC
      - INDEXER_ACCESS_URL=https://files.example.com
      - INDEXER_CACHE_TYPE=sqlite
      - INDEXER_ICON_TYPE=default
      - INDEXER_INDEX_HIDDEN=false
      - INDEXER_DENY_LIST=.env,.git,admin,logs
    volumes:
      - indexer-config:/config
      - /srv/public:/files:ro
    deploy:
      resources:
        limits:
          memory: 512M
          cpus: '0.5'
    logging:
      driver: "json-file"
      options:
        max-size: "10m"
        max-file: "3"

volumes:
  indexer-config:
```

## Manual Configuration

While environment variables are recommended, you can still manually edit `config.json` in the `/config` volume:

```json
{
  "version": "1.1.18",
  "main": {
    "access_url": "",
    "cache_type": "sqlite",
    "icon_type": "default",
    "disable_file_downloads": false,
    "disable_folder_downloads": false,
    "index_hidden": false,
    "index_all": false,
    "deny_list": "",
    "allow_list": ""
  },
  "exclusions": {
    "index_folders": true,
    "index_non-descript-files": true,
    "index_php": false,
    "index_js": true
  },
  "viewable_files": {
    "view_non-descript-files": false,
    "view_php": false,
    "view_js": true
  }
}
```

Environment variables will override config.json settings on container startup.

## Features

- **File browsing** with sorting by name, size, date, type
- **Environment variable configuration** for Docker-native setup
- **Dynamic filetype support** for any file extension
- **Dot folder support** - Browse hidden directories and files
- **Download support** for individual files and folders (ZIP)
- **In-browser viewing** for text, images, videos, PDFs
- **Icon system** with 150+ file type icons
- **High-performance caching** using SQLite
- **Security controls** with configurable access rules
- **Mobile responsive** interface
- **No JavaScript** required
- **Smart configuration updates** preserve settings across versions

## Security

- Runs as non-root user (`www-data`, UID 82)
- Path traversal protection built-in
- Configurable file access controls via environment variables
- Selective security rules (blocks sensitive files, allows legitimate access)
- No privileged access required
- Read-only mounts supported

## Health Check

The container includes a built-in health check that verifies the web service is responding correctly.

## Updates

```bash
# Pull latest image
docker compose pull

# Recreate container (preserves config automatically)
docker compose up -d

# Clean up old images
docker image prune
```

Your configuration will be automatically updated to support new features while preserving all your custom settings. Environment variables take precedence and are applied on every startup.

## Migration from Earlier Versions

If upgrading from versions prior to 1.1.18:

1. Your existing configuration will be automatically preserved
2. New configuration fields will be added for enhanced functionality  
3. Consider migrating manual config.json edits to environment variables
4. The system will log what changes were made during the update
5. No manual intervention required

## Troubleshooting

### Permission Issues
```bash
# Fix config directory permissions
sudo chown -R 82:82 ./config

# Or use permissive permissions
chmod -R 755 ./config ./files
```

### Container Logs
```bash
# View logs (includes config update details)
docker compose logs -f 5q12-indexer

# Check environment variable processing
docker compose logs 5q12-indexer | grep -E "(environment|override)"
```

### Configuration Issues
```bash
# View startup logs to see config updates
docker compose logs 5q12-indexer | grep -E "(config|version|environment)"

# Check current config version
docker exec 5q12-indexer cat /config/config.json | grep version

# Verify environment variable processing
docker exec 5q12-indexer env | grep INDEXER_
```

### Access Issues
```bash
# Test connectivity
curl -I http://localhost:5012

# Check port availability
netstat -tlnp | grep :5012
```

## Links

- **Source Code:** https://ccls.icu/src/repositories/5q12-indexer/main/
- **Documentation:** https://ccls.icu/src/repositories/5q12-indexer/main/docs/
- **Releases:** https://ccls.icu/src/repositories/5q12-indexer/releases/

## Links (GitHub)

- **Source Code:** https://github.com/5q12-ccls/5q12-s-Indexer
- **Documentation:** https://github.com/5q12-ccls/5q12-s-Indexer/blob/main/docs/
- **Issues:** https://github.com/5q12-ccls/5q12-s-Indexer/issues