<?php
require_once('inc/common.php');

if (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] == 'login' and !$user_logged_in)
	login_box('', 'documents.php');
else if (!$user_logged_in)
	redirect('.');
else if (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] == 'logout') {
	unset($_SESSION['user_id']);
	unset($_SESSION['user_name']);
	unset($_SESSION['user_pass']);
	redirect('.');
}

$title = 'Documentation Translate Tool';
$ltop = '';

if ($user_logged_in) {
	$top = 'Logged as <b>' . htmlspecialchars($user_name) .
		'</b> <a href="?logout">[Logout]</a> <a href="user.php">[Settings]</a>';

	if ($user_role >= ROLE_AUTHOR)
		$ltop .= ' <a href="new_doc.php">Create New Document</a>';

	if ($user_role == ROLE_ADMIN)
		$ltop .= ($ltop ? ' • ' : '') .'<a href="admin/">Administration</a>';
} else
	$top = '<a href="?login">[Log In]</a> ';

$ltop .= ($ltop ? ' • ' : '') .'<a href="log.php">Changelog</a>';

include('inc/start_html.php');

$first_row = '<th>English (original)</th>';
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

<h1>Current translation status</h1>
<table class="list compact">
<thead>
<tr>
<?=$first_row?>
</tr>
</thead>
<tbody>
<?php
$req = db_query('SELECT * FROM ' . DB_DOCS . ' ORDER BY name ASC');

while ($row = db_fetch($req)) {
	$doc_id = $row['doc_id'];

	$path = 'view.php?doc_id=' . $doc_id;
	$path_edit = 'edit.php?doc_id=' . $doc_id;
	$block_edit = 'block_edit.php?doc_id=' . $doc_id;
	$name = $row['name'] ? $row['name'] : $row['path_original'];
	$name = htmlspecialchars($name);
?>
<tr>
<td><a href="<?=$path?>"><?=$name?></a><br/>
<a href="<?=$block_edit?>">Block Edit</a> •
<a href="<?=$path_edit?>">Full Edit</a></td>
<?php
	if ($row['strings_count'] > 0) {
		foreach ($lang_codes as $lang_id => $lang_code) {
			$path_trans = $row['path_translations'];
			$path_translated = str_replace('{LANG}', $lang_code, $path_trans);

			$percent = 100 * intval($row['count_' . $lang_code]);
			$percent = round($percent / intval($row['strings_count']), 0);

			$current = 'view.php?doc_id=' . $doc_id . '&amp;l=' . $lang_code;
			$translate = 'translate.php?doc_id=' . $doc_id . '&amp;l=' . $lang_code;

			$fuzzy = '';
			if ($row['count_fuzzy_' . $lang_code])
				$fuzzy = ' - ' . $row['count_fuzzy_' . $lang_code] . ' fuzzy';
?>
<td>
<a href="<?=$current?>" title="View latest version">View</a> •
<a href="<?=$translate?>" title="Translate the document">Translate</a><br/>
(<b style="color:<?=color($percent, $row['count_fuzzy_' . $lang_code])?>"><?=$percent?> %<?=$fuzzy?></b>)
</td>
<?php
		}
	} else {
?>
<td colspan="<?=$len?>">No translatable text found in this document.</td>
<?php
	}
?>
</tr>
<?php
}
?>
</tbody>
</table>
<?php

include('inc/end_html.php');

function color($percent, $fuzzy_count) {
	if ($fuzzy_count > 0)
		return 'orange';

	if ($percent == 100)
		return 'green';

	if ($percent == 0)
		return 'black';

	return 'red';
}
?>
