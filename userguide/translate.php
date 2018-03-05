<?php
require('inc/common.php');
role_needed(ROLE_TRANSLATOR);

$xml_msg = '';

if (isset($_POST['translate_doc']) and isset($_POST['translate_string'])
	and isset($_POST['translate_source']) and isset($_POST['translate_lang'])
	and isset($_POST['translate_text']) and isset($_POST['is_fuzzy'])) {
	// Translation submitted to the database

	$doc_id = intval($_POST['translate_doc']);
	$string_id = intval($_POST['translate_string']);
	$lang = validate_lang($_POST['translate_lang']);
	$text = $_POST['translate_text'];
	$source_text = $_POST['translate_source'];
	$is_fuzzy = ($_POST['is_fuzzy'] ? 1 : 0);

	$req = db_query('SELECT 1 FROM ' . DB_LANGS . " WHERE lang_code = ?", array($lang));

	if (!$text or db_num_rows($req) != 1)
		die('No text or incorrect lang');

	db_free($req);

	$req = db_query('
		SELECT source_md5 FROM ' . DB_STRINGS . "
		WHERE string_id = ?", array($string_id));

	$row = db_fetch($req);
	db_free($req);

	$orig_md5 = md5($source_text);

	if (!$row)
		die('Incorrect source ID!');

	if ($row['source_md5'] != $orig_md5)
		die('The source text seems to have changed since you opened the translation page.');

	$trans_doc = new DOMDocument();
	set_error_handler('xml_error_handler');
	$status = $trans_doc->loadXML('<?xml version="1.0" encoding="UTF-8"?>' .
		'<code>' . $text . '</code>');
	restore_error_handler();

	if (!$status || $xml_msg)
		die("badxml $xml_msg");

	if (!check_xml_tags($text, $source_text))
		die('diffxml');

	db_query('UPDATE ' . DB_STRINGS . "
		SET \"translation_$lang\"=?, \"is_fuzzy_$lang\" = ?
		WHERE source_md5 = ?", array($text, $is_fuzzy, $orig_md5));

	$req = db_query('SELECT doc_id FROM ' . DB_STRINGS . " WHERE source_md5 = ?", array($orig_md5));
	$updated_ids = array();
	while ($row = db_fetch($req)) {
		$up_id = intval($row['doc_id']);
		$updated_ids[] = $up_id;
		update_id($up_id);
	}

	$ids_list = implode(', ', $updated_ids);
	db_query('UPDATE ' . DB_DOCS . "
		SET \"is_dirty_$lang\" = 1
		WHERE doc_id IN ($ids_list)");

	// Log
	$time = time();
	$delay = $time - 5 * 60;
	$result = db_query('SELECT log_id FROM ' . DB_LOG . "
		WHERE log_user = ? AND log_time > ? AND log_doc = ?
		AND log_action = 'trans' AND log_trans_lang = ? LIMIT 1",
		array($user_id, $delay, $doc_id, $lang));

	if (!db_num_rows($result)) {
		db_query('
			INSERT INTO ' . DB_LOG . '
			(log_user, log_time, log_action, log_doc, log_trans_number,
				log_trans_lang) ' . "
			VALUES (?, ?, ?, ?, ?, ?)", array($user_id, $time, 'trans', $doc_id, 1, $lang));
	} else {
		$row = db_fetch($result);
		db_query('UPDATE ' . DB_LOG . '
			SET log_trans_number = log_trans_number + 1 WHERE log_id = ?',
			array($row['log_id']));
	}

	db_query('UPDATE ' . DB_USERS . '
		SET num_translations = num_translations + 1 ' . "
		WHERE user_id = ?", array($user_id));

	exit('ok');
}

$doc_id = (isset($_GET['doc_id']) ? intval($_GET['doc_id']) : 0);
$lang = (isset($_GET['l']) ? validate_lang($_GET['l']) : '');

$req = db_query('
	SELECT lang_name FROM ' . DB_LANGS . "
	WHERE lang_code = ?", array($lang));
$row = db_fetch($req);
db_free($req);

if (!$row)
	redirect('index.php');

$lang_name = $row['lang_name'];

$req = db_query('
	SELECT path_original, path_translations FROM ' . DB_DOCS . "
	WHERE doc_id = ?", array($doc_id));
$row = db_fetch($req);
db_free($req);

if (!$row)
	redirect('index.php');

$path_original = $row['path_original'];
$path_trans = dirname(str_replace('{LANG}', $lang, $row['path_translations']));

$doc = new DOMDocument();
$doc->load(REF_DIR . '/' . $path_original)
	or die('Unable to load the document.');

// Get all the translations
$col_name = 'translation_' . $lang;
$col_fuzzy = 'is_fuzzy_' . $lang;

$req = db_query('SELECT string_id, "' . $col_name  . '", "' . $col_fuzzy . '"
	FROM ' . DB_STRINGS . "
	WHERE doc_id = ?" . '
	ORDER BY string_id', array($doc_id));

// Build the JavaScript array that will hold the source and translation strings
$js = '';
$js .= 'const base_url = \'' . str_replace("'", "\'", $base_url) . "';\n";
$js .= 'const base_local = \'' . str_replace("'", "\'", $path_trans) . "';\n";
$js .= 'const lang = window.lang = \'' . str_replace("'", "\'", $lang) . "';\n";
$js .= 'const lang_name = window.lang_name = \'' . str_replace("'", "\'", $lang_name) . "';\n";
$js .= "const doc_id = $doc_id;\n";
$js .= "var source_strings = new Array();\n";
$js .= "var translated_strings = new Array();\n";
$js .= "var is_fuzzy = new Array();\n";

while ($row = db_fetch($req)) {
	$id = $row['string_id'];

	$translated = str_replace('\\', '\\\\', $row[$col_name]);
	$translated = str_replace('"', '\"', $translated);
	$translated = str_replace("\n", '\n', $translated);
	$translated = str_replace("\r", '', $translated);

	$js .= "translated_strings[$id] = \"$translated\";\n";
	if ($row[$col_fuzzy])
		$js .= "is_fuzzy[$id] = 1;\n";
}

$js .= get_source_strings($doc);

db_free($req);

$head = $doc->getElementsByTagName('head')->item(0);

// Redirect all links of the page to the translated version
$metas = $doc->getElementsByTagName('meta');
$node = $head->firstChild;
if ($metas) {
	foreach ($metas as $meta)
		if ($meta->getAttribute('http-equiv') == 'content-type')
			$node = $meta;
}

$node = append_sibling($doc->createTextNode("\n\t"), $node);
$base = $doc->createElement('base');
$base->setAttribute('href', $base_url . '/' . EXPORT_DIR . '/' . $path_trans . '/');
$node = append_sibling($base, $node);
$node = append_sibling($doc->createTextNode("\t"), $node);

// Pass the script path to JavaScript
append_js_code($head, $js);

// Include the JavaScript translation helper
append_js_file($head, $base_url . '/shared/translate_tool.js');

html_set_lang($doc, $lang);

echo $doc->saveXML();

function append_js_code($node, $code) {
	global $doc;
	// We are generating an XML document so the JS code should be encapsuled in a
	// CDATA tag. However, the opening <![CDATA[ and closing ]]> must be preceded
	// by "//" so they are treated like JS comments.

	$js = $doc->createElement('script');
	$js->setAttribute('type', 'text/JavaScript');

	$js->appendChild($doc->createTextNode("\n//"));
	$js->appendChild($doc->createCDATASection("\n$code\n//"));
	$js->appendChild($doc->createTextNode("\n"));

	$node->appendChild($js);
	$node->appendChild($doc->createTextNode("\n"));
}

function append_js_file($node, $file) {
	global $doc;

	$js = $doc->createElement('script');
	$js->setAttribute('type', 'text/JavaScript');
	$js->setAttribute('src', $file);

	$node->appendChild($js);
	$node->appendChild($doc->createTextNode("\n"));
}

function get_source_strings($node) {
	static $used_ids = array();
	$to_return = '';
	foreach ($node->childNodes as $child) {
		if ($child instanceOf DOMElement) {
			if ($child->hasAttribute(ATTR_TRANS_ID)) {
				$id = intval($child->getAttribute(ATTR_TRANS_ID));
				if (isset($used_ids[$id]))
					continue;

				$used_ids[$id] = true;

				$text = DOMInnerHTML($child);

				$text = str_replace('\\', '\\\\', $text);
				$text = str_replace('"', '\"', $text);
				$text = str_replace("\n", '\n', $text);

				$to_return .= "source_strings[$id] = \"$text\";\n";
			} else {
				$to_return .= get_source_strings($child);
			}
		}
	}

	return $to_return;
}

function xml_error_handler($errno, $errstr, $errfile, $errline) {
	global $xml_msg;

	$err = (preg_match('/^DOMDocument::loadXML\(\) \[.*\]: (.*)$/',
		$errstr, $matches) ? $matches[1] : $errstr);

	$xml_msg .= htmlspecialchars_decode($err) . "\n";
}

function check_xml_tags($text_original, $text_translation) {
	global $relaxed_parsing_attributes, $relaxed_parsing_complete;

	$tags_original = extract_tags($text_original);
	$tags_translation = extract_tags($text_translation);

	foreach ($relaxed_parsing_complete as $tag_name) {
		unset($tags_original[$tag_name]);
		unset($tags_translation[$tag_name]);
	}

	foreach ($relaxed_parsing_attributes as $tag_name => $tag_attributes) {
		if (!isset($tags_original[$tag_name]) or !isset($tags_translation[$tag_name])) {
			if (!isset($tags_original[$tag_name]) and !isset($tags_translation[$tag_name]))
				continue;

			return false;
		}

		foreach ($tag_attributes as $tag_attribute) {
			$tags_original[$tag_name] = preg_replace("!$tag_attribute=\"[^\"]*\" ?!", '',
				$tags_original[$tag_name]);
			$tags_translation[$tag_name] = preg_replace("!$tag_attribute=\"[^\"]*\" ?!", '',
				$tags_translation[$tag_name]);
		}

		if ($tags_original[$tag_name] != $tags_translation[$tag_name])
			return false;
	}

	return true;
}


function extract_tags($text) {
	$matches = array();
	$tags = array();

	preg_match_all('!<([^/][^ >]*)( ([^>]*?))?>!', $text, $matches, PREG_SET_ORDER);

	foreach ($matches as $match) {
		$tag_name = $match[1];

		if (substr($tag_name, -1, 1) == '/') // autoclosing tag
			$tag_name = substr($tag_name, 0, strlen($tag_name) - 1);

		$tag_attr = (isset($match[3]) ? $match[3] : '');

		if (!isset($tags[$tag_name]))
			$tags[$tag_name] = array();

		$tags[$tag_name][] = $tag_attr;
	}

	return $tags;
}

function update_id($id) {
	static $lang_codes = false;

	if (!$lang_codes) {
		$lang_codes = array();
		$req = db_query('SELECT lang_code FROM ' .  DB_LANGS);
		while ($row = db_fetch($req)) {
			$lang_codes[] = $row['lang_code'];
		}
	}

	$sql = 'UPDATE ' . DB_DOCS . ' SET ';
	$first = true;

	foreach ($lang_codes as $lang_code) {
		$req = db_query('SELECT COUNT(*) FROM ' . DB_STRINGS . "
			WHERE doc_id = ? AND \"translation_$lang_code\" <> ''
			AND unused_since IS NULL AND \"is_fuzzy_$lang_code\" = 0",
		array($id));

		$row = db_fetch($req);
		$count = $row['count'];
		db_free($req);

		$req = db_query('SELECT COUNT(*) FROM ' . DB_STRINGS . "
			WHERE doc_id = ? AND \"translation_$lang_code\" <> ''
			AND unused_since IS NULL AND \"is_fuzzy_$lang_code\" = 1",
		array($id));

		$row = db_fetch($req);
		$fuzzy = $row['count'];
		db_free($req);

		$sql .= ($first ? '' : ', ');
		$sql .= "\"count_$lang_code\"=$count, \"count_fuzzy_$lang_code\"=$fuzzy";

		$first = false;
	}

	$sql .= " WHERE doc_id=?";

	db_query($sql, array($id));
}
