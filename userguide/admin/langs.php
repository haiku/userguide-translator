<?php
$path_prefix = '../';
require('../inc/common.php');

role_needed(ROLE_ADMIN);

// Delete languages
$title = 'Languages';
$del = (isset($_GET['del']) ? $_GET['del'] : '');
if (isset($_GET['del']) and strlen($del) >= 2 and strlen($del) <= 5) {
	if (isset($_POST['confirm_ok'])) {
		$lang_code = validate_lang($_GET['del']);

		$result = db_query('DELETE FROM ' . DB_LANGS . "
			WHERE lang_code = ?", array($lang_code));

		if (db_num_rows($result) > 0) {
			db_query('ALTER TABLE ' . DB_DOCS . "
				DROP `count_$lang_code`");
			db_query('ALTER TABLE ' . DB_DOCS . "
				DROP `count_fuzzy_$lang_code`");
			db_query('ALTER TABLE ' . DB_DOCS . "
				DROP `is_dirty_$lang_code`");
			db_query('ALTER TABLE ' . DB_STRINGS . "
				DROP `translation_$lang_code`");
			db_query('ALTER TABLE ' . DB_STRINGS . "
				DROP `is_fuzzy_$lang_code`");
		}
		redirect('langs.php');
	} else if (isset($_POST['confirm_cancel'])) {
		redirect('langs.php');
	} else {
		confirm_box($title, 'Do you really want to delete this language ?',
			'Cancel', 'Delete');
	}
}

include('admin_top.php');
include('../inc/start_html.php');

// Add languages
$lang_code = '';
$lang_name = '';
$lang_loc_name = '';

if (isset($_POST['update_status'])) {
	$dis_list = ((isset($_POST['dis_list']) and is_array($_POST['dis_list'])) ?
		array_map('escape_lang', array_keys($_POST['dis_list'])) : array());

	db_query('UPDATE ' . DB_LANGS . ' SET is_disabled = 0 WHERE TRUE');
	if (count($dis_list)) {
		db_query('
			UPDATE ' . DB_LANGS . '
			SET is_disabled = 1
			WHERE lang_code = ' . implode(' OR lang_code = ', $dis_list));
	}

} else if (isset($_POST['lang_code']) and isset($_POST['lang_name'])
	and isset($_POST['lang_loc_name'])) {
	$lang_code = $_POST['lang_code'];
	$lang_name = $_POST['lang_name'];
	$lang_loc_name = $_POST['lang_loc_name'];

	if (strlen($lang_name) > 1 and strlen($lang_loc_name) > 1
		and validate_lang_code($lang_code)) {

		$lang_code = validate_lang($lang_code);

		db_query('
			INSERT INTO ' . DB_LANGS . '
			(lang_code, lang_name, loc_name) ' . "
			VALUES (?, ?, ?)", array($lang_code, $lang_name, $lang_loc_name));
		db_query('ALTER TABLE ' . DB_DOCS . "
			ADD `count_$lang_code` INT UNSIGNED NOT NULL DEFAULT '0'");
		db_query('ALTER TABLE ' . DB_DOCS . "
			ADD `count_fuzzy_$lang_code` INT UNSIGNED NOT NULL DEFAULT '0'");
		db_query('ALTER TABLE ' . DB_DOCS . "
			ADD `is_dirty_$lang_code` BOOLEAN NOT NULL DEFAULT '1'");
		db_query('ALTER TABLE ' . DB_STRINGS . "
			ADD `translation_$lang_code` TEXT collate utf8_bin NOT NULL");
		db_query('ALTER TABLE ' . DB_STRINGS . "
			ADD `is_fuzzy_$lang_code` BOOL NOT NULL DEFAULT '0'");

		$lang_code = '';
		$lang_name = '';
		$lang_loc_name = '';

		echo '<div class="box-info">New language added successfully.</div>';
	} else {
		echo '<div class="box-stop">Adding language failed: Incorrect ' .
		'parameters.</div>';
	}
}
?>
<h1>Languages</h1>
<form action="" method="post">
<table class="list">
<tr>
<th>English name</th><th>Localized name</th><th style="width:2em">Disabled</th><th style="width:5em">Options</th>
</tr>
<?php
$req = db_query('SELECT * FROM ' . DB_LANGS);
if (db_num_rows($req) > 0) {
	while ($row = db_fetch($req)) {
		$lang_code_r = $row['lang_code'];
		$lang_name_r = htmlspecialchars($row['lang_name']);
		$loc_name_r = htmlspecialchars($row['loc_name']);
		$disabled_checked = ($row['is_disabled'] ?  ' checked="checked"' : '');
?>
<tr class="<?=alt_row()?>">
<td><?=$lang_name_r?></td>
<td><?=$loc_name_r?></td>
<td style="text-align:center"><input type="checkbox" name="dis_list[<?=$lang_code_r?>]"<?=$disabled_checked?> /></td>
<td><a href="?del=<?=$lang_code_r?>">Delete</a></li></td>
<?php
	}
} else {
	echo '<tr><td colspan="4">None defined.</td></tr>';
}
?>
<tr class="bottom">
<td colspan="4">
<input type="submit" name="update_status" value="Update status" />
</td>
</tr>
</table>
<dl class="fieldset">
<dt><span>Add new language</span></dt>
<dd>
<label for="lang_code">Language code:</label>
<input type="text" name="lang_code" id="lang_code" value="<?=$lang_code?>"
	maxlength="5" size="5" />
<br/>
<em>Two-character language code, e.g ‘fr’, or five-character language/region code, e.g ‘en_US’.
Must be lowercase.</em><br/>
<label for="lang_name">Language name:</label>
<input type="text" name="lang_name" id="lang_name" value="<?=$lang_name?>"
	maxlength="32" size="32" />
<br/>
<em>Human readable name (in English)</em><br/>
<label for="lang_loc_name">Localised name:</label>
<input type="text" name="lang_loc_name" id="lang_loc_name"
	value="<?=$lang_loc_name?>" maxlength="32" size="32" />
<br/>
<em>Human readable name (localised)</em><br/>
<input type="submit" name="add_lang" value="Add" />
</dd>
</dl>
</form>

<?php
include('../inc/end_html.php');

function validate_lang_code($code) {
	$len = strlen($code);

	if ($len < 2)
		return false;

	// Two first characters: must be lowercase
	if ($code[0] < 'a' or $code[0] > 'z' or $code[1] < 'a' or $code[1] > 'z')
		return false;

	if ($len == 2) // Two-chars: OK
		return true;

	if ($len != 5) // Not five-chars, not OK
		return false;

	if ($code[2] != '_')
		return false;

	if ($code[3] < 'A' or $code[3] > 'Z' or $code[4] < 'A' or $code[4] > 'Z')
		return false;

	return true;
}

function escape_lang($code) {
	if (!validate_lang_code($code))
		return "''";

	return "'$code'";
}

?>
