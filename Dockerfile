FROM webdevops/php-apache:8.2

LABEL maintainer="Fast Technologies <fctbd1@gmail.com>"
LABEL app="FastPos"
LABEL vendor="Fast Technologies"

# ─── System Dependencies ──────────────────────────────────────────────────────
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libicu-dev \
    libonig-dev \
    unzip \
    git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        gd \
        zip \
        intl \
        mbstring \
        exif \
        bcmath \
        opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# ─── PHP Config ───────────────────────────────────────────────────────────────
ENV PHP_MEMORY_LIMIT=512M
ENV PHP_MAX_EXECUTION_TIME=300
ENV PHP_UPLOAD_MAX_FILESIZE=64M
ENV PHP_POST_MAX_SIZE=64M
ENV PHP_OPCACHE_ENABLE=1
ENV PHP_OPCACHE_MEMORY_CONSUMPTION=256
ENV PHP_OPCACHE_MAX_ACCELERATED_FILES=20000
ENV PHP_OPCACHE_REVALIDATE_FREQ=0
ENV PHP_OPCACHE_VALIDATE_TIMESTAMPS=0

# ─── Apache Config ────────────────────────────────────────────────────────────
ENV WEB_DOCUMENT_ROOT=/app/public
ENV PHP_DISPLAY_ERRORS=0

# ─── App Files ────────────────────────────────────────────────────────────────
WORKDIR /app
COPY . /app

# Create required Laravel storage directories
RUN mkdir -p storage/framework/views storage/framework/cache/data storage/framework/sessions storage/framework/testing storage/logs storage/app/public

# ─── Composer Install (production, no dev) ───────────────────────────────────
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && composer install --no-dev --optimize-autoloader --no-interaction --no-progress \
    && composer clear-cache

# ─── Storage Permissions ─────────────────────────────────────────────────────
RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache \
    && chmod -R 775 /app/storage /app/bootstrap/cache

# ─── Laravel Optimization (runs at build time via entrypoint) ────────────────
# Note: config:cache, route:cache, view:cache run at startup after .env is mounted
COPY docker-entrypoint.sh /usr/local/bin/fastpos-entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/fastpos-entrypoint.sh \
    && chmod +x /usr/local/bin/fastpos-entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/fastpos-entrypoint.sh"]
