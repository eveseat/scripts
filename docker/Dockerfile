FROM debian:buster

MAINTAINER Leon Jacobs <leonja511@gmail.com>

# avoid debconf and initrd
ENV DEBIAN_FRONTEND noninteractive
ENV INITRD No

# install operating system dependencies
RUN apt-get update && \
    apt-get upgrade -y && \
    apt-get install -y --no-install-recommends \

    # dependencies for the php7 repo
    wget \
    apt-transport-https \
    lsb-release \
    ca-certificates && \

    # install seat dependencies
    apt-get update && \
    apt-get install -y --no-install-recommends \ 

    # seat - database
    mysql-server \
    expect \
    
    # php && nginx
    php \
    php-cli \
    php-intl \
    php-mysql \
    php-curl \
    php-gd \
    php-mbstring \
    php-bz2 \ 
    php-redis \
    php-xml \
    php-fpm \
    nginx \

    # redis
    redis-server \

    # supervisor as a process minitor
    supervisor \

    # other dependencies
    curl \
    unzip \
    git && \

    # cleanup time!
    apt-get clean && \
    rm -rf /var/cache/apt/archives/* /var/lib/apt/lists/*

# install composer
RUN curl -sS https://getcomposer.org/installer | \
    php -- --install-dir=/usr/local/bin --filename=composer 

# add the expect script to secure mysql and run it
ADD mysql/mysql_setup.sh /
RUN /mysql_setup.sh

# create the seat project
WORKDIR /
RUN composer create-project eveseat/seat seat --no-dev

# add the env
ADD seat/env /seat/.env

# set the application key
RUN cd /seat && php artisan key:generate

# add the setup script and run it
ADD seat/install.sh /seat/install.sh
RUN /seat/install.sh

# fix up the permissions needed for seat
RUN chown -R www-data:www-data /seat && \
    chmod -R guo+w /seat/storage/

# add the nginx configuration
ADD nginx/default /etc/nginx/sites-available/default

# configure php-fpm
RUN sed -i -e "s/;daemonize\s*=\s*yes/daemonize = no/g" /etc/php/7.3/fpm/php-fpm.conf
RUN sed -i "s/;cgi.fix_pathinfo=1/cgi.fix_pathinfo=0/" /etc/php/7.3/fpm/php.ini
RUN sed -i "s/listen = \/run\/php\/php7.3-fpm.sock/listen = \/var\/run\/php7.3-fpm.sock/" /etc/php/7.3/fpm/pool.d/www.conf
RUN sed -i "s/pid = \/run\/php\/php7.3-fpm.pid/pid = \/var\/run\/php7.3-fpm.pid/" /etc/php/7.3/fpm/php-fpm.conf

# add the supervisor configurations
ADD supervisor/mysql.conf /etc/supervisor/conf.d/
ADD supervisor/redis.conf /etc/supervisor/conf.d/
ADD supervisor/nginx.conf /etc/supervisor/conf.d/
ADD supervisor/php-fpm.conf /etc/supervisor/conf.d/

EXPOSE 80

CMD ["supervisord", "-n", "-c", "/etc/supervisor/supervisord.conf"]
