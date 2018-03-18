#!/bin/sh

cd /var/www/seat

if [ ! -f /var/www/seat/vendor/autoload.php ]; then
    composer install

    #sed -i -- 's/DB_USERNAME=seat/DB_USERNAME=root/g' .env
    sed -i -- 's/DB_PASSWORD=secret/DB_PASSWORD=seat/g' .env
    sed -i -- 's/APP_DEBUG=false/APP_DEBUG=true/g' .env
    sed -i -- 's/DB_HOST=127.0.0.1/DB_HOST=mariadb/g' .env
    sed -i -- 's/REDIS_HOST=127.0.0.1/REDIS_HOST=redis/g' .env
else
    composer update
fi

# publish new assets
php artisan vendor:publish --force --all
php artisan migrate

php-fpm -F

