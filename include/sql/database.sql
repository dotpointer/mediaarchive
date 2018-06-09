CREATE DATABASE mediaarchive;

USE mediaarchive;

CREATE TABLE photos(
	id BIGINT NOT NULL PRIMARY KEY AUTO_INCREMENT,
	id_cameras INT NOT NULL,
	path TINYTEXT NOT NULL,
	name TINYTEXT NOT NULL,
	ed2khash TINYTEXT NOT NULL,
	verified INT NOT NULL DEFAULT 0,
	existing INT NOT NULL DEFAULT 1,
	width INT NOT NULL DEFAULT 0,
	height INT NOT NULL DEFAULT 0,
	latitude  decimal(18,12) NOT NULL default 0,
	longitude decimal(18,12) NOT NULL default 0	
	exposured datetime not null default '0000-00-00'
	
);

CREATE TABLE users(
	id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
	id_visum INT NOT NULL UNIQUE,
	nickname VARCHAR(16) NOT NULL,
	gender enum('0','1','2') NOT NULL,
	birth DATETIME NOT NULL,
	updated DATETIME NOT NULL,
	created DATETIME NOT NULL
);

CREATE TABLE cameras (
	id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
	make TINYTEXT NOT NULL,
	model TINYTEXT NOT NULL
);

CREATE TABLE trash (
	id BIGINT NOT NULL PRIMARY KEY AUTO_INCREMENT,
	id_photos BIGINT NOT NULL,
	created DATETIME NOT NULL
);

CREATE TABLE labels (
	id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
	id_users INT NOT NULL,
	title TINYTEXT NOT NULL,
	title_short VARCHAR(8) NOT NULL,
	updated DATETIME NOT NULL,
	created DATETIME NOT NULL
);

CREATE TABLE relations_media_labels (
	id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
	id_media INT NOT NULL,
	id_labels INT NOT NULL,
	id_users INT NOT NULL,
	created DATETIME NOT NULL
);

CREATE INDEX index_id_media ON relations_media_labels (id_media);

CREATE INDEX index_id_labels ON relations_media_labels (id_labels);
