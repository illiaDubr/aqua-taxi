# ===== СТЕЙДЖ 1: Сборка фронтенда =====
FROM node:18 as frontend

WORKDIR /app
COPY package*.json ./
RUN npm install
COPY . .
RUN npm run build

# ===== СТЕЙДЖ 2: PHP + готовый build =====
FROM php:8.2-fpm

# Установка зависимостей
RUN apt-get update && apt-get install -y \
    git unzip zip curl libzip-dev libpng-dev libonig-dev libxml2-dev \
    && docker-php-ext-install pdo_mysql mbstring zip bcmath

# Установка Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Копирование проекта
WORKDIR /var/www/html
COPY . .

# 🔥 ВАЖНО: скопировать build из frontend
COPY --from=frontend /app/public/build ./public/build

# Установка PHP зависимостей
RUN composer install --no-dev --optimize-autoloader

CMD ["php-fpm"]
