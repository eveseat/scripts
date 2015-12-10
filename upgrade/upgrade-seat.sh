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

read -p "Where is your SeAT installation ? " seatpath

if [ ! -d "$seatpath" ]; then
	echo "The specified SeAT installation path '$seatpath' does not exist."
	echo "SeAT Auto Upgrader will now exit."
	exit 1
fi

echo " * Changing directories to '$seatpath'"
cd $seatpath
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
echo " * Taking SeAT out of maintenance mode"
php artisan up
echo " * Done"
