#!/bin/bash

dbaccess=0
until [[ $dbaccess = 1 ]]; do
  mysql -uroot -ppassword -P3306 -h mysql -e exit 2>/dev/null
  dbstatus=`echo $?`
  if [ $dbstatus = 0 ]; then
    dbaccess=1
  else
    echo "Waiting for MySQL container to be available... Retrying in 3 seconds."
    sleep 3
  fi
done

migrations="$(php /var/www/seat/artisan migrate:status | grep '| Y    |' | wc -l)"
if [ ${migrations} -eq 0 ]; then
	php artisan migrate
	php artisan db:seed --class=Seat\\Notifications\\database\\seeds\\ScheduleSeeder
	php artisan db:seed --class=Seat\\Services\\database\\seeds\\NotificationTypesSeeder
	php artisan db:seed --class=Seat\\Services\\database\\seeds\\ScheduleSeeder
	php artisan eve:update-sde -n

	/usr/bin/expect /root/seat_admin
  echo "UPDATE seat.users SET active=1 WHERE id=1;" | mysql -uroot -ppassword -P3306 -h mysql
fi

service supervisor restart

rm -f /var/run/apache2/apache2.pid

apache2ctl -D FOREGROUND