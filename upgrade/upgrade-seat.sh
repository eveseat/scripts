echo " * SeAT Auto Upgrader"
echo -en '\n'
echo "Be sure to read the source before continuing if you are unsure."
echo -en '\n'

read -p "Are you sure you want to continue? (y/n) " -n 1 -r
echo -en '\n'
if [[ ! $REPLY =~ ^[Yy]$ ]]
then
    exit 1
fi
echo -en '\n'

set -e

echo " * Changing directories to /var/www/seat"
cd /var/www/seat
echo " * Putting SeAT into maintenance mode"
php artisan down
echo " * Updating composer itself"
composer self-update
echo " * Updating SeAT packages"
composer update --no-dev
echo " * Publishing vendor directories"
php artisan vendor:publish --force
echo " * Running any database migrations"
php artisan migrate
echo " * Running the seeders"
php artisan db:seed --class=Seat\\Notifications\\database\\seeds\\ScheduleSeeder
php artisan db:seed --class=Seat\\Services\\database\\seeds\\NotificationTypesSeeder
php artisan db:seed --class=Seat\\Services\\database\\seeds\\ScheduleSeeder
echo " * Asking supervisor to restart all workers"
supervisorctl restart all
echo " * Taking SeAT out of maintenance mode"
php artisan up
echo " * Done"

