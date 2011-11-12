<?php

if (!defined('IN_TRANSLATE'))
	exit;

function svn_update($path) {
	$path = escapeshellarg($path);
	
	run_command("svn update --non-interactive $path 2>&1");
}

function svn_add($path) {
	$path = escapeshellarg($path);
	
	run_command("svn add $path 2>&1");
}

function svn_del($path) {
	$path = escapeshellarg($path);
	
	run_command("svn del $path 2>&1");
}

function svn_commit($path, $comment) {
	$path = escapeshellarg($path);
	$comment = escapeshellarg($comment);	
	
	run_command("svn commit --non-interactive --message $comment $path 2>&1");
}

function svn_log($path) {
	$path = escapeshellarg($path);
	run_command("svn update --non-interactive $path 2>&1");
	
	$data = run_command("svn --xml log $path");
	
	$doc = new DOMDocument();
	if (!@$doc->loadXML($data))
		return false;

	$entries = $doc->getElementsByTagName('logentry');
	if (!$entries)
		return false;
	
	$revs = array();
	
	foreach ($entries as $entry) {
		$rev = $entry->getAttribute('revision');
		
		$children = $entry->childNodes;
		foreach ($children as $child) {
			if (!($child instanceOf DOMElement))
				continue;
		
			switch ($child->tagName) {
				case 'date':
					$date = $child->firstChild->wholeText;
					$date = substr($date, 0, 10) . ' ' . substr($date, 11, 8);
					$revs[$rev]['date'] = $date;
				break;
				case 'msg':
					$revs[$rev]['msg'] = $child->firstChild->wholeText;
				break;
			}
		}
	}
	
	return $revs;	
}

function svn_cat($path, $rev) {
	$path = escapeshellarg($path);
	$rev = intval($rev);
	$output = run_command("svn cat -r $rev $path 2>&1");
	
	if (!$output)
		return false;

	return $output;
}


function run_command($command) {
	$return = 0;
	ob_start();
	passthru($command, $return);
	$output = ob_get_clean();
	
	if ($return != 0) {
		$command = htmlspecialchars($command);
		$output = htmlspecialchars($output);
		error_box('SVN Error', <<<EOD
An error occured during the execution of the SVN command:
<pre>$command
$output</pre>
Please contact a website administrator.
EOD
);
	}

	return $output;
}
