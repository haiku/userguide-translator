<?php
define('IN_TRANSLATE', 1);
require_once('inc/common.php');

role_needed(ROLE_AUTHOR);

$title = 'Create New Document';

$src_path = (isset($_POST['src_path']) ? unprotect_quotes($_POST['src_path']) : '');
$trans_path = (isset($_POST['trans_path']) ? unprotect_quotes($_POST['trans_path']) : '');
$doc_title = (isset($_POST['doc_title']) ? unprotect_quotes($_POST['doc_title']) : '');

if ($src_path and $trans_path and $doc_title) {
	if (trim($doc_title) and validate_path($src_path) and validate_path(str_replace('{LANG}', '', $trans_path, $count))
		and $count == 1) {

		if (isset($_POST['confirm_ok'])) {
			if (file_exists(REF_DIR . '/' . $src_path))
				error_box($title, 'This document already exists !');

			$src_path = db_esc($src_path);
			$trans_path = db_esc($trans_path);
			$doc_title_esc = db_esc($doc_title);
			$time = time();

			if(!is_dir(dirname(REF_DIR . '/' . $src_path)))
				mkdir(dirname(REF_DIR . '/' . $src_path), 0770, true);

			file_put_contents(REF_DIR . '/' . $src_path,
				str_replace('{TITLE}', $doc_title, $base_document))
					or error_box($title, 'Unable to write to the destination directory!');


			// Insert entry in the database
			db_query('
				INSERT INTO ' . DB_DOCS . '
				(name, path_original, path_translations) ' . "
				VALUES ('$doc_title_esc', '$src_path', '$trans_path')");

			$doc_id = db_insert_id();

			require_once('inc/subversion.php');
			svn_add(REF_DIR . '/' . $src_path);
			svn_commit(REF_DIR . '/' . $src_path, 'New document: \"' . $doc_title . '"');
			svn_update(REF_DIR . '/' . $src_path);

			// Log
			db_query('
				INSERT INTO ' . DB_LOG . '
				(log_user, log_time, log_action, log_doc) ' . "
				VALUES ($user_id, $time, 'creat', $doc_id)");

			redirect('edit.php?doc_id=' . $doc_id);

		} else if(!isset($_POST['confirm_cancel'])) {
			$doc_title = htmlspecialchars($doc_title);
			confirm_box($title, 'Do you really want to add this document ?',
				'Cancel', 'Add', <<<EOD
<input type="hidden" name="src_path" value="$src_path" />
<input type="hidden" name="trans_path" value="$trans_path" />
<input type="hidden" name="doc_title" value="$doc_title" />
EOD
			);
		} else {
			include('inc/start_html.php');
		}
	} else {
		include('inc/start_html.php');
?>
<div class="box-stop">
Incorrect parameter(s).
<ul>
<li>Name field must not be blank</li>
<li>Special characters are not allowed in paths</li>
<li>The translations path must contain the {LANG} placeholder once.</li>
</div>
<?php
	}
} else {
	include('inc/start_html.php');
}

?>
<form action="" method="post">
<dl class="fieldset">
<dt><span>New document</span></dt>
<dd>
<label for="doc_title">Document name:</label>
<input type="text" name="doc_title" id="doc_title" value="<?=htmlspecialchars($doc_title)?>" size="64" maxlength="256" />
<em>Example: “WebPositive”</em>
<br/>
<label for="src_path">Source path:</label>
<input type="text" name="src_path" id="src_path" value="<?=htmlspecialchars($src_path)?>" size="64" maxlength="256" />
<br/>
<em>Example: userguide/en/applications/webpositive.html</em><br/>
<label for="trans_path">Translations path:</label>
<input type="text" name="trans_path" id="trans_path" value="<?=htmlspecialchars($trans_path)?>" size="64" maxlength="256" />
<br/>
<em>Example: userguide/{LANG}/applications/webpositive.html</em><br/>
<input type="submit" name="add_docs" value="Add" />
</dd>
</dl>
</form>
<?php
include('inc/end_html.php');

function validate_path($path) {
	if (preg_match('/^[+.-9A-Za-z_-]*$/', $path))
		if (strpos($path, '..') === false)
			return true;
	return false;
}
?>
