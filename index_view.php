<?php

	#changelog
	# 2013-09-21 - creating first version
	# 2013-10-06 21:16:22 - improving
	# 2013-10-27
	# 2013-10-29 - adding tiff support
	# 2014-09-06 13:03:01 - adding trash
	# 2014-10-14 20:17:39
	# 2015-11-05 16:40:22 - adding date search, dimension search
	# 2015-11-30 12:14:53 - going multibyte
	# 2016-07-17 13:13:31 - adding raw formats
	# 2016-09-13 17:11:56 - adding database table prefixes
	# 2016-09-14 19:51:14 - absolute path to media
	# 2016-09-15 20:25:29
	# 2016-09-16 17:03:34 - base domainname
	# 2016-09-16 17:03:53
	# 2016-09-17 16:24:07 - table photos->media
	# 2016-09-22 22:33:45 - base 2 to base 3
	# 2016-09-26 17:14:05 - bugfix, common sql ran without needing to
	# 2017-02-12 00:16:33 - trailing space removal

	$sqllog = array();

	# not logged in and not even guest mode?
	if (!is_logged_in(false)) {
		# then go to the login instead
		$request['page'] = 'login';
	}

	$js = array(
		'limit' => (int)$request['limit'],
		'start' => (int)$request['start'],
		'page' => $request['page']

	);

	if (!isset($request['page'])) die();

	function _array($array, $affected_columns) {
		return $array;
		# convert from iso to utf
		foreach ($array as $k1 => $unused) {
			foreach ($affected_columns as $k2) {
				if (isset($array[$k1][$k2])) {
					$array[$k1][$k2] = ($array[$k1][$k2]);
				}
			}

		}
		return $array;
	}
/*
	$sql = 'SELECT
				c.id,
				c.make,
				c.model,
				count(*) AS images
			FROM
				'.DATABASE_TABLES_PREFIX.'cameras AS c,
				'.DATABASE_TABLES_PREFIX.'media AS p
			WHERE
				c.id=p.id_cameras
			GROUP BY
				p.id_cameras
			ORDER
				BY make, model
			';
	$sqllog[] = $sql;
	$cameras = db_query($link, $sql);

	$sql =
			'SELECT
				YEAR(exposured) AS year
			FROM
				'.DATABASE_TABLES_PREFIX.'media
			GROUP BY
				YEAR(exposured)
			ORDER BY
				(exposured)
			';
	$sqllog[] = $sql;
	$years = db_query($link, $sql);

	$sql = 'SELECT
				l.id,
				l.title,
				r.amount
			FROM
				'.DATABASE_TABLES_PREFIX.'labels AS l
				LEFT JOIN (
					SELECT
						COUNT(*) AS amount, id_labels
					FROM
						'.DATABASE_TABLES_PREFIX.'relations_media_labels
					GROUP BY
						id_labels
				) AS r
				ON
					r.id_labels=l.id
			ORDER BY l.title';
	$sqllog[] = $sql;
	$labels = db_query($link, $sql);

	*/

	# find out what page to display
	switch ($request['page']) {

		# to find items -------------------------------------------------------------------------------------
		case 'find':

			$items = array();

			# no search words - get out
			# if (!strlen(trim($request['find']))) break;

			# split search words by space
			$sql_like = explode(' ', trim($request['find']));

			$sql_and = array();

			# walk the sql clauses and male
			foreach ($sql_like as $k => $v) {
				$v = trim($v);
				# no word at all - then go next
				if (!strlen($v)) continue;

				$sql_and[$k] = '(path LIKE "%'.dbres($link, ($v)).'%" OR name LIKE "%'.dbres($link, ($v)).'%")';
			}


			# dates
			if ($request['yearfrom'] !== false && strlen($request['yearfrom'])) {
				$sql_and[] = 'YEAR(exposured) >= '.(int)$request['yearfrom'];
			}

			if ($request['monthfrom'] !== false && strlen($request['monthfrom'])) {
				$sql_and[] = 'month(exposured) >= '.(int)$request['monthfrom'];
			}

			if ($request['dayfrom'] !== false && strlen($request['dayfrom'])) {
				$sql_and[] = 'day(exposured) >= '.(int)$request['dayfrom'];
			}

			if ($request['yearto'] !== false && strlen($request['yearto'])) {
				$sql_and[] = 'YEAR(exposured) <= '.(int)$request['yearto'];
			}

			if ($request['monthto'] !== false && strlen($request['monthto'])) {
				$sql_and[] = 'month(exposured) <= '.(int)$request['monthto'];
			}

			if ($request['dayto'] !== false && strlen($request['dayto'])) {
				$sql_and[] = 'day(exposured) <= '.(int)$request['dayto'];
			}


			# is both from and to specified, and the FROM date is bigger than the TO date?
			if ($request['hourfrom'] !== false && strlen($request['hourfrom']) && $request['hourto'] !== false && strlen($request['hourto']) && $request['hourfrom'] > $request['hourto']) {
					$sql_and[] = '(HOUR(exposured) >= '.(int)$request['hourfrom'].' OR '.'HOUR(exposured) <= '.(int)$request['hourto'].')';
			# or anything else
			} else {
				if ($request['hourfrom'] !== false && strlen($request['hourfrom'])) {
					$sql_and[] = 'HOUR(exposured) >= '.(int)$request['hourfrom'];
				}

				if ($request['hourto'] !== false && strlen($request['hourto'])) {
					$sql_and[] = 'HOUR(exposured) <= '.(int)$request['hourto'];
				}
			}

			# width and height
			if ($request['widthfrom'] !== false && strlen($request['widthfrom'])) {
				$sql_and[] = 'width >= '.(int)$request['widthfrom'];
			}

			if ($request['widthto'] !== false && strlen($request['widthto'])) {
				$sql_and[] = 'width <= '.(int)$request['widthto'];
			}

			if ($request['heightfrom'] !== false && strlen($request['heightfrom'])) {
				$sql_and[] = 'height >= '.(int)$request['heightfrom'];
			}

			if ($request['heightto'] !== false && strlen($request['heightto'])) {
				$sql_and[] = 'height <= '.(int)$request['heightto'];
			}

			if ($request['id_labels'] && (int)$request['id_labels'] > 0) {
				$sql_and[] = 'r.id_labels = '.(int)$request['id_labels'];
			} else if ($request['id_labels'] === '-1') {
				$sql_and[] = 'r.id IS NULL';
			}

			# make sure the items exists
			$sql_and[] = 'existing=1';

			# add cameras if it is specified
			if (is_numeric($request['id_cameras'])) {
				$sql_and[] = 'id_cameras='.(int)$request['id_cameras'];
			}

			# find out scending
			$request['scending'] = in_array($request['scending'], array('asc', 'desc')) ? $request['scending'] : 'desc';

			$sql = 'SELECT
						p.id,
						p.id_cameras,
						p.path,
						p.name,
						p.ed2khash,
						p.verified,
						p.existing,
						p.height,
						p.width,
						p.latitude,
						p.longitude,
						p.exposured
					FROM
						'.DATABASE_TABLES_PREFIX.'media AS p
						'.($request['id_labels'] || $request['id_labels'] === '-1'  ? ' LEFT JOIN '.DATABASE_TABLES_PREFIX.'relations_media_labels AS r ON r.id_media = p.id': '').'
					WHERE
						'.implode(' AND ', $sql_and).'
					ORDER BY
						CONCAT(p.path, p.name) '.$request['scending'].'
					LIMIT '.(int)$request['start'].','.(int)$request['limit'];
			$sqllog[] = $sql;

			$items = db_query($link, $sql);

			$items = _array($items, array('name','path'));

			# make sure the paths are fullpaths
			# $items = fullpaths($items, array('path'));

			# no items - then end here
			if (!count($items)) break;

			# --- items and labels

			$id_items = array();
			# collect item ids
			foreach ($items as $v) {
				$id_items[] = (int)$v['id'];
			}

			# get label relations related to the items
			$sql = '
					SELECT
						id_media,
						id_labels
					FROM
						'.DATABASE_TABLES_PREFIX.'relations_media_labels
					WHERE
						id_media IN ('.implode(',', $id_items).')
					';
			$sqllog[] = $sql;
			$relations = db_query($link, $sql);

			# were there any relations?
			if (count($relations)) {
				# walk items
				foreach ($items as $k => $v) {
					$labels = array();
					# walk relations
					foreach ($relations as $r) {
						# does this relation item id match the item id?
						if ((int)$r['id_media'] === (int)$v['id']) {
							# then add this label to the list of label ids
							$labels[] = (int)$r['id_labels'];
						}
					}
					# add the labels to this item
					$items[$k]['id_labels'] = $labels;
				}
			}



			break;

		case 'labels':

			$labels = db_query($link,
				'
				SELECT
					l.id,
					l.title,
					p.id AS id_first_item,
					count(p.id) AS amount
				FROM
					'.DATABASE_TABLES_PREFIX.'labels AS l,
					'.DATABASE_TABLES_PREFIX.'relations_media_labels AS r,
					'.DATABASE_TABLES_PREFIX.'media AS p
				WHERE
					l.id = r.id_labels
					AND
					r.id_media = p.id
					AND
					p.existing = 1
				GROUP BY
					l.id
				ORDER BY
					l.title
				'
				);

			break;

		# to display a folder -------------------------------------------------------------------------------------
		case 'folder':

			$folders = array();
			$items = array();

			$tmp_path = rawurldecode($request['path']);

			# no working path, get out
			if (
				# no length
				!mb_strlen(trim($tmp_path)) ||
				# not beginning with a slash
				mb_substr($tmp_path, 0,1) !== '/' ||
				# not ending with a slash
				mb_substr($tmp_path, -1) !== '/'
			) {
				# this will be false when failing to deliver
				die($tmp_path);
			}


			# glue the rootpath together with the relative path from the frontend
			# remove the first slash from the frontend path
			$tmp_path = ROOTPATH.mb_substr($tmp_path, 1);

			$sql =				'
				SELECT
					*
				FROM
					'.DATABASE_TABLES_PREFIX.'media
				WHERE
					path = "'.dbres($link, ($tmp_path)).'"
					AND
					existing=1
				ORDER BY CONCAT(path,name)
				';


			$sqllog[] = $sql;

			# get files exactly on this path
			$items = db_query($link, $sql);

			# extract an array consisting of name and path
			$items = _array($items, array('name','path'));

			foreach ($items as $k => $v) {
				$items[$k]['latitude'] = (float)$items[$k]['latitude'];
				$items[$k]['longitude'] = (float)$items[$k]['longitude'];
			}

			# --- items and labels

			$id_items = array();
			# collect item ids
			foreach ($items as $v) {
				$id_items[] = (int)$v['id'];
			}

			# were there any items?
			if (count($id_items)) {

				# get label relations related to the items
				$sql = 'SELECT
							id_media,
							id_labels
						FROM
							'.DATABASE_TABLES_PREFIX.'relations_media_labels
						WHERE
							id_media IN ('.implode(',', $id_items).')
						';
				$sqllog[] = $sql;
				$relations = db_query($link, $sql);

				# were there any relations?
				if (count($relations)) {
					# walk items
					foreach ($items as $k => $v) {
						$labels = array();
						# walk relations
						foreach ($relations as $r) {
							# does this relation item id match the item id?
							if ((int)$r['id_media'] === (int)$v['id']) {
								# then add this label to the list of label ids
								$labels[] = (int)$r['id_labels'];
							}
						}
						# add the labels to this item
						$items[$k]['id_labels'] = $labels;
					}
				}
			}

			# --- eof items and labels

			# find out scending
			$request['scending'] = in_array($request['scending'], array('asc', 'desc')) ? $request['scending'] : 'desc';


			# get folder names below this path
			# id_first_item is a hack here, as manually walking it takes too long
			# for some reason it "just works" to let the id flow through the select:s below

			# 2014-10-14 - BUG, SUBSTRING() is not multibyte and
			# therefore FAILS to count locale characters

			# 2014-10-15 - we must group by the old folderold path, otherwise
			# it won't get grouped

			# echo $tmp_path."\n";

/*
			$sql =
				'
				SELECT
					p.path AS folder,
					SUBSTRING_INDEX(
						SUBSTRING(p.path, '.(strlen($tmp_path) > 0 ? strlen($tmp_path) + 1: 0).'),
						"/",
						1
					) AS folderold,
					id AS id_first_item
				FROM
					(
					SELECT
						p1.id,
						p1.path
					FROM
						photos AS p1
					WHERE
						p1.path LIKE "'.dbres($link, ($tmp_path)).'%"
						AND p1.existing=1
					GROUP BY p1.path
					) AS p
				GROUP BY
					folderold
				ORDER BY folderold '.$request['scending'];
*/


			$sql =
				'
				SELECT
					p.path AS folder,
					SUBSTRING_INDEX(
						SUBSTRING(p.path, '.(mb_strlen($tmp_path) > 0 ? mb_strlen($tmp_path) + 1: 0).'),
						"/",
						1
					) AS folderold,
					id AS id_first_item
				FROM
					(
					SELECT
						p1.id,
						p1.path
					FROM
						'.DATABASE_TABLES_PREFIX.'media AS p1
					WHERE
						p1.path LIKE "'.dbres($link, ($tmp_path)).'%"
						AND p1.existing=1
					GROUP BY p1.path
					) AS p
				GROUP BY
					folderold
				ORDER BY folderold '.$request['scending'];

			$sqllog[] = $sql;

			$folders = db_query($link, $sql);

			foreach ($folders as $k => $v) {
				$folders[$k]['folder'] = mb_substr($v['folder'], mb_strlen($tmp_path));

				$folders[$k]['folder'] = mb_substr($folders[$k]['folder'], 0, mb_strpos($folders[$k]['folder'], '/'));
			}

			# make sure the paths are fullpaths
			# $items = fullpaths($items, array('folder'));

			$tmp = array();
			foreach ($folders as $k => $v) {
				if ($folders[$k]['folder'] === '') continue;
				$tmp[] = $v;
			}
			$folders = $tmp;

			# extract folder
			$folders = _array($folders, array('folder'));

			break;

		# to display an item -------------------------------------------------------------------------------------
		case 'item':

			if (!isset($request['id_media']) || !is_numeric($request['id_media'])) {
				die('id_media must be supplied.');
			}
			$sql = 'SELECT
						*
					FROM
						'.DATABASE_TABLES_PREFIX.'media
					WHERE
						id = "'.dbres($link, $request['id_media']).'"
						AND existing=1
					ORDER BY
						CONCAT(path,name)
					LIMIT 1';
			$sqllog[] = $sql;
			$item = db_query($link, $sql);
			$item = _array($item, array('name','path'));

			# make sure the paths are fullpaths
			# $item = fullpaths($item, array('path'));


			$item = count($item) ? $item[0] : false;

			$request['path'] = mb_substr($item['path'], mb_strlen(ROOTPATH));


			# walk possible raw file extensions
			$item['raws'] = array();
			foreach (array(
				'3FR',
				'ARI',
				'ARW',
				'BAY',
				'CAP',
				'CR2',
				'CRW',
				'DATA',
				'DCR',
				'DCS',
				'DNG',
				'DRF',
				'EIP',
				'ERF',
				'FFF',
				'IIQ',
				'K25',
				'KDC',
				'MDC',
				'MEF',
				'MOS',
				'MRW',
				'NEF',
				'NRW',
				'OBM',
				'ORF',
				'PEF',
				'PTX',
				'PXN',
				'R3D',
				'RAF',
				'RAW',
				'RW2',
				'RWL',
				'RWZ',
				'SR2',
				'SRF',
				'SRW',
				'X3F'
			) as $k => $v) {
				# get raw ext path
				$rawpath = $item['path'].substr($item['name'],0,strrpos($item['name'],'.')).'.'.$v;

				# does this raw file exist?
				if (file_exists(($rawpath))) {
					$item['raws'][] = array(
						'ext'	=> $v,
						'size'	=> filesize($rawpath)
					);
				}
			}

			$item['size'] = filesize(($item['path'].$item['name']));


			# is it in trash?

			$sql = 'SELECT 1 FROM '.DATABASE_TABLES_PREFIX.'trash WHERE id_photos="'.dbres($link, $request['id_media']).'"';
			$sqllog[] = $sql;
			$trash = db_query($link, $sql);
			$item['trash'] = count($trash) ? true : false;

			# get label relations related to this item
			$sql = 'SELECT id_media, id_labels FROM '.DATABASE_TABLES_PREFIX.'relations_media_labels WHERE id_media="'.dbres($link, $request['id_media']).'"';
			$sqllog[] = $sql;
			$relations = db_query($link, $sql);

			$item['id_labels'] = array();

			# were there any relations?
			if (count($relations)) {
					# walk relations
					foreach ($relations as $r) {
						# does this relation item id match the item id?
						if ((int)$r['id_media'] === (int)$request['id_media']) {
							# then add this label to the list of label ids
							$item['id_labels'][] = (int)$r['id_labels'];
						}
					}
			}

			# calculate prev item id
			$sql = '
			SELECT
				id
			FROM
				'.DATABASE_TABLES_PREFIX.'media
			WHERE
				CONCAT(path,name) < "'.dbres($link, ($item['path'].$item['name'])).'"
				AND existing=1
			ORDER BY
				CONCAT(path,name) DESC
			LIMIT 1';
			$sqllog[] = $sql;

			# calculate next item id
			$r = db_query($link, $sql);
			$js['id_prev'] = (count($r)) ? (int)$r[0]['id'] : 0;

			$sql = '
			SELECT
				id
			FROM
				'.DATABASE_TABLES_PREFIX.'media
			WHERE
				CONCAT(path,name) > "'.dbres($link, ($item['path'].$item['name'])).'"
				AND existing=1
			ORDER BY
				CONCAT(path,name)
			LIMIT 1';
			$sqllog[] = $sql;

			$r = db_query($link, $sql);
			$js['id_next'] = (count($r)) ? (int)$r[0]['id'] : 0;

			break;

		case 'login':
			# goto login page
			header('Location: http://www.'.BASE_DOMAINNAME.'/?section=visum&id_sites='.ID_VISUM);
			die();

		# to send a file -------------------------------------------------------------------------------------
		case 'get': # get a file


			$sql = 'SELECT * FROM '.DATABASE_TABLES_PREFIX.'media WHERE id = "'.dbres($link, $request['id_media']).'" AND existing=1 LIMIT 1';
			$sqllog[] = $sql;
			$item = db_query($link, $sql);




			if (!count($item)) die('Not found');


			$item = $item[0];
			# guess MIME
			switch (strtolower(substr($item['name'], strrpos($item['name'], '.') + 1 ))) {
				case 'jpg':
				case 'jpeg':
					$mime = 'image/jpeg';
					break;
				case 'gif':
					$mime = 'image/gif';
					break;
				case 'png':
					$mime = 'image/x-png';
					break;
				case 'tif':
				case 'tiff':
					$mime = 'image/tiff';
					break;
				case 'avi':
				case 'mp4':
				case 'mp3':
				case 'mpg':
				case 'mpeg':
					$mime = mime_content_type($item['path'].$item['name']);
					break;
				default:
					$mime = 'text/plain';
					break;
			}



			# update views counter
			$sql = 'UPDATE '.DATABASE_TABLES_PREFIX.'media SET views=views+1 WHERE id="'.dbres($link, $request['id_media']).'"';
			$sqllog[] = $sql;
			$update_counter = db_query($link, $sql);



			set_time_limit(0);


			# is this an image and we do not want original version?
			if (strpos($mime, 'image/') !== false && $request['version'] !== 'original') {

				switch ($request['version']) {
					case 'raw':


						$rawextension = preg_replace("/[^A-Za-z0-9 ]/", '', $request['rawextension']);

						if (!strlen($rawextension)) {
							die('Missing rawextension parameter.');
						}


						$filepath = $item['path'].substr($item['name'],0,strrpos($item['name'],'.')).'.'.$rawextension;
						if (!file_exists($filepath)) die('File not found.');

						header('Content-Type: image/x-raw');
						header('Content-Disposition: inline; filename="'.basename($filepath).'"');
						readfile($filepath);
						die();

					case 'small':
						# prepare thumbnails
						$success = make_thumbnails($item['ed2khash'], $item['path'].$item['name']);
						$success = true;

						if ($success) {
							# get path for this
							$filepath = get_thumbnail_path($item['ed2khash'], $request['version']);
						} else {
							$filepath = 'img/convert_160x120.jpg';
						}

						header('Content-Type: image/jpeg');
						header('Content-Disposition: inline; filename="'.basename($filepath).'"');
						readfile($filepath);
						die();

					case 'normal':
						# prepare thumbnails
						$success = make_thumbnails($item['ed2khash'], $item['path'].$item['name']);

						if ($success) {
							# get path for this
							$filepath = get_thumbnail_path($item['ed2khash'], $request['version']);
						} else {
							$filepath = 'img/convert_1024x768.jpg';
						}

						header('Content-Type: image/jpeg');
						header('Content-Disposition: inline; filename="'.basename($filepath).'"');
						readfile($filepath);
						die();

					default:
						die('Unidentified version: '.$request['version']);

				}

			} else if (strpos($mime, 'video/') !== false && $request['version'] !== 'original') {


				switch ($request['version']) {
					/*case 'raw':


						$rawextension = preg_replace("/[^A-Za-z0-9 ]/", '', $request['rawextension']);

						if (!strlen($rawextension)) {
							die('Missing rawextension parameter.');
						}


						$filepath = $item['path'].substr($item['name'],0,strrpos($item['name'],'.')).'.'.$rawextension;
						if (!file_exists($filepath)) die('File not found.');

						header('Content-Type: image/x-raw');
						header('Content-Disposition: inline; filename="'.basename($filepath).'"');
						readfile($filepath);
						die();*/

					case 'small':
						# prepare thumbnails
						$success = make_thumbnails($item['ed2khash'], $item['path'].$item['name']);
						$success = true;

						if ($success) {
							# get path for this
							$filepath = get_thumbnail_path($item['ed2khash'], $request['version']);
						} else {
							$filepath = 'img/convert_160x120.jpg';
						}

						header('Content-Type: image/jpeg');
						header('Content-Disposition: inline; filename="'.basename($filepath).'"');
						readfile($filepath);
						die();

					case 'normal':
						# prepare thumbnails
						$success = make_thumbnails($item['ed2khash'], $item['path'].$item['name']);

						if ($success) {
							# get path for this
							$filepath = get_thumbnail_path($item['ed2khash'], $request['version']);
						} else {
							$filepath = 'img/convert_1024x768.jpg';
						}

						header('Content-Type: image/jpeg');
						header('Content-Disposition: inline; filename="'.basename($filepath).'"');
						readfile($filepath);
						die();

					default:
						die('Unidentified version: '.$request['version']);

				}
			} else {
				header('Content-Type: '.$mime);
				header('Content-Disposition: inline; filename="'.$item['name'].'"');
				readfile(($item['path'].$item['name']));
			}

			die();

	}

?>
