FROM composer:2 AS composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-ansi \
    --no-progress \
    --no-scripts \
    --prefer-dist \
    --classmap-authoritative

COPY app app
COPY bootstrap bootstrap
COPY config config
COPY core core
COPY database database
COPY modules modules
COPY public public
COPY resources resources
COPY routes routes
COPY artisan .

RUN composer dump-autoload \
    --no-dev \
    --no-interaction \
    --no-ansi \
    --classmap-authoritative

FROM php:8.3-fpm-bookworm AS runtime

RUN apt-get update \
    && apt-get install -y --no-install-recommends default-mysql-client libicu-dev libzip-dev \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install pdo_mysql intl zip \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

WORKDIR /var/www/html

COPY --from=composer --chown=www-data:www-data /app /var/www/html
COPY --chown=root:root docker/entrypoint.sh /usr/local/bin/entrypoint

RUN chmod 0755 /usr/local/bin/entrypoint \
    && mkdir -p \
        storage/app/private \
        storage/app/public \
        storage/app/temp \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && ln -s ../storage/app/public public/storage

USER www-data

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm", "-F"]

FROM caddy:2.8-alpine AS web

COPY docker/caddy/Caddyfile /etc/caddy/Caddyfile
COPY --from=composer /app/public /var/www/html/public

RUN rm -f /var/www/html/public/storage \
    && ln -s ../storage/app/public /var/www/html/public/storage

FROM runtime AS development

USER root

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
