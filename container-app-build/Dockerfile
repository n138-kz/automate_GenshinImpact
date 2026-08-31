FROM php:8.5-fpm-alpine

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN apk --no-cache add \
    tree \
    jq \
    postgresql-dev \
    && docker-php-ext-install pdo_pgsql

RUN (delgroup $(getent group 983 | cut -d: -f1) || true) \
    && addgroup -g 983 -S docker \
    && addgroup www-data docker

WORKDIR /var/www/html
