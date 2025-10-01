# ---- Frontend build (Vite) ----
FROM node:20-bookworm-slim AS frontend-builder
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
# Сборка фронта в /app/public/build (на выходе манифест и ассеты)
RUN npm run build

# ---- PHP + Laravel ----
FROM php:8.2-fpm

# Системные зависимости
RUN apt-get update && apt-get install -y \
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

# Prod зависимости PHP
RUN composer install --no-dev --optimize-autoloader \
 && php artisan config:clear \
 && php artisan route:clear \
 && php artisan view:clear \
 && chown -R www-data:www-data /var/www

EXPOSE 9000
CMD ["php-fpm"]
