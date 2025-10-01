FROM php:8.2-fpm

# Установка зависимостей
RUN apt-get update && apt-get install -y \
    zip unzip curl git nano vim bash \
    libzip-dev libpng-dev libonig-dev libxml2-dev libpq-dev \
    && docker-php-ext-install pdo_mysql zip

# Установка Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
