<?php
$path_prefix = '';
require('inc/common.php');

role_needed(ROLE_TRANSLATOR);

$title = 'Resource upload';

if (isset($_GET['path'])) {
	$path = get_absolute_path($_GET['path']);

	if (!$path)
		error_box($title, 'Incorrect path!');

	if (isset($_GET['lang'])) {
		$req = db_query('SELECT resource_id, path_translated FROM ' . DB_RESOURCES);
		$id = 0;
		while (!$id and $row = db_fetch($req)) {
			if ($path == str_replace('{LANG}', $_GET['lang'], $row['path_translated']))
				$id = $row['resource_id'];
		}

		if (!$id)
			error_box($title, 'Unable to resolve the image path! Is it translatable?');

		redirect('res_upload.php?id=' . $id . '&lang=' . $_GET['lang']);
	} else {
		$req = db_query('
			SELECT resource_id FROM ' . DB_RESOURCES . "
			WHERE path_untranslated = ?", array($path));

		$row = db_fetch($req);
		if (!$row) {
			if ($user_role >= ROLE_AUTHOR) {
				redirect('admin/resources_add.php?src_path=' . urlencode($path));
			}
			error_box($title, 'Unable to resolve the image path!');
		}

		redirect('res_upload.php?id=' . $row['resource_id']);
	}

}

$id = intval(@$_GET['id']);
$error = '';

$req = db_query('
	SELECT path_untranslated, path_translated FROM ' . DB_RESOURCES . "
	WHERE resource_id = ?", array($id));

$row = db_fetch($req);

if (!$row) {
	include('inc/start_html.php');
	die("No resources with this ID!");
}

$language = 'Original';
$file_path = '';

if (isset($_GET['lang'])) {
	$code = $_GET['lang'];
	$file_path = str_replace('{LANG}', $code, $row['path_translated']);
	if (!$file_path)
		error_box($title, "This resource is not translatable.");

	$req = db_query('SELECT lang_name, loc_name FROM ' . DB_LANGS . " WHERE lang_code = ?", array($code));
	$row = db_fetch($req);

	if (!$row)
		error_box($title, "No such language!");

	$language = "$row[lang_name] / $row[loc_name]";

} else {
	$file_path = $row['path_untranslated'];
}

$res_path = EXPORT_DIR . '/' . $file_path;
$orig_type = get_image_type($res_path);
if (!$orig_type and $user_role < ROLE_AUTHOR) {
	$error = 'The original image format does not seem valid. Uploading a new image will not be possible. Please contact the webmaster.';
}

if (!$error and isset($_FILES['new_res']) and $_FILES['new_res']['error'] != UPLOAD_ERR_NO_FILE) {
	if ($_FILES['new_res']['error'] != UPLOAD_ERR_OK) {
		$error = get_error($_FILES['new_res']['error']);
	} else {
		$temp_path = $_FILES['new_res']['tmp_name'];

		$type = get_image_type($temp_path);

		if (!$type) {
			$error = 'The uploaded image seems invalid.';
		} else if ($type != $orig_type and $user_role < ROLE_AUTHOR) {
			$error = "The new image format ($type) is different from the original ($orig_type).";
		} else {
			make_path(dirname($res_path));
			if (!@move_uploaded_file($temp_path, $res_path)) {
				$error = 'An error occurred while saving the uploaded file. Please try again.';
			}
		}
	}
}

include('inc/start_html.php');

?>

<h1>Resource manager</h1>
<?php if ($error) { ?>
<div class="box-stop"><?=$error?></div>
<?php } ?>
<dl>
<dt>Resource path:</dt>
<dd><tt><?=$file_path?></tt></dd>
<dt>Language:</dt>
<dd><?=$language?></dd>
<dt>Current Resource:</dt>
<dd><img src="<?=$res_path?>" /></dd>
</dl>

<form enctype="multipart/form-data" method="post" action="">
<dl class="fieldset">
<dt><span>Upload new resource</span></dt>
<dd>
<label for="new_res">Upload file:</label> <input type="file" name="new_res" id="new_res" /><br/>
<input type="submit" value="Send File" />
</dd>
</dl>
</form>

<?php
include('inc/end_html.php');

function get_image_type($image_path) {
	$info = @getimagesize($image_path);
	if (!$info)
		return false;

	if ($info[0] < 2 or $info[1] < 2)
		return false;

	return $info['mime'];
}

function get_error($code) {
	$fatal = 'Please contact the webmaster.';

	switch ($code) {
		case UPLOAD_ERR_INI_SIZE:
		case UPLOAD_ERR_FORM_SIZE:
			return 'The updated file is too big.';
		break;

		case UPLOAD_ERR_PARTIAL:
			return 'The file was only partially sent. Please retry.';
		break;

		case UPLOAD_ERR_NO_TMP_DIR:
			return "The temporary dir missing. $fatal";
		break;

		case UPLOAD_ERR_CANT_WRITE:
			return "An error occurred while saving the file. $fatal";
		break;

		default:
			return "An unknown error occurred. $fatal";
		break;
	}
}

function get_absolute_path($path) {
	$path = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $path);
	$parts = array_filter(explode(DIRECTORY_SEPARATOR, $path), 'strlen');
	$absolutes = array();
	foreach ($parts as $part) {
		if ('.' == $part) continue;
		if ('..' == $part) {
			array_pop($absolutes);
		} else {
			$absolutes[] = $part;
		}
	}
	return implode(DIRECTORY_SEPARATOR, $absolutes);
}

function make_path($path) {
	if (!file_exists($path)) {
		make_path(dirname($path));
		mkdir($path);
	} else if (!is_dir($path)) {
		error_box("Error creating path: $path exists and is not a directory!");
	}
}
