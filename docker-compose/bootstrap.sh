#!/bin/bash

# This script attempts to bootstrap an environment, ready to
# use as a docker installation for SeAT.

# 2018 @leonjza

SEAT_DOCKER_INSTALL=/opt/seat-docker

set -e

# Running as root?
if (( $EUID != 0 )); then

    echo "Please run as root"
    exit
fi

# Have curl?
if ! [ -x "$(command -v curl)" ]; then

    echo "curl is not installed."
    exit 1
fi

# Have docker?
if ! [ -x "$(command -v docker)" ]; then

    echo "Docker is not installed. Installing..."

    sh <(curl -fsSL get.docker.com)

    echo "Docker installed"
fi

# Have docker-compose?
if ! [ -x "$(command -v docker-compose)" ]; then

    echo "docker-compose is not installed. Installing..."

    curl -L https://github.com/docker/compose/releases/download/1.21.0/docker-compose-$(uname -s)-$(uname -m) -o /usr/local/bin/docker-compose
    chmod +x /usr/local/bin/docker-compose

    echo "docker-compose installed"
fi

# Make sure /opt/seat-docker exists
echo "Ensuring $SEAT_DOCKER_INSTALL is available..."
mkdir -p $SEAT_DOCKER_INSTALL
cd $SEAT_DOCKER_INSTALL

echo "Grabbing docker-compose and .env file"
curl -L https://raw.githubusercontent.com/eveseat/scripts/master/docker-compose/docker-compose.yml -o $SEAT_DOCKER_INSTALL/docker-compose.yml
curl -L https://raw.githubusercontent.com/eveseat/scripts/master/docker-compose/.env -o $SEAT_DOCKER_INSTALL/.env
curl -L https://raw.githubusercontent.com/eveseat/scripts/master/docker-compose/my.cnf -o $SEAT_DOCKER_INSTALL/my.cnf

echo "Generating a random database password and writing it to the .env file."
sed -i -- 's/DB_PASSWORD=i_should_be_changed/DB_PASSWORD='$(head /dev/urandom | tr -dc A-Za-z0-9 | head -c22 ; echo '')'/g' .env
echo "Generating an application key and writing it to the .env file."
sed -i -- 's/APP_KEY=insecure/APP_KEY='$(head /dev/urandom | tr -dc A-Za-z0-9 | head -c32 ; echo '')'/g' .env
echo ""
echo "Please provide a valid e-mail address. It will be used to register an account against Let's Encrypt."
echo "For more information, see : https://letsencrypt.org"
read -p "e-mail address: " acme_email
sed -i -- 's/ACME_EMAIL=change_me@example.com/ACME_EMAIL='$acme_email'/g' .env
echo ""

echo "Please provide the domain from which SeAT have to be reachable (the base address, without slash or http(s) parts)"
echo "ie: seat.yourdomain.com"
read -p "domain: " host_address

sed -i -- 's/HOST=change.me/HOST='$host_address'/g' .env
sed -i -- 's/APP_URL=https:\/\/change.me/APP_URL=https:\/\/'$host_address'/g' .env
sed -i -- 's/EVE_CALLBACK_URL=https:\/\/seat.yourdomain.com\/auth\/eve\/callback/EVE_CALLBACK_URL=https:\/\/'$host_address'\/auth\/eve\/callback/g' .env

echo ""
echo "Starting docker stack. This will download the images too. Please wait..."

docker-compose up -d

echo ""
echo "Images downloaded. The containers are now initialising. To check what is happening, run 'docker-compose logs --tail 5 -f' in /opt/seat-docker"
echo ""
echo "Done!"
