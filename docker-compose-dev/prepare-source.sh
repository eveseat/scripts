#!/bin/bash

git clone https://github.com/eveseat/seat && cd seat
curl -fsSL https://raw.githubusercontent.com/eveseat/scripts/master/development/composer.dev.json > composer.json

mkdir -p packages/eveseat && cd packages/eveseat
git clone https://github.com/eveseat/api
git clone https://github.com/eveseat/console
git clone https://github.com/eveseat/eveapi
git clone https://github.com/eveseat/eseye
git clone https://github.com/eveseat/notifications
git clone https://github.com/eveseat/services
git clone https://github.com/eveseat/web

echo "Done! docker-compose up now!"
