#!/bin/sh

cd /var/www/seat

# Ensure we have vendor/ ready
while [ ! -f /var/www/seat/vendor/autoload.php ]
do
    echo "SeAT might not be ready yet... sleeping for 20 seconds"
    sleep 20
done

php artisan horizon
