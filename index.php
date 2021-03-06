<?php

#changelog
# 2013-09-20 - creating first version
# 2013-09-21 - updating
# 2013-09-22 - updating
# 2013-09-23 - updating
# 2013-10-27 - updating
# 2015-11-04 15:44:16 - adding cameras
# 2018-06-16 01:05:09
# 2018-07-19 18:47:16 - indentation change, tab to 2 spaces
# 2018-12-20 19:04:00 - moving translation to Base translate

# get required functions
require_once('include/functions.php');

# get request variables
require_once('include/request.php');

# warm up translations
start_translations(dirname(__FILE__).'/include/locales/');

# do actions
require_once('index_actions.php');

# get view init
require_once('index_view.php');

# find out what content format that is requested
switch ($request['format']) {
  default:
    # get content init
    require_once('index_content_html.php');
    die();
  case 'json':
    # get content init
    require_once('index_content_json.php');
    die();
}

?>
