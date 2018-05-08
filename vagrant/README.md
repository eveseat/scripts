# Prerequisites

* [Download and install Vagrant.](https://www.vagrantup.com/) [More info about Vagrant here.](https://www.vagrantup.com/docs/)
* Virtualbox ^5.2.X [Download here](https://www.virtualbox.org/wiki/Downloads)
* `vagrant plugin install vagrant-docker-compose` (installs automatically)

# Installation

* Download eveseat/scripts `git clone https://github.com/eveseat/scripts.git`
* `cd scripts/vagrant`
* `vagrant up`. This will take some time since it's deploying a whole dev environment with docker-compose.
* `vagrant ssh` to open an SSH connection to the machine. Working directory is:
`/vagrant/vm-files/docker-compose-dev/` to execute all docker-commands.

# Usage

Connect to your SeAT instance by browsing to `http://192.168.33.100:8080`.
(You can change the set ip in the vagrant file.)

By default, you will receive a synced folder inside your vagrant-folder called `vm-files`.
You are able to route your IDE to this folder and work from there.

# Worth noting

* by using `vagrant up` vagrant will run `docker-compose up -d`

# Troubleshooting

* If the installation fails, execute `vagrant destroy` to rollback to a clean state then try again with `vagrant up`.
* If you alter the `.env` of your `docker-compose.yml` do run `vagrant provision`, else it won't take effect.
