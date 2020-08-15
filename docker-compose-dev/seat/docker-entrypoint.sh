#!/bin/bash

# The SeAT Development Container Entrypoint
#
# This script invokes logic depending on the specific service
# command given. The first argument to the script should be
# provided by the `command:` directive in the compose file.

set -e

if ! [[ "$1" =~ ^(web|worker|cron)$ ]]; then
    echo "Usage: $0 [service]"
    echo " Services can be web; worker; cron"
    exit 1
fi

# Wait for MySQL
while ! mysqladmin ping -hmariadb -u$MYSQL_USER -p$MYSQL_PASSWORD --silent; do
    echo "MariaDB container might not be ready yet... sleeping..."
    sleep 3
done

# Wait for Redis
while ! redis-cli -h redis ping; do
    echo "Redis container might not be ready yet... sleeping..."
    sleep 3
done

# start_web_service
#
# this function gets the container ready to start apache.
function start_web_service() {

    # Ensure we have vendor/ ready. If not it's install time!
    if [ ! -f /var/www/seat/vendor/autoload.php ]; then

        # fix up permissions for the storage directory
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

    # publish new assets
    php artisan vendor:publish --force --all
    php artisan migrate

    # Regenerate API documentation
    php artisan l5-swagger:generate

    apache2-foreground
}

# start_worker_service
#
# this function gets the container ready to process jobs.
# it will wait for the source directory to complete composer
# installation before starting up.
function start_worker_service() {

    # Ensure we have vendor/ ready
    while [ ! -f /var/www/seat/vendor/autoload.php ]
    do
        echo "SeAT App container might not be ready yet... sleeping..."
        sleep 30
    done

    # fix up permissions for the storage directory
    chown -R www-data:www-data storage

    php artisan horizon
}

# start_cron_service
#
# this function gets the container ready to process the cron schedule.
# it will wait for the source directory to complete composer
# installation before starting up.
function start_cron_service() {

    # Ensure we have vendor/ ready
    while [ ! -f /var/www/seat/vendor/autoload.php ]
    do
        echo "SeAT App container might not be ready yet... sleeping..."
        sleep 30
    done

    echo "starting 'cron' loop"

    while :
    do
        php /var/www/seat/artisan schedule:run &
        sleep 60
    done
}

case $1 in
    web)
        echo "starting web service"
        start_web_service
        ;;
    worker)
        echo "starting workers via horizon"
        start_worker_service
        ;;
    cron)
        echo "starting cron service"
        start_cron_service
        ;;
esac
