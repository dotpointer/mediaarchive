<?php

	# changelog
	# 2013-09-21 - first version
	# mån, sep 23 2013 03:59:57
	# 2013-10-06 - adding scending button
	# 2014-09-06 13:02:49 - adding trash
	# 2015-05-10 22:45:06
	# 2015-08-25 10:47:59
	# 2015-11-04 15:44:10 - adding cameras
	# 2016-09-13 10:49:22 - guest mode
	# 2016-09-14 19:50:52 - absolute path to media
	# 2016-09-15 03:41:24 - absolute page to media - find
	# 2017-02-12 00:14:38 - trailing space removal

	if (!isset($request['page'])) die();

	# find out what view to display
	switch ($request['page']) {
		case 'find':

			if (!is_logged_in(false)) {
				die(json_encode(array(
					'status' => 'false',
					'data' => array(
						'error' => 'Login is required.'
					),
					(int)$request['prettyprint'] ? JSON_PRETTY_PRINT : 0
				)));
			}

			$curpath = false;
			# walk all items

			$data = array();

			$folders=0;
			$folderindex = -1;
			foreach ($items as $item) {

				# strip root path
				$item['path'] = mb_substr($item['path'], mb_strlen(ROOTPATH) - 1);


				if ($item['path'] !== $curpath) {
					$folderindex++;

					$folders++;
					# set the current path so we know til next time
					$curpath = $item['path'];

					$data[$folderindex] = array(
						'path' => $item['path'],
						'items'=> array()
					);

				} # eof-if-new folder

				$data[$folderindex]['items'][] = $item;
			}

			$data['sql'] = $sqllog;

			die(json_encode(
				array(
				'status' => 'true',
				'data' => $data
				),
				(int)$request['prettyprint'] ? JSON_PRETTY_PRINT : 0
			));


		case 'labels': # to display labels ------------------------------------------------------------------------------------

			if (!is_logged_in(false)) {
				die(json_encode(
					array(
						'status' => 'false',
						'data' => array(
							'error' => 'Login is required.'
						)
					),
					(int)$request['prettyprint'] ? JSON_PRETTY_PRINT : 0
				));
			}

			die(json_encode(
				array(
					'status' => 'true',
					'data' => array(
						'labels' => $labels,
						'sqllog' => $sqllog
					)
				),
				(int)$request['prettyprint'] ? JSON_PRETTY_PRINT : 0
			));

		case 'folder': # to display a folder with folders and items ------------------------------------------------------------------------------------

			if (!is_logged_in(false)) {
				die(json_encode(
					array(
						'status' => 'false',
						'data' => array(
							'error' => 'Login is required.'
						)

					),
					(int)$request['prettyprint'] ? JSON_PRETTY_PRINT : 0
				));
			}
			die(json_encode(
					array(
						'status' => 'true',
						'data' => array(
							'folders' => $folders,
							'items' => $items,
							'sqllog' => $sqllog
						)
					),
					# JSON_PRETTY_PRINT
					(int)$request['prettyprint'] ? JSON_PRETTY_PRINT : 0
				)
			);

		case 'item': # to display an item ------------------------------------------------------------------------------------------------------------

			if (!is_logged_in(false)) {
				die(json_encode(array(
					'status' => 'false',
					'data' => array(
						'error' => 'Login is required.'
					),
					(int)$request['prettyprint'] ? JSON_PRETTY_PRINT : 0
				)));
			}


			if (!$item) {

				if (!is_logged_in(false)) {
					die(json_encode(array(
						'status' => 'false',
						'data' => array(
							'error' => 'Not found.'
						),
						(int)$request['prettyprint'] ? JSON_PRETTY_PRINT : 0
					)));
				}

			}

			$data = @exif_read_data(($item['path'].$item['name']));

			$data_short = array();

			if (isset($data['Make'], $data['Model'])) {
				$data_short[] = array(
					'title' => ('Camera'),
					'value' => strpos($data['Model'], $data['Make']) !== false ? $data['Model'] : $data['Make'].' '.$data['Model']
				);
			}

			if (isset($data['DateTime'])) {

				$datetime = get_exposure_date($data, false);

				if ($datetime) {
					$data_short[] = array(
						'title' => ('Date'),
						'value' => substr($datetime, 0, strpos($datetime, ' '))
					);


					$data_short[] = array(
						'title' => ('Time'),
						'value' => substr($datetime, strpos($datetime, ' '))
					);

				}
			}


			if (isset($data['ShutterSpeedValue'])) {
				$data_short[] = array(
					'title' => ('Shutter'),
					'value' => exif_get_shutter($data)
				);
			# sony/samsung version of shutter speed
			} else if (isset($data['ExposureTime'])) {

				# formulas from: http://stackoverflow.com/questions/3049998/parsing-exifs-exposuretime-using-php
				$parts = explode('/', $data['ExposureTime']);
				$exposure = implode('/', array(1, round($parts[1]/$parts[0])));


				$data_short[] = array(
					'title' => ('Shutter'),
					'value' => $exposure.'s'
				);
			}

			if (isset($data['ApertureValue'])) {
				$data_short[] = array(
					'title' => ('Aperture'),
					'value' => exif_get_fstop($data)
				);
			} else if (isset($data['FNumber'])) {
				$tmp = array();
				$tmp['ApertureValue'] = $data['FNumber'];
				$data_short[] = array(
					'title' => ('Aperture'),
					'value' => exif_get_fstop($tmp)
				);
			}

			if (isset($data['ISOSpeedRatings'])) {
				$data_short[] = array(
					'title' => ('ISO'),
					'value' => $data['ISOSpeedRatings']
				);
			}


			if (isset($data['FocalLength'])) {
				$tmp = explode('/', $data['FocalLength']);
				$tmp = round($tmp[0] / $tmp[1], 1);
				$data_short[] = array(
					'title' => ('Focal length'),
					'value' => $tmp.'mm'
				);
			}

			if (isset($data['ExposureBiasValue'])) {
				$tmp = explode('/', $data['ExposureBiasValue']);
				$tmp = round($tmp[0] / $tmp[1], 2);
				$data_short[] = array(
					'title' => ('Exposure compensation'),
					'value' => $tmp.' EV'
				);
			}

			if (isset($data['ExposureProgram'])) {

				# list of programs according to http://www.awaresystems.be/imaging/tiff/tifftags/privateifd/exif/exposureprogram.html
				$tmp = array(
						0 => ('Not defined'),
						1 => ('Manual'),
						2 => ('Normal program'),
						3 => ('Aperture priority'),
						4 => ('Shutter priority'),
						5 => ('Creative program'), # (biased toward depth of field)
						6 => ('Action program'), # (biased toward fast shutter speed)
						7 => ('Portrait mode'), # (for closeup photos with the background out of focus)
						8 => ('Landscape mode') # (for landscape photos with the background in focus)
				);

				$data_short[] = array(
					'title' => ('Exposure program'),
					'value' => array_key_exists((int)$data['ExposureProgram'], $tmp) ? $tmp[(int)$data['ExposureProgram']] : ('Unknown').' ('.$data['ExposureProgram'].')'
				);
			}

			# https://www.maketecheasier.com/managing-exif-data-from-command-line/
			#0=Flash did not fire
			#1=Flash fired
			#5=Strobe return light not detected
			#7=Strobe return light detected
			#9=Flash fired, compulsory flash mode
			#13=Flash fired, compulsory flash mode, return light not detected
			#15=Flash fired, compulsory flash mode, return light detected
			#16=Flash did not fire, compulsory flash mode
			#24=Flash did not fire, auto mode
			#25=Flash fired, auto mode
			#29=Flash fired, auto mode, return light not detected
			#31=Flash fired, auto mode, return light detected
			#32=No flash function
			#65=Flash fired, red-eye reduction mode
			#69=Flash fired, red-eye reduction mode, return light not detected
			#71=Flash fired, red-eye reduction mode, return light detected
			#73=Flash fired, compulsory flash mode, red-eye reduction mode
			#77=Flash fired, compulsory flash, red-eye reduction, no return light
			#79=Flash fired, compulsory, red-eye reduction, return light detected
			#89=Flash fired, auto mode, red-eye reduction mode
			#93=Flash fired, auto mode, no return light, red-eye reduction
			#95=Flash fired, auto mode, return light detected, red-eye reduction

			if (isset($data['Flash'])) {
				$data_short[] = array(
					'title' => ('Flash'),
					'value' => in_array((int)$data['Flash'], array(1,9,13,15,25,29,31,65,69,71,73,77,79,89,93,95)) ? ('Yes') : ('No')
				);
			}


			if (is_array($data)) {
				array_walk_recursive($data, function(&$value, $key) {
					if (is_string($value)) {
						$value = utf8_encode($value);
					}
				});
			}

			if (is_array($data)) {
				array_walk_recursive($data_short, function(&$value, $key) {
					if (is_string($value)) {
						$value = utf8_encode($value);
					}
				});
			}

			$item['exif'] = $data;
			$item['exif_short'] = $data_short;
			$item['id_next'] = $js['id_next'];
			$item['id_prev'] = $js['id_prev'];
			$item['latitude'] = (float)$item['latitude'];
			$item['longitude'] = (float)$item['longitude'];

			$item['path'] = mb_substr($item['path'], mb_strlen(ROOTPATH) - 1);

			die(json_encode(array(
					'status' => 'true',
					'data' => array(
						'item' => $item,
						'sqllog' => $sqllog
					)
				),
				(int)$request['prettyprint'] ? JSON_PRETTY_PRINT : 0
			));
	}

?>
		</div>
	</div>
</body>
</html>
