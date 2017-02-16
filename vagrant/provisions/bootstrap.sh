#!/usr/bin/env bash

debconf-set-selections <<< 'mysql-server mysql-server/root_password password password'
debconf-set-selections <<< 'mysql-server mysql-server/root_password_again password password'
apt-get update
apt-get install apache2 zip unzip mysql-server expect php php-cli php-mcrypt php-intl php-mysql php-curl php-gd php-mbstring php-bz2 php-dom php-zip libapache2-mod-php redis-server curl git supervisor -y
echo "create database seat;" | mysql -uroot -ppassword
echo "GRANT ALL ON *.* to 'root'@'%' IDENTIFIED BY 'password';" | mysql -uroot -ppassword
echo "FLUSH PRIVILEGES;" | mysql -uroot -ppassword
sed -i -- 's/bind-address/# bind-address/g' /etc/mysql/mysql.conf.d/mysqld.cnf
service mysql restart
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer && hash -r
cd /var/www/
composer create-project eveseat/seat seat
sed -i -- 's/DB_USERNAME=seat/DB_USERNAME=root/g' /var/www/seat/.env
sed -i -- 's/DB_PASSWORD=secret/DB_PASSWORD=password/g' /var/www/seat/.env
sed -i -- 's/APP_DEBUG=false/APP_DEBUG=true/g' /var/www/seat/.env
cd /var/www/seat/
php artisan vendor:publish --force
php artisan migrate
php artisan db:seed --class=Seat\\Notifications\\database\\seeds\\ScheduleSeeder
php artisan db:seed --class=Seat\\Services\\database\\seeds\\NotificationTypesSeeder
php artisan db:seed --class=Seat\\Services\\database\\seeds\\ScheduleSeeder
php artisan eve:update-sde -n
/usr/bin/expect /vagrant/provisions/admin_seat
echo "UPDATE seat.users SET active=1 WHERE id=1;" | mysql -uroot -ppassword
cp /vagrant/provisions/supervisor /etc/supervisor/conf.d/seat.conf
systemctl start supervisor
systemctl enable supervisor
supervisorctl reload
crontab -u www-data /vagrant/provisions/crontab
adduser ubuntu www-data
chown -R www-data:www-data /var/www
chmod -R guo+w /var/www/seat/storage/
cp /vagrant/provisions/vhost /etc/apache2/sites-available/seat.conf
chmod 777 -R /var/www/seat
a2dissite 000-default.conf
a2ensite seat
a2enmod rewrite
apachectl restart


echo " "
echo " "
echo " "
echo "******************************"
echo "******************************"
echo " "
echo "Deployment completed, SeAT should be available on one of these IP (if multiple) : `hostname -I`"
echo "SeAT : admin:password"
echo "MySQL : root:password (remote access allowed)"
echo " "
echo "Check for errors above. If there is any, most of the time, it's easier to just destroy the machine and try again."
echo " "
echo "******************************"
echo "******************************"
echo " "
echo " "
echo " "