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

echo " * Ensuring ca-certificates & curl is installed and up to date"
apt-get install ca-certificates curl -y

echo " * Adding dotdeb repostitory"
echo "deb http://packages.dotdeb.org jessie all" >> /etc/apt/sources.list
echo "deb-src http://packages.dotdeb.org jessie all" >> /etc/apt/sources.list

echo " * Installing dotdeb GPG signing key"
wget https://www.dotdeb.org/dotdeb.gpg
apt-key add dotdeb.gpg
rm -f dotdeb.gpg

echo " * Updating repolist"
apt update

echo " * Installing installer dependencies"
apt install php-cli php-mysql unzip git -y

echo " * Installing SeAT tool"
curl -fsSL https://git.io/vXb0u -o /usr/local/bin/seat
chmod +x /usr/local/bin/seat
hash -r

echo " * Installer download complete!"
echo " * You can now run the seat tool by just running the 'seat' command."
echo " * Start installing SeAT with: seat install:production"
