#!/bin/bash

# 5q12's Indexer Docker Build Script with S6-Overlay
# This script builds the Docker image for the indexer

set -e

# Configuration
DOCKER_USERNAME="5q12"
IMAGE_NAME="5q12-indexer"
VERSION="1.1.19"
DOCKERFILE_PATH="."

# Full image names
VERSIONED_IMAGE="$DOCKER_USERNAME/$IMAGE_NAME:$VERSION"
LATEST_IMAGE="$DOCKER_USERNAME/$IMAGE_NAME:latest"
S6_IMAGE="$DOCKER_USERNAME/$IMAGE_NAME:$VERSION-s6"

echo "Building 5q12's Indexer Docker Image with S6-Overlay..."
echo "Versioned: $VERSIONED_IMAGE"
echo "Latest: $LATEST_IMAGE"
echo "S6-Enhanced: $S6_IMAGE"
echo "Dockerfile path: $DOCKERFILE_PATH"
echo ""
echo "Architecture Notes:"
echo "- source/ directory contains index.php and default config"
echo "- /config mount point for indexer configuration and cache"
echo "- /files mount point for content to be indexed"
echo "- Symlinks: /www/indexer/.indexer_files -> /config"
echo "- Symlinks: /www/indexer/files -> /files"
echo "- Default config copied to /app/default-config/ (used if /config is empty)"
echo "- Process Manager: S6-Overlay v3 (NO SUPERVISOR)"
echo ""

# Create docker directory if it doesn't exist
mkdir -p docker

# Check if required files exist (REMOVED supervisord.conf, ADDED s6-services)
REQUIRED_FILES=(
    "docker/nginx.conf"
    "docker/5q12-indexer.conf" 
    "docker/php-fpm.conf"
    "source/index.php"
    "source/config/config.json"
    "Dockerfile"
)

# Check if s6-services directory exists
S6_SERVICES_DIR="docker/s6-services"

echo "Checking required files..."
for file in "${REQUIRED_FILES[@]}"; do
    if [ ! -f "$file" ]; then
        echo "ERROR: Required file '$file' not found!"
        echo "Please make sure all configuration files are in place."
        exit 1
    fi
    echo "✓ $file"
done

# Check for s6-services directory
if [ ! -d "$S6_SERVICES_DIR" ]; then
    echo "ERROR: S6-services directory not found at $S6_SERVICES_DIR"
    echo "Please run the S6-Overlay setup script first:"
    echo "  ./s6.sh"
    exit 1
fi

# Verify s6-services structure
S6_REQUIRED_DIRS=(
    "docker/s6-services/nginx"
    "docker/s6-services/php-fpm"
    "docker/s6-services/init-indexer"
    "docker/s6-services/user/contents.d"
)

echo "Checking S6-Overlay service structure..."
for dir in "${S6_REQUIRED_DIRS[@]}"; do
    if [ ! -d "$dir" ]; then
        echo "ERROR: Required S6 service directory '$dir' not found!"
        echo "Please run the S6-Overlay setup script: ./s6.sh"
        exit 1
    fi
    echo "✓ $dir"
done

# Check for source directory structure
if [ ! -d "source" ]; then
    echo "ERROR: source/ directory not found!"
    echo "Please make sure the source directory exists with index.php and config/ subdirectory."
    exit 1
fi

if [ ! -d "source/config" ]; then
    echo "ERROR: source/config/ directory not found!"
    echo "Please make sure the config directory exists in source/."
    exit 1
fi

echo "✓ source/ directory structure verified"

# Verify s6 service run scripts are executable
echo "Setting S6 service script permissions..."
find docker/s6-services -name "run" -type f -exec chmod +x {} \;
echo "✓ S6 service scripts made executable"

# Show supervisor migration status
if [ -f "docker/supervisord.conf.backup" ]; then
    echo "✓ Found supervisor backup - migration completed"
elif [ -f "docker/supervisord.conf" ]; then
    echo "⚠ WARNING: Found supervisord.conf but no backup"
    echo "  This suggests migration may be incomplete"
    echo "  Consider running the S6 setup script again"
fi

# Build the Docker image with multiple tags
echo ""
echo "Building S6-Overlay Docker image..."
docker build -t "$VERSIONED_IMAGE" -t "$LATEST_IMAGE" -t "$S6_IMAGE" .

if [ $? -eq 0 ]; then
    echo "✓ Docker image built successfully with S6-Overlay!"
    echo ""
    echo "Image tags created:"
    echo "  - $VERSIONED_IMAGE"
    echo "  - $LATEST_IMAGE"
    echo "  - $S6_IMAGE"
    echo ""
    echo "Image size comparison:"
    docker images | grep "$DOCKER_USERNAME/$IMAGE_NAME" | head -3
    echo ""
    echo "Security improvements:"
    echo "  ✓ No supervisor vulnerabilities"
    echo "  ✓ Reduced Python attack surface"
    echo "  ✓ Container-native process management"
    echo "  ✓ Industry-standard S6-Overlay"
    echo ""
    echo "To test locally:"
    echo "  mkdir -p test-indexer/{config,files}"
    echo "  docker run -d --name test-indexer-s6 -p 5012:5012 \\"
    echo "    -v \$(pwd)/test-indexer/config:/config \\"
    echo "    -v \$(pwd)/test-indexer/files:/files \\"
    echo "    -e TZ=UTC \\"
    echo "    -e INDEXER_INDEX_HIDDEN=false \\"
    echo "    $VERSIONED_IMAGE"
    echo ""
    echo "  # Test the indexer:"
    echo "  curl http://localhost:5012"
    echo ""
    echo "  # Check S6 logs:"
    echo "  docker logs test-indexer-s6"
    echo ""
    echo "  # Check S6 service status:"
    echo "  docker exec test-indexer-s6 s6-rc -a list"
    echo ""
    echo "  # Stop and remove:"
    echo "  docker rm -f test-indexer-s6"
    echo "  rm -rf test-indexer/"
    echo ""
    echo "Environment variables supported:"
    echo "  -e INDEXER_ACCESS_URL=https://example.com"
    echo "  -e INDEXER_INDEX_HIDDEN=true"
    echo "  -e INDEXER_DISABLE_FILE_DOWNLOADS=false"
    echo "  -e INDEXER_INDEX_FILETYPE_PHP=false"
    echo "  -e INDEXER_VIEW_FILETYPE_MD=true"
    echo "  -e TZ=America/New_York"
    echo ""
    echo "To push to Docker Hub:"
    echo "  docker login"
    echo "  docker push $VERSIONED_IMAGE"
    echo "  docker push $LATEST_IMAGE"
    echo "  docker push $S6_IMAGE"
    echo ""
    echo "Docker Compose example:"
    cat << 'EOF'
version: '3.8'
services:
  indexer:
    image: 5q12/5q12-indexer:1.1.19
    container_name: indexer-s6
    ports:
      - "5012:5012"
    volumes:
      - ./config:/config
      - ./files:/files
    environment:
      - TZ=UTC
      - INDEXER_INDEX_HIDDEN=false
    restart: unless-stopped
    security_opt:
      - no-new-privileges:true
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:5012/"]
      interval: 30s
      timeout: 10s
      retries: 3
EOF
    echo ""
    echo "🎉 S6-Enhanced container built successfully!"
    echo "   No more supervisor vulnerabilities!"
else
    echo "✗ Docker build failed!"
    echo ""
    echo "Common issues:"
    echo "  1. Missing s6-services directory - run: ./s6.sh"
    echo "  2. Missing source files - check source/ directory"
    echo "  3. Dockerfile still references supervisor - use S6 version"
    exit 1
fi