<?php
require_once('inc/common.php');

$title = 'Log';
$top = '<a href="documents.php">Return to index</a>';
include('inc/start_html.php');
?>
<h1>Latest changes</h1>
<ul>
<?php

$db_result = db_query('SELECT count(log_id) as count FROM ' . DB_LOG . ' WHERE 1');

$db_row = db_fetch($db_result);
$num_logs = $db_row['count'];
$num_per_page = 500;
$num_pages = ceil($num_logs/$num_per_page);
$page=1;

if(isset($_GET['page']) && is_numeric($_GET['page']))
	if($_GET['page']>$num_pages){
		$page=$num_pages;
	}else{
		$page=$_GET['page'];
	}

for($i=1; $i<$num_pages; $i++){
	if($page==$i){
		echo " $i ";
	}else{
		echo "<a href=?page=$i> $i </a>";
	}
}

$req = db_query('
	SELECT l.*, u.username, d.name, d.path_original, x.lang_name
	FROM ' . DB_USERS . ' u, ' . DB_LOG . ' l
	LEFT JOIN ' . DB_DOCS . ' d ON l.log_doc = doc_id
	LEFT JOIN ' . DB_LANGS . ' x ON l.log_trans_lang = lang_code ' . "
	WHERE l.log_user = u.user_id " . '
	ORDER BY l.log_time DESC LIMIT ' . ($page-1)*$num_per_page.', '.$num_per_page);

$time = time();



while ($row = db_fetch($req)) {
	$user_id = $row['log_user'];
	$user_name = $row['username'];
	$log_time = intval($row['log_time']);
	$doc_id = $row['log_doc'];
	$doc_name = htmlspecialchars($row['name'] ? $row['name']
		: $row['path_original']);
	$doc_exists = true;
	if (!$doc_name) {
		$doc_name = htmlspecialchars($row['log_del_doc_title']);
		$doc_exists = false;
	}
	// Format date
	$date = '';
	if ($time - $log_time < 4 * 60 * 60 * 24) {
		$delta = getday($time) - getday($log_time);

		if ($delta < 0)
			$delta += 7;

		if ($delta == 0) {
			$date = 'Today';
		} else if ($delta == 1) {
			$date = 'Yesterday';
		}
	}

	if (!$date)
		$date = 'On ' . gmdate('l, \t\h\e jS \o\f F', $log_time);

	$hour = gmdate('H:i', $log_time);

	if ($doc_exists) {
		$doc_url = '<a href="view.php?doc_id=' . $doc_id . '">' . $doc_name .
			'</a>';
		$doc_url2 = '<a href="view.php?doc_id=' . $doc_id . '&l=' .
			$row['log_trans_lang'] .  '">' . $doc_name . '</a>';
	} else {
		$doc_url = $doc_url2 = $doc_name;
	}

	$action = '';
	switch ($row['log_action']) {
		case 'creat':
			$action = "added the document “${doc_url}”.";
		break;
		case 'mod':
			$action = "modified the document “${doc_url}”.";
		break;
		case 'del':
			$action = "deleted the document “${doc_name}”.";
		break;
		case 'ed_block':
			$s = ($row['log_trans_number'] == 1 ? '' : 's');
			$action = "edited $row[log_trans_number] block$s " .
			" in the document “${doc_url2}”.";
		break;
		case 'trans':
			$s = ($row['log_trans_number'] == 1 ? '' : 's');
			$action = "translated $row[log_trans_number] block$s " .
			"to $row[lang_name] in the document “${doc_url2}”.";
		break;
	}

	echo "<li>$date at $hour, <b>$user_name</b> $action</li>";
}

?>
</ul>
<?php


include('inc/end_html.php');


function getday($timestamp) {
	return intval(gmdate('j', $timestamp));
}
?>
