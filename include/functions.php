<?php
	#changelog
	# 2013-09-20 - creating first version
	# 2013-09-21 - updating
	# 2013-09-22 - updating
	# 2014-10-31 - moving thumbnails from data dir to datacache dir due to inotifywait on data dir
	# 2015-05-09 14:25:50
	# 2015-07-15 13:12:46 - adding shutdown function
	# 2015-07-15 13:13:20
	# 2015-08-25 11:15:25
	# 2016-09-12 14:14:54 - sorting functions
	# 2016-09-14 10:45:43
	# 2016-09-16 17:00:45 - base domainname
	# 2016-09-18 12:22:34 - adding config constant for thumbnail quality
	# 2016-09-22 22:27:59 - base 2 to base 3
	# 2017-02-12 00:15:21 - trailing space removal

	# legend
	# lnr = login not required
	# lr = login required

	# warm up sessions
	session_start();

	# warm up translations
	$language = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) && ($language = explode(",", $_SERVER['HTTP_ACCEPT_LANGUAGE'], 2 )) ? str_replace('-', '_', strtolower($language[0])) : 'en_US';

	switch ($language) {
		default:
			$locale = 'en_US';
			break;
		case 'sv':
		case 'sv_se':
			$locale = 'sv_SE';
			break;
	}

	$locale .= '.utf-8';

	putenv('LC_ALL='.$locale);
	setlocale(LC_ALL, $locale);
	bindtextdomain("messages", "locale");
	textdomain("messages");

	# tell the main config file that there is a custom config file for this site
	define('CONFIG_NAME', 'mediaarchive');

	# get base constants
	require_once('setup.php');

	define('SITE_SHORTNAME', 'mediaarchive');

	# make sure we got base array
	$_SESSION[SITE_SHORTNAME] = isset($_SESSION[SITE_SHORTNAME]) ? $_SESSION[SITE_SHORTNAME] : array();

	# get database functions and so on
	require_once('base3.php');

	# get db connections
	$link = db_connect();
	# m-ysql_set_charset('utf8', $link);
	# var_dump(db_query($link, "SET NAMES 'utf8' COLLATE 'utf8_unicode_ci'"));

	if (!function_exists('shutdown_function')) {
		# a function to run when the script shutdown
		function shutdown_function($link) {
			if ($link) {
				db_close($link);
			}
		}
	}

	# register a shutdown function
	register_shutdown_function('shutdown_function', $link);

	# m-ysql_query("SET NAMES 'utf8'");
	# m-ysql_query("SET CHARACTER SET utf8 ");

	$months = array(
		1 => _('January'),
		2 => _('February'),
		3 => _('March'),
		4 => _('April'),
		5 => _('May'),
		6 => _('June'),
		7 => _('July'),
		8 => _('August'),
		9 => _('September'),
		10 => _('October'),
		11 => _('November'),
		12 => _('December')
	);

	# base structure for translations
	$translations = array(
		'current' => array(
			'index' => 0,
			'locale' => 'en-US'
		),
		'languages' => array(
			array(
				# content for the locale
				'content' => array(),
				'content_logged_in' => array(),
				'locales' => array(
					'en-US'
				)
			)
		)
	);

	# lnr - to make a thumbnail
	function makeThumbnail($desired_size, $in, $out, $display=false) {

		# missing imagemagick - try service
		if (!file_exists(MAGICK_PATH.'convert') && SERVICE_KEY_MAKETHUMBNAIL !== false) {

			# get url to service
			$url = file_get_contents('http://www.'.BASE_DOMAINNAME.'/service/?action=dynresolve&redirect=0&hostname=rnbw&pattern=http://___IP___/');
			if (strpos($url, 'http:')=== false)	die('failed');
			$url = $url.'/dotpointer/service/index.php';

			# this needs to be the full path to the file you want to send.
			$file = realpath($in);

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, array(
				'file' => new CurlFile($file),
				'action' => 'makethumbnail',
				'lsk' => SERVICE_KEY_MAKETHUMBNAIL,
				'size' => $desired_size, # 1024x768
				'quality' => MAGICK_THUMBNAIL_QUALITY
			));
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			$response = curl_exec ($ch);

			$httpcode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

			curl_close($ch);

			if ($httpcode > 399) {
				die('Failed: '.$httpcode.' '.substr($response, 0, 200));
			}

			file_put_contents($out, $response);

			return true;
		}


		#if ($desired_size === '160x120') {
			# 30 ser skabbigt ok ut
		#	MAGICK_THUMBNAIL_QUALITY = 30;
		#} else {
			# 30 var ok
		#	MAGICK_THUMBNAIL_QUALITY = 20;
		#}

		# 6.3-6.4, generation took 4s
		# 6.8, generation takes 1.3s

		# setlocale(LC_CTYPE, 'sv_SE.iso-8859-1');
		# setlocale(LC_CTYPE, 'sv_SE.iso-8859-1');

		# 40 funkar ok, himlar blir visserligen pixlade men inte så mkt
		# 30 är gränsfall, det är randigt om man kollar noga
		# 25 är himlar synbart randiga
		# 10-15 pajar ansikten på 160x120
		# 5 är fruktansvärt

		# $s = trim(exec('ps ax|grep convert'));
		# if (strlen($s)) return false;
		$size = $desired_size;
	# $command = MAGICK_PATH.'convert '.escapeshellarg($in).' -auto-orient -resize '.$size.' -strip -quality '.MAGICK_THUMBNAIL_QUALITY.' '.escapeshellarg($out);
		$command = MAGICK_PATH.'convert '.escapeshellarg($in).' -quality '.MAGICK_THUMBNAIL_QUALITY.' -auto-orient -strip -sample '.$size.' '.escapeshellarg($out);

		exec($command);
		return true;
	}

	# lnr - to print breadcrumbs
	function print_breadcrumbs($path, $name = false, $up = false) {

		$breadcrumbs = explode('/', $path);

?>
	<div class="breadcrumbs">
<?php
		if (count($breadcrumbs)) {

			?><span class="separator">/</span><?php
			$prevpath='/';
			foreach ($breadcrumbs as $bc) {

				if (!strlen($bc)) continue;

				?><a href="?page=folder&amp;path=<?php echo $prevpath.$bc?>/"><?php echo $bc?></a><span class="separator">/</span><?php
				$prevpath .= $bc .'/';
			}
		}

		# if we are not at root folder
		if ($name) {
			# no spacing because it makes unwanted space in display
			?><span class="separator"><?php echo $name?></span><?php
		}

		# if we are not at root folder
		if ($up && $path !== '/') {
			if ($name) {
				?> <a href="?page=folder&amp;path=<?php echo $path?>"><?php echo _('Up')?></a><?php
			} else {
				?> <a href="?page=folder&amp;path=<?php echo substr($path, 0, strrpos($path, '/', -2) + 1)?>"><?php echo _('Up')?></a><?php
			}
		}
?>
		<div class="clear_both"/></div>
	</div>
<?php

		return true;
	}

	$thumbsizes = array(
		'small' => '160x120',
		'normal' => '1024x768'
	);

	# lnr - to construct a thumbnail path, needs id in db and sizename (small, normal)
	function get_thumbnail_path($hash, $size) {
		global $thumbsizes;

		if (!strlen($hash)) die('Invalid hash');

		# make sure the size is set
		if (!isset($thumbsizes[$size])) die('Invalid thumbnail size');
		return THUMBNAIL_DIR.''.$hash.'/'.$thumbsizes[$size].'.jpg';
	}

	# lnr - to make thumbs, needs id in database and path to source file
	function make_thumbnails($hash, $sourcepath) {

		global $thumbsizes;
		global $verbose;

		if (!strlen($hash)) {
			die('Invalid hash.');
		}

		$stats = array();

		# missing thumbnail dir?
		if (
			!is_dir(THUMBNAIL_DIR)
			|| trim(THUMBNAIL_DIR) === '/'
			|| substr(THUMBNAIL_DIR, -1,1) !== '/'
		) {
			die('Fatal, thumbnail directory does not exist: '.THUMBNAIL_DIR);
		}

		# is the folder for this item not created?
		if (!is_dir(THUMBNAIL_DIR.$hash.'/')) {
			# try to make the folder
			if (!mkdir(THUMBNAIL_DIR.$hash.'/')) {
				die('Failed creating folder '.THUMBNAIL_DIR.''.$hash.'/');
			}
		}

		$mime = mime_content_type($sourcepath);

		if (strpos($mime, 'image/') !== false) {

			# walk thumb sizes
			foreach ($thumbsizes as $k => $v) {

				$stats[$v] = '';

				$filepath = get_thumbnail_path($hash, $k);

				if ($filepath === false) {
					die('Failed getting thumbnail path: '.$sourcepath);
				}

				# does this thumbnail not exist?
				if (!file_exists($filepath)) {
					$s = trim(exec('ps ax|grep convert|grep -v grep'));
					if (strlen($s)) {
						echo 'Skipping, convert already running'."\n";
						return array(
							'small' => 'failed',
							'normal' => 'failed'
						);
					}


					# try to make a thumbnail
					makeThumbnail($v, $sourcepath, $filepath);
					if (!file_exists($filepath)) {
						echo 'Failed creating '.$v.' thumbnail: '.$filepath."\n";
						$stats[$v] = 'failed';
						continue;
					}

						$stats[$v] = 'created';
				} else {
						$stats[$v] = 'already done';
				}
			}
		} else if (strpos($mime, 'video/') !== false) {

			# walk thumb sizes
			# foreach ($thumbsizes as $k => $v) {

			# check if the main thumb is there
			$normalthumbpath = get_thumbnail_path($hash, 'normal');
			$smallthumbpath = get_thumbnail_path($hash, 'small');

			# no main thumb?
			if (!file_exists($normalthumbpath)) {

				# run video sheet to make it
				$c = 'php '.DPTOOLS_DIR.'videosheet --filename='.escapeshellarg($sourcepath).($verbose ? ' -vv' : '').' --format=jpeg --quality=75 --thumbsize=205,-1 --compact --output='.escapeshellarg($normalthumbpath);
				passthru($c, $r);
				if ($r !== 0) {
					echo 'Failed creating videosheet for: '.$sourcepath."\n";
					return array(
						'small' => 'failed',
						'normal' => 'failed'
					);
				}

				if (!file_exists($normalthumbpath)) {
					echo 'Failed creating videosheet for: '.$sourcepath;
					return array(
						'small' => 'failed',
						'normal' => 'failed'
					);
				}

				$stats[$thumbsizes['normal']] = 'created';

			} else {
				$stats[$thumbsizes['normal']] = 'already done';
			}

			# get small thumb path
			$smallthumbpath = get_thumbnail_path($hash, 'small');
			if (!file_exists($smallthumbpath)) {
				makeThumbnail($thumbsizes['small'], $normalthumbpath, $smallthumbpath);
				if (!file_exists($smallthumbpath)) {
					echo 'Failed creating thumbnail: '.$filepath;

					return array(
						'small' => 'failed',
						'normal' => $stats[$thumbsizes['normal']]
					);

				}
				$stats[$thumbsizes['small']] = 'created';

			} else {
				$stats[$thumbsizes['normal']] = 'already done';
			}

		} # else {
			#echo $sourcepath;
			#echo "\n".$mime;
		#}

		return $stats;
	}

	# lnr - convert exif-formatted datetime with : date separator to - separator
	function correct_exif_datetime($datetime) {

		# match xxxx-xx-xxTxx:xx:xxZ - is T and Z in the string?
		if (strpos($datetime, 'T') !== false && strpos($datetime, 'Z') !== false) {
			# try to decode it as a date string
			$test = strtotime ($datetime);
			if ($test !== false) {
				return date('Y-m-d H:i:s', $test);
			}
		}

		# match xxxx:xx:xx xx:xx:xx - check for the format
		preg_match_all('/^([0-9]{3}[0-9]+)[\:|\-]([0-1][0-9])[\:|\-]([0-3][0-9]) ([0-5][0-9]\:[0-5][0-9]\:[0-5][0-9])$/mi', $datetime, $m);
		# check if xxxx-xx-xx xx:xx:xx is not there
		if (!isset($m[1][0], $m[2][0], $m[3][0], $m[4][0])) {
			# failed finding results
			return false;
		}

		# return formatted string
		return $m[1][0].'-'.$m[2][0].'-'.$m[3][0].' '.$m[4][0];
	}

	# lnr - to get shutter speed and fstop
	# from: darkain at darkain dot com
	function exif_get_float($value) {
		$pos = strpos($value, '/');
		if ($pos === false) return (float) $value;
		$a = (float) substr($value, 0, $pos);
		$b = (float) substr($value, $pos+1);
		return ($b == 0) ? ($a) : ($a / $b);
	}

	# lnr - to get shutter
	function exif_get_shutter(&$exif) {
		if (!isset($exif['ShutterSpeedValue'])) return false;
		$apex    = exif_get_float($exif['ShutterSpeedValue']);
		$shutter = pow(2, -$apex);
		if ($shutter == 0) return false;
		if ($shutter >= 1) return round($shutter) . 's';
		return '1/' . round(1 / $shutter) . 's';
	}

	# lnr - to get fstop
	function exif_get_fstop(&$exif) {
		if (!isset($exif['ApertureValue'])) return false;
		$apex  = exif_get_float($exif['ApertureValue']);
		$fstop = pow(2, $apex/2);
		if ($fstop == 0) return false;
		return 'f/' . round($fstop,1);
	}

	# lnr - file sizing function, from stachu540 at gmail dot com 30-Aug-2011 04:02, http://se2.php.net/filesize
	function file_size_endings($FZ) {
		# $FZ = ($file && @is_file($file)) ? filesize($file) : NULL;
		$FS = array("B","kB","MB","GB","TB","PB","EB","ZB","YB");
		if (!$FZ) return '0 '.$FS[0];
		return number_format($FZ/pow(1024, $I=floor(log($FZ, 1024))), ($I >= 1) ? 2 : 0) . ' ' . $FS[$I];
	}

	# lnr - to get the exposure date from a file, first based on exif data, then on the date in the path
	function get_exposure_date($exifdata, $file) {

		# first try to get it from exifdata
		if (isset($exifdata['DateTime']) && $date = strtotime(correct_exif_datetime($exifdata['DateTime']))) return date('Y-m-d H:i:s', $date);
		if (isset($exifdata['DateTimeOriginal']) && $date = strtotime(correct_exif_datetime($exifdata['DateTimeOriginal']))) return date('Y-m-d H:i:s', $date);
		if (isset($exifdata['DateTimeDigitized']) && $date = strtotime(correct_exif_datetime($exifdata['DateTimeDigitized']))) return date('Y-m-d H:i:s', $date);

		# then try to find a date in the path
		if ($file) {

			$m = array();

			$mcount = preg_match_all('/^.*([0-9x]{3}[0-9x]+)-([0-1x][0-9x])-([0-3x][0-9x]).*$/im', $file, $m);

			if ($mcount) {

				$year	= isset($m[1][0]) ? str_replace('x', '0', $m[1][0]) : '0000';
				$month	= isset($m[2][0]) ? str_replace('x', '0', $m[2][0]) : '00';
				$day	= isset($m[3][0]) ? str_replace('x', '0', $m[3][0]) : '00';

				if ((float)$year > 0 && (int)$month < 1) $month = '01';
				if ((float)$year > 0 && (int)$day < 1) $day = '01';

				return date('Y-m-d H:i:s', strtotime($year.'-'.$month.'-'.$day));
			}

			# try to get only year
			$mcount = preg_match_all('/^.*\/([0-9x]{3}[0-9x]+)\/.*$/im', $file, $m);

			if ($mcount) {

				$year	= isset($m[1][0]) ? str_replace('x', '0', $m[1][0]) : '0000';
				$month = 1;
				$day = 1;

				return date('Y-m-d H:i:s', strtotime($year.'-'.$month.'-'.$day));
			}
		}

		# all failed, try this
		return '0000-00-00 00:00:00';
	}

	# lr - to get the logged in user
	function get_logged_in_user($field=false) {
		if (!is_logged_in(true)) return false;
		if (!$field) return $_SESSION[SITE_SHORTNAME]['user'];
		if (!isset($_SESSION[SITE_SHORTNAME]['user'][$field])) return false;
		return $_SESSION[SITE_SHORTNAME]['user'][$field];
	}

	# to check if user is logged in
	function is_logged_in($no_guest_mode=false) {

		# is guest mode allowed and activated
		if (!$no_guest_mode && GUEST_MODE) return true;
		# or are we really logged in
		if (!isset($_SESSION[SITE_SHORTNAME])) return false;
		if (!isset($_SESSION[SITE_SHORTNAME]['user'])) return false;
		return true;
	}

	# lnr
	/**
	* Returns an array of latitude and longitude from the Image file
	* @param image $file
	* @return multitype:number |boolean
	* @source LoneWOLFs - http://stackoverflow.com/questions/5449282/reading-geotag-data-from-image-in-php
	*/
	function read_gps_location($exifdata) {

		if (isset($exifdata['GPSLatitude']) && isset($exifdata['GPSLongitude']) &&
			isset($exifdata['GPSLatitudeRef']) && isset($exifdata['GPSLongitudeRef']) &&
			in_array($exifdata['GPSLatitudeRef'], array('E','W','N','S')) && in_array($exifdata['GPSLongitudeRef'], array('E','W','N','S'))) {

			$GPSLatitudeRef  = strtolower(trim($exifdata['GPSLatitudeRef']));
			$GPSLongitudeRef = strtolower(trim($exifdata['GPSLongitudeRef']));

			$lat_degrees_a = explode('/',$exifdata['GPSLatitude'][0]);
			$lat_minutes_a = explode('/',$exifdata['GPSLatitude'][1]);
			$lat_seconds_a = explode('/',$exifdata['GPSLatitude'][2]);
			$lng_degrees_a = explode('/',$exifdata['GPSLongitude'][0]);
			$lng_minutes_a = explode('/',$exifdata['GPSLongitude'][1]);
			$lng_seconds_a = explode('/',$exifdata['GPSLongitude'][2]);

			$lat_degrees = $lat_degrees_a[0] / $lat_degrees_a[1];
			$lat_minutes = $lat_minutes_a[0] / $lat_minutes_a[1];
			$lat_seconds = $lat_seconds_a[0] / $lat_seconds_a[1];
			$lng_degrees = $lng_degrees_a[0] / $lng_degrees_a[1];
			$lng_minutes = $lng_minutes_a[0] / $lng_minutes_a[1];
			$lng_seconds = $lng_seconds_a[0] / $lng_seconds_a[1];

			$lat = (float) $lat_degrees+((($lat_minutes*60)+($lat_seconds))/3600);
			$lng = (float) $lng_degrees+((($lng_minutes*60)+($lng_seconds))/3600);

			//If the latitude is South, make it negative.
			//If the longitude is west, make it negative
			$GPSLatitudeRef  == 's' ? $lat *= -1 : '';
			$GPSLongitudeRef == 'w' ? $lng *= -1 : '';

			return array(
				'lat' => $lat,
				'lng' => $lng
			);
		}
		return false;
	}

	# --- translation - from kreosot ----

	# to get the current locale
	function get_current_locale(){
		global $translations;
		return reset($translations['languages'][ $translations['current']['index'] ]['locales']);
	}

	# to translate string
	function get_translation_texts() {
		# get translation data and translations
		global $translations;

		# make sure we have the index
		$tindex = isset($translations['current']['index']) ? $translations['current']['index'] : 0;

		# is this language not present
		if (!isset($translations['languages'][$tindex])) {
			# then get out
			return array();
		}

		return is_logged_in(false) ? array_merge($translations['languages'][$tindex]['content'], $translations['languages'][$tindex]['content_logged_in']) : $translations['languages'][$tindex]['content'];
	}

	# to get a matching locale translation index, send in locale and get a working translation index in return
	function get_working_locale($langs_available, $try_lang = false) {

		$accept_langs = array();

		# no language to try provided?
		if (!$try_lang) {
			# try with header - or if not there, go en
			$try_lang = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : false;
		}

		# any language to try now?
		if ($try_lang) {
			preg_match_all(
				'/([a-z]{1,8}(-[a-z]{1,8})?)\s*(;\s*q\s*=\s*(1|0\.\d+))?/i',
				$try_lang,
				$lang_parse
			);

			if (isset($lang_parse[1]) && count($lang_parse[1])) {

				# create a list like 'en-US' => 0.7
				$accept_langs = array_combine($lang_parse[1], $lang_parse[4]);

				# set default to 1 for any without q factor
				foreach ($accept_langs as $k => $v) {
					if ($v === '') {
						$accept_langs[$k] = 1;
					}
				}

				arsort($accept_langs, SORT_NUMERIC);
			} # if match
		} # if-trylang


		# walk the languages - en, sv, es etc...
		foreach (array_keys($accept_langs) as $current_acceptlang) {
			# walk the available languages provided
			foreach ($langs_available as $k => $v) {
				# walk the locales in this provided language
				foreach ($v['locales'] as $k2 => $v2) {
					# compare the language
					if (strtolower($v2) === strtolower($current_acceptlang)) {
						return $k;
					}
				}
			}

			$acceptlang_intro = stristr($current_acceptlang, '-') ? substr($current_acceptlang, 0, strpos($current_acceptlang, '-')) : $current_acceptlang;

			foreach ($langs_available as $k => $v) {
				foreach ($v['locales'] as $k2 => $v2) {
					if (strtolower($v2) === strtolower($acceptlang_intro)) {
						return $k;
					}
				}
			}

			foreach ($langs_available as $k => $v) {
				foreach ($v['locales'] as $availlang) {
					if (strtolower($acceptlang_intro) === strtolower(stristr($availlang, '-') ? substr($availlang, 0, strpos($availlang, '-')) : $availlang)) {
						return $k;

					}
				}
			}
		}

		return 0;
	}

	function start_translations() {
		global $translations;

		# directory where the translations are located
		$locale_basepath = substr(__FILE__, 0, strrpos(__FILE__, '/') + 1 ).'locales/';

		# scan the directory
		$dircontents = scandir($locale_basepath);

		# walk contents of directory
		foreach ($dircontents as $item) {

			# does this item end with the desired ending?
			if (substr($item, -9) === '.lang.php') {

				# get the contents of the translation - stop if there was there an error
				#if (!($data = file_get_contents($locale_basepath.$item))) {
				#	continue;
				#}

				# try to decode the data
				/*$data = json_decode($data, true);

				if ($data === null) {
					# find out what error that was
					switch (json_last_error()) {
						default:
							echo 'Error when decoding JSON, '.json_last_error().', in: '.$item."\n";
							break;
						case 4:
							echo 'Syntax error when decoding JSON in: '.$item."\n";
							break;

					}
					continue;
				}

				# store this into translations
				$translations['languages'][] = $data;
				*/
				require_once($locale_basepath.$item);
			}
		}

		# get the parameters
		# $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : false;
		$translations['current']['index'] = isset($_REQUEST['translationindex']) ? $_REQUEST['translationindex'] : false;

		# find out what action to take
		/*switch ($action) {
			case 'change_language':
				if (isset($translations['languages'][$translations['current']['index']])) {
					$_SESSION['translation_index'] = $translations['current']['index'];
				}
				die(json_encode(array(
					'status'	=> true,
					'data'		=> array()
				)));
				break;
		}
		*/

		# session_start();

		$translations['current']['index'] = !isset($_SESSION['translation_index']) ? get_working_locale($translations['languages']) : $_SESSION['translation_index'];
		$translations['current']['locale'] = reset($translations['languages'][$translations['current']['index']]['locales']);
		$_SESSION['translation_index'] = $translations['current']['index'];
	}

	# to switch locale if possible
	function switch_locale($locale) {
		global $translations;
		# get a working locale index
		$translations['current']['index'] = get_working_locale($translations['languages'], $locale);

		# set this locale
	$translations['current']['locale'] = reset($translations['languages'][$translations['current']['index']]['locales']);

		return true;
	}

	# to translate string
	function t($s) {
		# get translation data and translations
		global $translations;

		# make sure we have the index
		$tindex = isset($translations['current']['index']) ? $translations['current']['index'] : 0;

		# is this language not present
		if (!isset($translations['languages'][$tindex])) {
			# then get out
			return $s;
		}

		foreach ($translations['languages'][$tindex]['content'] as $sentence) {
			if (
				# are all parts there
				isset($sentence[0], $sentence[1]) &&
				# is the sentence the one we are looking for
				$s === $sentence[0] &&
				# and there is an replacement sentence
				$sentence[1] !== false
			) {
					# then return it
				return $sentence[1];
			}
		}

		if (isset($translations['languages'][$tindex]['content_logged_in'])) {
			foreach ($translations['languages'][$tindex]['content_logged_in'] as $sentence) {
				if (
					# are all parts there
					isset($sentence[0], $sentence[1]) &&
					# is the sentence the one we are looking for
					$s === $sentence[0] &&
					# and there is an replacement sentence
					$sentence[1] !== false
				) {
					# then return it
				return $sentence[1];
				}
			}
		}
		return $s;
	}

	# to convert the paths in an array result to real paths
	function fullpaths($data, $columns) {

		foreach ($data as $k => $v) {

			foreach ($columns as $column) {

				$realpath = realpath($data[$k][$column]);

				die($realpath);

				if ($realpath !== false) {
					$data[$k][$column] = $realpath;
				}
			}

		}
		return $data;
	}
?>
