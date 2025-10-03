# 5q12 Indexer - Build System

This directory contains the Docker build system for the 5q12 Indexer application. The build system uses Alpine Linux with s6-overlay for process management.

## Directory Structure

```
.
├── build.sh                    # Main build script (includes all functionality)
├── Dockerfile                 # Multi-stage Docker build
├── docker-compose.yml         # Development compose file
├── README.md                  # This file
├── docker/                    # Docker configuration files
│   ├── nginx.conf            # Nginx main configuration
│   ├── 5q12-indexer.conf     # Nginx site configuration
│   ├── php-fpm.conf          # PHP-FPM configuration
│   ├── s6-services/          # S6-overlay service definitions
│   │   ├── nginx/            # Nginx service
│   │   │   ├── dependencies.d/
│   │   │   │   └── init-indexer
│   │   │   ├── run
│   │   │   └── type
│   │   ├── php-fpm/          # PHP-FPM service
│   │   │   ├── dependencies.d/
│   │   │   │   └── init-indexer
│   │   │   ├── run
│   │   │   └── type
│   │   ├── init-indexer/     # Initialization service
│   │   │   ├── run
│   │   │   ├── type
│   │   │   └── up
│   │   ├── user/             # User bundle (nginx + php-fpm)
│   │   │   ├── contents.d/
│   │   │   │   ├── nginx
│   │   │   │   └── php-fpm
│   │   │   └── type
│   │   └── user2/            # User2 bundle (contains user)
│   │       ├── contents.d/
│   │       │   └── user
│   │       └── type
│   └── scripts/              # Initialization scripts
│       └── init-indexer.sh   # Main initialization script
└── source/                    # Application source code
    ├── index.php             # Main application file
    └── config/               # Default configuration
```

## Architecture Overview

### Volume Mount Strategy

The indexer uses a **clean separation** architecture with individual symlinks:

**Mount Points:**
- `/config` - Configuration files only (config.json, config-reference.txt)
- `/app` - Application files only (icons, favicon, local_api, php, caches)
- `/files` - User files to be indexed (read-only)

**Symlink Structure:**
```
/www/indexer/.indexer_files/  (directory with individual symlinks)
├── config.json → /config/config.json
├── config-reference.txt → /config/config-reference.txt
├── icons → /app/icons
├── favicon → /app/favicon
├── local_api → /app/local_api
├── php → /app/php
├── zip_cache → /app/zip_cache
├── index_cache → /app/index_cache
└── (all other /app contents)
```

**Key Benefits:**
- **No file conflicts**: Config files only in `/config`, app files only in `/app`
- **Live config changes**: Edits to `/config/config.json` take effect immediately
- **Clean mounts**: Both `/config` and `/app` can be mounted externally without symlink issues
- **Always current reference**: `config-reference.txt` refreshed on every restart
- **Optimal persistence**: User changes in `/config`, cache in `/app`, source files in `/files`

### Configuration File Flow

1. **Startup Merge**: `/container-app/default-config/config.json` merged with `/config/config.json`
2. **Environment Override**: Environment variables applied to `/config/config.json`
3. **Live Access**: PHP app reads/writes `/www/indexer/.indexer_files/config.json` → `/config/config.json`
4. **Persistence**: Changes to `/config/config.json` survive container restarts
5. **Reference Update**: `config-reference.txt` copied fresh to `/config` on each restart

## Version System

The project uses a revision-based versioning system for security patches:

- **Base Version**: `X.Y.Z` (e.g., `1.1.19`)
- **Security Patches**: `X.Y.Z-rN` (e.g., `1.1.19-r1`, `1.1.19-r2`)

Where:
- `X.Y.Z` represents major.minor.patch versions for feature releases
- `rN` represents revision number for security patches and hotfixes

## Build Scripts

### build.sh

The main build script that handles all build system functionality including Docker image creation, s6-overlay setup, and deployment operations.

**Usage:**
```bash
./build.sh                    # Build with default version
./build.sh 1.1.19-r1          # Build with custom version
./build.sh --no-cache         # Build with no cache
./build.sh 1.1.19-r1 --no-cache # Build custom version with no cache
```

**Generated Tags:**
- `5q12/5q12-indexer:VERSION`
- `5q12/5q12-indexer:latest`
- `5q12/5q12-indexer:VERSION-s6`

**Prerequisites:**
- Docker installed and running
- All required files present (automatically checked)
- S6-overlay services configured (automatically set up if needed)

## Docker Configuration

### Process Management

The build system uses s6-overlay v3 instead of supervisor for better security and container-native process management.

**Services:**
- `init-indexer`: Initialization and configuration setup
- `nginx`: Web server (depends on init-indexer)
- `php-fpm`: PHP FastCGI Process Manager (depends on init-indexer)
- `user`: Service bundle containing nginx and php-fpm
- `user2`: Service bundle containing user

### Environment Variables

The build system supports comprehensive environment variable configuration:

**Main Configuration:**
- `INDEXER_ACCESS_URL`: Base URL for the application
- `INDEXER_CACHE_TYPE`: Cache type (sqlite/json)
- `INDEXER_ICON_TYPE`: Icon display type (default/disabled)
- `INDEXER_INDEX_ALL`: Index all files (true/false)
- `INDEXER_INDEX_HIDDEN`: Include hidden files (true/false)
- `INDEXER_DISABLE_FILE_DOWNLOADS`: Disable file downloads (true/false)
- `INDEXER_DISABLE_FOLDER_DOWNLOADS`: Disable folder downloads (true/false)
- `INDEXER_MAX_DOWNLOAD_SIZE_FILE`: Maximum file download size
- `INDEXER_MAX_DOWNLOAD_SIZE_FOLDER`: Maximum folder download size
- `INDEXER_DENY_LIST`: Comma-separated list of denied patterns
- `INDEXER_ALLOW_LIST`: Comma-separated list of allowed patterns

**Filetype Controls:**
- `INDEXER_VIEW_FILETYPE_*`: Control file viewing (use underscores or hyphens)
  - Example: `INDEXER_VIEW_FILETYPE_PHP=true`
  - Example: `INDEXER_VIEW_FILETYPE_NON-DESCRIPT-FILES=true`
- `INDEXER_INDEX_FILETYPE_*`: Control file indexing
  - Example: `INDEXER_INDEX_FILETYPE_LOG=false`

**System Settings:**
- `TZ`: Timezone setting (e.g., `America/New_York`)
- `S6_VERBOSITY`: S6-overlay logging level (0-2, default 1)

## Building Images

### Prerequisites Check

The build script automatically verifies:
- Required files exist
- Directory structure is correct
- S6 services are configured
- Script permissions are set
- Environment variable processing is present

### Build Process

1. **Validation**: Checks all required files and directories
2. **Permission Setup**: Makes scripts executable
3. **Docker Build**: Creates multi-tagged images
4. **Verification**: Shows build results and usage examples

### Development Workflow

```bash
# Clean build environment
docker system prune -a --volumes --force

# Build development image
./build.sh devtest-r1 --no-cache

# Test locally with individual mounts
mkdir -p test-indexer/{config,app,files}
docker run -d --name test-indexer-s6 -p 5012:5012 \
  -v $(pwd)/test-indexer/config:/config \
  -v $(pwd)/test-indexer/app:/app \
  -v $(pwd)/test-indexer/files:/files \
  -e INDEXER_CACHE_TYPE=json \
  -e INDEXER_ICON_TYPE=disabled \
  5q12/5q12-indexer:devtest-r1

# Verify symlink structure
docker exec test-indexer-s6 ls -la /www/indexer/.indexer_files/

# Test live config changes
docker exec test-indexer-s6 cat /config/config.json
# Edit config.json in test-indexer/config/ from host
# Changes take effect immediately without restart

# Test environment variables
docker exec test-indexer-s6 cat /config/config.json | grep cache_type

# Check logs
docker logs test-indexer-s6

# Clean up
docker rm -f test-indexer-s6
rm -rf test-indexer/
```

## Volume Mount Examples

### Minimal Setup (Config Only)
```yaml
version: '3.8'
services:
  indexer:
    image: 5q12/5q12-indexer:1.1.19
    ports:
      - "5012:5012"
    volumes:
      - ./config:/config
      - ./files:/files
    environment:
      - TZ=UTC
      - INDEXER_CACHE_TYPE=json
```

### Full Setup (Config + App + Files)
```yaml
version: '3.8'
services:
  indexer:
    image: 5q12/5q12-indexer:1.1.19
    ports:
      - "5012:5012"
    volumes:
      - ./config:/config      # Config files (editable, persisted)
      - ./app:/app            # App files (icons, caches, persisted)
      - ./files:/files:ro     # Source files (read-only)
    environment:
      - TZ=America/New_York
      - INDEXER_CACHE_TYPE=sqlite
      - INDEXER_ICON_TYPE=default
      - INDEXER_INDEX_ALL=true
      - INDEXER_VIEW_FILETYPE_PHP=true
```

### Production Setup with Environment Variables
```yaml
version: '3.8'
services:
  indexer:
    image: 5q12/5q12-indexer:1.1.19
    container_name: indexer-prod
    ports:
      - "5012:5012"
    volumes:
      - /mnt/configs/indexer:/config
      - /mnt/cache/indexer:/app
      - /mnt/data:/files:ro
    environment:
      - TZ=UTC
      - INDEXER_ACCESS_URL=https://files.example.com
      - INDEXER_CACHE_TYPE=sqlite
      - INDEXER_ICON_TYPE=default
      - INDEXER_INDEX_ALL=false
      - INDEXER_INDEX_HIDDEN=false
      - INDEXER_DISABLE_FOLDER_DOWNLOADS=false
      - INDEXER_MAX_DOWNLOAD_SIZE_FILE=2048 MB
      - INDEXER_MAX_DOWNLOAD_SIZE_FOLDER=2048 MB
      - INDEXER_VIEW_FILETYPE_PHP=false
      - INDEXER_VIEW_FILETYPE_JS=false
    restart: unless-stopped
    security_opt:
      - no-new-privileges:true
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:5012/"]
      interval: 30s
      timeout: 10s
      retries: 3
```

## Configuration Management

### Accessing Config Files

**From Host (when mounted):**
```bash
# Edit config
vim ./config/config.json

# View reference
cat ./config/config-reference.txt

# Changes to config.json take effect immediately
```

**From Container:**
```bash
# View current config
docker exec CONTAINER_NAME cat /config/config.json

# View through app symlink
docker exec CONTAINER_NAME cat /www/indexer/.indexer_files/config.json

# Both paths point to the same file
```

### Config File Locations

| Path | Type | Purpose | Editable | Persists |
|------|------|---------|----------|----------|
| `/config/config.json` | Real file | Source of truth | Yes | Yes (if mounted) |
| `/config/config-reference.txt` | Real file | Documentation | No (refreshed) | Yes (if mounted) |
| `/www/indexer/.indexer_files/config.json` | Symlink | App access point | Yes | Via `/config` |
| `/www/indexer/.indexer_files/config-reference.txt` | Symlink | App access point | No | Via `/config` |

### Config Merge Behavior

On container startup:
1. Default config from `/container-app/default-config/config.json` is loaded
2. Existing `/config/config.json` is read (if present)
3. Missing fields are added from default
4. Version is updated to match container version
5. Existing user values are preserved
6. Environment variables override config values
7. Result is written back to `/config/config.json`

## Security Features

### Process Management
- No supervisor vulnerabilities
- Reduced Python attack surface
- Container-native s6-overlay
- Proper signal handling

### File System Isolation
- Config files isolated in `/config`
- App files isolated in `/app`
- Source files read-only in `/files`
- No file conflicts between mounts
- Clean separation of concerns

### Environment Variable Processing
- Multiple fallback methods for reading variables
- Support for s6-overlay environment files
- Enhanced debugging and error reporting
- Secure configuration merging
- Type-safe boolean conversions

### Container Hardening
- Non-root user execution (www-data)
- Minimal Alpine Linux base
- Position-independent executables
- Stack-smashing protection
- Read-only source file mount

## Troubleshooting

### Common Build Issues

**Missing s6-services directory:**
The build script will check for this automatically and warn you if the s6-overlay structure is missing.

**Permission errors:**
```bash
chmod +x build.sh
chmod +x docker/scripts/*.sh
```

**Missing files:**
- Check that all files in the build script's validation exist
- Verify source directory structure
- Ensure Docker configuration files are present

**Environment variables not working:**
- Verify `init-indexer.sh` contains enhanced processing
- Check s6-overlay environment files in `/var/run/s6/container_environment/`
- Use `S6_VERBOSITY=2` for debugging

### Common Runtime Issues

**Config changes not taking effect:**
- Config changes take effect immediately without restart
- Verify you're editing `/config/config.json` (source of truth)
- Check file permissions (should be owned by www-data)

**Symlink issues:**
```bash
# Verify symlink structure
docker exec CONTAINER_NAME ls -la /www/indexer/.indexer_files/

# Should show individual symlinks to /config and /app
```

**Cache issues:**
```bash
# Clear caches (if /app is mounted)
rm -rf ./app/zip_cache/*
rm -rf ./app/index_cache/*

# Or from within container
docker exec CONTAINER_NAME rm -rf /app/zip_cache/*
docker exec CONTAINER_NAME rm -rf /app/index_cache/*
```

### Build Script Validation

The build script performs comprehensive validation:
- File and directory existence
- Script permissions
- S6 service structure
- Environment variable processing presence

### Testing Environment Variables

```bash
# Test specific variable
docker run --rm -e INDEXER_CACHE_TYPE=json IMAGE_NAME \
  sh -c 'cat /config/config.json | grep cache_type'

# Test multiple variables
docker run --rm \
  -e INDEXER_CACHE_TYPE=json \
  -e INDEXER_INDEX_ALL=true \
  IMAGE_NAME sh -c 'cat /config/config.json | grep -E "(cache_type|index_all)"'

# Test filetype variable with special characters
docker run --rm \
  -e INDEXER_VIEW_FILETYPE_NON-DESCRIPT-FILES=true \
  IMAGE_NAME sh -c 'cat /config/config.json | grep "view_non-descript-files"'
```

## Migration Notes

### From Supervisor to S6-Overlay

If migrating from supervisor-based builds:
1. Run `./s6.sh` to set up s6 services
2. Remove any supervisor configuration references
3. Update any custom scripts to use s6-overlay patterns
4. Test thoroughly with environment variables

### From Single Mount to Split Mounts

If migrating from a single volume mount:
1. Create separate mount points: `mkdir -p config app files`
2. Move config files to `config/` directory
3. Move cache/icon files to `app/` directory
4. Update docker-compose.yml with new mount structure
5. Test with new volumes before removing old setup

### Legacy Compatibility

The build system maintains backward compatibility for:
- Volume mount points (`/config`, `/app`, `/files`)
- Port configuration (5012)
- Environment variable names
- Health check endpoints
- API endpoints and behavior

## Development Guidelines

### Adding New Environment Variables

1. Update `init-indexer.sh` environment mapping in `$envMappings` array
2. Add to build script documentation and README
3. Test with fallback methods (getenv, $_ENV, s6 files)
4. Update docker-compose examples
5. Document in config-reference.txt

### Modifying S6 Services

1. Edit service definitions in `docker/s6-services/`
2. Ensure proper dependencies are set
3. Test service startup order
4. Verify logging and error handling
5. Test service restart behavior

### Modifying Symlink Structure

1. Edit `init-indexer.sh` symlink creation section
2. Ensure no file conflicts between `/config` and `/app`
3. Test with both mounted and unmounted volumes
4. Verify PHP app can access all required files
5. Test config change propagation

### Security Considerations

- Always validate input from environment variables
- Use proper file permissions (www-data ownership)
- Test with minimal privileges
- Regular security updates for base image
- Audit symlink targets for security
- Never symlink outside container filesystem

## Performance Considerations

### Cache Management

The `/app` directory contains performance-critical caches:
- `zip_cache/` - Cached ZIP file listings
- `index_cache/` - Cached directory indexes

**Recommendations:**
- Mount `/app` to fast storage (SSD)
- Regular cache cleanup for large deployments
- Monitor cache directory sizes
- Use `INDEXER_CACHE_TYPE=sqlite` for better performance

### Icon Storage

Icons are stored in `/app/icons/`:
- Set `INDEXER_ICON_TYPE=disabled` to reduce storage and bandwidth
- Icons are regenerated if missing (one-time performance hit)
- Consider pre-populating icons for faster startup

## Support and Documentation

- **GitHub Issues**: Report bugs and request features
- **Docker Hub**: `5q12/5q12-indexer`
- **Documentation**: See `config-reference.txt` for all config options
- **Logs**: `docker logs CONTAINER_NAME` for troubleshooting