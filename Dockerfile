FROM php:8.3-cli
RUN apt-get update && apt-get install -y git unzip libzip-dev libicu-dev libsqlite3-dev && docker-php-ext-install pdo_mysql pdo_sqlite zip intl
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint
ENTRYPOINT ["entrypoint"]
CMD ["php","artisan","serve","--host=0.0.0.0","--port=8000"]
