#!/bin/bash

# 2016 Leon Jacobs

echo ' * SeAT Installer Operating System Selection'

PS3=' * Please select the target operating system: '
options=("CentOS 6" "CentOS 7" "Ubuntu 16x" "Debian 8x" "Debian 9x" "Quit")
select opt in "${options[@]}"
do
    case $opt in
        "CentOS 6")
            echo ' * Downloading and running CentOS 6 installer'
            bash <(curl -fsSL https://raw.githubusercontent.com/eveseat/scripts/master/install/centos-6-x.sh)
            break
            ;;
        "CentOS 7")
            echo ' * Downloading and running CentOS 7 installer'
            bash <(curl -fsSL https://raw.githubusercontent.com/eveseat/scripts/master/install/centos-7-x.sh)
            break
            ;;
        "Ubuntu 16x")
            echo ' * Downloading and running Ubuntu 16x installer'
            bash <(curl -fsSL https://raw.githubusercontent.com/eveseat/scripts/master/install/ubuntu-16-x.sh)
            break
            ;;
        "Debian 8x")
            echo ' * Downloading and running Debian 8x installer'
            bash <(curl -fsSL https://raw.githubusercontent.com/eveseat/scripts/master/install/debian-8-x.sh)
            break
            ;;
        "Debian 9x")
            echo ' * Downloading and running Debian 9x installer'
            bash <(curl -fsSL https://raw.githubusercontent.com/eveseat/scripts/master/install/debian-9-x.sh)
            break
            ;;
        "Quit")
            echo ' * Exiting without any action'
            break
            ;;
        *) echo ! Invalid Option;;
    esac
done
