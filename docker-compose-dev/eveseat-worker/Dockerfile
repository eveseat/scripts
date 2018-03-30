FROM php:7.1-fpm-alpine

RUN apk add --no-cache git zip unzip curl \
    libpng-dev libmcrypt-dev bzip2-dev icu-dev && \
    docker-php-ext-install pdo_mysql gd bz2 intl mcrypt pcntl mariadb-client

VOLUME /var/www
WORKDIR /var/www/seat

ENTRYPOINT ["php", "artisan", "horizon"]
