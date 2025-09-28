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
- `INDEXER_ICON_TYPE`: Icon display type
- `INDEXER_INDEX_ALL`: Index all files
- `INDEXER_INDEX_HIDDEN`: Include hidden files
- `INDEXER_DISABLE_FILE_DOWNLOADS`: Disable file downloads
- `INDEXER_DISABLE_FOLDER_DOWNLOADS`: Disable folder downloads

**Filetype Controls:**
- `INDEXER_VIEW_FILETYPE_*`: Control file viewing (use underscores for hyphens)
- `INDEXER_INDEX_FILETYPE_*`: Control file indexing

**System Settings:**
- `TZ`: Timezone setting
- `S6_VERBOSITY`: S6-overlay logging level

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

# Test locally
mkdir -p test-indexer/{config,files}
docker run -d --name test-indexer-s6 -p 5012:5012 \
  -v $(pwd)/test-indexer/config:/config \
  -v $(pwd)/test-indexer/files:/files \
  -e INDEXER_CACHE_TYPE=json \
  5q12/5q12-indexer:devtest-r1

# Test environment variables
docker exec test-indexer-s6 cat /config/config.json | grep cache_type

# Check logs
docker logs test-indexer-s6

# Clean up
docker rm -f test-indexer-s6
rm -rf test-indexer/
```

## Security Features

### Process Management
- No supervisor vulnerabilities
- Reduced Python attack surface
- Container-native s6-overlay
- Proper signal handling

### Environment Variable Processing
- Multiple fallback methods for reading variables
- Support for s6-overlay environment files
- Enhanced debugging and error reporting
- Secure configuration merging

### Container Hardening
- Non-root user execution
- Minimal Alpine Linux base
- Position-independent executables
- Stack-smashing protection

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
```

## Migration Notes

### From Supervisor to S6-Overlay

If migrating from supervisor-based builds:
1. Run `./s6.sh` to set up s6 services
2. Remove any supervisor configuration references
3. Update any custom scripts to use s6-overlay patterns
4. Test thoroughly with environment variables

### Legacy Compatibility

The build system maintains backward compatibility for:
- Volume mount points (`/config`, `/files`)
- Port configuration (5012)
- Environment variable names
- Health check endpoints

## Development Guidelines

### Adding New Environment Variables

1. Update `init-indexer.sh` environment mapping
2. Add to build script documentation
3. Test with fallback methods
4. Update docker-compose examples

### Modifying S6 Services

1. Edit service definitions in `docker/s6-services/`
2. Ensure proper dependencies are set
3. Test service startup order
4. Verify logging and error handling

### Security Considerations

- Always validate input from environment variables
- Use proper file permissions
- Test with minimal privileges
- Regular security updates for base image