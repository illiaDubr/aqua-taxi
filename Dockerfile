# Сборка фронта
FROM node:20 AS frontend-builder
WORKDIR /app
COPY package*.json ./
RUN npm install
COPY . .
RUN npm run build

# PHP + Laravel
FROM php:8.2-fpm

# Установим зависимости
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip unzip curl git \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Установим Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY --from=frontend-builder /app/public/build /var/www/public/build
COPY . .

RUN composer install --no-dev --optimize-autoloader \
    && php artisan config:clear \
    && php artisan route:clear \
    && php artisan view:clear

CMD ["php-fpm"]
