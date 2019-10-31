<?php
  # 2013-09-22 14:18:33 - first version?
  # 2013-10-06 17:54 - adding image width and height
  # 2013-10-17 - bugfix where inserted items were not set as validated or existing
  # 2014-06-13 - adding verbose parameter to make quiet cronjob
  # 2016-09-13 16:56:18 - adding database tables prefix
  # 2016-09-17 12:00:11
  # 2016-09-17 16:21:59 - table photos->media
  # 2016-09-18 11:09:37 - bugfix, explode made extra empty lines
  # 2016-09-18 11:54:49 - thumbnailing, updating display with progressbar
  # 2017-02-12 00:14:52 - trailing space removal
  # 2017-05-24 20:11:45 - bugfix, make_thumbnails returned invalid values
  # 2018-06-16 01:05:11
  # 2018-07-19 18:47:16 - indentation change, tab to 2 spaces
  # 2019-10-31 20:54:00 - bugfix, variable initialization

  require_once('include/functions.php');

  # to output data
  function o($s, $newline="\n") {
    global $verbose;
    if ($verbose) {
      echo $s.($newline ? $newline : '');
    }
  }

  function progressbar($part, $total) {

    # character length for progress bar
    $pb_length = 10;

    # calculate the part
    $pb_part = round( ($part > 0 && $total > 0) ? ($part/$total) * $pb_length : 0);
    # calculate the total
    $pb_total = $pb_length;

    return
      '['.
      str_repeat('#', ($part < $total && $pb_part > 0) ? $pb_part - 1 : $pb_part ).

      ($part < $total && $pb_part > 0 ? '>' : '').

      str_repeat('.', $pb_total - $pb_part).
      '] '.
      # the first number is the actual amount of characters in the
      # resulting string, and we want 000.000, so that's 7
      sprintf("%07.3f", ($part > 0 && $total > 0) ? ($part / $total) * 100 : 0, 1).
      '% '.
      str_pad($part, strlen($total), '0', STR_PAD_LEFT).
      '/'.
      $total.
      ' '
      ;
  }

  # VERY important, otherwise find won't find any filenames (but find folders!) with non-utf-8 names
  $locale = 'sv_SE.ISO-8859-1';
  setlocale(LC_ALL, $locale);
  putenv('LC_ALL='.$locale);

  # check who is running this script
  $username = trim(shell_exec('whoami'));
  if (!strlen(trim($username))) die('Could not check username');
  if ($username === 'root') die('Cannot run as root, thumbs/folders may get wrong username.'."\n");

  # get the options
  $opts = getopt('va:');
  $action = false;
  $verbose = false;

  # walk options
  foreach ($opts as $k => $v) {
    # find out if it matches any of the expected options
    switch ($k) {
      case 'a': # action
        if (strlen($v) < 3) die('Invalid action'."\n");
        $action = $v;
        break;
      case 'v': # verbose
          $verbose = true;
          break;
    }
  }

  if (!$action) die('No action given, use -a [action]');

  # find out what action to take
  switch ($action) {
    case 'index': # to index files
      o('Starting action '.$action);
      # remove limit as this may take longer than default limit
      set_time_limit(0);

      # root path for images
      $rootpath = ROOTPATH;
      chdir($rootpath);

      # get a list of files
      # $cmd = 'find '.escapeshellarg($rootpath).' \( -type f -iname "*.JPG" \) -o \( -type f -iname "*.JPEG" \} -o \( -type f -iname "*.TIF" \) -o \( -type f -iname "*.TIFF" \)';
      # $cmd = 'find '.escapeshellarg($rootpath).' -type f -iname "*.JPG" -o -iname "*.JPEG" -o -iname "*.TIF" -o -iname "*.TIFF"';

      $exts = array();

      $imgexts = array(
        'jpg',
        'jpeg',
        'tif',
        'tiff'
      );

      $videoexts = array(
        'avi',
        '3gp',
        'mpg',
        'mpeg',
        'mp4',
        'mov'
      );

      foreach (array_merge($imgexts, $videoexts) as $v) {
        $exts[] = '-iname "*.'.$v.'"';
      }

      # $cmd = 'find '.escapeshellarg($rootpath).' -type f \( -iname "*.JPG" -o -iname "*.JPEG" -o -iname "*.TIF" -o -iname "*.TIFF" \)';

      $cmd = 'find '.escapeshellarg($rootpath).' -type f \( '.implode(' -o ', $exts).' \)';
      o('Running: '.$cmd);

      $files = shell_exec($cmd);
      if ($files === false) {
        die('Could not do find.');
      }

      $files = explode("\n", $files);

      # explode leave empty lines (at the end)
      # we do this as there is no multibyte trim
      $files = array_filter($files, function($value) {
        return (mb_strlen($value) > 0);
      });

      $new_files = array();
      # walk files
      foreach ($files as $k => $v) {
        # make the relative paths to absolute paths if possible
        $realpath = realpath($v);
        # make sure there is a path
        if ($realpath === false) continue;
        # make sure it is a file and not a folder, should not happen but test it anyway
        if (!is_file($realpath)) continue;

        $new_files[] = $realpath;
      }
      $files = $new_files;

      # reset verified column in db
      $sql = 'UPDATE '.DATABASE_TABLES_PREFIX.'media SET verified=0';
      $r = db_query($link, $sql);

      # simple stats collector
      $stats = array(
        'inserted' => 0,
        'nonexistant' => 0,
        'started' => time(),
        'updated' => 0,
        'verified' => 0
      );

      $total = count($files);
      o('Files found: '.$total);

      if ($total < 5) {
        die('Too few files, something is wrong, only found '.$total.' files.');
      }

      # walk files found
      $i=0;
      foreach ($files as $k => $file) {

        $i++;

        # TODO: how to deal with nonexistant files?
        if (!file_exists($file) || filesize($file) < 1) {
            # o("\n".'Nonexistant / 0-size: '.$file);
            $stats['nonexistant']++;

            o(
              progressbar($i, $total).
              str_pad($stats['nonexistant'], strlen($total.''), '0', STR_PAD_LEFT).' - Nonexistant...', "\r"
            );

            continue;
        }

        # extract basename and path
        $name = basename($file);
        $path = substr($file, 0, strrpos($file, '/') + 1);

        # read exif data
        $exifdata = @exif_read_data($file);

        # try to get this photo based on path+name
        $sql = 'SELECT id FROM '.DATABASE_TABLES_PREFIX.'media WHERE path="'.dbres($link, $path).'" AND name="'.dbres($link, $name).'" AND verified=0 LIMIT 1';
        $r = db_query($link, $sql);

        # photo found
        if (count($r)) {


          # fix

          #$dimensions = getimagesize($file);
          #if (!is_array($dimensions) || !isset($dimensions[0], $dimensions[1]) || !is_numeric($dimensions[0]) || !is_numeric($dimensions[1])) {
          #	$dimensions = array(0 => 0, 1 => 0);
          #}
          #$sql = 'UPDATE '.DATABASE_TABLES_PREFIX.'media SET verified=1,width='.dbres($link, (int)$dimensions[0]).',height='.dbres($link, (int)$dimensions[1]).' WHERE id="'.dbres($link, $r[0]['id']).'"';

          #$gps = read_gps_location($exifdata);
          #$gps = is_array($gps) ? $gps : array();
          #$gps['lat'] = isset($gps['lat']) ? str_replace(',','.',$gps['lat']) : -1;
          #$gps['lng'] = isset($gps['lng']) ? str_replace(',','.',$gps['lng']) : -1;
          #echo $k.' UPDATE 1: '.implode(', ', $gps)."\n";
          #$sql = 'UPDATE '.DATABASE_TABLES_PREFIX.'media SET verified=1,existing=1,latitude='.$gps['lat'].',longitude='.$gps['lng'].' WHERE id="'.dbres($link, $r[0]['id']).'"';


          # $exposured = get_exposure_date($exifdata, $file);
          # get camera make and model
          # $sql = 'SELECT id FROM '.DATABASE_TABLES_PREFIX.'cameras WHERE make="'.dbres($link, isset($exifdata['Make']) ? $exifdata['Make'] : '').'" AND model="'.dbres($link, isset($exifdata['Model']) ? $exifdata['Model'] : '').'"';
          # $rc = db_query($link, $sql);
          # no camera like this before?
          # if (!count($rc)) {
            # then insert it
          #	$sql = 'INSERT INTO '.DATABASE_TABLES_PREFIX.'cameras (make, model) VALUES("'.dbres($link, (isset($exifdata['Make']) ? $exifdata['Make'] : '')).'","'.dbres($link, isset($exifdata['Model']) ? $exifdata['Model'] : '').'")';
          #	db_query($link, $sql);
          #	$id_cameras = mysqli_insert_id($link);
          #} else {
          #	$id_cameras = (int)$rc[0]['id'];
          #}
          #$sql = 'UPDATE photos SET verified=1,existing=1,id_cameras="'.dbres($link, $id_cameras).'",exposured="'.dbres($link, $exposured).'" WHERE id="'.dbres($link, $r[0]['id']).'"';


          # set it as verfied
          $sql = 'UPDATE '.DATABASE_TABLES_PREFIX.'media SET verified=1,existing=1 WHERE id="'.dbres($link, $r[0]['id']).'"';
          db_query($link, $sql);
          $stats['verified']++;

          o(
            progressbar($i, $total).
            str_pad($stats['verified'], strlen($total.''), '0', STR_PAD_LEFT).' - Verified...', "\r"
          );

          # go next photo
          continue;
        }


        $text = ' - Calculating hash, filesize: '.filesize($file).' b';
        o(
          progressbar($i, $total).
          str_pad($stats['verified'], strlen($total.''), '0', STR_PAD_LEFT).
          $text
          , "\r"
        );

        # compute ed2khash
        $hash = ed2khash($file);
        if (!$hash) {
          continue;
        }

        # blanking
        o(
          progressbar($i, $total).
          str_pad($stats['verified'], strlen($total.''), '0', STR_PAD_LEFT).
          str_repeat(' ', strlen($text))
          , "\r"
        );


        # try to get by ed2khash
        $sql = 'SELECT id FROM '.DATABASE_TABLES_PREFIX.'media WHERE ed2khash="'.dbres($link, $hash).'" AND verified=0 LIMIT 1';
        $r = db_query($link, $sql);
        $found = count($r);

        # photo found by hash
        if ($found) {

          #$dimensions = getimagesize($file);
          #if (!is_array($dimensions)||!isset($dimensions[0], $dimensions[1])||!is_numeric($dimensions[0])||!is_numeric($dimensions[1])) {
          #	$dimensions = array(0 => 0, 1 => 0);
          #}
          #$sql = 'UPDATE '.DATABASE_TABLES_PREFIX.'media SET verified=1, name="'.dbres($link, $name).'", path="'.dbres($link, $path).'",width='.dbres($link, (int)$dimensions[0]).',height='.dbres($link, (int)$dimensions[1]).' WHERE id="'.dbres($link, $r[0]['id']).'"';

          #$gps = read_gps_location($exifdata);
          #$gps = is_array($gps) ? $gps : array();
          #$gps['lat'] = isset($gps['lat']) ? str_replace(',','.',$gps['lat']) : -1;
          #$gps['lng'] = isset($gps['lng']) ? str_replace(',','.',$gps['lng']) : -1;
          #echo $k.' UPDATE 2: '.implode(', ', $gps)."\n";
          #$sql = 'UPDATE '.DATABASE_TABLES_PREFIX.'media SET verified=1,existing=1,name="'.dbres($link, $name).'", path="'.dbres($link, $path).'",latitude='.$gps['lat'].',longitude='.$gps['lng'].' WHERE id="'.dbres($link, $r[0]['id']).'"';


          #$exposured = get_exposure_date($exifdata, $file);

          # get camera make and model
          #$sql = 'SELECT id FROM '.DATABASE_TABLES_PREFIX.'cameras WHERE make="'.dbres($link, isset($exifdata['Make']) ? $exifdata['Make'] : '').'" AND model="'.dbres($link, isset($exifdata['Model']) ? $exifdata['Model'] : '').'"';
          #$rc = db_query($link, $sql);
          # no camera like this before?
          #if (!count($rc)) {
          # then insert it
          #	$sql = 'INSERT INTO '.DATABASE_TABLES_PREFIX.'cameras (make, model) VALUES("'.dbres($link, (isset($exifdata['Make']) ? $exifdata['Make'] : '')).'","'.dbres($link, isset($exifdata['Model']) ? $exifdata['Model'] : '').'")';
          #	db_query($link, $sql);
          #	$id_cameras = mysqli_insert_id($link);
          #} else {
          #	$id_cameras = (int)$rc[0]['id'];
          #}
          #$sql = 'UPDATE '.DATABASE_TABLES_PREFIX.'media SET verified=1, existing=1, name="'.dbres($link, $name).'", path="'.dbres($link, $path).'",id_cameras="'.dbres($link, $id_cameras).'",exposured="'.dbres($link, $exposured).'" WHERE id="'.dbres($link, $r[0]['id']).'"';

          # set it as verfied
          $sql = 'UPDATE '.DATABASE_TABLES_PREFIX.'media SET verified=1, existing=1, name="'.dbres($link, $name).'", path="'.dbres($link, $path).'" WHERE id="'.dbres($link, $r[0]['id']).'"';
          db_query($link, $sql);
          $stats['updated']++;

          o(
            progressbar($i, $total).
            str_pad($stats['updated'], strlen($total.''), '0', STR_PAD_LEFT).' - Updating...', "\r"
          );

          # go next photo
          continue;
        }

        $dimensions = getimagesize($file);
        if (!is_array($dimensions)||!isset($dimensions[0], $dimensions[1])||!is_numeric($dimensions[0])||!is_numeric($dimensions[1])) {
          $dimensions = array(0 => 0, 1 => 0);
        }

        # get gps locations
        $gps = read_gps_location($exifdata);
        $gps = is_array($gps) ? $gps : array();
        $gps['lat'] = isset($gps['lat']) ? str_replace(',','.',$gps['lat']) : -1;
        $gps['lng'] = isset($gps['lng']) ? str_replace(',','.',$gps['lng']) : -1;

        # get exposure date
        $exposured = get_exposure_date($exifdata, $file);

        # get camera make and model
        $sql = 'SELECT id FROM '.DATABASE_TABLES_PREFIX.'cameras WHERE make="'.dbres($link, isset($exifdata['Make']) ? $exifdata['Make'] : '').'" AND model="'.dbres($link, isset($exifdata['Model']) ? $exifdata['Model'] : '').'"';
        $rc = db_query($link, $sql);
        # no camera like this before?
        if (!count($rc)) {
          # then insert it
          $sql = 'INSERT INTO '.DATABASE_TABLES_PREFIX.'cameras (make, model) VALUES("'.dbres($link, (isset($exifdata['Make']) ? $exifdata['Make'] : '')).'","'.dbres($link, isset($exifdata['Model']) ? $exifdata['Model'] : '').'")';
          db_query($link, $sql);
          $id_cameras = mysqli_insert_id($link);
        } else {
          $id_cameras = (int)$rc[0]['id'];
        }

        # prepare an insert array
        $ui = dbpia($link, array(
          'ed2khash' => $hash,
          'existing' => 1,
          'exposured' => $exposured,
          'height' => $dimensions[1],
          'id_cameras' => $id_cameras,
          'latitude' => $gps['lat'],
          'longitude' => $gps['lng'],
          'name' => $name,
          'path' => $path,
          'verified' => 1,
          'width' => $dimensions[0]
        ));

        # insert it into db
        $sql = 'INSERT INTO '.DATABASE_TABLES_PREFIX.'media ('.implode(',', array_keys($ui)).') VALUES('.implode(',', $ui).')';
        db_query($link, $sql);
        $stats['inserted']++;

        o(
          progressbar($i, $total).
          str_pad($stats['inserted'], strlen($total.''), '0', STR_PAD_LEFT).' - Inserting...', "\r"
        );
      }

      $sql = 'UPDATE '.DATABASE_TABLES_PREFIX.'media SET existing=0 WHERE verified=0';
      db_query($link, $sql);

      $stats['duration'] = time() - $stats['started'];

      o(var_export($stats, true));

    case 'thumbnail': # generate all thumbs
      o('Starting action '.$action);

      set_time_limit(0);
      $start = time();

      # get all photos
      $sql = 'SELECT id,ed2khash,path,name,thumbstatus FROM '.DATABASE_TABLES_PREFIX.'media WHERE thumbstatus = 0 ORDER BY id';
      $r = db_query($link, $sql);

      # for statistics, count the amount of photos
      $total = count($r);

      # walk the items
      $i=0;
      $text = '';
      $prevlength = 0;
      foreach ($r as $k => $item) {
        $i++;
        $fullpath = $item['path'].$item['name'];

        if (!file_exists($fullpath) || !is_file($fullpath) || filesize($fullpath) < 100) {
          continue;
        }

        # print info
        #o(($k > 0 ? round(($k/$total)*100) : 0).'% - #'.$item['id']);

        # make a thumbnail
        $stats = make_thumbnails($item['ed2khash'], $item['path'].$item['name'], true);

        # has the creation of thumbs failed?
        if (array_search('failed', $stats) !== false) {
          # mark it in db
          $sql = 'UPDATE media SET thumbstatus=-1 WHERE id='.dbres($link, $item['id']);
          $update_media = db_query($link, $sql);

        # or is it done
        } else if (
          (isset($stats[$thumbsizes['normal']], $stats[$thumbsizes['small']])) &&
          ($stats[$thumbsizes['normal']] === 'already done' || $stats[$thumbsizes['normal']] === 'created') &&
          ($stats[$thumbsizes['small']] === 'already done' || $stats[$thumbsizes['small']] === 'created')
        ) {
          # mark it in db as done
          $sql = 'UPDATE media SET thumbstatus=1 WHERE id='.dbres($link, $item['id']);
          $update_media = db_query($link, $sql);
        }

        # make stat key => stat value to stat key => stat key: stat value
        foreach ($stats as $statkey => $statvalue) {
          $stats[$statkey] = $statkey.': '.$statvalue;
        }

        $text = str_pad(implode(', ', $stats), $prevlength, ' ', STR_PAD_RIGHT);

        $prevlength = strlen($text);

        $text = progressbar($i, $total).str_pad($i, strlen($total.''), '0', STR_PAD_LEFT).' - Generating thumbnails, #'.str_pad($item['id'], strlen($total.''), '0', STR_PAD_LEFT).', '.$text;

        o(
          $text, "\r"
        );
      }
      o("\nDuration: ".(time() - $start).'s');
      break;

    case 'verifydouble': # generate all thumbs

      o('Starting action '.$action);
      set_time_limit(0);
      $start = time();

      # get all photos
      $sql = 'SELECT * FROM '.DATABASE_TABLES_PREFIX.'media where existing=0';
      $r = db_query($link, $sql);

      # for statistics, count the amount of photos
      $total = count($r);
      echo 'Nonexisting files found: '.$total."\n";
      if ($total < 1) die();

      # walk the items
      foreach ($r as $k => $item) {
        echo 'Walking '.$k."\r";
        $sql = 'SELECT 1 FROM '.DATABASE_TABLES_PREFIX.'media WHERE ed2khash="'.dbres($link, $item['ed2khash']).'" AND existing=1';
          $copies = db_query($link, $sql);
          if (count($copies) < 1) {
          o($r[$k]['id'].': '.$r[$k]['path'].$r[$k]['name']);
        }
      }

      o("Duration: ".(time() - $start).'s');
      break;

    case 'thumbstatusreset': # to reset thumbnail ban status (-1)
      $sql = 'UPDATE media SET thumbstatus=0';
      $update_media = db_query($link, $sql);

      break;

    case 'xtempmove': # generate all thumbs

      die();

      o('Starting action '.$action);
      set_time_limit(0);
      $start = time();

      # get all photos
      $sql = 'SELECT * FROM '.DATABASE_TABLES_PREFIX.'media';
      $r = db_query($link, $sql);

      # walk all photos
      foreach ($r as $photo) {
        # walk thumbs for this photo
        /*
        foreach ($thumbsizes as $ts) {

          # make path to old thumb and new thumb
          $infile = THUMBNAIL_DIR.$photo['id'].'/'.$photo['id'].'_'.$ts.'.jpg';
          $outfile = THUMBNAIL_DIR.$photo['id'].'/'.$ts.'.jpg';

          # check if thumb exists
          if (file_exists($infile)) {
            echo 'mv '.escapeshellarg($infile).' '.escapeshellarg($outfile)."\n";
            # echo $infile."\n";
            # echo ' -> '.$outfile."\n";


          } else {
            echo '# FAIL'.$photo['id']."\n";
          }

        }

        */
        $infolder = THUMBNAIL_DIR.$photo['id'];
        $outfolder = THUMBNAIL_DIR.$photo['ed2khash'];

        if (file_exists($infolder)) {
          # echo $infolder."\n";
          # echo ' -> '.$outfolder."\n";
          # rename($infolder, $outfolder);
          o('mv '.escapeshellarg($infolder).' '.escapeshellarg($outfolder));
        }
        #else {
          # echo $infolder."\n";
        #}
      }
      break;

    case 'xxxxtempmove': # generate all thumbs

      die();

      # turn off execution time limit
      set_time_limit(0);
      $start = time();

      # get all photos
      $sql = 'SELECT * FROM '.DATABASE_TABLES_PREFIX.'media where existing=0';
      $r = db_query($link, $sql);

      # walk all photos
      foreach ($r as $photo) {
        $infolder = THUMBNAIL_DIR.$photo['id'];
        if (file_exists($infolder)) {
          o('BORT '.$infolder);
        }
      }
      break;

    case 'xxxgps':

      die();

      # $file = '';
      $file = '';
      #$exifdata = exif_read_data($file, 'IFD0', true);

      echo '-----------'."\n";

      $c = 'exiftool -G -j '.escapeshellarg($file);
      exec($c, $o, $r);

      if ($r === 0) {

        $o = implode("\n", $o);
        $o = json_decode($o, true);


        $gps['GPSLongitude'] = $o[0]['EXIF:GPSLatitude'];
        $gps['GPSLatitude'] = $o[0]['EXIF:GPSLongitude'];
        $gps['GPSLongitudeRef'] = strtoupper(substr($o[0]['EXIF:GPSLatitudeRef'], 0, 1));
        $gps['GPSLatitudeRef'] = strtoupper(substr($o[0]['EXIF:GPSLongitudeRef'], 0, 1));

        $gps = read_gps_location($gps);
        var_dump($gps);
      }


      #var_dump($exifdata);

      #var_dump(read_gps_location($exifdata));

      break;
  }
?>
