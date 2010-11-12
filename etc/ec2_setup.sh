#!/bin/sh

# This file will take a 100% fresh debian-6-squeeze image and configure openkeyval.org
apt-get update
DEBIAN_FRONTEND=noninteractive apt-get -y install sudo git php5-memcache libmemcache-dev locate php5-dev php-pear php5 libapache2-mod-php5 apache2 telnet memcached phpunit php5-curl
cd /var
git clone git://github.com/shinyplasticbag/openkeyval.git
cd /var/openkeyval
</dev/urandom tr -dc A-Za-z0-9 | head -c 70 > /var/openkeyval/salt.txt
mkdir /var/openkeyval/data
chmod 777 /var/openkeyval/data
rm /etc/apache2/sites-enabled/000-default
cp /var/openkeyval/etc/httpd.conf /etc/apache2/sites-available/000-openkeyval
ln -s /etc/apache2/sites-available/000-openkeyval /etc/apache2/sites-enabled/000-openkeyval
a2enmod rewrite
a2enmod php5
/etc/init.d/apache2 restart
cd /var/openkeyval/phpunit
phpunit .

# Things to do by hand:

# Update a real hostname in
# nano /var/openkeyval/config.inc

# Update real hostname in
# /etc/apache2/sites-enabled/000-openkeyval