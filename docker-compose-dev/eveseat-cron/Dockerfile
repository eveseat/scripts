FROM php:7.1-alpine

RUN apk add --no-cache git zip unzip curl \
    libpng-dev libmcrypt-dev bzip2-dev icu-dev && \
    docker-php-ext-install pdo_mysql gd bz2 intl mcrypt pcntl

VOLUME /var/www
WORKDIR /var/www/seat

RUN touch crontab.tmp \
    && echo '* * * * * php /var/www/seat/artisan schedule:run' > crontab.tmp \
    && crontab crontab.tmp \
    && rm -rf crontab.tmp

CMD ["/usr/sbin/crond", "-f", "-d", "7"]
