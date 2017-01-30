<?php
define('IN_TRANSLATE', 1);

$path_prefix = '../';
require('../inc/common.php');

role_needed(ROLE_ADMIN);

$title = 'Manage Resources';
include('admin_top.php');

// Delete resources
if (isset($_POST['delete_selection']) and is_array(@$_POST['del_list'])
	and !empty($_POST['del_list'])) {

	$del_list = array_map('intval', array_keys($_POST['del_list']));
	$del_list = implode(', ', $del_list);

	confirm_box($title, 'Do you really want to delete the selected resources ?',
		'Cancel', 'Delete',
		'<input type="hidden" name="del_list" value="' . $del_list . '" />');
} else if(isset($_POST['del_list']) and isset($_POST['confirm_ok'])) {
	$del_list = explode(', ', $_POST['del_list']);
	$del_list = array_map('intval', $del_list);
	$del_list = implode(', ', $del_list);

	db_query('
		DELETE FROM ' . DB_RESOURCES . "
		WHERE resource_id IN ($del_list)
	");

	redirect('resources_unhand.php');
}



include('../inc/start_html.php');
?>
<h1><?=$title?></h1>
Every non-textual resource present in the documentation should be listed here.

<h2>Resources</h2>
Striked links indicate missing resources.
<?php
$first_row = '';
$lang_rows = array();
$lang_codes = array();

$req = db_query('SELECT * FROM ' . DB_LANGS . ' ORDER BY lang_name');
while ($row = db_fetch($req)) {
	$first_row .= '<th>' . htmlspecialchars($row['lang_name']) . '</th>';
	$lang_codes[] = $row['lang_code'];
}
$len = count($lang_codes) + 1;
db_free($req);
?>
<form action="" method="post">
<table class="list compact">
<thead>
<tr>
<th> </th><th>English (original)</th><?=$first_row?>
</tr>
</thead>
<tbody>
<?php
$req = db_query('SELECT * FROM ' . DB_RESOURCES);
while ($row = db_fetch($req)) {
	$res_id = $row['resource_id'];
	$path_untrans = '../' . EXPORT_DIR . '/' . $row['path_untranslated'];
	$path_trans = $row['path_translated'];
	$name = basename($row['path_untranslated']);
	$colspan = ($path_trans ? 1 : $len);
	$style = (file_exists($path_untrans) ? '' : ' style="color:#FF0000;font-weight:bold;text-decoration:line-through"');

	echo '<tr class="' . alt_row() . '">' . "\n";
	echo "<td><input type=\"checkbox\" name=\"del_list[$res_id]\" /></td>\n";
	echo "<td colspan=\"$colspan\"><a$style href=\"$path_untrans\">$row[path_untranslated]</a><br/>";
	echo "<a href=\"../res_upload.php?id=$res_id\">Upload…</a></td>\n";

	if ($path_trans) {
		foreach ($lang_codes as $lang_id => $lang_code) {
			$loc_name = basename($path_trans);
			if ($loc_name == $name)
				$loc_name = 'View';
			else
				$loc_name = str_replace('{LANG}', $lang_code, $loc_name);


			$path_loc = '../' . EXPORT_DIR . '/' . str_replace('{LANG}', $lang_code, $path_trans);
			$style = (file_exists($path_loc) ? '' : ' style="color:#FF0000;font-weight:bold;text-decoration:line-through"');
			echo "<td><a$style href=\"$path_loc\">$loc_name</a><br/>\n";
			echo "<a href=\"../res_upload.php?id=$res_id&amp;lang=$lang_code\">Upload…</a></td>\n";
		}
	}

	echo "</tr>\n";
}
?>
<tr class="bottom">
<td colspan="<?=$len + 1?>">
<input type="submit" name="delete_selection" value="Delete selection" />
</td>
</tbody>
</table>
</form>

<h2>Options</h2>
<dl>
<dt><a href="resources_add.php">Add resources</a></dt>
<dt><a href="resources_unhand.php">Display unhandled resources</a></dt>
<dd>Show every file in the export directory not currently handled by the translate tool or the
resource manager.</dd>
<dt><a href="resources_sync.php">Copy missing resources</a></td>
<dd>Fixes any missing translated resource by copying the one from the original language.</dd>
</dl>

<?php
include('../inc/end_html.php');

