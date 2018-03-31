FROM php:7.1-fpm-alpine

RUN apk add --no-cache git zip unzip curl bash \
    libpng-dev libmcrypt-dev bzip2-dev icu-dev mariadb-client && \
    docker-php-ext-install pdo_mysql gd bz2 intl mcrypt pcntl
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin \
    --filename=composer && hash -r

COPY startup.sh /root/startup.sh
RUN chmod +x /root/startup.sh

VOLUME /var/www/seat
WORKDIR /var/www/seat

CMD ["php-fpm", "-F"]

ENTRYPOINT ["/bin/sh", "/root/startup.sh"]
