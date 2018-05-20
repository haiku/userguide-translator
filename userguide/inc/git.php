<?php

function git_pull($path) {
	if (1) return; // Pull/push disabled for now.
	$path = escapeshellarg($path);

	run_command("git -C $path pull --ff-only 2>&1");
}
function git_push($path) {
	if (1) return; // Pull/push disabled for now.
	$path = escapeshellarg($path);

	run_command("git -C $path push 2>&1");
}

function git_add($thepath) {
	$path = escapeshellarg(basename($thepath));
	$repo = escapeshellarg(dirname($thepath));

	run_command("git -C $repo add $path 2>&1");
}

function git_rm($thepath) {
	$path = escapeshellarg(basename($thepath));
	$repo = escapeshellarg(dirname($thepath));

	run_command("git -C $repo rm $path 2>&1");
}

function git_commit($path, $comment) {
	$path = escapeshellarg($path);
	$comment = escapeshellarg($comment);

	run_command("git -C $path commit -m $comment 2>&1");
}

function git_log($thepath) {
	$path = escapeshellarg(basename($thepath));
	$repo = escapeshellarg(dirname($thepath));
	git_pull($thepath);

	$data = run_command("git -C $repo log --format=\"%H\t\t\t\t%aI\t\t\t\t%s\" $path");

	$entries = explode("\n", $data);
	if (!$entries)
		return false;

	$at = 0;
	$revs = array();
	foreach ($entries as $entry) {
		if (!$entry)
			continue;
		$entry = explode("\t\t\t\t", $entry);
		if (count($entry) < 3)
			continue;
		$revs[$at]['commit'] = $entry[0];
		$revs[$at]['date'] = $entry[1];
		$revs[$at]['msg'] = $entry[2];
		$at++;
	}

	return $revs;
}

function git_cat($thepath, $commit) {
	$path = escapeshellarg(basename($thepath));
	$repo = escapeshellarg(dirname($thepath));

	$output = run_command("git -C $repo show $commit:./$path 2>&1");

	if (!$output)
		return false;

	return $output;
}


function run_command($command) {
	$return = 0;
	$output = array();
	exec($command, $output, $return);
	$output = implode("\n", $output);

	if ($return != 0) {
		$command = htmlspecialchars($command);
		$output = htmlspecialchars($output);
		error_box('Git Error', <<<EOD
An error ($return) occured during the execution of the Git command:
<pre>$command</pre>
<pre>$output</pre>
Please contact a website administrator.
EOD
);
	}

	return $output;
}
