#!/bin/sh
set -e

cd /var/www/seat

# Ensure we have vendor/ ready
while [ ! -f /var/www/seat/vendor/autoload.php ]
do
    echo "SeAT App container might not be ready yet... sleeping..."
    sleep 30
done

/usr/sbin/crond -f -d 7
