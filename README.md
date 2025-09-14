# 5q12's Indexer

PHP file browser with sorting, filtering, download, icons, caching, and API integration. Configurable indexing.

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
      # Configuration directory - stores settings and cache
      - /example_host_path/config:/config
      
      # Files directory - mount your content here to index
      - /example_host_path/files:/files
```

```bash
# Create the compose file above, then start
docker-compose up -d
```

Access at: `http://localhost:5012`

**Important:** Replace `/example_host_path/` with your actual host directories. The `/config` volume persists settings and cache, while `/files` contains the content you want to browse.

### 2. Automated Script Installation

**For Debian/Ubuntu systems with automatic dependency management:**

```bash
# Download installer
wget https://github.com/5q12-ccls/5q12-s-Indexer/raw/main/install.sh
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
# Download indexer
wget https://github.com/5q12-ccls/5q12-s-Indexer/raw/main/index.php

# Place in web directory
cp index.php /var/www/html/files/

# Configure web server (Nginx/Apache)
# Access via browser to auto-initialize
```

**Requirements:**
- PHP 8.3+
- Web server (Nginx/Apache)
- SQLite3 extension (recommended)
- ZipArchive extension (for downloads)

## Features

- **File browsing** with sorting by name, size, date, type
- **Download support** for files and folders (ZIP)
- **File viewing** in browser for supported types
- **Icon system** with file type recognition
- **Caching** (SQLite/JSON) for performance
- **Security controls** with path filtering
- **Responsive design** for mobile devices
- **No JavaScript** required

## Configuration

The indexer creates `.indexer_files/config.json` automatically. Key settings:

```json
{
  "main": {
    "cache_type": "sqlite",           // "sqlite" or "json"
    "disable_file_downloads": false,  // Enable/disable downloads
    "disable_folder_downloads": false,
    "index_hidden": false,            // Show hidden files
    "deny_list": "admin, logs, .git", // Exclude paths
    "local_icons": true               // Use local icons
  }
}
```

## File Structure

```
installation-directory/
├── index.php                 # Main indexer file
├── .indexer_files/           # Auto-created configuration
│   ├── config.json          # Settings file
│   ├── index_cache/         # Performance cache
│   ├── zip_cache/           # Temporary downloads
│   └── icons/               # Local icon files
└── files/                   # Your content directory
```

## Documentation

- **[Installation Guide](docs/installation.md)** - Detailed setup procedures
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

## Browser Support

Works with all modern browsers including mobile devices. No JavaScript required.