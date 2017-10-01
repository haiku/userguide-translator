<?php
require('inc/common.php');
role_needed(ROLE_TRANSLATOR);

$doc_id = (isset($_GET['doc_id']) ? intval($_GET['doc_id']) : 0);
$lang = (isset($_GET['l']) ? validate_lang($_GET['l']) : '');

if ($lang) {
	$req = db_query('
		SELECT lang_name FROM ' . DB_LANGS . "
		WHERE lang_code = ?", array($lang));
	$row = db_fetch($req);
	db_free($req);

	$lang_name = $row['lang_name'];
}

$req = db_query('
	SELECT path_original, path_translations FROM ' . DB_DOCS . "
	WHERE doc_id = ?", array($doc_id));
$row = db_fetch($req);
db_free($req);

if (!$row)
	redirect('index.php');

$path_original = $row['path_original'];

if ($lang) {
	$path_trans = dirname(str_replace('{LANG}', $lang, $row['path_translations']));

	// Get all the translations
	$col_name = 'translation_' . $lang;

	$req = db_query('
		SELECT string_id, ' . $col_name  . ' FROM ' . DB_STRINGS . "
		WHERE doc_id = ?
	", array($doc_id));

	$translations = array();
	while ($row = db_fetch($req)) {
		$id = intval($row['string_id']);
		$translations[$id] = $row[$col_name];
	}

	db_free($req);
} else {
	$path_trans = dirname($path_original);
}

$doc = new DOMDocument();
$doc->load(REF_DIR . '/' . $path_original)
	or die('Unable to load the document.');

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
$base->setAttribute('href', $base_url . '/' . EXPORT_DIR . '/' .
	($path_trans == '.' ? '' : $path_trans . '/'));
$node = append_sibling($base, $node);
$node = append_sibling($doc->createTextNode("\t"), $node);

html_inject_viewport($doc);
html_set_lang($doc, $lang);

replace_translations($doc, $doc);

echo $doc->saveXML();

function replace_translations($doc, $node) {
	global $translations;

	foreach ($node->childNodes as $child) {
		if ($child instanceOf DOMElement) {
			if ($child->hasAttribute(ATTR_TRANS_ID)) {
				$id = $child->getAttribute(ATTR_TRANS_ID);
				$child->removeAttribute(ATTR_TRANS_ID);

				if (isset($translations[$id]) and $translations[$id]) {

					while ($child->hasChildNodes())
						$child->removeChild($child->firstChild);

					$temp_doc = new DOMDocument();
					$temp_doc->loadXML('<?xml version="1.0" encoding="UTF-8"?>'
						. '<code>' . $translations[$id] . '</code>')
						or die("String ID $id has XML errors !");

					foreach ($temp_doc->firstChild->childNodes as $sub_child) {
						$child->appendChild($doc->importNode($sub_child, true));
					}
				}
			} else {
				replace_translations($doc, $child);
			}
		}
	}
}
