#!/bin/bash

echo "Backing up database to seat-backup.db"
docker-compose --project-name seat-dev exec mariadb sh -c 'exec mysqldump "$MYSQL_DATABASE" -u"$MYSQL_USER" -p"$MYSQL_PASSWORD"' > seat-backup.db
echo "Gzipping backup"
gzip seat-backup.db
