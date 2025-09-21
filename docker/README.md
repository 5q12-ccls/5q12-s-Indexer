# 5q12's Indexer - Docker Setup

This directory contains everything needed to run 5q12's Indexer in a Docker container.

## Quick Start

1. **Build the image:**
   ```bash
   chmod +x build.sh
   ./build.sh
   ```

2. **Run with Docker Compose:**
   ```bash
   docker-compose up -d
   ```

3. **Access the indexer:**
   Open your browser to `http://localhost:5012`

## Directory Structure

```
your-project/
├── Dockerfile
├── docker-compose.yml
├── build.sh
├── index.php                    # Your main indexer file
├── docker/
│   ├── nginx.conf              # Main nginx configuration
│   ├── 5q12-indexer.conf       # Indexer-specific nginx config
│   ├── php-fpm.conf            # PHP-FPM pool configuration
│   ├── supervisord.conf        # Supervisor process manager config
│   └── entrypoint.sh           # Container startup script
├── config/                     # Volume mount for persistence
│   └── config.json            # Will be created automatically
└── files/                      # Mount your content here
    └── (your files to index)
```

## Configuration

### Environment Variables

- `TZ`: Timezone (default: `Etc/UTC`)
  ```yaml
  environment:
    - TZ=America/New_York
  ```

### Volume Mounts

The compose file includes the required volume mounts:

```yaml
volumes:
  # Configuration persistence (required)
  - ./config:/www/indexer/.indexer_files
  
  # Files directory for content indexing (required)
  - ./files:/www/indexer/files
  
  # Example: Mount external content into files subdirectories
  - /host/documents:/www/indexer/files/documents:ro
  - /host/media:/www/indexer/files/media:ro
  - /host/downloads:/www/indexer/files/downloads:ro
```

**Important Volume Notes:**
- `./config` → Configuration and cache data (always needed)
- `./files` → Content directory structure (always needed)
- External mounts should go into subdirectories of `/www/indexer/files`
- Use `:ro` (read-only) for content you don't want modified

### Port Configuration

The container exposes port 5012. To change it:

```yaml
ports:
  - "8080:5012"  # Access via localhost:8080
```

## Building Custom Images

### Build Arguments

You can customize the build with build arguments:

```bash
docker build \
  --build-arg PHP_VERSION=8.3 \
  -t 5q12/indexer:custom .
```

### Multi-Architecture Builds

For ARM64 and AMD64 support:

```bash
docker buildx build \
  --platform linux/amd64,linux/arm64 \
  -t 5q12/indexer:latest \
  --push .
```

## Development Setup

For development with live code reloading:

```yaml
services:
  5q12-indexer-dev:
    build: .
    volumes:
      - .:/www/indexer
      - ./config:/www/indexer/.indexer_files
    ports:
      - "5012:5012"
    environment:
      - TZ=Etc/UTC
```

## Troubleshooting

### Container Won't Start

1. **Check logs:**
   ```bash
   docker-compose logs 5q12-indexer
   ```

2. **Check configuration:**
   ```bash
   docker exec -it 5q12-indexer nginx -t
   ```

3. **Access container shell:**
   ```bash
   docker exec -it 5q12-indexer sh
   ```

### Permission Issues

If you see permission errors:

```bash
# Fix ownership of config directory
sudo chown -R 82:82 ./config

# Or use the docker user ID
docker exec -it 5q12-indexer chown -R www-data:www-data /www/indexer
```

### Performance Issues

For large directories, increase PHP limits in `docker/php-fpm.conf`:

```ini
php_admin_value[memory_limit] = 512M
php_admin_value[max_execution_time] = 600
```

### SSL/HTTPS Setup

To add SSL support, modify the nginx configuration:

```nginx
server {
    listen 443 ssl;
    ssl_certificate /path/to/cert.pem;
    ssl_certificate_key /path/to/key.pem;
    # ... rest of config
}
```

And mount your certificates:

```yaml
volumes:
  - ./ssl:/etc/ssl/certs:ro
```

## Health Checks

The container includes a health check that curls the indexer endpoint:

```bash
# Check health status
docker inspect --format='{{.State.Health.Status}}' 5q12-indexer
```

## Security Considerations

1. **Read-only mounts:** Use `:ro` for content directories
2. **User permissions:** Container runs as www-data (UID 82)
3. **Network isolation:** Uses bridge network by default
4. **File access:** Only mounted directories are accessible

## Production Deployment

For production use:

1. **Use specific image tags:**
   ```yaml
   image: 5q12/indexer:1.1.10
   ```

2. **Set resource limits:**
   ```yaml
   deploy:
     resources:
       limits:
         memory: 512M
         cpus: '0.5'
   ```

3. **Use secrets for sensitive config:**
   ```yaml
   secrets:
     - indexer_config
   ```

4. **Enable log rotation:**
   ```yaml
   logging:
     driver: "json-file"
     options:
       max-size: "10m"
       max-file: "3"
   ```

## Updates

To update the indexer:

1. **Pull new image:**
   ```bash
   docker-compose pull
   ```

2. **Recreate containers:**
   ```bash
   docker-compose up -d
   ```

3. **Or rebuild from source:**
   ```bash
   ./build.sh
   docker-compose up -d --force-recreate
   ```

## Support

- Check the main documentation for configuration options
- Monitor logs: `docker-compose logs -f`
- For issues, provide output of: `docker-compose logs` and `docker inspect`