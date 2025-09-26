#!/bin/bash

# 5q12's Indexer Docker Deploy Script
# This script builds, tests, and publishes the Docker image to Docker Hub

set -e

# Configuration
DOCKER_USERNAME="5q12"
IMAGE_NAME="5q12-indexer"
VERSION="1.1.18"

VERSIONED_IMAGE="$DOCKER_USERNAME/$IMAGE_NAME:$VERSION"
LATEST_IMAGE="$DOCKER_USERNAME/$IMAGE_NAME:latest"

echo "Deploying 5q12's Indexer to Docker Hub"
echo "======================================"
echo "Versioned: $VERSIONED_IMAGE"
echo "Latest: $LATEST_IMAGE"
echo ""

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
        exit 1
    fi
    echo "✓ $file"
done

# Check for source directory structure
if [ ! -d "source" ]; then
    echo "ERROR: source/ directory not found!"
    exit 1
fi

if [ ! -d "source/config" ]; then
    echo "ERROR: source/config/ directory not found!"
    exit 1
fi

echo "✓ source/ directory structure verified"

# Fix entrypoint script permissions and line endings
chmod +x docker/entrypoint.sh
if file docker/entrypoint.sh | grep -q "CRLF"; then
    echo "Converting CRLF line endings to LF in entrypoint.sh"
    sed -i 's/\r$//' docker/entrypoint.sh
fi

# Step 1: Build the image with both tags
echo ""
echo "Building Docker image..."
docker build -t "$VERSIONED_IMAGE" -t "$LATEST_IMAGE" .

if [ $? -ne 0 ]; then
    echo "Build failed!"
    exit 1
fi

echo "✓ Build successful!"

# Step 2: Test the image locally
echo ""
echo "Testing image locally..."

# Create test directories with some test content
mkdir -p test-indexer-deploy/{config,files}
echo "# Test File" > test-indexer-deploy/files/test.md
echo "console.log('Hello World!');" > test-indexer-deploy/files/test.js

# Start test container
docker run -d --name indexer-deploy-test -p 5013:5012 \
    -v "$(pwd)/test-indexer-deploy/config:/config" \
    -v "$(pwd)/test-indexer-deploy/files:/files" \
    "$VERSIONED_IMAGE"

echo "Waiting for container to start..."
sleep 15

# Check if container is running
if ! docker ps | grep -q indexer-deploy-test; then
    echo "Container failed to start!"
    echo "Container logs:"
    docker logs indexer-deploy-test
    docker rm -f indexer-deploy-test 2>/dev/null || true
    rm -rf test-indexer-deploy/
    exit 1
fi

# Test HTTP response
echo "Testing HTTP endpoint..."
max_attempts=6
attempt=1
while [ $attempt -le $max_attempts ]; do
    if curl -f -s http://localhost:5013 > /dev/null 2>&1; then
        echo "✓ HTTP test passed!"
        break
    else
        if [ $attempt -eq $max_attempts ]; then
            echo "HTTP test failed after $max_attempts attempts!"
            echo "Container logs:"
            docker logs indexer-deploy-test
            docker rm -f indexer-deploy-test 2>/dev/null || true
            rm -rf test-indexer-deploy/
            exit 1
        fi
        echo "Attempt $attempt/$max_attempts failed, retrying in 10 seconds..."
        sleep 10
        attempt=$((attempt + 1))
    fi
done

# Test that our test files are visible
echo "Testing file indexing..."
if curl -s http://localhost:5013 | grep -q "test.md\|test.js"; then
    echo "✓ File indexing test passed!"
else
    echo "File indexing test failed - files not visible in index"
    echo "Response content:"
    curl -s http://localhost:5013 | head -20
    docker rm -f indexer-deploy-test 2>/dev/null || true
    rm -rf test-indexer-deploy/
    exit 1
fi

# Test configuration persistence
echo "Testing configuration..."
if docker exec indexer-deploy-test test -f /config/config.json; then
    echo "✓ Configuration test passed!"
else
    echo "Configuration test failed - config.json not found"
    docker rm -f indexer-deploy-test 2>/dev/null || true
    rm -rf test-indexer-deploy/
    exit 1
fi

# Cleanup test container
docker rm -f indexer-deploy-test
rm -rf test-indexer-deploy/

# Step 3: Login to Docker Hub
echo ""
echo "Logging into Docker Hub..."
echo "You will be prompted for your Docker Hub credentials:"

docker login

if [ $? -ne 0 ]; then
    echo "Docker Hub login failed!"
    exit 1
fi

# Step 4: Push both tags to Docker Hub
echo ""
echo "Pushing images to Docker Hub..."

echo "Pushing versioned image..."
docker push "$VERSIONED_IMAGE"

if [ $? -ne 0 ]; then
    echo "Push of versioned image failed!"
    exit 1
fi

echo "Pushing latest tag..."
docker push "$LATEST_IMAGE"

if [ $? -eq 0 ]; then
    echo ""
    echo "✓ Successfully published both tags!"
    echo ""
    echo "Your images are now publicly available:"
    echo "  - $VERSIONED_IMAGE"
    echo "  - $LATEST_IMAGE"
    echo ""
    echo "Docker Hub URL:"
    echo "  https://hub.docker.com/r/$DOCKER_USERNAME/$IMAGE_NAME"
    echo ""
    echo "Image size:"
    docker images | grep "$DOCKER_USERNAME/$IMAGE_NAME" | head -2
    echo ""
    echo "To use this image anywhere:"
    echo "  docker pull $VERSIONED_IMAGE"
    echo "  # or"
    echo "  docker pull $LATEST_IMAGE"
    echo ""
    echo "Quick start command:"
    echo "  mkdir -p indexer/{config,files}"
    echo "  docker run -d --name my-indexer -p 5012:5012 \\"
    echo "    -v \$(pwd)/indexer/config:/config \\"
    echo "    -v \$(pwd)/indexer/files:/files \\"
    echo "    $VERSIONED_IMAGE"
    echo ""
    echo "Or with docker-compose:"
    echo "  services:"
    echo "    indexer:"
    echo "      image: $VERSIONED_IMAGE"
    echo "      ports:"
    echo "        - \"5012:5012\""
    echo "      volumes:"
    echo "        - \"./config:/config\""
    echo "        - \"./files:/files\""
    echo "      restart: unless-stopped"
else
    echo "Push to Docker Hub failed!"
    exit 1
fi