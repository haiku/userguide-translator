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

$sel_lang = (isset($_GET['l']) ? validate_lang($_GET['l']) :  '');

echo "<h1>Translation status</h1>\nDisplay: ";
if ($sel_lang)
	echo '<a href="documents2.php">';
echo 'Every language';
if ($sel_lang)
	echo '</a>';

$sel_lang_name = '';
$sel_loc_name = '';
$req = db_query('SELECT * FROM ' . DB_LANGS . ' ORDER BY lang_name');
$language_names = array();
while ($row = db_fetch($req)) {
	$lang_name = htmlspecialchars($row['lang_name']);
	$lang_code = $row['lang_code'];
	if ($lang_code == $sel_lang) {
		$sel_lang_name = $lang_name;
		$sel_loc_name = htmlspecialchars($row['loc_name']);
		echo ' • ' . $lang_name;
	} else {
		echo ' • <a href="documents2.php?l=' . $lang_code . '">' . $lang_name . '</a>';
	}
	$language_names[$lang_code] = $lang_name;
}

if (!$sel_lang_name) // We haven't found the specified language code
	$sel_lang = '';

if ($sel_lang) { // Specific status page
?>
<br/>
<table class="list">
<tr>
<th>Document</th><th><?=$sel_lang_name?> (<?=$sel_loc_name?>)</th>
</tr>
<?php
	$req = db_query('
		SELECT doc_id, name, path_original, path_translations, strings_count, ' . "
			count_${sel_lang}, count_fuzzy_${sel_lang} FROM " . DB_DOCS . '
		ORDER BY name ASC');
	while ($row = db_fetch($req)) {
		$doc_id = $row['doc_id'];

		$path_trans = 'view.php?doc_id=' . $doc_id . '&amp;l=' . $sel_lang;
		$name = $row['name'] ? $row['name'] : $row['path_original'];
		$name = htmlspecialchars($name);
		$translate_path = 'translate.php?doc_id=' . $doc_id . '&amp;l=' . $sel_lang;

		$count = intval($row['strings_count']);
		$percent = round(100 * intval($row['count_' . $sel_lang]) / $count, 0);
		$fuzzy_count = intval($row['count_fuzzy_' . $sel_lang]);
		$status = '<b style="color:' . color($percent, $fuzzy_count) . '">' . $percent . '%';
		if ($fuzzy_count)
			$status .= " — $fuzzy_count fuzzy";
		$status .= '</b>';
?>
<tr>
<td><?=$name?></td>
<td><a href="<?=$path_trans?>">View</a>
<?php if ($count) { ?>• <a href="<?=$translate_path?>">Translate</a> (Progress: <?=$status?>) <?php } ?>
</td>
</tr>
<?php
	}
	echo '</table>';
} else { // Multilanguage status page
?>
<br/>
<table class="list">
<tr>
<th>English (original)</th><?php
	$sql = 'SELECT doc_id, name, strings_count, path_translations';
	$count_langs = count($language_names);
	foreach($language_names as $code => $name) {
		$sql .= ", count_$code, count_fuzzy_$code";
		echo "<th>$name</th>";
	}
	echo "\n</tr>\n";
	$sql .= ' FROM ' . DB_DOCS . ' ORDER BY name ASC';

	$req = db_query($sql);
	while ($row = db_fetch($req)) {
		$doc_id = $row['doc_id'];
		$name = htmlspecialchars($row['name']);
		$path = 'view.php?doc_id=' . $doc_id;
		$path_edit = 'edit.php?doc_id=' . $doc_id;
		$block_edit = 'block_edit.php?doc_id=' . $doc_id;
		$count = intval($row['strings_count']);
		$columns = '';
		if ($count) {
			foreach ($language_names as $code => $unused) {
				$percent = round(100 * intval($row['count_' . $code]) / $count, 0);
				$fuzzy_count = intval($row['count_fuzzy_' . $code]);
				$columns .= '<td><a href="view.php?doc_id=' . $doc_id .
					'&amp;l=' . $code. '" style="color:' . color($percent, $fuzzy_count) . '">' .
					$percent . '%';
				if ($fuzzy_count)
					$columns .= " ($fuzzy_count f.)";
				$columns .= '</a></td>';
			}
		} else {
			$columns = '<td colspan="' . $count_langs . '">This document is not translatable.</td>';
		}
?>
<tr>
<td><a href="view.php?doc_id=<?=$doc_id?>"><?=$name?></a><br>
<a class="c" href="<?=$block_edit?>">Block Edit</a> •
<a class="c" href="<?=$path_edit?>">Full Edit</a></td></td><?=$columns?>
</tr>
<?php
	}
	echo "</table>\n";
}
include('inc/end_html.php');

function color($percent, $fuzzy_count) {
	if ($percent == 100)
		return 'green';

	if ($fuzzy_count > 0)
		return 'orange';

	if ($percent == 0)
		return 'black';

	return 'red';
}
?>
