<?php

	# changelog
	# 2013-09-21 - first version
	# mån, sep 23 2013 03:59:57
	# 2013-10-06 - adding scending button
	# 2014-09-06 13:02:49 - adding trash
	# 2015-05-10 22:45:06
	# 2015-08-25 10:47:59
	# 2015-11-04 15:44:10 - adding cameras
	# 2016-09-13 10:49:43 - guest mode
	# 2016-09-15 21:02:25 - using jquery/jquery-ui 3.1.0/1.12.0
	# 2016-09-16 17:31:01 - http/https

	if (!isset($request['page'])) die();

	
?><!DOCTYPE html>
<html>
<head>
	<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	<title><?php echo t('The media archive')?></title>
	<link rel="shortcut icon" href="favicon.ico" />

	<!-- 1.9.1, 2.1.4 -->
	<script type="text/javascript" src="include/jquery-3.1.0.min.js"></script>

	<!-- migrate 1.1.1, 3.0.0 -->

	<!-- 1.10.3, 1.12.0 -->
	<script type="text/javascript" src="include/jquery-ui-1.12.0.custom.min.js"></script>

	<script type="text/javascript" src="include/jquery.hotkeys.js"></script>

    <script async defer src="//maps.googleapis.com/maps/api/js<?php echo defined('MAPS_API_KEY') && strlen(MAPS_API_KEY) ? '?key='.MAPS_API_KEY : '' ?><?php /* &callback=initMap */ ?>" type="text/javascript"></script>	

	<script type="text/javascript" src="include/intro.js"></script>

	<script type="text/javascript">
		params = <?php echo json_encode($request, JSON_PRETTY_PRINT); ?>;
	</script>
</head>
<body>
<?php
	# make sure we're logged in
	if (!is_logged_in(false)) {
		# this should not happend as index_view should take care of it
?>
	<script type="text/javascript">
		window.location = '?page=login';
	</script>
	<div class="login">
		<a href="?page=login">Login required. <?php echo _('Click here to login')?>.<a/>
	</div>
<?php
	}
?>
</body>
</html>
