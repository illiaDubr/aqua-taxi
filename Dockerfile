# STAGE 1 — Node для сборки фронта
FROM node:18 as frontend

WORKDIR /app

COPY package*.json ./
RUN npm install
COPY . .
RUN npm run build

# STAGE 2 — PHP
FROM php:8.2-fpm

# Установка зависимостей PHP
RUN apt-get update && apt-get install -y \
    git unzip zip curl libzip-dev libpng-dev libonig-dev libxml2-dev \
    && docker-php-ext-install pdo_mysql mbstring zip bcmath

# Установка Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Копируем проект
WORKDIR /var/www/html
COPY . .

# Копируем собранные ассеты из Node stage
COPY --from=frontend /app/public/build ./public/build

# Устанавливаем зависимости PHP
RUN composer install --no-dev --optimize-autoloader

CMD ["php-fpm"]
