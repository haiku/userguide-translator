<?php
$grace_period = 181; // in seconds, maximum time a lock is held

function extend_lock($doc_id) {
	global $user_id;

	db_query('
		UPDATE ' . DB_DOCS . "
		SET edit_time = ?
		WHERE doc_id = ? AND edited_by = ?", array(time(), $doc_id, $user_id));
}

function lock_and_get($doc_id) {
	global $user_id, $grace_period;

	$req = db_query('
		SELECT d.name, d.path_original, d.path_translations,
			d.edited_by, d.edit_time, u.username
		FROM ' . DB_DOCS . ' d
		LEFT JOIN ' . DB_USERS . ' u
			ON d.edited_by = u.user_id '. "
		WHERE d.doc_id = ?", array($doc_id));

	$row = db_fetch($req);
	db_free($req);

	if (!$row)
		redirect('index.php');

	$time = time();

	if ($row['edited_by'] != $user_id
		and $row['edit_time'] + $grace_period > $time) {

		$doc_name = '“' . htmlspecialchars($row['name']) . '”';
		$name = (isset($row['username']) ? $row['username'] : 'NOBODY (OOPS!)');
		$name = htmlspecialchars($name);
		error_box('Edit ' . $doc_name,
			"$doc_name is currently being edited by <b>$name</b>. " .
			"Please try again later.");
	}

	db_query('
		UPDATE ' . DB_DOCS . "
		SET edited_by = ?, edit_time = ?
		WHERE doc_id = ?", array($user_id, $time, $doc_id));

	return $row;
}

