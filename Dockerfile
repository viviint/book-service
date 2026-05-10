FROM php:8.3-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    libcurl4-openssl-dev \
    libicu-dev \
    libonig-dev \
    libzip-dev \
    && docker-php-ext-install bcmath curl intl mbstring opcache pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

COPY . ./
COPY docker-entrypoint.sh /usr/local/bin/laravel-entrypoint

RUN composer dump-autoload --optimize \
    && chmod +x /usr/local/bin/laravel-entrypoint \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

EXPOSE 8002

ENTRYPOINT ["laravel-entrypoint"]
CMD ["sh", "-c", "php artisan serve --host=0.0.0.0 --port=${APP_PORT:-8002}"]
