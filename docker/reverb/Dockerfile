FROM php:8.2-cli

# Установка зависимостей
RUN apt-get update && apt-get install -y \
    git unzip curl libcurl4-openssl-dev libevent-dev \
    && docker-php-ext-install sockets pcntl

# Composer
RUN curl -s https://getcomposer.org/installer | php && mv composer.phar /usr/local/bin/composer

# Установка Laravel + Reverb
WORKDIR /var/www
RUN composer create-project --prefer-dist laravel/laravel reverb-app && \
    cd reverb-app && \
    composer require laravel/reverb

WORKDIR /var/www/reverb-app

# Запуск на 6001 порту
CMD ["php", "artisan", "reverb:start", "--port=6001"]
