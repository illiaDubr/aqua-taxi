FROM node:20-bookworm-slim AS frontend-builder
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
# Сборка фронта в /app/public/build (на выходе манифест и ассеты)
RUN npm run build

# ---- PHP + Laravel ----
FROM php:8.2-fpm

# Установка зависимостей
# Системные зависимости
RUN apt-get update && apt-get install -y \
    zip unzip curl git nano vim bash \
    libzip-dev libpng-dev libonig-dev libxml2-dev libpq-dev \
    && docker-php-ext-install pdo_mysql zip \
    libpng-dev libonig-dev libxml2-dev \
    libzip-dev zip unzip curl git \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip \
    && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Рабочая директория
WORKDIR /var/www

# Скопируем код (кроме игнорируемого .dockerignore)
COPY . .

# Перенесём собранные Vite-ассеты
COPY --from=frontend-builder /app/public/build /var/www/public/build

# Установка Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
# Prod зависимости PHP
RUN composer install --no-dev --optimize-autoloader \
 && php artisan config:clear \
 && php artisan route:clear \
 && php artisan view:clear \
 && chown -R www-data:www-data /var/www

WORKDIR /var/www/html
EXPOSE 9000
CMD ["php-fpm"]
