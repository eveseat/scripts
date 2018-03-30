#!/bin/sh

cd /var/www/seat

# Ensure we have vendor/ ready
if [ ! -f /var/www/seat/vendor/autoload.php ]; then

    composer install
fi

# Ensure we have .env
if [ ! -f /var/www/seat/.env ]; then

    cp /var/www/seat/.env.example /var/www/seat/.env

    # Fix up MariaDB and Redis connection info
    sed -i -- 's/DB_PASSWORD=secret/DB_PASSWORD=seat/g' .env
    sed -i -- 's/APP_DEBUG=false/APP_DEBUG=true/g' .env
    sed -i -- 's/DB_HOST=127.0.0.1/DB_HOST=mariadb/g' .env
    sed -i -- 's/REDIS_HOST=127.0.0.1/REDIS_HOST=redis/g' .env

    # Generate an app key
    php artisan key:generate
fi

# publish new assets
php artisan vendor:publish --force --all
php artisan migrate

php-fpm -F
