# ---- Frontend build ----
FROM node:20-bookworm-slim AS frontend-builder
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build

# ---- PHP ----
FROM php:8.2-fpm

RUN apt-get update && apt-get install -y \
    libpng-dev libonig-dev libxml2-dev \
    libzip-dev zip unzip curl git \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# только копируем код
COPY . .

# копируем билд фронта
COPY --from=frontend-builder /app/public/build /var/www/public/build

RUN chown -R www-data:www-data /var/www

EXPOSE 9000
CMD ["php-fpm"]