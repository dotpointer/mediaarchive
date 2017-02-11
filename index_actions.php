<?php

	# changelog
	# 2013-09-23 - creating file
	# 2014-09-06 13:02:35 - adding trash
	# 2014-10-31 02:28:13 visum moved to vps01, but mediaarchive is still locally at rckserver, so activating visum over http
	# 2015-12-04 12:53:26 - reloading page after login from visum
	# 2016-09-13 16:56:01 - adding database tables prefix
	# 2016-09-15 03:28:53
	# 2016-09-17 16:19:44 - table photos->media
	# 2017-02-12 00:14:02 - trailing space removal

	if (!isset($request['action'])) die();

	switch ($request['action']) {

		case 'label_media': # lr - to relate media to labels

			if (!is_logged_in(true)) break;

			# id_media may be an array
			$request['id_labels'] = (int)$request['id_labels'];
			$request['title'] = trim($request['title']);

			$updates = array();

			if (!$request['id_media']) {
				die(json_encode(
					array(
						'status' => false,
						'data' => array(
							'error' => 'ID of media must be supplied.'
						)
					),
					(int)$request['prettyprint'] ? JSON_PRETTY_PRINT : 0
				));
			}

			if (!$request['id_labels'] && !strlen($request['title'])) {
				die(json_encode(
					array(
						'status' => false,
						'data' => array(
							'error' => 'ID of label or title must be supplied.'
						)
					),
					(int)$request['prettyprint'] ? JSON_PRETTY_PRINT : 0
				));
			}

			# does label exist by id?

			#  && ($request['title'] === '' || !strlen($request['title']))
			if ($request['id_labels']) {
				$sql = 'SELECT * FROM '.DATABASE_TABLES_PREFIX.'labels WHERE id="'.dbres($link, $request['id_labels']).'"';
				$r = db_query($link, $sql);
				if (!count($r)) die(json_encode(
					array(
						'status' => false,
						'data' => array(
							'error' => 'Label with ID '.$request['id_labels'].' does not exist.'
						)

					),
					(int)$request['prettyprint'] ? JSON_PRETTY_PRINT : 0
				));

			# or does it exist by title?
			} else {
				$sql = 'SELECT * FROM '.DATABASE_TABLES_PREFIX.'labels WHERE LOWER(title)=LOWER("'.dbres($link, $request['title']).'")';
				$r = db_query($link, $sql);

				# does it exist in labels?
				if (count($r)) {
					$request['id_labels'] = (int)$r[0]['id'];
				# or is it new?
				} else {

					# add it to labels
					$iu = array(
						'id_users' => $_SESSION[SITE_SHORTNAME]['user']['id'],
						'title' => $request['title'],
						'title_short' => substr($request['title'], 0, 3),
						'created' => date('Y-m-d H:i:s'),
						'updated' => date('Y-m-d H:i:s')
					);
					$iu = dbpia($link, $iu);
					$sql = 'INSERT INTO '.DATABASE_TABLES_PREFIX.'labels ('.implode(',', array_keys($iu)).') VALUES('.implode(',', $iu).')';
					db_query($link, $sql);

					$request['id_labels'] = mysql_insert_id($link);
					if ($request['id_labels'] === false) {
						die(json_encode(
							array(
								'status' => false,
								'data' => array(
									'error' => 'Failed getting insertion id for label: '.db_error($link)
								)
							),
							(int)$request['prettyprint'] ? JSON_PRETTY_PRINT : 0
						));
					}
				}



			}

			# is this media already related?

			$status_insertions = 0;

			$request['id_media'] = explode(',', $request['id_media']);
			foreach ($request['id_media'] as $v) {
				$sql = 'SELECT * FROM '.DATABASE_TABLES_PREFIX.'relations_media_labels WHERE id_media="'.dbres($link, $v).'" AND id_labels="'.dbres($link, $request['id_labels']).'"';
				$r = db_query($link, $sql);

				if (!count($r)) {
					$iu = array(
						'id_labels' => $request['id_labels'],
						'id_media' => $v,
						'id_users' => $_SESSION[SITE_SHORTNAME]['user']['id'],
						'created' => date('Y-m-d H:i:s')
					);
					$iu = dbpia($link, $iu);
					$sql = 'INSERT INTO '.DATABASE_TABLES_PREFIX.'relations_media_labels ('.implode(',', array_keys($iu)).') VALUES('.implode(',', $iu).')';
					$r = db_query($link, $sql);

					$status_insertions++;
				}
			}

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
			$updates['labels'] = db_query($link, $sql);


			$sql = 'SELECT COUNT(distinct id_media) AS labeled_media from '.DATABASE_TABLES_PREFIX.'relations_media_labels';
			$label_statistics['labeled_media'] = db_query($link, $sql);
			$label_statistics['labeled_media'] = $label_statistics['labeled_media'][0]['labeled_media'];
			$sql = 'SELECT COUNT(a.id) AS total_media FROM '.DATABASE_TABLES_PREFIX.'media AS a';
			$label_statistics['total_media'] = db_query($link, $sql);
			$label_statistics['total_media'] = $label_statistics['total_media'][0]['total_media'];


			$updates['label_statistics'] = $label_statistics;

			die(json_encode(
				array(
					'status' => true,
					'data' => array(
						'updates' => $updates,
						'refresh_view' => true,
						'status_insertions' => $status_insertions
					)
				),
				(int)$request['prettyprint'] ? JSON_PRETTY_PRINT : 0
			));
			break;

		case 'unlabel_media': # lr - to remove labels from media

			if (!is_logged_in(true)) break;

			$id_media = $request['id_media']; # may be an array
			$id_labels = (int)$request['id_labels'];

			if (!$id_media) {
				die('ID of media must be supplied.');
			}

			if (!$id_labels) {
				die('ID of label must be supplied.');
			}

			$id_media = explode(',', $id_media);
			$id_media = dbpia($link, $id_media);

			$sql = 'DELETE FROM '.DATABASE_TABLES_PREFIX.'relations_media_labels WHERE id_labels="'.dbres($link, $id_labels).'" AND id_media IN ('.implode(',', $id_media).')';
			db_query($link, $sql);


			$label_statistics = array();
			$sql = 'SELECT COUNT(distinct id_media) AS labeled_media from '.DATABASE_TABLES_PREFIX.'relations_media_labels';
			$label_statistics['labeled_media'] = db_query($link, $sql);
			$label_statistics['labeled_media'] = $label_statistics['labeled_media'][0]['labeled_media'];
			$sql = 'SELECT COUNT(a.id) AS total_media FROM '.DATABASE_TABLES_PREFIX.'media AS a';
			$label_statistics['total_media'] = db_query($link, $sql);
			$label_statistics['total_media'] = $label_statistics['total_media'][0]['total_media'];



			die(json_encode(
				array(
					'status' => true,
					'data' => array(
						'updates' => array(
						'label_statistics' => $label_statistics
						)
					)
				),
				(int)$request['prettyprint'] ? JSON_PRETTY_PRINT : 0
			));

			break;

		case 'untrash':	# lr - to put files out of trash
		case 'trash':	# lr - to trash items
			if (!is_logged_in(true)) break;

			if (!$request['id_media']) die('ID of media must be supplied.');

			$ids = explode(',', $request['id_media']);

			# put into trash
			if ($request['action'] === 'trash') {
				$data = array(
					'trashed' => 0,
					'already_in_trash' => 0
				);

				# walk the ids
				foreach ($ids as $thisid) {
					# make sure it is numeric
					if (!is_numeric($request['id_media'])) continue;

					# is it already trashed?
					$sql = 'SELECT 1 FROM '.DATABASE_TABLES_PREFIX.'trash WHERE id_photos="'.dbres($link, $thisid).'"';
					$r = db_query($link, $sql);
					if ($r === false) {
						# did it fail?
						die(json_encode(
							array(
								'status' => false,
								'data' => array(
									'error' => db_error($link)
								)
							),
							(int)$request['prettyprint'] ? JSON_PRETTY_PRINT : 0
						));
					}

					# already trashed - go next
					if (count($r)) {
						continue;
						$data['already_in_trash']++;
					}

					# make insert-update-array
					$iu = array(
						'id_photos' => $thisid,
						'created' => date('Y-m-d H:i:s')
					);

					# make insertion array
					$iu = dbpia($link, $iu);

					# insert into the trash
					$sql = 'INSERT INTO '.DATABASE_TABLES_PREFIX.'trash ('.implode(',', array_keys($iu)).') VALUES('.implode(',', $iu).')';
					$r = db_query($link, $sql);
					if ($r === false) {
						# did it fail?
						die(json_encode(
							array(
								'status' => false,
								'data' => array(
									'error' => db_error($link)
								)
							),
							(int)$request['prettyprint'] ? JSON_PRETTY_PRINT : 0
						));
					}
					$data['trashed']++;
				} # foreach-photos

				# return json
				/*
				die(json_encode(array(
					'status' => true,
					'data' => $data
				),(int)$request['prettyprint'] ? JSON_PRETTY_PRINT : 0));
				*/
			# put out of trash?
			} else if ($request['action'] === 'untrash') {
				# walk the ids
					foreach ($ids as $k => $v) {
						# quote em
						$ids[$k] = dbres($link, $v);
					}
					# is it already trashed?
					$sql = 'DELETE FROM '.DATABASE_TABLES_PREFIX.'trash WHERE id_photos IN ('.implode(', ', $ids).')';
					$r = db_query($link, $sql);
					if ($r === false) {
						# did it fail?
						die(json_encode(
							array(
								'status' => false,
								'data' => array(
									'error' => db_error($link)
								)
							),
							(int)$request['prettyprint'] ? JSON_PRETTY_PRINT : 0
						));
					}

			}

			break;

		case 'login': # lnr - to login

			# 2011-09-18 - visum based :)
			if (is_logged_in(true)) break;
			if (!$request['ticket']) die('Missing ticket.');
			$method='http';
			if ($method === 'http') {
				# this is what is needed to get Visum login over HTTP
				require_once('class-visum.php');
				 $visum = new Visum();
				# var_dump($visum->getUserByTicket($request['ticket']));
			} else if ($method === 'direct') {
				# this is what is needed to get Visum login directly
				#define('DATABASE_NAME', 'visum'); # just because base wants this
				#require_once('base.php'); # needed because of connection functions and such
				# require_once('../include/functions.php'); # visum functionality used for direct communication
				require_once('class-visum.php'); # visum client class
				#file_get_contents('class-visum.php');
				#$link = db_connect();
				# m-ysql_set_charset('utf8', $link);
				$visum = new Visum(VISUM_METHOD_DIRECT, $link);
			}

			try {
				$visum_user = $visum->getUserByTicket($request['ticket']);
			} catch(VisumException $e) {
				$t = $e->getResponseArray();
				die('Error: '.$t['error']);
			} catch(Exception $e) {
				die($e->getMessage());
			}


			if (!isset($visum_user['id_users'])) {
				die('Missing user id in visum response.');
			}

			$id_visum = $visum_user['id_users'];

			# update local credentials with what we got from visum
			$iu = array();
			# scan visum response for credentials to update
			foreach (array('gender','nickname','birth') as $k => $v) {
				if (!isset($visum_user[$v])) continue;
				# put it into the update array
				$iu[$v] = $visum_user[$v];
			}


			# was there anything to update supplied?
			if (count($iu) > 0) {
				$iu['updated'] = date('Y-m-d H:i:s');
				$iu = dbpua($link, $iu);
				$sql = 'UPDATE '.DATABASE_TABLES_PREFIX.'users SET '.implode(',',$iu).' WHERE id_visum="'.dbres($link, $id_visum).'"';
				$r = db_query($link, $sql);
			}

			# try to find the user, did it exist in local db?
			$sql = 'SELECT * FROM '.DATABASE_TABLES_PREFIX.'users WHERE id_visum="'.dbres($link, $id_visum).'"';
			$r = db_query($link, $sql);

			# mysql_result_as_array($result, $users);
			if (count($r) < 1) die(_('No user found in local db.'));
			$user = reset($r);

			# this means user is logged in
			$_SESSION[SITE_SHORTNAME]['user'] = $user;

			# now we have a visum user id to match against our own database and then create a login, that's all that is needed

			# reload page
			header('Location: ./');
			?><a href="./">Click here to continue</a><?php
			die();

		case 'logout': # lr - to logout
			if (!is_logged_in(true)) { report_sysmessage(SYSMESSAGE_NOTICE, 'Redan utloggad.'); $request['page']=''; break; }
			$_SESSION[SITE_SHORTNAME]['user'] = false;
			unset($_SESSION[SITE_SHORTNAME]['user']);
			break;
	}
?>
