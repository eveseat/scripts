echo " * SeAT Ubuntu 16.04.x Auto Installer"
echo
echo "Warning: This script will install SeAT onto this server."
echo "It assumes that this is pretty much a fresh install of Ubuntu 16.04.x."
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

# Stop if any errors occur
set -e

# Generate some data that we need to work with
echo " * Generating Passwords for the MySQL root user and SeAT DB user"
echo
MYSQL_ROOT_PASS=$(< /dev/urandom tr -dc _A-Z-a-z-0-9 | head -c36)
SEAT_DB_PASS=$(< /dev/urandom tr -dc _A-Z-a-z-0-9 | head -c36)

# Work from roots home
cd /root/

# Make Ubuntu not ask any questions
export DEBIAN_FRONTEND=noninteractive

echo " * Updating Package Repositories and Packages"
apt update
apt upgrade -y

# Install MySQL. expect is installed to automate the mysql_secure_installation
echo " * Installing MySQL Server"
echo
apt install mysql-server expect -y

echo " * Running mysql_secure_installation"
echo
SECURE_MYSQL=$(expect -c "
set timeout 10
spawn mysql_secure_installation
expect \"Press y|Y for Yes, any other key for No:\"
send \"n\r\"
expect \"New password:\"
send \"$MYSQL_ROOT_PASS\r\"
expect \"Re-enter new password:\"
send \"$MYSQL_ROOT_PASS\r\"
expect \"Remove anonymous users? (Press y|Y for Yes, any other key for No) :\"
send \"y\r\"
expect \"Disallow root login remotely? (Press y|Y for Yes, any other key for No) :\"
send \"y\r\"
expect \"Remove test database and access to it? (Press y|Y for Yes, any other key for No) :\"
send \"y\r\"
expect \"Reload privilege tables now? (Press y|Y for Yes, any other key for No) :\"
send \"y\r\"
expect eof
")
echo "$SECURE_MYSQL"

echo " * Running mysql_config_editor"
echo
CONFIG_EDITOR=$(expect -c "
set timeout 10
spawn mysql_config_editor set --login-path=seatinstall --host=localhost --user=root --password
expect \"Enter password:\"
send \"$MYSQL_ROOT_PASS\r\"
expect eof
")
echo "$CONFIG_EDITOR"

echo " * Creating SeAT Database and configuring access"
echo
mysql --login-path=seatinstall -e "create database seat;"
mysql --login-path=seatinstall -e "GRANT ALL ON seat.* to seat@localhost IDENTIFIED BY '$SEAT_DB_PASS';"

echo " * Saving credentials to /root/seat-install-creds"
echo "MySQL Root Pass: $MYSQL_ROOT_PASS" > /root/seat-install-creds
echo "SeAT User Pass:  $SEAT_DB_PASS" >> /root/seat-install-creds
echo

echo " * Clearing the MySQL login file"
echo
mysql_config_editor reset

echo " * Setting up PHP & Apache"
echo
apt install apache2 php php-cli php-mcrypt php-intl php-mysql php-curl php-gd php-mbstring php-bz2 libapache2-mod-php php-dom -y

echo " * Setting up Redis"
echo
apt install redis-server -y

echo " * Setting up Composer, Git & Unzip"
echo
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer && hash -r
apt install git unzip -y

echo " * Getting SeAT Setup"
echo
cd /var/www
composer create-project eveseat/seat seat --keep-vcs --no-dev

echo " * Configuring Permissions"
echo
chown -R www-data:www-data /var/www/seat
chmod -R guo+w /var/www/seat/storage/

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
apt install supervisor -y

echo " * Configuring Supervisor for 4 workers"
echo
cat >>/etc/supervisor/conf.d/seat.conf <<EOL
[program:seat]
command=/usr/bin/php /var/www/seat/artisan queue:work --queue=high,medium,low,default --tries 1 --timeout=86100
process_name = %(program_name)s-80%(process_num)02d
stdout_logfile = /var/log/seat-80%(process_num)02d.log
stdout_logfile_maxbytes=100MB
stdout_logfile_backups=10
numprocs=4
directory=/var/www/seat
stopwaitsecs=600
user=www-data
EOL

echo " * Restarting supervisor and checking status"
echo
service supervisor restart
sleep 1
supervisorctl status

echo " * Ensuring supervisor starts on boot"
systemctl enable supervisor.service

echo " * Adding crontab entry"
echo
TMP_TAB=$(mktemp)
set +e  # Temporarily stop the errexit option for the crontab listing
crontab -u www-data -l > ${TMP_TAB}
set -e  # Restore errexit
echo "* * * * * /usr/bin/php /var/www/seat/artisan schedule:run 1>> /dev/null 2>&1" >> ${TMP_TAB}
crontab -u www-data ${TMP_TAB}
rm ${TMP_TAB}

echo " * Setting Up Apache Virtual Host"
echo
echo " * Disabling default page"
unlink /etc/apache2/sites-enabled/000-default.conf

echo " * Hardening Apache"
echo " * Disabling directory Indexing"
sed -i -r "s/Options Indexes FollowSymLinks/Options FollowSymLinks/" /etc/apache2/apache2.conf
echo " * Removing Server signature & Tokens"
sed -i -r "s/ServerTokens OS/ServerTokens Prod/" /etc/apache2/conf-enabled/security.conf
sed -i -r "s/ServerSignature On/ServerSignature Off/" /etc/apache2/conf-enabled/security.conf

cat >>/etc/apache2/sites-available/100-seat.local.conf <<EOL
<VirtualHost *:80>
    ServerAdmin webmaster@your.domain
    DocumentRoot "/var/www/html/seat.local"
    ServerName seat.local
    ServerAlias www.seat.local
    ErrorLog /var/log/apache2/seat.local-error.log
    CustomLog /var/log/apache2/seat.local-access.log combined
    <Directory "/var/www/html/seat.local">
        AllowOverride All
        Order allow,deny
        Allow from all
    </Directory>
</VirtualHost>
EOL

echo " * Linking new vhost to be enabled"
ln -s /var/www/seat/public /var/www/html/seat.local
ln -s /etc/apache2/sites-available/100-seat.local.conf /etc/apache2/sites-enabled/

echo " * Enabling mod_rewrite"
sudo a2enmod rewrite

apachectl restart
apachectl -t -D DUMP_VHOSTS

echo
echo " ** Done. Remember to set the admin password with: php artisan seat:admin:reset"
echo "    and the administrator email with php artisan seat:admin:email"
echo
