<?php
require_once('inc/common.php');
require_once('inc/lock.php');

role_needed(ROLE_AUTHOR);

if (isset($_GET['doc_id'])) {
	$doc_id = intval($_GET['doc_id']);
	extend_lock($doc_id);
}
