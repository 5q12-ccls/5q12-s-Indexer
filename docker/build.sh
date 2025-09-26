#!/bin/bash

# 5q12's Indexer Docker Build Script
# This script builds the Docker image for the indexer

set -e

# Configuration
DOCKER_USERNAME="5q12"
IMAGE_NAME="5q12-indexer"
VERSION="1.1.18"
DOCKERFILE_PATH="."

# Full image names
VERSIONED_IMAGE="$DOCKER_USERNAME/$IMAGE_NAME:$VERSION"
LATEST_IMAGE="$DOCKER_USERNAME/$IMAGE_NAME:latest"

echo "Building 5q12's Indexer Docker Image..."
echo "Versioned: $VERSIONED_IMAGE"
echo "Latest: $LATEST_IMAGE"
echo "Dockerfile path: $DOCKERFILE_PATH"
echo ""
echo "Architecture Notes:"
echo "- source/ directory contains index.php and default config"
echo "- /config mount point for indexer configuration and cache"
echo "- /files mount point for content to be indexed"
echo "- Symlinks: /www/indexer/.indexer_files -> /config"
echo "- Symlinks: /www/indexer/files -> /files"
echo "- Default config copied to /app/default-config/ (used if /config is empty)"
echo ""

# Create docker directory if it doesn't exist
mkdir -p docker

# Check if required files exist
REQUIRED_FILES=(
    "docker/nginx.conf"
    "docker/5q12-indexer.conf" 
    "docker/php-fpm.conf"
    "docker/supervisord.conf"
    "docker/entrypoint.sh"
    "source/index.php"
    "source/config/config.json"
    "Dockerfile"
)

echo "Checking required files..."
for file in "${REQUIRED_FILES[@]}"; do
    if [ ! -f "$file" ]; then
        echo "ERROR: Required file '$file' not found!"
        echo "Please make sure all configuration files are in place."
        exit 1
    fi
    echo "✓ $file"
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

# Make entrypoint script executable and check line endings
chmod +x docker/entrypoint.sh
if file docker/entrypoint.sh | grep -q "CRLF"; then
    echo "WARNING: Converting CRLF line endings to LF in entrypoint.sh"
    sed -i 's/\r$//' docker/entrypoint.sh
fi

# Build the Docker image with both tags
echo ""
echo "Building Docker image..."
docker build -t "$VERSIONED_IMAGE" -t "$LATEST_IMAGE" .

if [ $? -eq 0 ]; then
    echo "✓ Docker image built successfully!"
    echo ""
    echo "Image tags created:"
    echo "  - $VERSIONED_IMAGE"
    echo "  - $LATEST_IMAGE"
    echo ""
    echo "Image size:"
    docker images | grep "$DOCKER_USERNAME/$IMAGE_NAME" | head -2
    echo ""
    echo "To test locally:"
    echo "  mkdir -p test-indexer/{config,files}"
    echo "  docker run -d --name test-indexer -p 5012:5012 \\"
    echo "    -v \$(pwd)/test-indexer/config:/config \\"
    echo "    -v \$(pwd)/test-indexer/files:/files \\"
    echo "    $VERSIONED_IMAGE"
    echo ""
    echo "  # Test the indexer:"
    echo "  curl http://localhost:5012"
    echo ""
    echo "  # Check logs:"
    echo "  docker logs test-indexer"
    echo ""
    echo "  # Stop and remove:"
    echo "  docker rm -f test-indexer"
    echo "  rm -rf test-indexer/"
    echo ""
    echo "To push to Docker Hub:"
    echo "  docker login"
    echo "  docker push $VERSIONED_IMAGE"
    echo "  docker push $LATEST_IMAGE"
    echo ""
    echo "To use in docker-compose.yml:"
    echo "  image: $VERSIONED_IMAGE"
    echo "  # or"
    echo "  image: $LATEST_IMAGE"
else
    echo "✗ Docker build failed!"
    exit 1
fi