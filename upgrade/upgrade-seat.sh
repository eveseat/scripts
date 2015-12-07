echo " * SeAT Auto Upgrader"
echo
echo "Be sure to read the source before continuing if you are unsure."
echo

read -p "Are you sure you want to continue? (y/n) " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]
then
    exit 1
fi
echo

set -e

echo " * Changing directories to /var/www/seat"
cd /var/www/seat
echo " * Putting SeAT into maintenance mode"
php artisan down
echo " * Updating composer itself"
composer self-update
echo " * Updating SeAT packages"
composer update
echo " * Publishing vendor directories"
php artisan vendor:publish --force
echo " * Running any database migrations"
php artisan migrate
echo " * Taking SeAT out of maintenance mode"
php artisan up
echo " * Done"

