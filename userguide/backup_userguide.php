<?php

if (file_exists('backup.zip')) {
	unlink('backup.zip') or die('Unable to delete the old backup file!');
}

$files = get_files('', array('userguide.zip', 'pma'));

foreach($files as $file) {
	$return = 0;
	passthru('zip -v backup.zip ' . $file . ' 2>&1', $return);
	flush();
	ob_flush();
	if ($return != 0)
		die();
}

// Remove files that should not be backed up

function get_files($path, $exclude_list) {
	$matches = array();
	$dir = opendir($path ? $path : '.') or die("Unable to open path $path!");

	if ($path)
		$path .= '/';

	while ($item = readdir($dir)) {
		if ($item != '.' and $item != '..' and !in_array($item, $exclude_list)) {
			if (is_dir($path . $item)) {
				$matches = array_merge($matches, get_files($path . $item, array()));
			} else {
				$matches[] = $path . $item;
			}
		}
	}

	closedir($dir);

	return $matches;
}

?>