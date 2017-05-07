FROM php:7.0-apache

RUN apt-get update 

RUN apt-get install expect git zip unzip curl nano libpng-dev libbz2-dev libicu-dev libmcrypt-dev mysql-client supervisor cron -y
RUN docker-php-ext-install pdo_mysql gd bz2 intl mcrypt

WORKDIR /var/www
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer && hash -r
RUN composer create-project eveseat/seat seat
WORKDIR /var/www/seat
RUN composer update
RUN sed -i -- 's/DB_USERNAME=seat/DB_USERNAME=root/g' .env
RUN sed -i -- 's/DB_PASSWORD=secret/DB_PASSWORD=password/g' .env
RUN sed -i -- 's/APP_DEBUG=false/APP_DEBUG=true/g' .env
RUN sed -i -- 's/DB_HOST=127.0.0.1/DB_HOST=mysql/g' .env
RUN sed -i -- 's/REDIS_HOST=127.0.0.1/REDIS_HOST=redis/g' .env
RUN php artisan vendor:publish --force
RUN chmod 777 -R /var/www/seat

COPY provisions/seat_admin /root/seat_admin
COPY provisions/startup.sh /root/startup.sh
RUN chmod +x /root/startup.sh
RUN chmod +x /root/seat_admin

COPY provisions/apache_vhost /etc/apache2/sites-available/seat.conf
RUN a2dissite 000-default.conf
RUN a2ensite seat
RUN a2enmod rewrite
RUN apachectl restart

COPY provisions/supervisor_cfg /etc/supervisor/conf.d/seat.conf
RUN service supervisor restart

COPY provisions/crontab /root/crontab
RUN crontab -u www-data /root/crontab

CMD ["/bin/bash", "/root/startup.sh"]