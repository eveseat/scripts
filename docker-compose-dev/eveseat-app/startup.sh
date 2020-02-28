#!/bin/sh
set -e

cd /var/www/seat

# Ensure we have vendor/ ready
if [ ! -f /var/www/seat/vendor/autoload.php ]; then

    chown -R www-data:www-data storage

    # Ensure we have .env, if we dont, try and fix it automatically
    if [ ! -f /var/www/seat/.env ]; then

        cp /var/www/seat/.env.example /var/www/seat/.env

        # Fix up MariaDB and Redis connection info
        sed -i -- 's/DB_USERNAME=seat/DB_USERNAME='$MYSQL_USER'/g' .env
        sed -i -- 's/DB_PASSWORD=secret/DB_PASSWORD='$MYSQL_PASSWORD'/g' .env
        sed -i -- 's/DB_DATABASE=seat/DB_DATABASE='$MYSQL_DATABASE'/g' .env
        sed -i -- 's/DB_HOST=127.0.0.1/DB_HOST=mariadb/g' .env
        sed -i -- 's/APP_DEBUG=false/APP_DEBUG=true/g' .env
        sed -i -- 's/REDIS_HOST=127.0.0.1/REDIS_HOST=redis/g' .env
    fi

    composer install
    php artisan key:generate

    # Publish assets and migrate the database
    php artisan vendor:publish --force --all
    php artisan migrate

    # seed the scheduler table
    php artisan db:seed --class=Seat\\Console\\database\\seeds\\ScheduleSeeder

    # Download the SDE
    php artisan eve:update:sde -n
fi

# Wait for the database
while ! mysqladmin ping -hmariadb -u$MYSQL_USER -p$MYSQL_PASSWORD --silent; do

    echo "MariaDB container might not be ready yet... sleeping..."
    sleep 3
done

# publish new assets
php artisan vendor:publish --force --all
php artisan migrate

# Regenerate API documentation
php artisan l5-swagger:generate

php-fpm -F
