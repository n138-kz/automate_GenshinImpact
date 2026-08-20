FROM php:8.5-fpm

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN apt-get update && apt-get install -y \
    tree \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
