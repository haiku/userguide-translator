<?php
header('Content-type: text/plain;charset=utf8');
define('IN_TRANSLATE', 1);

$path_prefix = '../';
require('../inc/common.php');

role_needed(ROLE_ADMIN);

$req = db_query('SELECT * FROM ' . DB_LANGS . ' ORDER BY lang_name');
$lang_codes = array();
while ($row = db_fetch($req)) {
	$lang_codes[] = $row['lang_code'];
}
db_free($req);

chdir('../' . EXPORT_DIR) or die('Unable to chdir!');

$req = db_query('
	SELECT * FROM ' . DB_RESOURCES . '
	WHERE path_translated <> \'\'
');

while ($row = db_fetch($req)) {
	$path_untrans = $row['path_untranslated'];
	$path_trans = $row['path_translated'];
	
	foreach ($lang_codes as $lang_id => $lang_code) {
		$path_loc = str_replace('{LANG}', $lang_code, $path_trans);
		if (!file_exists($path_loc)) {
			make_path(dirname($path_loc));
			copy($path_untrans, $path_loc) or die("Unable to copy $path_untrans to $path_loc");
		}
	}
}

redirect('resources.php');

function make_path($path) {
	if (!file_exists($path)) {
		make_path(dirname($path));
		mkdir($path) or die("Unable to create directory $path!");
	} else if (!is_dir($path)) {
		error_box("Error creating path: $path exists and is not a directory!");
	}
}

?>