# The media archive

This is a photo gallery made by dotpointer in jQuery, JavaScript, PHP, MySQL,
HTML and CSS. This site loads data using web API calls and the data is
transferred using the JSON format. Photos are indexed through a home built
indexer.

## Getting Started

These instructions will get you a copy of the project up and running on your
local machine for development and testing purposes. See deployment for notes on
how to deploy the project on a live system.

### Prerequisites

What things you need to install the software and how to install them

```
- Debian Linux 9 or similar system
- nginx
- MariaDB (or MySQL)
- PHP
- PHP-FPM
- PHP-MySQLi
- PHP-MBstring
```

Setup the nginx web server with PHP-FPM support and MariaDB/MySQL.

In short: apt-get install nginx mariadb-server php-fpm php-mysqli php-mbstring
and then configure nginx, PHP and setup a user in MariaDB.

### Installing

Head to the nginx document root and clone the repository:

```
cd /var/www/html
git clone https://gitlab.com/dotpointer/mediaarchive.git
cd mediaarchive/
```

Import database structure, located in sql/database.sql

Standing in the project root directory login to the database:

```
mariadb/mysql -u <username> -p

```

If you do not have a user for the web server, then login as root and do
this to create the user named www with password www:

```
CREATE USER 'www'@'localhost' IDENTIFIED BY 'www';
```

Then import the database structure and assign a user to it, replace
www with the web server user in the database system:
```
SOURCE include/database.sql
GRANT ALL PRIVILEGES ON mediaarchive.* TO 'www'@'localhost';
FLUSH PRIVILEGES;
```

Fill in the configuration in include/setup.php.

There are also shell scripts to run as cron jobs to regularly index new files
and create thumbnails in the cronjobs/ directory.

## Authors

* **Robert Klebe** - *Development* - [dotpointer](https://gitlab.com/dotpointer)

See also the list of
[contributors](https://gitlab.com/dotpointer/mediaarchive/contributors)
who participated in this project.

## License

This project is licensed under the MIT License - see the
[LICENSE.md](LICENSE.md) file for details.

Contains dependency files that may be licensed under their own respective
licenses.
