# -*- mode: ruby -*-
# vi: set ft=ruby :

Vagrant.configure("2") do |config|
  config.vm.box = "ubuntu/xenial64"
  config.vm.provision :shell, path: "provisions/bootstrap.sh"
  config.vm.network "private_network", type: "dhcp"
end
