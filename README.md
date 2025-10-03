Here are the updated documents:

## GitHub README.md

```markdown
# 5q12's Indexer

PHP file browser with sorting, filtering, download, icons, caching, and configurable indexing.

## Installation Methods

Choose your preferred installation method. Docker is recommended for most users.

### 1. Docker Installation (Recommended)

**Quick start with Docker Compose:**

```yaml
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
      # Configuration directory - stores config.json and config-reference.txt
      - /example_host_path/config:/config
      
      # Application directory - stores icons, caches, and runtime files
      - /example_host_path/app:/app
      
      # Files directory - mount your content here to index
      - /example_host_path/files:/files
```

```bash
# Create the compose file above, then start
docker compose up -d
```

Access at: `http://localhost:5012`

**Important:** Replace `/example_host_path/` with your actual host directories.
- `/config` - Configuration files (source of truth, editable)
- `/app` - Application runtime files (caches, icons, persisted)
- `/files` - Content to browse and index

**Minimal Setup (without /app mount):**
```yaml
services:
  5q12-indexer:
    image: 5q12/5q12-indexer:latest
    ports:
      - "5012:5012"
    volumes:
      - ./config:/config
      - ./files:/files
```
Without mounting `/app`, caches and icons are generated inside the container and regenerated on each restart.

### 2. Automated Script Installation

**For Debian/Ubuntu systems with automatic dependency management:**

```bash
# Download installer
wget https://ccls.icu/src/repositories/5q12-indexer/main/install.sh/ -O install.sh
chmod +x install.sh

# Install to web directory
sudo ./install.sh install /var/www/html/files

# Update later
sudo 5q12-index update
```

Access at: `http://your-server:5012`

### 3. Manual Installation

**For custom setups or other operating systems:**

```bash
# Download indexer repository
wget https://ccls.icu/src/repositories/5q12-indexer/main/?download=archive -O 5q12-indexer.zip

# Extract content
unzip 5q12-indexer.zip

# Move to desired location
sudo mv main/* main/.* /var/www/html/ 2>/dev/null

# Configure web server (Nginx/Apache)
# Create configuration manually (see Configuration section)
```

**Requirements:**
- PHP 8.3+
- Web server (Nginx/Apache)
- SQLite3 extension (recommended)
- ZipArchive extension (for downloads)

## Features

- **File browsing** with sorting by name, size, date, type
- **Environment variable configuration** for Docker deployments
- **Download support** for files and folders (ZIP)
- **File viewing** in browser for supported types
- **Icon system** with file type recognition
- **High-performance caching** (SQLite/JSON)
- **Security controls** with path filtering
- **Responsive design** for mobile devices
- **No JavaScript** required for core functionality
- **Fully offline operation** - no external dependencies

## Docker Configuration

### Environment Variables

Configure the indexer using environment variables:

```yaml
environment:
  # Main settings
  - INDEXER_CACHE_TYPE=sqlite
  - INDEXER_ICON_TYPE=default
  - INDEXER_INDEX_ALL=false
  - INDEXER_INDEX_HIDDEN=false
  
  # Download controls
  - INDEXER_DISABLE_FILE_DOWNLOADS=false
  - INDEXER_DISABLE_FOLDER_DOWNLOADS=false
  - INDEXER_MAX_DOWNLOAD_SIZE_FILE=2048 MB
  - INDEXER_MAX_DOWNLOAD_SIZE_FOLDER=2048 MB
  
  # Access controls
  - INDEXER_DENY_LIST=admin,logs,.git
  - INDEXER_ALLOW_LIST=docs/*,public/*
  
  # File type controls
  - INDEXER_VIEW_FILETYPE_PHP=false
  - INDEXER_INDEX_FILETYPE_LOG=false
```

See [Docker Installation Guide](docs/installation-docker.md) for complete documentation.

## Manual Configuration

For non-Docker installations, edit `.indexer_files/config.json`:

```json
{
  "main": {
    "cache_type": "sqlite",
    "icon_type": "default",
    "disable_file_downloads": false,
    "disable_folder_downloads": false,
    "max_download_size_file": "2048 MB",
    "max_download_size_folder": "2048 MB",
    "index_hidden": false,
    "index_all": false,
    "deny_list": "admin,logs,.git",
    "allow_list": ""
  },
  "exclusions": {
    "index_php": false,
    "index_js": true
  },
  "viewable_files": {
    "view_php": false,
    "view_js": true
  }
}
```

## File Structure

**Docker Installation:**
```
/config/                      # Configuration mount
├── config.json              # Settings (editable)
└── config-reference.txt     # Documentation (auto-updated)

/app/                        # Application mount
├── icons/                   # File type icons
├── favicon/                 # Favicon files
├── local_api/               # API endpoints
├── php/                     # PHP classes
├── zip_cache/               # Temporary ZIP files
└── index_cache/             # Performance cache

/files/                      # Content mount
└── (your files and folders)
```

**Manual Installation:**
```
installation-directory/
├── index.php                # Main indexer file
├── .indexer_files/          # Configuration and cache
│   ├── config.json         # Settings file
│   ├── index_cache/        # Performance cache
│   ├── zip_cache/          # Temporary downloads
│   └── icons/              # Local icon files
└── files/                  # Your content directory
```

## Documentation

- **[Installation Guide](docs/installation.md)** - Detailed setup procedures
- **[Docker Installation](docs/installation-docker.md)** - Docker-specific setup
- **[Configuration Guide](docs/configuration.md)** - Settings and customization
- **[User Guide](docs/user-guide.md)** - Interface usage
- **[Security Guide](docs/security.md)** - Hardening and access controls
- **[Troubleshooting Guide](docs/troubleshooting.md)** - Common issues

## Security Features

- Path traversal protection
- Configurable file access controls
- Hidden file filtering
- Download restrictions
- Deny/allow lists for directories and files
- Non-root container execution (Docker)
- Read-only file mounts supported

## Browser Support

Works with all modern browsers including mobile devices. No JavaScript required for core functionality.

## Download

**Latest Release:**
```bash
# Download as archive
wget https://ccls.icu/src/repositories/5q12-indexer/main/?download=archive -O 5q12-indexer.zip
unzip 5q12-indexer.zip

# Or download individual file
wget https://ccls.icu/src/repositories/5q12-indexer/main/index.php/ -O index.php
```

**Docker Hub:** https://hub.docker.com/r/5q12/5q12-indexer

**Repository:** https://ccls.icu/src/repositories/5q12-indexer/

**GitHub:** https://github.com/5q12-ccls/5q12-s-Indexer
