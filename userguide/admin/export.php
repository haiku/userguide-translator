<?php
$path_prefix = '../';
require('../inc/common.php');
require('../inc/config_export.php');

role_needed(ROLE_ADMIN);

$title = 'Export Documents';
include('admin_top.php');

$delay = 25000;

$trans_by_md5 = array();

if (isset($_SESSION['exp_cur_lang']) and isset($_SESSION['exp_langs_loc'])
	and isset($_SESSION['exp_langs'])) {

	$lang_code = $_SESSION['exp_cur_lang'];
	$lang_name = ($lang_code ? $_SESSION['exp_langs'][$lang_code] : '');
	$dirty_column = 'is_dirty' . ($lang_code ? '_' . $lang_code : '');
	$documents = array();
	$doc_ids = '';

	if ($lang_code) {
		$req = db_query('
			SELECT doc_id, path_original, path_translations, strings_count,
			count_' . $lang_code . ', count_fuzzy_' . $lang_code . '
			FROM ' . DB_DOCS . "
			WHERE is_disabled = 0 AND $dirty_column = 1");
	} else {
		$req = db_query('
			SELECT doc_id, path_original, path_translations
			FROM ' . DB_DOCS . "
			WHERE is_disabled = 0 AND $dirty_column = 1");
	}
	while ($row = db_fetch($req)) {
		$doc_id = $row['doc_id'];
		$documents[$doc_id]['o'] = $row['path_original'];
		$documents[$doc_id]['t'] = $row['path_translations'];
		$documents[$doc_id]['s'] = array();
		$documents[$doc_id]['c'] = (!$lang_code or ($row['strings_count'] == $row['count_' . $lang_code] and !$row['count_fuzzy_' . $lang_code]));
		$doc_ids .= ($doc_ids ? ', ' . $doc_id : $doc_id);
	}

	// Non-English: load the translations
	if ($lang_code and $doc_ids) {
		$req = db_query('
			SELECT doc_id, source_md5, string_id, translation_' . $lang_code . '
			FROM ' . DB_STRINGS . "
			WHERE doc_id IN ($doc_ids) AND translation_$lang_code <> '' AND unused_since IS NULL AND is_fuzzy_$lang_code = 0");

		$trans_by_md5 = array();
		while ($row = db_fetch($req)) {
			$documents[$row['doc_id']]['s'][$row['string_id']] = $row['translation_' . $lang_code];
			$trans_by_md5[$row['source_md5']] = $row['translation_' . $lang_code];
		}
	}

	foreach ($documents as $doc_id => $data) {
		$doc = new DOMDocument();
			@$doc->load('../' . REF_DIR . '/' . $data['o'])
				or error_box($title, "Unable to load $data[o] !");

		$file_path = '';
		if ($lang_code) {
			replace_translations($doc, $doc, $lang_code, $data['s']);
			$file_path = '../' . EXPORT_DIR . '/' . str_replace('{LANG}', $lang_code, $data['t']);
		} else {
			delete_tags($doc, $doc);
			$file_path = '../' . EXPORT_DIR . '/' . $data['o'];
		}

		create_path(dirname($file_path));

		$text = $doc->saveXML()
			or error_box($title, "Unable to create the XML for $file_path !");

		document_hook($data['o'], $data['t'], $doc_id, $lang_code, $data['c'], $text);

		if ($text) {
			file_put_contents($file_path, $text)
				or die("Error: unable to save $file_path !");
		}

		unset($text);
		unset($doc);
		usleep($delay);
	}

	$sql = 'UPDATE ' . DB_DOCS . " SET $dirty_column = 0";

	if (!$lang_code) {
		// If we regenerated some untranslated, documents, we must invalidate
		// all of their translations.

		foreach ($_SESSION['exp_langs_loc'] as $lang => $name) {
			$sql .= ", is_dirty_$lang = 1";
		}
	}


	$sql .= " WHERE is_disabled = 0 AND doc_id IN ($doc_ids)";

	if ($doc_ids)
		db_query($sql);

	$count = count($_SESSION['exp_langs']);

	if ($count == 0 or ($count == 1 and $lang_code)) { // Done !
		if (isset($_SESSION['exp_zip'])) {
			$files = '';
			$langs = array();
			$req = db_query('SELECT lang_code FROM ' . DB_LANGS . ' WHERE is_disabled = 0');
			while ($row = db_fetch($req)) {
				$langs[] = $row['lang_code'];
			}

			$db_paths = array();

			$req = db_query('SELECT path_original, path_translations FROM ' . DB_DOCS . ' WHERE is_disabled = 0');
			while ($row = db_fetch($req)) {
				if (file_exists('../' . EXPORT_DIR . '/' . $row['path_original']))
					$files .= ' docs/' . $row['path_original'];
				foreach ($langs as $lang) {
					$file = str_replace('{LANG}', $lang, $row['path_translations']);
					if (file_exists('../' . EXPORT_DIR . '/' . $file))
						$files .= ' docs/' . $file;
				}
			}

			$req = db_query('SELECT path_untranslated, path_translated FROM ' . DB_RESOURCES);
			while ($row = db_fetch($req)) {
				if (file_exists('../' . EXPORT_DIR . '/' . $row['path_untranslated']))
					$files .= ' docs/' . $row['path_untranslated'];
				foreach ($langs as $lang) {
					$file = str_replace('{LANG}', $lang, $row['path_translated']);
					if ($file and file_exists('../' . EXPORT_DIR . '/' . $file))
						$files .= ' docs/' . $file;
				}
			}

			DEBUG_LOG('About to start generating userguide.zip...');
			$pwd = getcwd();
			DEBUG_LOG('Working directory is ' . $pwd . '.');
			chdir('../' . EXPORT_DIR) or die('Unable to chdir!');
			DEBUG_LOG('Changed working directory to ' . getcwd() . '.');

			if(file_exists('userguide.zip')){
				DEBUG_LOG('userguiede.zip already exists. Attempting to deleting it...');
				if(!unlink('userguide.zip')){
					DEBUG_LOG('userguiede.zip could not be deleted. Bailing out.');
					exit;
				}else{
					DEBUG_LOG('userguiede.zip was successfully deleted.');
				}
			}


			DEBUG_LOG('Attempting to compress the userguide to userguide.zip.');

			$export=exec('zip -v userguide.zip' . $files, $output, $return);
			DEBUG_LOG('Zip output: '.$export);
			DEBUG_LOG('Zip output: '.serialize($output));
			DEBUG_LOG('Zip return: '.$return);

			chdir($pwd) or die('Unable to chdir!');
			unset($_SESSION['exp_cur_lang']);
			unset($_SESSION['exp_langs_loc']);
			unset($_SESSION['exp_langs']);
			unset($_SESSION['exp_zip']);
			$msg = final_status_hook();

			message_end('Exporting done!<br/>' . $msg, $top);
		} else {
			$_SESSION['exp_zip'] = true;
			header('Refresh: 1;url=' . $base_url . '/admin/export.php');
			message_end('Creating the archive…', $top);
		}
		unset($_SESSION['exp_cur_lang']);
		unset($_SESSION['exp_langs_loc']);
		unset($_SESSION['exp_langs']);
		$msg = final_status_hook();
		message_end('Exporting done!<br/>' . $msg, $top);
	} else {
		if ($lang_code)
			unset($_SESSION['exp_langs'][$lang_code]);

		list($new_code) = array_keys($_SESSION['exp_langs']);
		$new_name = $_SESSION['exp_langs'][$new_code];
		$_SESSION['exp_cur_lang'] = $new_code;
		header('Refresh: 1;url=' . $base_url . '/admin/export.php');
		message_end('Export in progress, please wait…<br/> Currently exporting ' . $new_name . ' pages.');
	}

	exit;
} else if (isset($_POST['export_docs'])) {
	$_SESSION['exp_cur_lang'] = '';
	$_SESSION['exp_langs'] = array();
	$_SESSION['exp_langs_loc'] = array();

	$req = db_query('SELECT * FROM ' . DB_LANGS . ' WHERE is_disabled = 0');
	while ($row = db_fetch($req)) {
		$_SESSION['exp_langs'][$row['lang_code']] = htmlspecialchars($row['lang_name']);
		$_SESSION['exp_langs_loc'][$row['lang_code']] = htmlspecialchars($row['loc_name']);
	}

	if (isset($_POST['rebuild_all']))
		db_query('UPDATE ' . DB_DOCS . ' SET is_dirty = 1 WHERE is_disabled = 0');

	start_hook();

	header('Refresh: 1;url=' . $base_url . '/admin/export.php');
	message_end('Export in progress, please wait…<br/> Currently exporting original (untranslated) pages.');
}


include('../inc/start_html.php');

?>
<form action="" method="post">
<dl class="fieldset">
<dt><span>Export options</span></dt>
<dd>
<label><input type="checkbox" name="rebuild_all">Export every document</label><br/>
<em>By default, only documents modified since the previous export will be re-generated.</em><br/>
<input type="submit" name="export_docs" value="Run Export" />
</dd>
</dl>
</form>


<?php
include('../inc/end_html.php');

function replace_translations($doc, $node, $lang, &$translations) {
	foreach ($node->childNodes as $child) {
		if ($child instanceOf DOMElement) {
			if ($child->hasAttribute(ATTR_TRANS_ID)) {
				$id = $child->getAttribute(ATTR_TRANS_ID);
				$child->removeAttribute(ATTR_TRANS_ID);

				if (isset($translations[$id])) {
					while ($child->hasChildNodes())
						$child->removeChild($child->firstChild);

					$temp_doc = new DOMDocument();
					@$temp_doc->loadXML('<?xml version="1.0" encoding="UTF-8"?>'
						. '<code>' . $translations[$id] . '</code>')
						or error_box($title, "String ID $id, lang $lang has XML errors !");

					foreach ($temp_doc->firstChild->childNodes as $sub_child) {
						$child->appendChild($doc->importNode($sub_child, true));
					}

				}
			} else {
				replace_translations($doc, $child, $lang, $translations);
			}
		}
	}
}

function delete_tags($doc, $node) {
	foreach ($node->childNodes as $child) {
		if ($child instanceOf DOMElement) {
			if ($child->hasAttribute(ATTR_TRANS_ID)) {
				$child->removeAttribute(ATTR_TRANS_ID);
			} else {
				delete_tags($doc, $child);
			}
		}
	}
}

function create_path($path) {
	$parent = dirname($path);

	if (!is_dir($path)) {
		if ($parent)
			create_path($parent);
		mkdir($path) or die("Unable to create $path !");
	}
}

function message_end($text, $top = ' ') {
	global $title, $path_prefix;

	include('../inc/start_html.php');
	echo '<div class="box-info">' . $text . '</div>';
	include('../inc/end_html.php');
	exit;
}

?>
