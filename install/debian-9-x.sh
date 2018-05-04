#!/bin/bash
if [ $EUID != 0 ]; then

    echo " * ERROR: This script should be run as root!"
    exit 1
fi

# Stop if any errors occur
set -e

# Work from roots home
cd /root/

# Make Ubuntu not ask any questions
export DEBIAN_FRONTEND=noninteractive

echo " * Ensuring we have all the prerequisites for the SeAT tool installer"
apt install apt-transport-https lsb-release ca-certificates curl dirmngr -y

echo " * Adding packages.sury.org repostitory & GPG signing key"
sh -c 'echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list'
wget -O /etc/apt/trusted.gpg.d/php.gpg https://packages.sury.org/php/apt.gpg

echo " * Adding MariaDB repostitory & GPG signing key"
sh -c 'echo "deb [arch=amd64,i386,ppc64el] http://mirrors.ukfast.co.uk/sites/mariadb/repo/10.2/debian stretch main" > /etc/apt/sources.list.d/mariadb.list'
apt-key adv --recv-keys --keyserver keyserver.ubuntu.com 0xF1656F24C74CD1D8

echo " * Updating repolist"
apt update

echo " * Installing installer dependencies"
apt install php7.1-cli php7.1-mysql unzip git -y

echo " * Installing SeAT tool"
curl -fsSL https://git.io/vXb0u -o /usr/local/bin/seat
chmod +x /usr/local/bin/seat
hash -r

echo " * Installer download complete!"
echo " * You can now run the seat tool by just running the 'seat' command."
echo " * Start installing SeAT with: seat install:production"
