<?php
$path_prefix = '../';
require_once('../inc/common.php');

role_needed(ROLE_ADMIN);

$title = 'Manage Resources';

if (isset($_POST['delete_selection']) and is_array(@$_POST['del_list'])
	and !empty($_POST['del_list'])) {

	$del_list = array_map('htmlspecialchars', array_keys($_POST['del_list']));

	$format_list = '';
	foreach ($del_list as $item) {
		$format_list .= '<li>' . htmlspecialchars($item) . "</li>\n";
	}

	$del_list = implode(', ', $del_list);

	confirm_box($title,
		"Do you really want to delete the following items ?\n<ul>\n" . $format_list .
		"</ul>",
		'Cancel', 'Delete',
		'<input type="hidden" name="del_list" value="' . $del_list . '" />');
} else if(isset($_POST['del_list']) and isset($_POST['confirm_ok'])) {
	$del_list = explode(', ', $_POST['del_list']);

	foreach ($del_list as $to_delete) {
		if (strpos($to_delete, '..') != false)
			die('Wrong file name!');

		unlink('../' . EXPORT_DIR . '/' . $to_delete) or die("Unable to delete $to_delete!");
	}

	redirect('resources_unhand.php');
}


require_once('admin_top.php');
require_once('../inc/start_html.php');

$langs = array();
$req = db_query('SELECT lang_code FROM ' . DB_LANGS);
while ($row = db_fetch($req)) {
	$langs[] = $row['lang_code'];
}

$db_paths = array();

$req = db_query('SELECT path_original, path_translations FROM ' . DB_DOCS);
while ($row = db_fetch($req)) {
	$db_paths[] = $row['path_original'];
	foreach ($langs as $lang) {
		$db_paths[] = str_replace('{LANG}', $lang, $row['path_translations']);
	}
}

$req = db_query('SELECT path_untranslated, path_translated FROM ' . DB_RESOURCES);
while ($row = db_fetch($req)) {
	$db_paths[] = $row['path_untranslated'];
	foreach ($langs as $lang) {
		$db_paths[] = str_replace('{LANG}', $lang, $row['path_translated']);
	}
}

chdir('../' . EXPORT_DIR) or die('Unable to chdir!');
$file_paths = list_files('.');

$unhandled_paths = array_diff($file_paths, $db_paths);
unset($file_paths, $db_paths);

?>
<h1>Unhandled resources</h1>
<script type="text/JavaScript">
function select_all() {
	var checkboxes = document.getElementsByClassName('del_path_check');
	var num_of_checkboxes = 0;
	var num_of_checked = 0;

	for (i in checkboxes) {
		if (num_of_checked<95) {
			checkboxes[i].checked = true;
			num_of_checked++;
		}
		num_of_checkboxes++;
	}

	document.getElementById("match_status").innerHTML = 'Checked ' + num_of_checked +'/' + num_of_checkboxes + '.';
}

function select_matching() {
	var ext = document.getElementsByName('match_extension')[0].value;

	if (!ext)
		return;

	if (ext.substr(0,1)!='.')
		ext = '.' + ext;

	ext = ext + ']';
	len = ext.length;

	var checkboxes = document.getElementsByClassName('del_path_check');
	var num_of_checkboxes = 0;
	var num_of_matched = 0;
	var num_of_checked = 0;

	for (i in checkboxes) {
		num_of_checkboxes++;

		if (checkboxes[i].name != undefined) {
			if (checkboxes[i].name.substr(-len) == ext) {
				num_of_matched++;
				if (num_of_checked<95) {
					checkboxes[i].checked = true;
					num_of_checked++;
				}
			} else {
				checkboxes[i].checked = false;
			}
		}
	}

	document.getElementById("match_status").innerHTML = 'Checked ' + num_of_checked +'/'+ num_of_matched + ' matched resources (' + num_of_checkboxes + ' in total).';


}
</script>
<form action="" method="post">
<table class="list">
<tr>
<th style="width:20px">&nbsp;</th><th>File name</th>
</tr>
<?php
foreach ($unhandled_paths as $path) {
	$path = htmlspecialchars($path);
	$enc = urlencode($path);
?>
<tr>
<td><input type="checkbox" class="del_path_check" name="del_list[<?=$path?>]" /></td>
<td><?=$path?></td>
</tr>
<?php
}
?>
<tr class="bottom">
<td colspan="2">
<input type="submit" name="delete_selection" value="Delete selection" />
<input type="button" value="Select first 95" onclick="select_all()"/>
<input type="button" value="Select matching extension:" onclick="select_matching()"/>
<input type="text" name="match_extension" value="" size="4" />
<span id="match_status"></span>
</td>
</tr>
</table>
</form>

<?php
/*
	This is causing a warning because it has been included somwhere else.
	Disabling this until I can find what's causing it.
*/
//require_once('../inc/end_html.php');

function list_files($dir) {
	$handle = opendir($dir) or die("Unable to open $dir!");

	$list = array();

	while (($file = readdir($handle)) !== false) {
		if ($file == '.' or $file == '..')
			continue;
		$path = ($dir == '.' ? $file : $dir . '/' . $file);
		if (is_dir($path))
			$list = array_merge($list, list_files($path));
		else
			$list[] = $path;
	}

	closedir($handle);

	return $list;
}