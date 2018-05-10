#!/bin/bash

echo "Starting database server"
/usr/sbin/mysqld &
echo "Starting redis server"
/usr/bin/redis-server &

echo "Giving the servers 5 seconds to settle"
sleep 5

echo "Running SeAT configuration commands"

cd /seat
php artisan vendor:publish --force
php artisan migrate
php artisan db:seed --class=Seat\\Notifications\\database\\seeds\\ScheduleSeeder
php artisan db:seed --class=Seat\\Services\\database\\seeds\\NotificationTypesSeeder
php artisan db:seed --class=Seat\\Services\\database\\seeds\\ScheduleSeeder
php artisan eve:update:sde -n

