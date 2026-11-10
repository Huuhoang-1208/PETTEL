FROM php:8.3-fpm-bookworm AS backend

RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip libjpeg62-turbo-dev libpng-dev libwebp-dev libicu-dev libzip-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install pdo_mysql pcntl opcache gd intl zip \
    && pecl install redis-6.2.0 \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

RUN printf 'upload_max_filesize=12M\npost_max_size=13M\nmemory_limit=192M\n' > /usr/local/etc/php/conf.d/uploads.ini

# Forward only application configuration variables to PHP-FPM workers.
RUN printf '%s\n' \
    'env[APP_NAME] = $APP_NAME' \
    'env[APP_ENV] = $APP_ENV' \
    'env[APP_KEY] = $APP_KEY' \
    'env[APP_DEBUG] = $APP_DEBUG' \
    'env[APP_URL] = $APP_URL' \
    'env[APP_LOCALE] = $APP_LOCALE' \
    'env[APP_PERFORMANCE_METRICS] = $APP_PERFORMANCE_METRICS' \
    'env[LOG_CHANNEL] = $LOG_CHANNEL' \
    'env[LOG_LEVEL] = $LOG_LEVEL' \
    'env[DB_CONNECTION] = $DB_CONNECTION' \
    'env[DB_HOST] = $DB_HOST' \
    'env[DB_PORT] = $DB_PORT' \
    'env[DB_DATABASE] = $DB_DATABASE' \
    'env[DB_USERNAME] = $DB_USERNAME' \
    'env[DB_PASSWORD] = $DB_PASSWORD' \
    'env[DB_URL] = $DB_URL' \
    'env[CACHE_STORE] = $CACHE_STORE' \
    'env[SESSION_DRIVER] = $SESSION_DRIVER' \
    'env[QUEUE_CONNECTION] = $QUEUE_CONNECTION' \
    'env[FILESYSTEM_DISK] = $FILESYSTEM_DISK' \
    'env[REDIS_CLIENT] = $REDIS_CLIENT' \
    'env[REDIS_HOST] = $REDIS_HOST' \
    'env[REDIS_PORT] = $REDIS_PORT' \
    'env[REDIS_PASSWORD] = $REDIS_PASSWORD' \
    'env[REDIS_QUEUE_RETRY_AFTER] = $REDIS_QUEUE_RETRY_AFTER' \
    'env[MAIL_MAILER] = $MAIL_MAILER' \
    'env[FRONTEND_URL] = $FRONTEND_URL' \
    'env[SANCTUM_STATEFUL_DOMAINS] = $SANCTUM_STATEFUL_DOMAINS' \
    'env[SESSION_DOMAIN] = $SESSION_DOMAIN' \
    'env[SESSION_SECURE_COOKIE] = $SESSION_SECURE_COOKIE' \
    'env[BCRYPT_ROUNDS] = $BCRYPT_ROUNDS' \
    > /usr/local/etc/php-fpm.d/zz-pettel-env.conf

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-progress --no-scripts
COPY backend/ ./
RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && composer dump-autoload --optimize \
    && chown -R www-data:www-data storage bootstrap/cache

FROM nginx:1-alpine AS frontend
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=backend /var/www/html/public /var/www/html/public
