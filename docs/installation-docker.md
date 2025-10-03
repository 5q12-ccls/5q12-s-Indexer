# Docker Installation Guide

## Overview

Docker installation is the recommended method for running 5q12's Indexer. It provides complete environment isolation, automatic dependency management, and works consistently across different operating systems.

### Why Docker?
- **Zero host dependencies** - No need to install PHP, web servers, or configure anything
- **Consistent environment** - Works the same on Windows, macOS, and Linux
- **Easy updates** - Pull new images and restart containers
- **Isolation** - Doesn't interfere with existing web servers or PHP installations
- **Production ready** - Built-in process management and health checks

## Prerequisites

- **Docker** (version 20.0+)
- **Docker Compose** (version 2.0+)
- **512MB RAM** minimum (1GB+ recommended)
- **100MB disk space** for image and data

### Install Docker

**Ubuntu/Debian:**
```bash
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh
sudo usermod -aG docker $USER
```

**Windows/macOS:**
Download Docker Desktop from [docker.com](https://www.docker.com/products/docker-desktop/)

## Quick Start

### Step 1: Create Docker Compose Configuration

Create a `docker-compose.yml` file:

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
      - /your/host/config:/config
      
      # Application directory - stores icons, caches, and runtime files
      - /your/host/app:/app
      
      # Files directory - mount your content here to index
      - /your/host/files:/files
```

### Step 2: Create Host Directories

```bash
# Create directories for persistent data
mkdir -p /your/host/{config,app,files}

# Set appropriate permissions
sudo chown -R 82:82 /your/host/config /your/host/app
chmod -R 755 /your/host/files
```

### Step 3: Start the Container

```bash
# Start the indexer
docker compose up -d

# Check status
docker compose logs -f 5q12-indexer
```

### Step 4: Access the Indexer

Open your browser to: `http://localhost:5012`

## Volume Architecture

The indexer uses a **clean separation architecture** with three distinct mount points:

```
/config/                      # Configuration files only
├── config.json              # Settings (source of truth, editable)
└── config-reference.txt     # Documentation (refreshed on restart)

/app/                        # Application runtime files only
├── icons/                   # File type icons (persisted)
├── favicon/                 # Favicon files
├── local_api/               # API endpoints
├── php/                     # PHP class files
├── zip_cache/               # Temporary ZIP downloads
└── index_cache/             # Performance cache

/files/                      # Your content to browse
└── (your files and folders)
```

### Internal Symlink Structure

The application directory (`/www/indexer/.indexer_files/`) contains individual symlinks to both `/config` and `/app`:

```
/www/indexer/.indexer_files/ (directory with individual symlinks)
├── config.json → /config/config.json
├── config-reference.txt → /config/config-reference.txt
├── icons → /app/icons
├── favicon → /app/favicon
├── local_api → /app/local_api
├── php → /app/php
├── zip_cache → /app/zip_cache
└── index_cache → /app/index_cache
```

**Benefits:**
- Config files only in `/config` (no duplication)
- App files only in `/app` (no config mixing)
- Both can be mounted externally without conflicts
- Config changes don't require container restart
- Reference documentation always current

## Configuration Examples

### Minimal Setup (Config + Files Only)

```yaml
services:
  5q12-indexer:
    image: 5q12/5q12-indexer:latest
    container_name: file-server
    restart: unless-stopped
    ports:
      - "8080:5012"
    environment:
      - TZ=America/New_York
    volumes:
      - ./config:/config
      - ./public-files:/files
```

Without mounting `/app`, icons and caches are generated inside the container and lost on restart (but automatically regenerated).

### Recommended Setup (All Volumes)

```yaml
services:
  5q12-indexer:
    image: 5q12/5q12-indexer:latest
    container_name: file-server
    restart: unless-stopped
    ports:
      - "8080:5012"
    environment:
      - TZ=America/New_York
    volumes:
      - ./config:/config
      - ./app:/app
      - ./public-files:/files
```

With `/app` mounted, icons and caches persist across restarts for better performance.

### Media Server with Multiple Mounts
```yaml
services:
  5q12-indexer:
    image: 5q12/5q12-indexer:latest
    container_name: media-indexer
    restart: unless-stopped
    ports:
      - "5012:5012"
    environment:
      - TZ=Europe/London
      - INDEXER_CACHE_TYPE=sqlite
      - INDEXER_ICON_TYPE=default
      - INDEXER_INDEX_FILETYPE_MP4=true
      - INDEXER_VIEW_FILETYPE_MP4=true
    volumes:
      - ./config:/config
      - ./app:/app
      - ./files:/files
      # Mount different content types
      - /mnt/movies:/files/movies:ro
      - /mnt/music:/files/music:ro
      - /mnt/documents:/files/documents:ro
      - /home/user/downloads:/files/downloads
```

### Production Setup with Resource Limits
```yaml
services:
  5q12-indexer:
    image: 5q12/5q12-indexer:latest
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
      - /opt/indexer/config:/config
      - /opt/indexer/app:/app
      - /srv/public:/files:ro
    deploy:
      resources:
        limits:
          memory: 512M
          cpus: '0.5'
    security_opt:
      - no-new-privileges:true
    logging:
      driver: "json-file"
      options:
        max-size: "10m"
        max-file: "3"
```

## Volume Configuration

### Required Volumes

| Host Path | Container Path | Purpose | Required | Contents |
|-----------|----------------|---------|----------|----------|
| `./config` | `/config` | Configuration files | Yes | config.json, config-reference.txt |
| `./app` | `/app` | Application runtime | Recommended | Icons, caches, runtime files |
| `./files` | `/files` | Content to browse | Yes | Your files and folders |

### Volume Details

**Configuration Volume (`/config`):**
- `config.json` - Settings file (editable, source of truth)
- `config-reference.txt` - Documentation (refreshed on each restart)
- Changes to config.json apply immediately without restart

**Application Volume (`/app`):**
- `icons/` - File type icons (persisted across restarts)
- `favicon/` - Favicon files
- `local_api/` - API endpoint files
- `php/` - PHP class files
- `zip_cache/` - Temporary ZIP files for folder downloads
- `index_cache/` - Directory listing cache for performance

**Files Volume (`/files`):**
- Root directory for browsing
- Mount your content here
- Can include subdirectories
- Supports read-only mounts (`:ro`)

### Permission Requirements

The container runs as `www-data` (UID 82, GID 82). Ensure host directories are accessible:

```bash
# Option 1: Match container user
sudo chown -R 82:82 /path/to/config /path/to/app

# Option 2: Use permissive permissions
chmod -R 755 /path/to/config /path/to/app /path/to/files
```

## Environment Variables

### Main Configuration

| Variable | Default | Description |
|----------|---------|-------------|
| `TZ` | `Etc/UTC` | Container timezone |
| `INDEXER_ACCESS_URL` | `""` | Base URL for absolute links |
| `INDEXER_CACHE_TYPE` | `sqlite` | Cache type (`sqlite` or `json`) |
| `INDEXER_ICON_TYPE` | `default` | Icon display type |
| `INDEXER_INDEX_ALL` | `false` | Index all files |
| `INDEXER_INDEX_HIDDEN` | `false` | Index hidden files/folders |

### Download Controls

| Variable | Default | Description |
|----------|---------|-------------|
| `INDEXER_DISABLE_FILE_DOWNLOADS` | `false` | Disable file downloads |
| `INDEXER_DISABLE_FOLDER_DOWNLOADS` | `false` | Disable folder downloads |
| `INDEXER_MAX_DOWNLOAD_SIZE_FILE` | `2048 MB` | Max file download size |
| `INDEXER_MAX_DOWNLOAD_SIZE_FOLDER` | `2048 MB` | Max folder download size |

### Access Controls

| Variable | Default | Description |
|----------|---------|-------------|
| `INDEXER_DENY_LIST` | `""` | Comma-separated deny patterns |
| `INDEXER_ALLOW_LIST` | `""` | Comma-separated allow patterns |

### File Type Configuration

Configure any file type using these patterns:
- `INDEXER_INDEX_FILETYPE_{TYPE}=true/false` - Show in listings
- `INDEXER_VIEW_FILETYPE_{TYPE}=true/false` - Allow viewing

```yaml
environment:
  - INDEXER_VIEW_FILETYPE_PHP=false
  - INDEXER_INDEX_FILETYPE_LOG=false
  - INDEXER_VIEW_FILETYPE_MD=true
```

**Timezone Examples:**
- `America/New_York`
- `Europe/London`
- `Asia/Tokyo`
- `Australia/Sydney`

## Port Configuration

**Default Port:** Container exposes port 5012

**Custom Port Examples:**
```yaml
ports:
  - "8080:5012"  # Access via localhost:8080
  - "80:5012"    # Access via localhost (requires sudo/root)
  - "443:5012"   # HTTPS setup (requires reverse proxy)
```

## Management Commands

### Container Management
```bash
# Start containers
docker compose up -d

# Stop containers
docker compose down

# Restart containers
docker compose restart

# View logs
docker compose logs -f

# Update to latest image
docker compose pull
docker compose up -d
```

### Maintenance
```bash
# Access container shell
docker exec -it 5q12-indexer sh

# Check container status
docker ps

# View resource usage
docker stats 5q12-indexer

# Clean up old images
docker image prune

# Verify symlink structure
docker exec 5q12-indexer ls -la /www/indexer/.indexer_files/
```

### Configuration Management
```bash
# View current config
docker exec 5q12-indexer cat /config/config.json

# View reference documentation
docker exec 5q12-indexer cat /config/config-reference.txt

# Edit config from host (if mounted)
vim ./config/config.json
# Changes apply immediately without restart
```

## Updates and Backups

### Updating
```bash
# Pull latest image
docker compose pull 5q12/5q12-indexer:latest

# Recreate container with new image
docker compose up -d

# Clean up old images
docker image prune
```

### Backup Configuration
```bash
# Backup config directory
tar -czf indexer-config-$(date +%Y%m%d).tar.gz /path/to/config

# Backup app directory (includes caches and icons)
tar -czf indexer-app-$(date +%Y%m%d).tar.gz /path/to/app

# Backup docker compose configuration
cp docker-compose.yml docker-compose.yml.backup
```

### Version Pinning
Use specific versions for production stability:
```yaml
services:
  5q12-indexer:
    image: 5q12/5q12-indexer:1.1.19  # Pin to specific version
```

## SSL/HTTPS Setup

### Using Reverse Proxy (Recommended)

**Nginx Proxy:**
```nginx
server {
    listen 443 ssl;
    server_name files.yourdomain.com;
    
    ssl_certificate /path/to/cert.pem;
    ssl_certificate_key /path/to/key.pem;
    
    location / {
        proxy_pass http://localhost:5012;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }
}
```

**Traefik Example:**
```yaml
services:
  5q12-indexer:
    image: 5q12/5q12-indexer:latest
    labels:
      - "traefik.enable=true"
      - "traefik.http.routers.indexer.rule=Host(`files.yourdomain.com`)"
      - "traefik.http.routers.indexer.tls.certresolver=letsencrypt"
```

## Troubleshooting

### Container Won't Start
```bash
# Check logs for errors
docker compose logs 5q12-indexer

# Check if ports are available
netstat -tlnp | grep :5012

# Verify volume permissions
ls -la /path/to/config /path/to/app /path/to/files
```

### Permission Issues
```bash
# Fix ownership
sudo chown -R 82:82 /path/to/config /path/to/app

# Or use permissive mode
chmod -R 755 /path/to/config /path/to/app /path/to/files
```

### Configuration Issues
```bash
# Check if config exists
docker exec 5q12-indexer cat /config/config.json

# Verify symlinks are correct
docker exec 5q12-indexer ls -la /www/indexer/.indexer_files/

# Test config edit (should work without restart)
echo '{"main":{"cache_type":"json"}}' > ./config/config.json
# Refresh browser - changes should apply immediately
```

### Performance Issues
```bash
# Check resource usage
docker stats 5q12-indexer

# Increase memory limits in compose file
deploy:
  resources:
    limits:
      memory: 1G

# Clear caches (if /app is mounted)
rm -rf ./app/zip_cache/* ./app/index_cache/*
```

### Network Issues
```bash
# Test internal connectivity
docker exec 5q12-indexer curl -I http://localhost:5012

# Check host networking
curl -I http://localhost:5012
```

## Security Considerations

### Container Security
- Container runs as non-root user (www-data)
- No privileged access required
- Clean file separation prevents config conflicts
- Resource limits prevent abuse
- Read-only filesystem where possible

### Network Security
- Only expose necessary ports
- Use reverse proxy for SSL termination
- Consider firewall rules for production

### File Access Security
- Use read-only mounts (`:ro`) for sensitive content
- Limit container access to specific directories
- Regular security updates via image updates
- Config files isolated in dedicated volume

## Production Deployment

### Multi-Container Setup
```yaml
services:
  indexer:
    image: 5q12/5q12-indexer:latest
    restart: unless-stopped
    volumes:
      - indexer-config:/config
      - indexer-app:/app
      - /srv/public:/files:ro
    networks:
      - web

  nginx:
    image: nginx:alpine
    restart: unless-stopped
    ports:
      - "443:443"
    volumes:
      - ./nginx.conf:/etc/nginx/nginx.conf:ro
    networks:
      - web
    depends_on:
      - indexer

volumes:
  indexer-config:
  indexer-app:

networks:
  web:
```

### Health Checks
```yaml
services:
  5q12-indexer:
    image: 5q12/5q12-indexer:latest
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:5012"]
      interval: 30s
      timeout: 10s
      retries: 3
      start_period: 40s
```

## Performance Tips

- **Mount `/app`** to persist caches and icons across restarts
- **Use SQLite cache** (`INDEXER_CACHE_TYPE=sqlite`) for better performance
- **Disable icons** (`INDEXER_ICON_TYPE=disabled`) if not needed
- **Mount `/files` as read-only** (`:ro`) when possible
- **Use SSD storage** for `/app` directory for faster cache access

---

**Next Steps:**
- [Configuration Guide](configuration.md) - Customize indexer settings
- [Security Guide](security.md) - Production security setup
- [User Guide](user-guide.md) - Using the interface