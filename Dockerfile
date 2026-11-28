# ==============================================================================
# DayWright Production Multi-Stage Dockerfile
# ==============================================================================

# ------------------------------------------------------------------------------
# Stage 1: Frontend Asset Compilation (Vite 7 + Vue 2.7)
# ------------------------------------------------------------------------------
FROM node:24-bookworm-slim AS frontend-builder

WORKDIR /app

# Copy dependency manifests first to maximize Docker layer caching
COPY package.json package-lock.json ./

# Install exact dependencies pinned in package-lock.json
RUN npm ci --prefer-offline --no-audit

# Copy build configuration and frontend sources
COPY vite.config.js ./
COPY resources/ ./resources/
COPY public/ ./public/

# Public Vite build-time variables (inlined into JS bundle during npm run build)
ARG VITE_API_BASE_URL=/api/v1
ARG VITE_APP_ENV=production
ARG VITE_PUSHER_APP_KEY=""
ARG VITE_PUSHER_APP_CLUSTER=mt1
ARG VITE_ZOOM_SDK_KEY=""
ARG VITE_SENTRY_DSN=""

# Compile production assets into public/build
ENV NODE_ENV=production
RUN npm run build

# ------------------------------------------------------------------------------
# Stage 2: PHP Vendor Dependencies (Composer + Patches)
# ------------------------------------------------------------------------------
FROM php:8.3-cli-bookworm AS vendor-builder

WORKDIR /app

# Install system tools and PHP extensions required by Composer dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    patch \
    unzip \
    libpng-dev \
    libzip-dev \
    && docker-php-ext-install -j$(nproc) \
        bcmath \
        gd \
        pcntl \
        zip \
    && rm -rf /var/lib/apt/lists/*

# Copy official Composer binary from composer:2 image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy Composer manifests, patch locks, and custom patches
COPY composer.json composer.lock patches.lock.json ./
COPY patches/ ./patches/

# Normalize line endings on patches and install production dependencies
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN sed -i 's/\r$//' patches/*.patch \
    && composer install \
        --no-dev \
        --no-interaction \
        --prefer-dist \
        --no-scripts \
        --no-autoloader

# ------------------------------------------------------------------------------
# Stage 3: Production PHP-FPM Runtime
# ------------------------------------------------------------------------------
FROM php:8.3-fpm-bookworm AS runtime

WORKDIR /var/www/html

# Install runtime shared libraries and build tools for PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpng16-16 \
    libjpeg62-turbo \
    libfreetype6 \
    libwebp7 \
    libzip4 \
    libicu72 \
    default-mysql-client \
    $PHPIZE_DEPS \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libwebp-dev \
    libzip-dev \
    libicu-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j2 \
        pdo_mysql \
        bcmath \
        pcntl \
        gd \
        zip \
        intl \
        exif \
    && pecl install redis-6.1.0 \
    && docker-php-ext-enable redis \
    && apt-get purge -y --auto-remove \
        $PHPIZE_DEPS \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libwebp-dev \
        libzip-dev \
        libicu-dev \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

# Adopt official PHP production configuration and custom INI settings
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

RUN { \
    echo '[PHP]'; \
    echo 'memory_limit = 512M'; \
    echo 'upload_max_filesize = 10M'; \
    echo 'post_max_size = 12M'; \
    echo 'max_execution_time = 60'; \
    echo 'expose_php = Off'; \
    echo 'date.timezone = UTC'; \
    echo ''; \
    echo '[opcache]'; \
    echo 'opcache.enable = 1'; \
    echo 'opcache.enable_cli = 0'; \
    echo 'opcache.memory_consumption = 256'; \
    echo 'opcache.interned_strings_buffer = 16'; \
    echo 'opcache.max_accelerated_files = 20000'; \
    echo 'opcache.validate_timestamps = 0'; \
    echo 'opcache.save_comments = 1'; \
} > "$PHP_INI_DIR/conf.d/99-daywright.ini"

# Copy application source code (respecting .dockerignore)
COPY . .

# Copy production vendor directory from Stage 2
COPY --from=vendor-builder /app/vendor/ ./vendor/

# Copy compiled frontend assets from Stage 1
COPY --from=frontend-builder /app/public/build/ ./public/build/

# Copy temporary Composer binary to generate optimized authoritative autoloader, then remove Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative \
    && rm -f /usr/bin/composer

# Run Laravel package discovery
RUN php artisan package:discover --ansi

# Create storage symlink
RUN php artisan storage:link --no-interaction

# Ensure storage and cache directory structures exist with correct permissions
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Copy PHP-FPM pool override (TCP listen on 0.0.0.0:9000, tuned pm settings)
COPY docker/php/php-fpm.d/zz-docker.conf /usr/local/etc/php-fpm.d/zz-docker.conf

# Copy and register the production entrypoint script
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 9000

# entrypoint.sh: waits for Redis, warms caches, then execs php-fpm
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

# ------------------------------------------------------------------------------
# Stage 4: Nginx Web Server
# ------------------------------------------------------------------------------
FROM nginx:1.30-alpine AS web

# Copy the complete public/ directory from the runtime stage.
# Remove dangling storage symlink from runtime stage (Nginx serves no local storage)
COPY --from=runtime /var/www/html/public /var/www/html/public
RUN rm -f /var/www/html/public/storage

# Install DayWright Nginx configuration as an envsubst template so ${DOMAIN}
# is substituted at container startup into /etc/nginx/conf.d/default.conf.
COPY docker/nginx/default.conf /etc/nginx/templates/default.conf.template

# Generate a throwaway self-signed certificate at the Let's Encrypt path and
# render the template with DOMAIN=localhost so `nginx -t` validates syntax at
# build time. At runtime, /etc/letsencrypt is bind-mounted read-only and
# 20-envsubst-on-templates.sh renders the real ${DOMAIN} config on startup.
RUN apk add --no-cache openssl \
    && mkdir -p /etc/letsencrypt/live/localhost \
    && openssl req -x509 -nodes -days 1 -newkey rsa:2048 \
        -keyout /etc/letsencrypt/live/localhost/privkey.pem \
        -out /etc/letsencrypt/live/localhost/fullchain.pem \
        -subj "/CN=localhost" \
    && DOMAIN=localhost envsubst '${DOMAIN}' \
        < /etc/nginx/templates/default.conf.template \
        > /etc/nginx/conf.d/default.conf \
    && apk del openssl

# Validate configuration syntax before the layer is committed
RUN nginx -t

EXPOSE 80 443

CMD ["nginx", "-g", "daemon off;"]

