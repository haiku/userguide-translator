<?php
function escape_js_string($text) {
	// Escape the text to be used as a double-quoted js string
	$text = str_replace('\\', '\\\\', $text);
	$text = str_replace('"', '\"', $text);
	$text = str_replace("\n", '\n', $text);
	$text = str_replace("\r", '', $text);
	return $text;
}

function append_js_code($node, $code) {
	$doc = $node->ownerDocument;

	$js = $doc->createElement('script');
	$js->setAttribute('type', 'text/JavaScript');

	// We are generating an XML document so the JS code should be encapsuled
	// in a CDATA tag. However, the opening <![CDATA[ and closing ]]> must be
	// preceded by "//" so they are treated like JS comments.
	$js->appendChild($doc->createTextNode("\n//"));
	$js->appendChild($doc->createCDATASection("\n$code\n//"));
	$js->appendChild($doc->createTextNode("\n"));

	$node->appendChild($js);
	$node->appendChild($doc->createTextNode("\n"));
}

function append_js_file($node, $file) {
	$doc = $node->ownerDocument;

	$js = $doc->createElement('script');
	$js->setAttribute('type', 'text/JavaScript');
	$js->setAttribute('src', $file);

	$node->appendChild($js);
	$node->appendChild($doc->createTextNode("\n"));
}

function append_css_file($node, $file) {
	$doc = $node->ownerDocument;

	$link = $doc->createElement('link');
	$link->setAttribute('rel', 'stylesheet');
	$link->setAttribute('type', 'text/css');
	$link->setAttribute('href', $file);

	$node->appendChild($link);
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

				$text = escape_js_string(DOMInnerHTML($child));
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

function load_doc_with_blocks($doc_id, $lang = null) {
	global $base_url;

	$req = db_query('
		SELECT path_original, path_translations FROM ' . DB_DOCS . "
		WHERE doc_id = ?", array($doc_id));
	$row = db_fetch($req);
	db_free($req);

	if (!$row)
		redirect('index.php');

	$path_doc = $row['path_original'];
	if ($lang) {
		$path_base = str_replace('{LANG}', $lang, $row['path_translations']);
	} else {
		$path_base = $path_doc;
	}

	$doc = new DOMDocument();
	$doc->load(REF_DIR . '/' . $path_doc)
		or die('Unable to load the document.');

	$js = '';
	$js .= 'const base_url = \'' . str_replace("'", "\'", $base_url) . "';\n";
	$js .= 'const base_local = \'' . str_replace("'", "\'", dirname($path_base)) . "';\n";
	$js .= "const doc_id = $doc_id;\n";
	$js .= "var source_strings = new Array();\n";
	$js .= get_source_strings($doc);

	$doc->loadXML(replace_placeholders($doc->saveXML(), $lang));

	$head = $doc->getElementsByTagName('head')->item(0);

	// Set the base dir
	$metas = $doc->getElementsByTagName('meta');
	$node = $head->firstChild;
	if ($metas) {
		foreach ($metas as $meta)
			if ($meta->getAttribute('http-equiv') == 'content-type')
				$node = $meta;
	}

	$node = append_sibling($doc->createTextNode("\n\t"), $node);
	$base = $doc->createElement('base');
	$base->setAttribute('href', $base_url . '/' . EXPORT_DIR . '/' . dirname($path_base) . '/');
	$node = append_sibling($base, $node);
	$node = append_sibling($doc->createTextNode("\t"), $node);

	// Pass the script path to JavaScript
	append_js_code($head, $js);

	append_css_file($head, $base_url . '/shared/blocks.css');

	if ($lang) {
		html_set_lang($doc, $lang);
	}

	return $doc;
}
