# Сборка фронтенда (vite build)
FROM node:18 as node

WORKDIR /app

COPY package*.json ./
RUN npm install

COPY . .
RUN npm run build


# PHP контейнер с Composer
FROM php:8.2-fpm

WORKDIR /var/www/html

# Установка зависимостей PHP
RUN apt-get update && apt-get install -y \
    git unzip zip curl libzip-dev libpng-dev libonig-dev libxml2-dev \
    && docker-php-ext-install pdo_mysql mbstring zip bcmath

# Установка Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Копируем проект и билд
COPY . .
COPY --from=node /app/public/build ./public/build

# Установка зависимостей Laravel
RUN composer install --optimize-autoloader --no-dev \
    && php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache

CMD ["php-fpm"]
