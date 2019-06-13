#!/bin/bash

MYSQL_ROOT_PASSWORD="!mysql_root_password@21123"

# start mysql
echo "Starting database server"
/usr/sbin/mysqld &

echo "Giving the server 5 seconds to settle"
sleep 5

# run the expect script
echo "Running mysql_secure_installation"
SECURE_MYSQL=$(expect -c "
set timeout 10
spawn mysql_secure_installation
expect \"Enter current password for root (enter for none):\"
send \"\r\"
expect \"Change the root password?\"
send \"y\r\"
expect \"New password:\"
send \"$MYSQL_ROOT_PASSWORD\r\"
expect \"Re-enter new password:\"
send \"$MYSQL_ROOT_PASSWORD\r\"
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

echo "Configurating SeAT database and user"
mysql -uroot -p$MYSQL_ROOT_PASSWORD -e "CREATE DATABASE seat;"
mysql -uroot -p$MYSQL_ROOT_PASSWORD -e "GRANT ALL ON seat.* to seat@localhost IDENTIFIED BY 'seat';"
mysql -uroot -p$MYSQL_ROOT_PASSWORD -e "GRANT ALL ON information_schema.* to seat@localhost IDENTIFIED BY 'seat';"
