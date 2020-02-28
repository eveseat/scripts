FROM php:7.3-alpine

RUN apk add --no-cache git zip unzip curl libzip-dev g++ gcc make \
    libpng-dev bzip2-dev icu-dev mariadb-client gmp gmp-dev autoconf && \
    pecl install redis && \
    docker-php-ext-configure zip --with-libzip=/usr/include && \
    docker-php-ext-install pdo_mysql gd bz2 intl pcntl gmp zip && \
    docker-php-ext-enable redis

VOLUME /var/www
WORKDIR /var/www/seat

COPY startup.sh /root/startup.sh
RUN chmod +x /root/startup.sh

ENTRYPOINT ["/bin/sh", "/root/startup.sh"]
