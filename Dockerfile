FROM php:8.2-fpm

# Установка зависимостей PHP и системных библиотек
RUN apt-get update && apt-get install -y \
    build-essential \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    unzip \
    curl \
    git \
    nodejs \
    npm \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Установка Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Установка рабочей директории
WORKDIR /var/www/html

# Копирование кода
COPY . .

# Установка PHP-зависимостей
RUN composer install --optimize-autoloader --no-dev

# Установка Node-зависимостей
RUN npm install && npm run build

CMD ["php-fpm"]
