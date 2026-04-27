# Optional Dockerfile for book-service — php:8.3-fpm

FROM php:8.3-fpm

# ── System dependencies ───────────────────────────────────────────────────────
# libpq-dev   : required to compile the pdo_pgsql / pgsql PHP extensions
# libzip-dev  : required to compile the zip PHP extension
# zip/unzip   : used by Composer to handle package archives
# git         : used by Composer to clone VCS repositories if needed
# curl        : general-purpose HTTP tool; also used during package install
RUN apt-get update && apt-get install -y --no-install-recommends \
        libzip-dev \
        zip \
        unzip \
        git \
        curl \
    && rm -rf /var/lib/apt/lists/*

# ── PHP extensions ────────────────────────────────────────────────────────────
# pdo         : PHP Data Objects abstraction layer (base for pdo_mysql)
# pdo_mysql   : PDO driver for MySQL / MariaDB
# mysqli      : MySQLi extension (used by some Laravel features)
# zip         : allows reading/writing ZIP archives (Composer, file exports)
# opcache     : bytecode cache — significantly improves production performance
RUN docker-php-ext-install \
        pdo \
        pdo_mysql \
        mysqli \
        zip \
        opcache

# ── OPcache tuning for production ─────────────────────────────────────────────
RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.memory_consumption=128'; \
        echo 'opcache.interned_strings_buffer=8'; \
        echo 'opcache.max_accelerated_files=10000'; \
        echo 'opcache.revalidate_freq=60'; \
        echo 'opcache.validate_timestamps=0'; \
        echo 'opcache.save_comments=1'; \
    } > /usr/local/etc/php/conf.d/opcache.ini

# ── Composer ──────────────────────────────────────────────────────────────────
# Copy the Composer binary from the official Composer image to avoid installing
# it manually and to always get a stable, verified release.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ── Working directory ─────────────────────────────────────────────────────────
WORKDIR /var/www

# ── Install PHP dependencies ──────────────────────────────────────────────────
# Copy only the manifest files first so that Docker can cache this layer and
# skip re-downloading packages when only application code changes.
COPY composer.json composer.lock ./

RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --optimize-autoloader

# ── Copy application source code ──────────────────────────────────────────────
COPY . .

# ── Generate optimised autoloader ────────────────────────────────────────────
# Run after COPY so the full class map can be built from actual source files.
RUN composer dump-autoload --optimize

# ── Laravel bootstrap caches (optional but recommended for production) ─────────
# Skip gracefully if APP_KEY is not yet set in the build environment.
RUN php artisan config:cache  2>/dev/null || true \
 && php artisan route:cache   2>/dev/null || true \
 && php artisan view:cache    2>/dev/null || true

# ── File permissions ──────────────────────────────────────────────────────────
# The php-fpm process runs as www-data; give it write access to storage and the
# bootstrap cache directory so Laravel can write logs, sessions, and views.
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache \
 && chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# ── Expose php-fpm port ───────────────────────────────────────────────────────
# php-fpm listens on 9000 by default; an Nginx container should proxy to this.
EXPOSE 9000

# ── Start php-fpm ─────────────────────────────────────────────────────────────
CMD ["php-fpm"]
