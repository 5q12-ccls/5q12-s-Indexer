# Use official PHP image with FPM - Updated to latest Alpine 3.22
FROM php:8.3-fpm-alpine3.22

# Set maintainer and version
LABEL maintainer="5q12"
LABEL description="5q12's Indexer - Web-based file browser and indexer (Security Hardened)"
LABEL version="1.1.18"
LABEL alpine.version="3.22.1"
LABEL supervisor.version="4.2.5-r5+"

# Update base system first to get latest security patches
RUN apk update && apk upgrade

# Install system dependencies and build tools with latest secure versions
# Note: Using --no-cache to avoid storing package cache
RUN apk add --no-cache \
    # Latest nginx from Alpine 3.22 repository
    nginx \
    # Latest supervisor from Alpine 3.22 (addresses CVE-2023-27482)
    supervisor \
    sqlite \
    sqlite-dev \
    # Use p7zip instead of unzip to avoid CVE-2008-0888
    p7zip \
    curl \
    ca-certificates \
    libzip-dev \
    bash \
    file \
    # Add specific security-focused packages
    tzdata \
    && rm -rf /var/cache/apk/*

# Install PHP extensions
RUN docker-php-ext-install \
    pdo_sqlite \
    zip \
    && docker-php-ext-enable \
    pdo_sqlite \
    zip

# Create a non-root user for better security
RUN addgroup -g 1001 indexer && \
    adduser -u 1001 -G indexer -s /bin/sh -D indexer

# Ensure www-data user exists with correct UID/GID
RUN if ! getent group www-data >/dev/null; then addgroup -g 82 -S www-data; fi && \
    if ! getent passwd www-data >/dev/null; then adduser -u 82 -D -S -G www-data www-data; fi

# Create necessary directories with proper permissions
RUN mkdir -p /www/indexer \
    && mkdir -p /var/log/nginx \
    && mkdir -p /var/log/supervisor \
    && mkdir -p /run/nginx \
    && mkdir -p /run/php \
    && mkdir -p /config \
    && mkdir -p /files \
    && mkdir -p /app \
    && mkdir -p /tmp/sessions \
    && chmod 1777 /tmp/sessions

# Copy nginx configuration
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/5q12-indexer.conf /etc/nginx/http.d/default.conf

# Copy PHP-FPM configuration with security enhancements
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf

# Copy supervisor configuration
COPY docker/supervisord.conf /etc/supervisord.conf

# Copy all source files including config and index.php
COPY source/ /app/source/

# Copy index.php to application directory
COPY source/index.php /www/indexer/

# Copy default config to container (will be used if no external config mounted)
COPY source/config /app/default-config

# Copy entrypoint script
COPY docker/entrypoint.sh /entrypoint.sh

# Create symlinks from application paths to mount points
# Remove any existing directories first, then create symlinks
RUN rm -rf /www/indexer/.indexer_files /www/indexer/files \
    && ln -sf /config /www/indexer/.indexer_files \
    && ln -sf /files /www/indexer/files

# Set strict permissions for security
RUN chown -R www-data:www-data /www/indexer \
    && chown -R www-data:www-data /config \
    && chown -R www-data:www-data /files \
    && chown -R www-data:www-data /app \
    && chown -R www-data:www-data /tmp/sessions \
    && chmod +x /entrypoint.sh \
    && chmod 644 /www/indexer/index.php \
    && chmod -R 755 /www/indexer \
    && chmod -R 750 /config \
    && chmod -R 755 /files

# Security: Remove unnecessary packages after installation
RUN apk del --purge \
    && rm -rf /var/cache/apk/* \
    && rm -rf /tmp/*

# Set environment variable to identify Docker environment
ENV DOCKER_ENV=true

# Security: Run as non-root user where possible
USER www-data

# Create volume mount points
VOLUME ["/config", "/files"]

# Expose port 5012
EXPOSE 5012

# Set working directory
WORKDIR /www/indexer

# Enhanced health check with security considerations
HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
    CMD curl -f --max-time 5 http://localhost:5012/ || exit 1

# Switch back to root for entrypoint (required for service management)
USER root

# Use entrypoint script
ENTRYPOINT ["/entrypoint.sh"]

# Start supervisor
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]