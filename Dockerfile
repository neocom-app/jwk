FROM php:7.4-fpm

WORKDIR /var/www/html

RUN apt-get update && \
    apt-get install -y build-essential git libgmp-dev libonig-dev libzip-dev gnupg locales zip unzip && \
    apt-get clean && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install gmp mbstring pdo pdo_mysql zip && \
    pecl install redis && \
    docker-php-ext-enable redis

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN groupadd -g 1000 www && \
    useradd -u 1000 -ms /bin/bash -g www www

USER www

COPY composer.json composer.lock ./
RUN composer install --prefer-dist --no-dev --no-plugins --no-scripts

COPY . .
COPY --chown=www:www . .

RUN rm bootstrap/cache/services.php && \
    rm bootstrap/cache/packages.php

RUN php artisan package:discover --ansi

EXPOSE 9000
CMD ["php-fpm"]
