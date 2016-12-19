#!/bin/bash
if [ $EUID != 0 ]; then

	echo " * ERROR: This script should be run as root!"
    exit 1
fi

# Stop if any errors occur
set -e

# Work from roots home
cd /root/

# Get Started
echo " * Installing EPEL Repository"
EPEL=epel-release-latest-6.noarch.rpm && curl -O https://dl.fedoraproject.org/pub/epel/$EPEL && yum localinstall -y $EPEL && rm -f $EPEL

echo " * Configuring EPEL GPG"
rpm --import http://download.fedoraproject.org/pub/epel/RPM-GPG-KEY-EPEL-6

echo " * Installing Remi Repository"
REMI=remi-release-6.rpm && curl -O http://rpms.remirepo.net/enterprise/$REMI && yum localinstall -y $REMI && rm -f $REMI

echo " * Configuring Remi GPG"
rpm --import http://rpms.remirepo.net/RPM-GPG-KEY-remi

echo " * Installing Ghettoforge Repository"
# Download and install the latest release
GF=gf-release-6-10.gf.el6.noarch.rpm && curl -O http://mirror.symnds.com/distributions/gf/el/6/gf/x86_64/$GF && yum localinstall -y $GF && rm -f $GF

echo " * Configuring Ghettoforge GPG"
# Import the GhettoForge signing keys
rpm --import http://mirror.symnds.com/distributions/gf/RPM-GPG-KEY-gf.el6

echo " * Enabling Repos"
yum install yum-utils -y
yum-config-manager --enable remi,remi-php70,gf-plus

echo " * Installing installer dependencies"
yum install -y php-cli php-mysql php-posix git unzip

echo " * Installing SeAT tool"
curl -fsSL https://git.io/vXb0u -o /usr/local/bin/seat
chmod +x /usr/local/bin/seat
hash -r

echo " * Installer download complete!"
echo " * You can now run the seat tool by just running the 'seat' command."
echo " * Start installing SeAT with: seat install:production"
