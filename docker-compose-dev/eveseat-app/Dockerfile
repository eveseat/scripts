FROM php:7.3-fpm-alpine

RUN apk add --no-cache git zip unzip curl libzip-dev g++ gcc make \
    libpng-dev bzip2-dev icu-dev mariadb-client gmp gmp-dev autoconf \
    freetype freetype-dev libjpeg-turbo libjpeg-turbo-dev && \
    pecl install redis && \
    docker-php-ext-configure zip --with-libzip=/usr/include && \
    docker-php-ext-configure gd \
        --with-freetype-dir=/usr/lib/ \
        --with-png-dir=/usr/lib/ \
        --with-jpeg-dir=/usr/lib/ \
        --with-gd && \
    docker-php-ext-install pdo_mysql gd bz2 intl pcntl gmp zip opcache && \
    docker-php-ext-enable redis
    
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin \
    --filename=composer && hash -r

COPY startup.sh /root/startup.sh
RUN chmod +x /root/startup.sh

VOLUME /var/www/seat
WORKDIR /var/www/seat

CMD ["php-fpm", "-F"]

ENTRYPOINT ["/bin/sh", "/root/startup.sh"]
