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
      # Configuration directory - stores settings and cache
      - /your/host/config:/config
      
      # Files directory - mount your content here to index
      - /your/host/files:/files
```

### Step 2: Create Host Directories

```bash
# Create directories for persistent data
mkdir -p /your/host/{config,files}

# Set appropriate permissions
sudo chown -R 82:82 /your/host/config
chmod -R 755 /your/host/files
```

### Step 3: Start the Container

```bash
# Start the indexer
docker-compose up -d

# Check status
docker-compose logs -f 5q12-indexer
```

### Step 4: Access the Indexer

Open your browser to: `http://localhost:5012`

## Configuration Examples

### Basic File Server
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
      - ./indexer-config:/config
      - ./public-files:/files
```

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
    volumes:
      - ./config:/config
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
    volumes:
      - /opt/indexer/config:/config
      - /srv/public:/files
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
```

## Volume Configuration

### Required Volumes

| Host Path | Container Path | Purpose | Required |
|-----------|----------------|---------|----------|
| `./config` | `/config` | Configuration, cache, icons | Yes |
| `./files` | `/files` | Content to index and browse | Yes |

### Volume Details

**Configuration Volume (`/config`):**
- Stores `config.json` settings
- Cache files for performance
- Local icons (if enabled)
- Log files and backups

**Files Volume (`/files`):**
- Root directory for browsing
- Mount your content here
- Can include subdirectories
- Supports read-only mounts (`:ro`)

### Permission Requirements

The container runs as `www-data` (UID 82, GID 82). Ensure host directories are accessible:

```bash
# Option 1: Match container user
sudo chown -R 82:82 /path/to/config

# Option 2: Use permissive permissions
chmod -R 755 /path/to/config
chmod -R 755 /path/to/files
```

## Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `TZ` | `Etc/UTC` | Container timezone |

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
docker-compose up -d

# Stop containers
docker-compose down

# Restart containers
docker-compose restart

# View logs
docker-compose logs -f

# Update to latest image
docker-compose pull
docker-compose up -d
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
```

## Updates and Backups

### Updating
```bash
# Pull latest image
docker-compose pull 5q12/5q12-indexer:latest

# Recreate container with new image
docker-compose up -d

# Clean up old images
docker image prune
```

### Backup Configuration
```bash
# Backup config directory
tar -czf indexer-config-$(date +%Y%m%d).tar.gz /path/to/config

# Backup docker-compose configuration
cp docker-compose.yml docker-compose.yml.backup
```

### Version Pinning
Use specific versions for production stability:
```yaml
services:
  5q12-indexer:
    image: 5q12/5q12-indexer:1.1.12  # Pin to specific version
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
docker-compose logs 5q12-indexer

# Check if ports are available
netstat -tlnp | grep :5012

# Verify volume permissions
ls -la /path/to/config /path/to/files
```

### Permission Issues
```bash
# Fix ownership
sudo chown -R 82:82 /path/to/config

# Or use permissive mode
chmod -R 755 /path/to/config /path/to/files
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
- Read-only filesystem where possible
- Resource limits prevent abuse

### Network Security
- Only expose necessary ports
- Use reverse proxy for SSL termination
- Consider firewall rules for production

### File Access Security
- Use read-only mounts (`:ro`) for sensitive content
- Limit container access to specific directories
- Regular security updates via image updates

## Production Deployment

### Multi-Container Setup
```yaml
services:
  indexer:
    image: 5q12/5q12-indexer:latest
    restart: unless-stopped
    volumes:
      - indexer-config:/config
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

---

**Next Steps:**
- [Configuration Guide](configuration.md) - Customize indexer settings
- [Security Guide](security.md) - Production security setup
- [User Guide](user-guide.md) - Using the interface