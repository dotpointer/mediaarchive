<?php
	# setup example file for mediaarchive

	# rename this file to setup.php and fill in the details below

	# the site-id in visum authentication
	# leave it as it is
	define('ID_VISUM', false);

	# database setup
	# fill this in
	define('DATABASE_HOST', 'localhost');
	define('DATABASE_USERNAME', 'root');
	define('DATABASE_PASSWORD', '');
	define('DATABASE_NAME', 'mediaarchive');
	define('DATABASE_TABLES_PREFIX', '' /* 'mediaarchive_'*/);

	# where to put generated thumbnails
	# fill in a directory writable for the httpd user
	define('THUMBNAIL_DIR', '/somewhere/to/store/thumbnails/');
	
	# where imagemagick convert resides
	# leave it as it is but install imagemagick
	# install with: sudo apt-get install imagemagick
	define('MAGICK_PATH','/usr/bin/');
	
	# the jpeg quality for thumbnails
	# adjust or leave it as it is
	define('MAGICK_THUMBNAIL_QUALITY', 75);
	
	# allow non-logged in mode
	# leave this as it is
	define('GUEST_MODE', true);
	
	# allow login to be available or not
	# leave this as it is
	define('LOGIN_MODE', false);
	
	# service key to make thumbnail if imagemagick does not exist
	# leave this as it is
	define('SERVICE_KEY_MAKETHUMBNAIL', false);
	
	# the root path where media resides
	# note, this path may not be a relative path
	# fill this in with the directory where media is available
	define('ROOTPATH', '/where/images-are-stored/');
?>
