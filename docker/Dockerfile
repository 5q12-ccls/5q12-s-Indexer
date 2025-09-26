# Use official PHP image with FPM
FROM php:8.3-fpm-alpine

# Set maintainer and version
LABEL maintainer="5q12"
LABEL description="5q12's Indexer - Web-based file browser and indexer"
LABEL version="1.1.18"

# Install system dependencies and build tools
RUN apk add --no-cache \
    nginx \
    supervisor \
    sqlite \
    sqlite-dev \
    zip \
    unzip \
    curl \
    ca-certificates \
    libzip-dev \
    bash \
    file

# Install PHP extensions
RUN docker-php-ext-install \
    pdo_sqlite \
    zip \
    && docker-php-ext-enable \
    pdo_sqlite \
    zip

# Ensure www-data user exists with correct UID/GID
RUN if ! getent group www-data >/dev/null; then addgroup -g 82 -S www-data; fi && \
    if ! getent passwd www-data >/dev/null; then adduser -u 82 -D -S -G www-data www-data; fi

# Create necessary directories
RUN mkdir -p /www/indexer \
    && mkdir -p /var/log/nginx \
    && mkdir -p /var/log/supervisor \
    && mkdir -p /run/nginx \
    && mkdir -p /run/php \
    && mkdir -p /config \
    && mkdir -p /files \
    && mkdir -p /app

# Copy nginx configuration
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/5q12-indexer.conf /etc/nginx/http.d/default.conf

# Copy PHP-FPM configuration  
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf

# Copy supervisor configuration
COPY docker/supervisord.conf /etc/supervisord.conf

# Copy all source files including config and index.php
COPY source/ /app/source/

# Copy index.php to application directory
COPY source/index.php /www/indexer/

# Copy default config to container (will be used if no external config mounted)
# Use explicit wildcard to copy all files and subdirectories
COPY source/config /app/default-config

# Copy entrypoint script
COPY docker/entrypoint.sh /entrypoint.sh

# Create symlinks from application paths to mount points
# Remove any existing directories first, then create symlinks
RUN rm -rf /www/indexer/.indexer_files /www/indexer/files \
    && ln -sf /config /www/indexer/.indexer_files \
    && ln -sf /files /www/indexer/files

# Set permissions
RUN chown -R www-data:www-data /www/indexer \
    && chown -R www-data:www-data /config \
    && chown -R www-data:www-data /files \
    && chown -R www-data:www-data /app \
    && chmod +x /entrypoint.sh \
    && chmod 644 /www/indexer/index.php

# Set environment variable to identify Docker environment
ENV DOCKER_ENV=true

# Create volume mount points
VOLUME ["/config", "/files"]

# Expose port 5012
EXPOSE 5012

# Set working directory
WORKDIR /www/indexer

# Health check to ensure the service is running
HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
    CMD curl -f http://localhost:5012/ || exit 1

# Use entrypoint script
ENTRYPOINT ["/entrypoint.sh"]

# Start supervisor
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]