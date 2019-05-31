FROM php:7.3-alpine

RUN apk update && \
    apk add --no-cache git zip unzip curl g++ make autoconf libpng-dev bzip2-dev icu-dev yaml-dev && \
	pecl install yaml && \
    docker-php-ext-install pdo_mysql gd bz2 intl pcntl && \
	docker-php-ext-enable yaml

VOLUME /var/www
WORKDIR /var/www/seat

COPY startup.sh /root/startup.sh
RUN chmod +x /root/startup.sh

ENTRYPOINT ["/bin/sh", "/root/startup.sh"]
