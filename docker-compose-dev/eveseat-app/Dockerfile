FROM php:7.3-fpm-alpine

RUN apk update && \
    apk add --no-cache git zip unzip curl g++ make autoconf libpng-dev bzip2-dev icu-dev yaml-dev mariadb-client && \
	pecl install yaml && \
    docker-php-ext-install pdo_mysql gd bz2 intl pcntl && \
	docker-php-ext-enable yaml
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin \
    --filename=composer && hash -r

COPY startup.sh /root/startup.sh
RUN chmod +x /root/startup.sh

VOLUME /var/www/seat
WORKDIR /var/www/seat

CMD ["php-fpm", "-F"]

ENTRYPOINT ["/bin/sh", "/root/startup.sh"]
