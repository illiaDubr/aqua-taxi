# СТЕЙДЖ 1: Сборка фронтенда
FROM node:18 AS frontend

WORKDIR /app
COPY package*.json ./
RUN npm install
COPY . .
RUN npm run build

# СТЕЙДЖ 2: PHP + Composer + готовый фронт
FROM php:8.2-fpm

RUN apt-get update && apt-get install -y \
    git unzip zip curl libzip-dev libpng-dev libonig-dev libxml2-dev \
    && docker-php-ext-install pdo_mysql mbstring zip bcmath

# Установка composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Копируем проект
WORKDIR /var/www/html
COPY . .

# Копируем собранный фронт
COPY --from=frontend /app/public/build ./public/build

# Установка PHP зависимостей
RUN composer install --no-dev --optimize-autoloader

CMD ["php-fpm"]
