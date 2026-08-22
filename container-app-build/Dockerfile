FROM php:8.5-fpm-alpine

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN apk --no-cache add \
    tree \
    jq \
    postgresql-dev \
    && docker-php-ext-install pdo_pgsql

WORKDIR /var/www/html
