#!/bin/bash

# 5q12's Indexer Docker Build Script
# This script builds the Docker image for the indexer

set -e

# Configuration
DOCKER_USERNAME="5q12"
IMAGE_NAME="5q12-indexer"
VERSION="1.1.15"
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
echo "- index.php is always recreated from image (never persisted)"
echo "- /config mount point for indexer configuration and cache"
echo "- /files mount point for content to be indexed"
echo "- Symlinks: /www/indexer/.indexer_files -> /config"
echo "- Symlinks: /www/indexer/files -> /files"
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
    "index.php"
)

echo "Checking required files..."
for file in "${REQUIRED_FILES[@]}"; do
    if [ ! -f "$file" ]; then
        echo "ERROR: Required file '$file' not found!"
        echo "Please make sure all configuration files are in place."
        exit 1
    fi
    echo "âœ“ $file"
done

# Make entrypoint script executable and check line endings
chmod +x docker/entrypoint.sh
if file docker/entrypoint.sh | grep -q "CRLF"; then
    echo "WARNING: Converting CRLF line endings to LF in entrypoint.sh"
    sed -i 's/\r$//' docker/entrypoint.sh
fi

# Build the Docker image with both tags
echo "Building Docker image..."
docker build -t "$VERSIONED_IMAGE" -t "$LATEST_IMAGE" .

if [ $? -eq 0 ]; then
    echo "âœ“ Docker image built successfully!"
    echo ""
    echo "Image tags created:"
    echo "  - $VERSIONED_IMAGE"
    echo "  - $LATEST_IMAGE"
    echo ""
    echo "To test locally:"
    echo "  docker run -d -p 5012:5012 -v ./config:/config -v ./files:/files $VERSIONED_IMAGE"
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
    echo "âœ— Docker build failed!"
    exit 1
fi