<?php
$path_prefix = '../';
require_once('../inc/common.php');

role_needed(ROLE_ADMIN);

$title = 'Manage Documents';
include('admin_top.php');

$time = time();

// Delete documents
if (isset($_POST['delete_selection']) and is_array(@$_POST['del_list'])
	and !empty($_POST['del_list'])) {

	$del_list = array_map('intval', array_keys($_POST['del_list']));
	$del_list = implode(', ', $del_list);

	confirm_box($title, 'Do you really want to delete the selected documents ?',
		'Cancel', 'Delete',
		'<input type="hidden" name="del_list" value="' . $del_list . '" />');
} else if(isset($_POST['del_list']) and isset($_POST['confirm_ok'])) {
	$del_list = explode(', ', $_POST['del_list']);
	$del_list = array_map('intval', $del_list);

	require_once('../inc/git.php');

	foreach ($del_list as $delete_id) {
		$delete_id = intval($delete_id);

		$req = db_query('
			SELECT path_original, name FROM ' . DB_DOCS . "
			WHERE doc_id = ?", array($delete_id));

		$row = db_fetch($req);

		if (!$row)
			continue;

		if (file_exists('../' . REF_DIR . '/' . $row['path_original'])) {
			$path = '../' . REF_DIR . '/' . $row['path_original'];
			git_pull(dirname($path));
			git_rm($path);
			git_commit(dirname($path), 'Deleting file.'); // TODO: maybe batch this
			git_push(dirname($path));
		}

		db_query('DELETE FROM ' . DB_DOCS . " WHERE doc_id = ?", array($delete_id));
		db_query('DELETE FROM ' . DB_STRINGS . " WHERE doc_id = ?", array($delete_id));

		// Log
		$name = $row['name'];
		db_query('
			INSERT INTO ' . DB_LOG . '
			(log_user, log_time, log_action, log_doc, log_del_doc_title) ' . "
			VALUES (?, ?, ?, ?, ?)", array($user_id, $time, 'del', $delete_id, $name));
		db_query('
			UPDATE ' . DB_LOG . "
			SET log_del_doc_title = ?
			WHERE log_doc = ?", array($name, $delete_id));
	}
}

// Change names
if (isset($_POST['submit_names']) and isset($_POST['name'])
	and is_array($_POST['name'])) {
	foreach ($_POST['name'] as $id => $new_name) {
		$new_disabled = (isset($_POST['dis_list'][$id]) ? 1 : 0);
		$id = intval($id);

		db_query('
			UPDATE ' . DB_DOCS . " SET name = ?, is_disabled = ?
			WHERE doc_id = ?", array($new_name, $new_disabled, $id));
	}
}

include('../inc/start_html.php');

?>
<form action="" method="post">
<h1><?=$title?></h1>
<table class="list">
<tr>
<th>&nbsp;</th><th>Name</th><th>Source path</th><th>Translations path</th><th>Disabled</th>
</tr>
<?php
$req = db_query('SELECT doc_id, name, path_original, path_translations, is_disabled ' . '
	FROM ' . DB_DOCS);

while ($row = db_fetch($req)) {
	$disabled_checked = ($row['is_disabled'] ?  ' checked="checked"' : '');
?>
<tr class="<?=alt_row()?>">
<td><input type="checkbox" name="del_list[<?=$row['doc_id']?>]" /></td>
<td><input type="text" class="textbox" name="name[<?=$row['doc_id']?>]"
	value="<?=htmlspecialchars($row['name'])?>" maxlength="64" size="40" /></td>
<td><?=htmlspecialchars($row['path_original'])?></td>
<td><?=htmlspecialchars($row['path_translations'])?></td>
<td style="text-align:center"><input type="checkbox" name="dis_list[<?=$row['doc_id']?>]" <?=$disabled_checked?>/></td>
</tr>
<?php
}
?>
<tr class="bottom">
<td colspan="5">
<input type="submit" name="submit_names" value="Update names/status" />
<input type="submit" name="delete_selection" value="Delete selection" />
</td>
</tr>
</table>
</form>
<?php
include('../inc/end_html.php');
