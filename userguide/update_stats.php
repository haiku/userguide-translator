<?php
ignore_user_abort(true);

require('inc/common.php');

$req = db_query('SELECT * FROM ' . DB_LANGS);
$lang_codes = array();

while ($row = db_fetch($req)) {
	$lang_codes[] = $row['lang_code'];
}

db_free($req);

if (isset($_GET['doc_id'])) {
	$id = intval($_GET['doc_id']);
	if ($id)
		update_id($id);
} else {
	$doc_req = db_query('SELECT doc_id FROM ' . DB_DOCS);

	while ($doc_row = db_fetch($doc_req)) {
		update_id(intval($doc_row['doc_id']));
	}

	db_free($doc_req);
}

if (isset($_GET['redir_to']))
	redirect($_GET['redir_to']);

echo 'ok';

function update_id($id) {
	global $lang_codes;

	$sql = 'UPDATE ' . DB_DOCS . ' SET ';
	$first = true;

	foreach ($lang_codes as $lang_code) {
		$req = db_query('SELECT COUNT(*) FROM ' . DB_STRINGS . "
			WHERE doc_id = ? AND \"translation_$lang_code\" <> ''
			AND unused_since IS NULL AND \"is_fuzzy_$lang_code\" = 0",
		array($id));

		$row = db_fetch($req);
		$count = $row['count'];
		db_free($req);

		$req = db_query('SELECT COUNT(*) FROM ' . DB_STRINGS . "
			WHERE doc_id = ? AND \"translation_$lang_code\" <> ''
			AND unused_since IS NULL AND \"is_fuzzy_$lang_code\" = 1",
		array($id));

		$row = db_fetch($req);
		$fuzzy = $row['count'];
		db_free($req);

		$sql .= ($first ? '' : ', ');
		$sql .= "\"count_$lang_code\"=$count, \"count_fuzzy_$lang_code\"=$fuzzy";

		$first = false;
	}

	$sql .= " WHERE doc_id=?";

	db_query($sql, array($id));
}
