# Use official PHP image with FPM - Updated to latest Alpine 3.22
FROM php:8.3-fpm-alpine3.22

# Set maintainer and version
LABEL maintainer="5q12"
LABEL description="5q12's Indexer - Secure file browser with s6-overlay (No Supervisor)"
LABEL version="1.1.19"
LABEL alpine.version="3.22.1"
LABEL process.manager="s6-overlay-v3"
LABEL security.status="hardened"

# Install s6-overlay (secure supervisor replacement)
ARG S6_OVERLAY_VERSION=3.1.6.2
ADD https://github.com/just-containers/s6-overlay/releases/download/v${S6_OVERLAY_VERSION}/s6-overlay-noarch.tar.xz /tmp
ADD https://github.com/just-containers/s6-overlay/releases/download/v${S6_OVERLAY_VERSION}/s6-overlay-x86_64.tar.xz /tmp

# Extract s6-overlay
RUN tar -C / -Jxpf /tmp/s6-overlay-noarch.tar.xz && \
    tar -C / -Jxpf /tmp/s6-overlay-x86_64.tar.xz && \
    rm -f /tmp/s6-overlay-*.tar.xz

# Update base system first to get latest security patches
RUN apk update && apk upgrade

# Install system dependencies (NO SUPERVISOR - using s6-overlay instead)
RUN apk add --no-cache \
    nginx \
    sqlite \
    sqlite-dev \
    p7zip \
    curl \
    ca-certificates \
    libzip-dev \
    bash \
    file \
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
    && mkdir -p /run/nginx \
    && mkdir -p /run/php \
    && mkdir -p /config \
    && mkdir -p /files \
    && mkdir -p /app \
    && mkdir -p /tmp/sessions \
    && chmod 1777 /tmp/sessions

# Add s6-overlay binaries to PATH
ENV PATH="/command:${PATH}"

# Create s6 service directories
RUN mkdir -p /etc/s6-overlay/s6-rc.d/nginx/{dependencies.d} \
             /etc/s6-overlay/s6-rc.d/php-fpm/{dependencies.d} \
             /etc/s6-overlay/s6-rc.d/init-indexer \
             /etc/s6-overlay/s6-rc.d/user/contents.d \
             /etc/s6-overlay/s6-rc.d/user2/contents.d

# Copy s6 service definitions
COPY docker/s6-services/ /etc/s6-overlay/s6-rc.d/

# Set permissions for s6 scripts
RUN chmod +x /etc/s6-overlay/s6-rc.d/*/run 2>/dev/null || true \
    && chmod 644 /etc/s6-overlay/s6-rc.d/*/type \
    && chmod 644 /etc/s6-overlay/s6-rc.d/*/up 2>/dev/null || true

# Set environment variables for s6-overlay
ENV S6_BEHAVIOUR_IF_STAGE2_FAILS=2
ENV S6_CMD_WAIT_FOR_SERVICES_MAXTIME=0
ENV S6_LOGGING=1

# Copy nginx configuration
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/5q12-indexer.conf /etc/nginx/http.d/default.conf

# Copy PHP-FPM configuration with security enhancements
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf

# Copy all source files including config and index.php
COPY source/ /app/source/

# Copy index.php to application directory
COPY source/index.php /www/indexer/

# Copy default config to container (will be used if no external config mounted)
COPY source/config /app/default-config

# Copy s6 service definitions (created by setup script)
COPY docker/s6-services/ /etc/s6-overlay/s6-rc.d/

# Copy initialization scripts
COPY docker/scripts/ /docker/scripts/
RUN chmod +x /docker/scripts/*.sh

# Create symlinks from application paths to mount points
RUN rm -rf /www/indexer/.indexer_files /www/indexer/files \
    && ln -sf /config /www/indexer/.indexer_files \
    && ln -sf /files /www/indexer/files

# Set strict permissions for security
RUN chown -R www-data:www-data /www/indexer \
    && chown -R www-data:www-data /config \
    && chown -R www-data:www-data /files \
    && chown -R www-data:www-data /app \
    && chown -R www-data:www-data /tmp/sessions \
    && chmod +x /etc/s6-overlay/s6-rc.d/*/run 2>/dev/null || true \
    && chmod 644 /www/indexer/index.php \
    && chmod -R 755 /www/indexer \
    && chmod -R 750 /config \
    && chmod -R 755 /files

# Security: Remove unnecessary packages and clean up
RUN rm -rf /var/cache/apk/* /tmp/* && \
    find / -type f -perm /u+s -exec chmod u-s {} \; 2>/dev/null || true && \
    find / -type f -perm /g+s -exec chmod g-s {} \; 2>/dev/null || true

# Set environment variables
ENV DOCKER_ENV=true
ENV S6_BEHAVIOUR_IF_STAGE2_FAILS=2
ENV S6_CMD_WAIT_FOR_SERVICES_MAXTIME=0
ENV S6_LOGGING=1

# Create volume mount points
VOLUME ["/config", "/files"]

# Expose port 5012
EXPOSE 5012

# Set working directory
WORKDIR /www/indexer

# Enhanced health check with security considerations
HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
    CMD curl -f --max-time 5 http://localhost:5012/ || exit 1

# Use s6-overlay as init (replaces supervisord completely)
ENTRYPOINT ["/init"]
CMD []