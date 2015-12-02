echo " * SeAT CentOS 6.x Auto Installer"
echo
echo "Warning: This script will install SeAT onto this server."
echo "It assumes that this is pretty much a fresh install of CentOS 6.x."
echo "It makes *no* attempt to detect existing installations / configurations."
echo "Be sure to read the source before continuing if you are unsure."
echo

read -p "Are you sure you want to continue? (y/n) " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]
then
    exit 1
fi
echo

if [ $EUID != 0 ]; then

	echo " * ERROR: This script should be run as root!"
    exit 1
fi

# Generate some data that we need to work with
MYSQL_ROOT_PASS=$(echo -e `date` | md5sum | awk '{ print $1 }');
sleep 1
SEAT_DB_PASS=$(echo -e `date` | md5sum | awk '{ print $1 }');

# Stop if any errors occur
set -e

# Work from roots home
cd /root/

# Get Started
echo " * Installing EPEL Repository"
echo
EPEL=epel-release-latest-6.noarch.rpm && curl -O https://dl.fedoraproject.org/pub/epel/$EPEL && yum localinstall -y $EPEL && rm -f $EPEL

echo " * Configuring EPEL GPG"
echo
rpm --import http://download.fedoraproject.org/pub/epel/RPM-GPG-KEY-EPEL-6

echo " * Installing Remi Repository"
echo
REMI=remi-release-6.rpm && curl -O http://rpms.remirepo.net/enterprise/$REMI && yum localinstall -y $REMI && rm -f $REMI

echo " * Configuring Remi GPG"
echo
rpm --import http://rpms.remirepo.net/RPM-GPG-KEY-remi

echo " * Enabling Remi and php55 repository"
echo
yum install yum-utils -y
yum-config-manager --enable remi,remi-php55

# Install MySQL. expect is installed to automate the mysql_secure_installation
echo " * Installing MySQL Server"
echo
yum install mysql mysql-server expect -y
/etc/init.d/mysqld start
chkconfig mysqld on

echo " * Running mysql_secure_installation"
echo
SECURE_MYSQL=$(expect -c "
set timeout 10
spawn mysql_secure_installation
expect \"Enter current password for root (enter for none):\"
send \"\r\"
expect \"Change the root password?\"
send \"y\r\"
expect \"New password:\"
send \"$MYSQL_ROOT_PASS\r\"
expect \"Re-enter new password:\"
send \"$MYSQL_ROOT_PASS\r\"
expect \"Remove anonymous users?\"
send \"y\r\"
expect \"Disallow root login remotely?\"
send \"y\r\"
expect \"Remove test database and access to it?\"
send \"y\r\"
expect \"Reload privilege tables now?\"
send \"y\r\"
expect eof
")
echo "$SECURE_MYSQL"

echo " * Creating SeAT Database and configuring access"
echo
mysql -uroot -p$MYSQL_ROOT_PASS -e "create database seat;"
mysql -uroot -p$MYSQL_ROOT_PASS -e "GRANT ALL ON seat.* to seat@localhost IDENTIFIED BY '$SEAT_DB_PASS';"

echo " * Saving credentials to /root/seat-install-creds"
echo "MySQL Root Pass: $MYSQL_ROOT_PASS" > /root/seat-install-creds
echo "SeAT User Pass:  $SEAT_DB_PASS" >> /root/seat-install-creds
echo

echo " * Setting up PHP & Apache"
echo
yum install -y httpd php php-mysql php-cli php-mcrypt php-process php-mbstring php-intl php-dom
/etc/init.d/httpd start
chkconfig httpd on

echo " * Setting up Redis"
echo
yum install -y redis
/etc/init.d/redis start
chkconfig redis on

echo " * Setting up Composer & Git"
echo
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer && hash -r
yum install git -y

echo " * Getting SeAT Setup"
echo
cd /var/www
composer create-project eveseat/seat seat --keep-vcs --prefer-source

echo " * Configuring Permissions"
echo
chown -R apache:apache /var/www/seat
chmod -R guo+w /var/www/seat/storage/

echo " * Configuring SELinux"
echo
setsebool -P httpd_can_network_connect 1
restorecon -Rv /var/www/seat

echo " * Configuring SeAT itself"
echo
cd /var/www/seat
sed -i -r "s/DB_DATABASE=homestead/DB_DATABASE=seat/" /var/www/seat/.env
sed -i -r "s/DB_USERNAME=homestead/DB_USERNAME=seat/" /var/www/seat/.env
sed -i -r "s/DB_PASSWORD=secret/DB_PASSWORD=$SEAT_DB_PASS/" /var/www/seat/.env
sed -i -r "s/CACHE_DRIVER=file/CACHE_DRIVER=redis/" /var/www/seat/.env
sed -i -r "s/QUEUE_DRIVER=sync/QUEUE_DRIVER=redis/" /var/www/seat/.env

# Run artisan commands
php artisan vendor:publish
php artisan migrate
php artisan db:seed --class=Seat\\Services\\database\\seeds\\NotificationTypesSeeder
php artisan db:seed --class=Seat\\Services\\database\\seeds\\ScheduleSeeder
php artisan eve:update-sde -n

echo " * Setting Up Supervisor"
echo
yum install supervisor -y
/etc/init.d/supervisord start
chkconfig supervisord on

echo " * Configuring Supervisor for 4 workers"
echo
cat >>/etc/supervisord.conf <<EOL
[program:seat1]
command=/usr/bin/php /var/www/seat/artisan queue:listen --queue=high,medium,low,default --tries 10 --timeout=3600
directory=/var/www/seat
stopwaitsecs=600
user=apache
stdout_logfile=/var/log/seat_out.log
stdout_logfile_maxbytes=100MB
stdout_logfile_backups=10
stderr_logfile=/var/log/seat_err.log
stderr_logfile_maxbytes=100MB
stderr_logfile_backups=5

[program:seat2]
command=/usr/bin/php /var/www/seat/artisan queue:listen --queue=high,medium,low,default --tries 10 --timeout=3600
directory=/var/www/seat
stopwaitsecs=600
user=apache
stdout_logfile=/var/log/seat_out.log
stdout_logfile_maxbytes=100MB
stdout_logfile_backups=10
stderr_logfile=/var/log/seat_err.log
stderr_logfile_maxbytes=100MB
stderr_logfile_backups=5

[program:seat3]
command=/usr/bin/php /var/www/seat/artisan queue:listen --queue=high,medium,low,default --tries 10 --timeout=3600
directory=/var/www/seat
stopwaitsecs=600
user=apache
stdout_logfile=/var/log/seat_out.log
stdout_logfile_maxbytes=100MB
stdout_logfile_backups=10
stderr_logfile=/var/log/seat_err.log
stderr_logfile_maxbytes=100MB
stderr_logfile_backups=5

[program:seat4]
command=/usr/bin/php /var/www/seat/artisan queue:listen --queue=high,medium,low,default --tries 10 --timeout=3600
directory=/var/www/seat
stopwaitsecs=600
user=apache
stdout_logfile=/var/log/seat_out.log
stdout_logfile_maxbytes=100MB
stdout_logfile_backups=10
stderr_logfile=/var/log/seat_err.log
stderr_logfile_maxbytes=100MB
stderr_logfile_backups=5
EOL

supervisorctl reload
supervisorctl status

echo " * Adding crontab entry"
echo
line="* * * * * /usr/bin/php /var/www/seat/artisan schedule:run 1>> /dev/null 2>&1"
(crontab -u apache -l; echo "$line" ) | crontab -u apache -

echo " * Setting Up Apache Virtual Host"
mkdir /var/www/html/seat.local
ln -s /var/www/seat/public /var/www/html/seat.local/seat
cat >>/etc/httpd/conf.d/seat.local.conf <<EOL
<VirtualHost *:80>
    ServerAdmin webmaster@your.domain
    DocumentRoot "/var/www/html/seat.local"
    ServerName seat.local
    ServerAlias www.seat.local
    ErrorLog "logs/seat.local-error_log"
    CustomLog "logs/seat.local-access_log" common
    <Directory "/var/www/html/seat.local">
        AllowOverride All
        Order allow,deny
        Allow from all
    </Directory>
</VirtualHost>
EOL
apachectl restart
apachectl -t -D DUMP_VHOSTS

echo
echo " ** Done. Remember to set the admin password with: php artisan seat:admin:reset"
echo

