#!/bin/bash

BRANCH=4.0.0
declare -a BRANCHED_PACKAGES=("api" "console" "eveapi" "notifications" "services" "web")
declare -a PACKAGES=("eseye")

echo "[i] Pulling parent seat project"
git clone -b ${BRANCH} https://github.com/eveseat/seat sources && cd sources
echo "[i] Getting development composer.json"
curl -fsSL https://raw.githubusercontent.com/eveseat/scripts/master/development/composer.dev.json > composer.json

echo "[i] Preparing packages directory"
mkdir -p packages/eveseat && cd packages/eveseat

echo "[i] Cloning branched packages"
for PACKAGE in "${BRANCHED_PACKAGES[@]}"
do
    git clone -b $BRANCH https://github.com/eveseat/$PACKAGE
done

echo "[i] Cloning non branched repos"
for PACKAGE in "${PACKAGES[@]}"
do
    git clone https://github.com/eveseat/$PACKAGE
done

echo "[i] Done! docker-compose up now!"
