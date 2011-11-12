<?php
define('IN_TRANSLATE', 1);

$path_prefix = '../';
require('../inc/common.php');
require('../inc/subversion.php');

role_needed(ROLE_ADMIN);

$title = 'Import Documents';
include('admin_top.php');

$src_path = '';
$trans_path = '';
$error = '';

$insert_ids = array();

$time = time();

define('IMPORT_DIR', 'import');

if (!is_dir('../' . IMPORT_DIR) or !is_readable('../' . IMPORT_DIR))
	error_box($title, 'The import directory is missing or unreadable!');

if (isset($_POST['src_path']))
	$src_path = unprotect_quotes($_POST['src_path']);

if (isset($_POST['trans_path']))
	$trans_path = unprotect_quotes($_POST['trans_path']);

if (isset($_POST['add_documents']) and $src_path and $trans_path
	and isset($_POST['confirm_ok'])) {
	
	ignore_user_abort(true);

	$add_documents = unprotect_quotes($_POST['add_documents']);
	$documents = explode(', ', $add_documents);
	
	$count = 0;
	$imported = array();
	$error = '';
	
	$regexp = preg_quote($src_path, '=');
	$regexp = str_replace('\*', '(.*)', $regexp);
	$regexp = '=(' . $regexp . ')=';
	
	svn_update('../' . REF_DIR);
	
	foreach ($documents as $document) {
		$doc_path = '../' . IMPORT_DIR . '/' . $document;
		$tagged_path = '../' . REF_DIR . '/' . $document;
		if (strpos($document, '..') !== false or !is_file($doc_path)
			or file_exists($tagged_path))
			continue;
		
		preg_match($regexp, $document, $matches);
		
		$dest_path = $trans_path;
		preg_match($regexp, $document, $matches);
				
		for ($i = 2 ; $i < count($matches) ; $i++)
			$dest_path = implode($matches[$i], explode('*', $dest_path, 2));
		
		// Load the original file
		$doc = new DOMDocument();
		set_error_handler('catch_messages');
		$status = $doc->load($doc_path);
		restore_error_handler();
		
		if (!$status and !$error) {
			$error = "An unknown error occured while reading <b>$document</b>!";
			break;
		} else if(!$status || $error) {
			$error = "An error occured while reading $document:<br/>\n$error";
			break;
		}
		
		$doc_esc = db_esc($document);
		$dest_path = db_esc($dest_path);
		$doc_title = db_esc(get_title($doc_path));
		
		// Insert entry in the database
		db_query('
			INSERT INTO ' . DB_DOCS . '
			(name, path_original, path_translations) ' . "
			VALUES ('$doc_title', '$doc_esc', '$dest_path')");
		
		$doc_id = db_insert_id();
		
		// Log
		db_query('
			INSERT INTO ' . DB_LOG . '
			(log_user, log_time, log_action, log_doc) ' . "
			VALUES ($user_id, $time, 'creat', $doc_id)");
		
		// Generate and save the tagged file
		$insert_ids = array();
		$num_translations = mark_translation($doc, $translate_tags, $doc_id);
		
		$path_name = dirname($tagged_path);
		make_path($path_name);
	
		$doc->save($tagged_path) or die("Error: unable to save $tagged_path !");
		svn_add($tagged_path);
		
		$imported[$document] = $num_translations;
		
		db_query('
			UPDATE ' . DB_DOCS . "
			SET strings_count = $num_translations
			WHERE doc_id = $doc_id");		
	}
	
	svn_commit('../' . REF_DIR, 'Imported documents.');
	
	include('../inc/start_html.php');
	if (!empty($imported)) {

		echo '<div class="box-info">The following documents were imported:';
		echo "\n<ul>\n";
		foreach($imported as $document => $count) {
			echo '<li>' . htmlspecialchars($document) . " (Found $count " .
				' translatable blocks)' . "</li>\n";
		}
		
		echo "</ul>\n</div>\n";
		
		if (!$error) {
			$src_path = '';
			$trans_path = '';
		}
		
	} else if (!$error) {
		echo '<div class="box-info">No documents were imported.</div>' ; "\n";
	}

	if ($error) {
		$add_documents = htmlspecialchars($add_documents);
		$src_path = htmlspecialchars($src_path);
		$trans_path = htmlspecialchars($trans_path);
		
?>
<form method="post" action="">
<div class="box-stop">
<?=$error?>
<input type="submit" name="confirm_ok" value="Retry" />
<input type="hidden" name="add_documents" value="<?=$add_documents?>" />
<input type="hidden" name="src_path" value="<?=$src_path?>" />
<input type="hidden" name="trans_path" value="<?=$trans_path?>" />
</div>
</form>
<?php
	include('../inc/end_html.php');
	die();
	}
	
} else if ($src_path and $trans_path and !isset($_POST['confirm_cancel'])) {
	$pos = strpos($trans_path, '{LANG}');
	
	$count = 0;

	if (strlen($src_path) > 1 and $pos !== false and strpos($trans_path,
		'{LANG}', $pos + 1) === false and strpos($src_path, '..') === false
		and count_str('*', $src_path) == count_str('*', $trans_path)) {
		chdir('../' . IMPORT_DIR) or die('Unable to chdir !');
		$files_list = my_glob($src_path);
		
		if (!is_array($files_list))
			die('It seems your ISP disabled the "glob" function. <br/>' .
				'Set $use_system_glob to false in inc/config.php.');
		
		$docs_list = array();
		
		foreach ($files_list as $file) {
			if (!file_exists('../' . REF_DIR . '/' . $file))
				$docs_list[] = $file;
		}		
		
		if (!empty($docs_list)) {
			$text = 'About to add ' . count($docs_list);
			$text .= (count($docs_list) == 1 ? ' document' : ' documents');
			$text .= " : \n<ul>\n";
			
			foreach($docs_list as $doc) {
				$text .= '<li>' . htmlspecialchars($doc) . "</li>\n";
			}
			
			$text .= "</ul>\n";
			$text .= "This process can take a long time. Please be patient.\n";
			
			$add_documents = implode(', ', array_map('htmlspecialchars',
				$docs_list));
			$src_path = htmlspecialchars($src_path);
			$trans_path = htmlspecialchars($trans_path);
			
			confirm_box($title, $text, 'Cancel', 'Add these documents',
<<<EOD
<input type="hidden" name="add_documents" value="$add_documents" />
<input type="hidden" name="src_path" value="$src_path" />
<input type="hidden" name="trans_path" value="$trans_path" />
EOD
);			
		} else {
			include('../inc/start_html.php');
			if (!empty($files_list))
				echo '<div class="box-info">Every matched document was ' .
					"already in the database.</div>\n";
			else
				echo '<div class="box-stop">No documents were matched.</div>'
					. "\n";
		}
	} else {
		include('../inc/start_html.php');
		echo '<div class="box-stop">Adding document(s) failed: Incorrect ' .
			"parameters.</div>\n";
	}
} else {
	include('../inc/start_html.php');
}

?>
<br/>
<form action="" method="post">
<dl class="fieldset">
<dt><span>Import Document(s)</span></dt>
<dd>
<label for="src_path">Source path:</label>
<input type="text" name="src_path" id="src_path" value="<?=$src_path?>"
	maxlength="256" />
<br/>
<label for="trans_path">Translations path:</label>
<input type="text" name="trans_path" id="trans_path" value="<?=$trans_path?>"
	maxlength="256" />
<br/>
<em>Paths are relative to the import directory.<br/>
The translation path must contain the {LANG} placeholder, which will
be replaced by the language code of each translated file.<br/>
You can add several files in one operation by using wildcards. For instance :
“en/*.html" => “{LANG}/*.html”.
</em><br/>
<input type="submit" name="add_docs" value="Add" />
</dd>
</dl>
</form>
<?php
include('../inc/end_html.php');

function mark_translation($node, $tags, $doc_id) {
	global $insert_ids;

	$my_count = 0;

	foreach ($node->childNodes as $child) {
		if ($child instanceOf DOMElement and isset($tags[$child->tagName])) {
			if(is_array($tags[$child->tagName])) {
				$my_count += mark_translation($child, $tags[$child->tagName],
					$doc_id);
			
			} else if(trim($child->nodeValue, "  \t\n\r\0\x0B") != '') {
				// This block should be added to the database
				$innerHTML = DOMinnerHTML($child);
				$md5 = md5($innerHTML);
				
				$id = 0;
				
				if (isset($insert_ids[$md5])) {
					$id = $insert_ids[$md5];
				} else {		
					$req = db_query('
						INSERT INTO ' . DB_STRINGS . '
						(doc_id, source_md5) ' . "
						VALUES ($doc_id, '$md5')"
					);
				
				
					$id = db_insert_id();
					$insert_ids[$md5] = $id;
					$my_count++;
				}
				
				$child->setAttribute(ATTR_TRANS_ID, $id);
				$to_translate[$id] = $innerHTML;
			}
		}
	}
	
	return $my_count;
}

function catch_messages($errno, $errstr, $errfile, $errline) {
	global $error;
	
	if (preg_match('/^DOMDocument::load\(\)( \[.*\])?: (.*) in .*?(,.*)$/s',
		$errstr, $matches)) {
		$error .= '[XML Parser] ' . $matches[2] . ' in current document'.
			$matches[3] . "<br/>\n";
	} else {
		$error .= $errstr;
	}
}

function get_title($path) {
	$file = fopen($path, 'r');
	
	if (!$file)
		return '';
	
	while (!feof($file)) {
		$line = fgets($file);
		if (preg_match('=<title>(.*)</title>=', $line, $matches))
			return $matches[1];
	}
	
	return '';
}

function make_path($path) {
	if (!file_exists($path)) {
		make_path(dirname($path));
		mkdir($path);
		svn_add($path);
	} else if (!is_dir($path)) {
		error_box("Error creating path: $path exists and is not a directory!");
	} else if ($path == '.' && !is_dir('.svn')) {
		svn_add($path);
	}
}
?>
