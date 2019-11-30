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
EPEL=epel-release-latest-7.noarch.rpm && curl -O https://dl.fedoraproject.org/pub/epel/$EPEL && yum localinstall -y $EPEL && rm -f $EPEL

echo " * Configuring EPEL GPG"
rpm --import "http://download.fedoraproject.org/pub/epel/RPM-GPG-KEY-EPEL-7"

echo " * Installing Remi Repository"
REMI=remi-release-7.rpm && curl -O http://rpms.remirepo.net/enterprise/$REMI && yum localinstall -y $REMI && rm -f $REMI

echo " * Configuring Remi GPG"
rpm --import http://rpms.remirepo.net/RPM-GPG-KEY-remi

echo " * Installing MariaDB 10.2 Repository"
cat <<EOT >> /etc/yum.repos.d/MariaDB.repo
# MariaDB 10.2 CentOS repository list - created 2018-05-10 21:43 UTC
# http://downloads.mariadb.org/mariadb/repositories/
[mariadb]
name = MariaDB
baseurl = http://yum.mariadb.org/10.2/centos7-amd64
gpgkey=https://yum.mariadb.org/RPM-GPG-KEY-MariaDB
gpgcheck=1
EOT

echo " * Configuring MariaDB 10.2 GPG"
rpm --import https://yum.mariadb.org/RPM-GPG-KEY-MariaDB

echo " * Enabling Remi PHP 7.3 repository"
yum install yum-utils -y
yum-config-manager --enable remi-php73

echo " * Running yum clean all"
yum clean all

echo " * Installing installer dependencies"
yum install -y php-cli php-mysql php-posix git unzip

echo " * Installing SeAT tool"
curl -fsSL https://git.io/vXb0u -o /usr/local/bin/seat
chmod +x /usr/local/bin/seat
hash -r

echo " * Installer download complete!"
echo " * You can now run the seat tool by just running the 'seat' command."
echo " * Start installing SeAT with: seat install:production"
