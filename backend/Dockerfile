FROM php:8.3-fpm-bookworm AS backend

RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip libjpeg62-turbo-dev libpng-dev libwebp-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install pdo_mysql pcntl opcache gd \
    && pecl install redis-6.2.0 \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

RUN printf 'upload_max_filesize=12M\npost_max_size=13M\nmemory_limit=192M\n' > /usr/local/etc/php/conf.d/uploads.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-progress --no-scripts
COPY backend/ ./
RUN composer dump-autoload --optimize \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

FROM nginx:1-alpine AS frontend
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=backend /var/www/html/public /var/www/html/public
