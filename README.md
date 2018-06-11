Installation

Debian 9 instructions

Follow a guide how to install the following:
- nginx
- MariaDB (or MySQL)
- PHP
- PHP-FPM
- PHP-MySQLi
- PHP-MBstring

In short: apt-get install nginx mariadb-server php-fpm php-mysqli php-mbstring

Head to the nginx document root and clone:
cd /var/www/html
git clone...

Import database structure, located in sql/database.sql
	mariadb or mysql
	source include/database.sql

If you do not have a user for the web server:
	CREATE USER 'www'@'localhost' IDENTIFIED BY 'www';
	GRANT ALL PRIVILEGES ON mediaarchive.* TO 'www'@'localhost';
	FLUSH PRIVILEGES;

Fill in the configuration in include/setup.php
