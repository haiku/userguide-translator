<?php
define('IN_TRANSLATE', 1);

$path_prefix = '../';
require('../inc/common.php');

role_needed(ROLE_ADMIN);

$title = 'Add Resources';
include('admin_top.php');

$src_path = '';
$trans_path = '';

if (isset($_POST['src_path']))
	$src_path = unprotect_quotes($_POST['src_path']);

if (isset($_POST['trans_path']))
	$trans_path = unprotect_quotes($_POST['trans_path']);

if (isset($_POST['added_resources']) and $src_path
	and isset($_POST['confirm_ok'])) {

	$added_resources = unprotect_quotes($_POST['added_resources']);
	$resources = explode(', ', $added_resources);
	
	$regexp = preg_quote($src_path, '=');
	$regexp = str_replace('\*', '(.*)', $regexp);
	$regexp = '=(' . $regexp . ')=';
	
	foreach ($resources as $resource) {
		chdir('../' . EXPORT_DIR) or die('Unable to chdir !');
			
		$dest_path = '';
		if ($trans_path) {
			$dest_path = $trans_path;
			preg_match($regexp, $resource, $matches);
				
			for ($i = 2 ; $i < count($matches) ; $i++)
				$dest_path = implode($matches[$i], explode('*', $dest_path, 2));
		}
		
		$path_untranslated = db_esc($resource);
		$path_translated = db_esc($dest_path);
		
		db_query('
			INSERT INTO ' . DB_RESOURCES . '
			(path_untranslated, path_translated)' . "
			VALUES ('$path_untranslated', '$path_translated')
		");
		
	}
	
	include('../inc/start_html.php');
	echo '<div class="box-info">The resources were added to the database.</div>' . "\n";

} else if ($src_path and !isset($_POST['confirm_cancel'])) {
	$req = db_query('SELECT path_untranslated FROM ' . DB_RESOURCES);
	$in_db = array();
	while ($row = db_fetch($req)) {
		$in_db[] = $row['path_untranslated'];
	}

	$req_ok = true;
	
	if (strlen($src_path) <= 1)
		$req_ok = false;
	
	if (strpos($src_path, '..') !== false)
		$req_ok = false;
	
	if ($trans_path) {
		$pos = strpos($trans_path, '{LANG}');
		
		if ($pos === false or strpos($trans_path, '{LANG}', $pos + 1) !== false)
			$req_ok = false;

		if (count_str('*', $src_path) != count_str('*', $trans_path))
			$req_ok = false;
		
		if (strpos($trans_path, '..') !== false)
			$req_ok = false;
	}
	
	if ($req_ok and strpos($src_path, '*') !== false) {
		chdir('../' . EXPORT_DIR) or die('Unable to chdir !');
		$files_list = my_glob($src_path);
		
		if (!is_array($files_list))
			die('It seems your ISP disabled the "glob" function. <br/>' .
				'Set $use_system_glob to false in inc/config.php.');
		
		$rsrc_list = array_diff($files_list, $in_db);
		if (!empty($rsrc_list)) {
			$text = 'About to add ' . count($rsrc_list);
			$text .= (count($rsrc_list) == 1 ? ' resource' : ' resources');
			$text .= " : \n<ul>\n";
			
			foreach($rsrc_list as $doc) {
				$text .= '<li>' . htmlspecialchars($doc) . "</li>\n";
			}
			
			$text .= "</ul>\n";
			$text .= "This process can take a long time. Please be patient.\n";
			
			$added_resources = implode(', ', array_map('htmlspecialchars',
				$rsrc_list));
			$src_path = htmlspecialchars($src_path);
			$trans_path = htmlspecialchars($trans_path);
			
			confirm_box($title, $text, 'Cancel', 'Add these resources',
<<<EOD
<input type="hidden" name="added_resources" value="$added_resources" />
<input type="hidden" name="src_path" value="$src_path" />
<input type="hidden" name="trans_path" value="$trans_path" />
EOD
);			
		} else {
			include('../inc/start_html.php');
			if (!empty($files_list))
				echo '<div class="box-info">Every resource was ' .
					"already in the database.</div>\n";
			else
				echo '<div class="box-stop">No resources were matched.</div>'
					. "\n";
		}
	} else if ($req_ok and isset($_FILES['src_file'])) {
		include('../inc/start_html.php');
		
		$file = $_FILES['src_file'];
		
		$path_untranslated = db_esc($src_path);
		$path_translated = db_esc($trans_path);
		
		$req = db_query('
			SELECT resource_id FROM ' . DB_RESOURCES . "
			WHERE path_untranslated = '$path_untranslated'
		");
		
		$already_exists = file_exists('../' . EXPORT_DIR . '/' . $src_path);
		$has_file = ($file['error'] == UPLOAD_ERR_NO_FILE ? false : true);
		
		if (db_fetch($req)) {
			echo '<div class="box-warning">This resource is already registered. Use the resource manager to upload a new file.</div>' . "\n";
		} else {
			if ($already_exists and $has_file) {
				echo '<div class="box-warning">An unregistred file with this name already exists.' . 
					' Please add it to the managed resources list (without uploading a new file), ' .
					' and then use the resource manager to replace the file.</div>' . "\n";
			} else if (!$already_exists and !$has_file) {
				echo '<div class="box-warning">The specified resource does not yet exist on the server. Please upload a source file.</div>' . "\n";
			} else if ($has_file and $file['error'] != UPLOAD_ERR_OK) {
				echo '<div class="box-stop">' . get_error($file['error']) . '</div>' . "\n";
			} else { // Phew! No error ! For now…
				make_path(dirname('../' . EXPORT_DIR . '/' . $src_path));
				if ($has_file and !move_uploaded_file($file['tmp_name'], '../' . EXPORT_DIR . '/' . $src_path)) {
					echo '<div class="box-stop">An error occurred while writing the uploaded file.</div>' . "\n";
				} else {
					db_query('
						INSERT INTO ' . DB_RESOURCES . '
						(path_untranslated, path_translated)' . "
						VALUES ('$path_untranslated', '$path_translated')
					");
					
					if ($path_translated) {
						echo '<div class="box-info">The localizable resource was successfully added.' .
						'<br/>The file may be missing from the translations. ' .
						'<a href="resources_sync.php">Click here</a> to copy any missing resource ' .
						'from the source directory to the translations directories.</div>' . "\n";
					} else {
						echo '<div class="box-info">The resource was successfully added.</div>' . "\n";
					}
				}
			}
		}
	
	} else {
		include('../inc/start_html.php');
		echo '<div class="box-stop">Adding document(s) failed: Incorrect ' .
			"parameters.</div>\n";
	}
} else {
	include('../inc/start_html.php');
}

if (isset($_GET['src_path']))
	$src_path = unprotect_quotes($_GET['src_path']);

?>
<br/>
<form action="<?php echo $_SERVER['PHP_SELF'];?>" method="post" enctype="multipart/form-data">
<dl class="fieldset">
<dt><span>Add Resource(s)</span></dt>
<dd>
<label for="src_path">Source path:</label>
<input type="text" name="src_path" id="src_path" value="<?=$src_path?>" size="64"
	maxlength="256" /><br/>
<em>Example: “userguide/en/images/apps-images/activitymonitor.png”</em>
<br/><br/>
<label for="src_file">Source file:</label>
<input type="file" name="src_file" id="src_file" />
<br/>
<em>Leave blank if the file(s) is/are already present on the server.</em>
<br/><br/>
<label for="trans_path">Translations path:</label>
<input type="text" name="trans_path" id="trans_path" value="<?=$trans_path?>" size="64"
	maxlength="256" />
<br/>
<em>Example: “userguide/{LANG}/images/apps-images/activitymonitor.png”.<br/>
Leave blank if this resource is not translatable.<br/>
Paths are relative to the export directory.<br/>
The translation path must contain the {LANG} placeholder, which will
be replaced by the language code of each translated file.<br/>
If the files are already on the server, you can add them in one operation by using wildcards.
For instance : “en/images/*.png" => “{LANG}/images/*.png”.
</em><br/>
<input type="submit" name="add_resources" value="Add" />
</dd>
</dl>
</form>

<?php
include('../inc/end_html.php');

function make_path($path) {
	if (!file_exists($path)) {
		make_path(dirname($path));
		mkdir($path);
	} else if (!is_dir($path)) {
		error_box("Error creating path: $path exists and is not a directory!");
	}
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