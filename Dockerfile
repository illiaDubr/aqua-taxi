# ---------- Frontend build (Vite) ----------
FROM node:20-bookworm-slim AS frontend-builder
WORKDIR /app

COPY package*.json ./
RUN npm ci

COPY . .
# Сборка фронта в public/build + проверка наличия манифеста
RUN npm run build
RUN test -f public/build/manifest.json

# ---------- PHP + Laravel ----------
FROM php:8.2-fpm

ARG DEBIAN_FRONTEND=noninteractive

# Системные зависимости для расширений и утилит
RUN apt-get update && apt-get install -y \
    git curl zip unzip nano vim bash \
    libzip-dev libpng-dev libjpeg-dev libfreetype6-dev \
    libonig-dev libxml2-dev libpq-dev \
 && rm -rf /var/lib/apt/lists/*

# PHP extensions
# Важно: сначала сконфигурировать gd с jpeg/freetype
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j$(nproc) \
    pdo_mysql mbstring exif pcntl bcmath gd zip

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Код приложения
COPY . .

# Переносим собранные ассеты из фронтенд-стадии
COPY --from=frontend-builder /app/public/build /var/www/public/build

# Установка PHP-зависимостей и очистка кэшей
RUN composer install --no-dev --optimize-autoloader --no-interaction \
 && php artisan config:clear \
 && php artisan route:clear \
 && php artisan view:clear \
 && chown -R www-data:www-data /var/www

EXPOSE 9000
CMD ["php-fpm"]
