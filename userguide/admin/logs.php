<?php
define('IN_TRANSLATE', 1);

$path_prefix = '../';
require_once('../inc/common.php');
require_once('../inc/a2_log.class.php');

role_needed(ROLE_ADMIN);

$title = 'Log viewer';
require_once('admin_top.php');

// Check if there are any logs configured to view
if(empty($a2_logs) || !is_array($a2_logs))
	error_box('Log viewer', 'There are no logs configured to be viewed.');

require_once('../inc/start_html.php');
?>
<h1>Log Viewer</h1>

<?php
	foreach($a2_logs as $name=>$file){
		$a2_log = new a2_log();
		$a2_log->name = $name;
		$a2_log->logfile = $file;
		echo '<h2>'.$a2_log->name.'</h2>';
		echo nl2br($a2_log->get_log());
	}

require_once('../inc/end_html.php');