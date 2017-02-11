<?php

# 2015-11-22 11:41:39
# 2016-09-14 16:59:51


$request = array();

# input parameters are in an array because
# we need to print them in js
$request['action'] = isset($_REQUEST['action']) ? $_REQUEST['action'] : false;
$request['dayfrom'] = isset($_REQUEST['dayfrom']) ? $_REQUEST['dayfrom'] : false;
$request['dayto'] = isset($_REQUEST['dayto']) ? $_REQUEST['dayto'] : false;
$request['effect'] = isset($_REQUEST['effect']) ? $_REQUEST['effect'] : 'none';
$request['findbarstate'] = isset($_REQUEST['findbarstate']) ? $_REQUEST['findbarstate'] : false;
$request['find'] = isset($_REQUEST['find']) ? $_REQUEST['find'] : false;
$request['format'] = isset($_REQUEST['format']) ? $_REQUEST['format'] : false;
$request['heightfrom'] = isset($_REQUEST['heightfrom']) ? $_REQUEST['heightfrom'] : false;
$request['heightto'] = isset($_REQUEST['heightto']) ? $_REQUEST['heightto'] : false;
$request['hourfrom'] = isset($_REQUEST['hourfrom']) ? $_REQUEST['hourfrom'] : false;
$request['hourto'] = isset($_REQUEST['hourto']) ? $_REQUEST['hourto'] : false;
$request['id_cameras'] = isset($_REQUEST['id_cameras']) ? $_REQUEST['id_cameras'] : false;
$request['id_labels'] = isset($_REQUEST['id_labels']) ? $_REQUEST['id_labels'] : false;
$request['id_media'] = isset($_REQUEST['id_media']) ? $_REQUEST['id_media'] : false;
$request['id_media'] = isset($_REQUEST['id']) ? $_REQUEST['id'] : $request['id_media'];
$request['limit'] = isset($_REQUEST['limit']) ? $_REQUEST['limit'] : 250;
$request['monthfrom'] = isset($_REQUEST['monthfrom']) ? $_REQUEST['monthfrom'] : false;
$request['monthto'] = isset($_REQUEST['monthto']) ? $_REQUEST['monthto'] : false;
$request['page'] = isset($_REQUEST['page']) ? $_REQUEST['page'] : 'folder';
$request['path'] = isset($_REQUEST['path']) ? $_REQUEST['path'] : '/';
$request['prettyprint'] = isset($_REQUEST['prettyprint']) ? $_REQUEST['prettyprint'] : false;
$request['rawextension'] = isset($_REQUEST['rawextension']) ? $_REQUEST['rawextension'] : false;
$request['scending'] = isset($_REQUEST['scending']) ? $_REQUEST['scending'] : 'desc';
$request['start'] = isset($_REQUEST['start']) ? $_REQUEST['start'] : 0;
$request['ticket'] = isset($_REQUEST['ticket']) ? $_REQUEST['ticket'] : false;
$request['title'] = isset($_REQUEST['title']) ? $_REQUEST['title'] : false;
$request['version'] = isset($_REQUEST['version']) ? $_REQUEST['version'] : 'original';
$request['viewoptions'] = isset($_REQUEST['viewoptions']) ? $_REQUEST['viewoptions'] : 'details';
$request['widthfrom'] = isset($_REQUEST['widthfrom']) ? $_REQUEST['widthfrom'] : false;
$request['widthto'] = isset($_REQUEST['widthto']) ? $_REQUEST['widthto'] : false;
$request['yearfrom'] = isset($_REQUEST['yearfrom']) ? $_REQUEST['yearfrom'] : false;
$request['yearto'] = isset($_REQUEST['yearto']) ? $_REQUEST['yearto'] : false;

?>
