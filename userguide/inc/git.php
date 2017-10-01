<?php

function git_pull($path) {
	if (1) return; // Pull/push disabled for now.
	$path = escapeshellarg($path);

	run_command("pushd $path && git pull --ff-only 2>&1 && popd");
}
function git_push($path) {
	if (1) return; // Pull/push disabled for now.
	$path = escapeshellarg($path);

	run_command("pushd $path && git push 2>&1 && popd");
}

function git_add($thepath) {
	$path = escapeshellarg(basename($thepath));
	$repo = escapeshellarg(dirname($thepath));

	run_command("pushd $repo && git add $path 2>&1 && popd");
}

function git_rm($thepath) {
	$path = escapeshellarg(basename($thepath));
	$repo = escapeshellarg(dirname($thepath));

	run_command("pushd $repo && git rm $path 2>&1 && popd");
}

function git_commit($path, $comment) {
	$path = escapeshellarg($path);
	$comment = escapeshellarg($comment);

	run_command("pushd $path && git commit -m $comment 2>&1 && popd");
}

function git_log($thepath) {
	$path = escapeshellarg(basename($thepath));
	$repo = escapeshellarg(dirname($thepath));
	git_pull($thepath);

	$data = run_command("pushd $repo && git log --format=\"%H\t\t\t\t%aI\t\t\t\t%s\" $path && popd");

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

	$output = run_command("pushd $repo && git show $commit:./$path 2>&1 && popd");

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
		error_box('Git Error', <<<EOD
An error occured during the execution of the Git command:
<pre>$command
$output</pre>
Please contact a website administrator.
EOD
);
	}

	return $output;
}
