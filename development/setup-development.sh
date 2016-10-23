echo " + SeAT Development Setup"
echo
echo "This script will setup a simple development ready environment for SeAT."
echo "It does not setup any Web Servers or Databases. Only the folder structure."
echo
echo "Edit the scripts' variables to match your forked repositories if needed."
echo

# Die if there are errors.
set -e

# -- Configurations --
#
# Directories
INSTALL_DIR="seat-development"
PACKAGES_DIR="packages/eveseat"

# Composer Json Location
DEV_COMPOSER_JSON="https://raw.githubusercontent.com/eveseat/scripts/master/development/composer.dev.json"

# Repositories Location
MAIN_SEAT_REPO="https://github.com/eveseat/seat.git"
API_REPO="https://github.com/eveseat/api.git"
CONSOLE_REPO="https://github.com/eveseat/console.git"
EVEAPI_REPO="https://github.com/eveseat/eveapi.git"
NOTIFICATIONS_REPO="https://github.com/eveseat/notifications.git"
SERVICES_REPO="https://github.com/eveseat/services.git"
WEB_REPO="https://github.com/eveseat/web.git"

# -- Setup --
#
# Get the current directory. We will use this as a reference
# when we need to move around the file system
CURRENT_DIRECTORY=`pwd`

# Check that the prereqs of this script is met. We cant continue
# without git and composer being available to run.
prereqs=(curl git composer)

for i in "${prereqs[@]}"; do
   command -v $i >/dev/null 2>&1 || { echo \
    "I require $i but it's not installed. Aborting." >&2; exit 1; }
done

# Clone the main repo and get the composer.json that is
# ready for development.
echo " + Cloning Main Repository"
git clone $MAIN_SEAT_REPO $INSTALL_DIR
cd $INSTALL_DIR

echo " + Downloading Development Composer Json"
curl -fsSL $DEV_COMPOSER_JSON -o composer.json

# Create the packages directory and move to it
echo " + Configuring and Cloning Packages"
mkdir -p $PACKAGES_DIR
cd $PACKAGES_DIR

# Clone the rest of the repositories
git clone $API_REPO
git clone $CONSOLE_REPO
git clone $EVEAPI_REPO
git clone $NOTIFICATIONS_REPO
git clone $SERVICES_REPO
git clone $WEB_REPO

# Move back to the start directory and then the install
# directory and start the composer install.
cd $CURRENT_DIRECTORY
cd $INSTALL_DIR

echo " + Installing Dependencies"
composer install

echo " + Configurating .env File"
cp .env.example .env
sed -i -r 's/APP_DEBUG=false/APP_DEBUG=true/' .env
php artisan key:generate

echo " + Installation to $INSTALL_DIR is done!"

