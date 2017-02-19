# Prerequisites

* [Download and install Vagrant.](https://www.vagrantup.com/) [More info about Vagrant here.](https://www.vagrantup.com/docs/)
* [Virtualbox 5.0.X. (recommended)](https://www.virtualbox.org/wiki/Download_Old_Builds_5_0) (5.1 should work but if you're having issue, consider downgrading to 5.0)

# Installation

* `git clone git@github.com:eveseat/scripts.git`
* `cd scripts/vagrant`
* `vagrant up`. It can take some times since it's deploying a whole dev environnement (mysql, php, apache, composer...) and configuring SeAT.
* `vagrant ssh` to open an SSH connection to the machine.

# Usage

Connect to your SeAT instance by browsing to `http://{ip address of the VM}/`.

For package development you can use shared folders by adding to your `Vagrantfile` a line like this : `config.vm.synced_folder "D:/dev/seat_packages/calendar", "/var/www/seat/packages/kassie/calendar"`.

# Credentials

* SeAT `admin:password`
* MySQL `root:password` (remote access allowed)

# Troubleshooting

If the installation fails, execute `vagrant destroy` to rollback to a clean state then try again with `vagrant up`.