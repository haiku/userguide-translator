<?php
require_once('inc/common.php');
require_once('inc/lock.php');

role_needed(ROLE_AUTHOR);

$xml_msg = '';

$time = time();

if (isset($_GET['ping_id'])) {
	$ping_id = intval($_GET['ping_id']);
	extend_lock($ping_id);
	exit;
}

if (isset($_POST['edit_doc']) and isset($_POST['edit_string'])
	and isset($_POST['edit_text']) and isset($_POST['dont_mark_fuzzy'])) {
	// Edit submitted to the database

	$doc_id = intval($_POST['edit_doc']);
	$string_id = intval($_POST['edit_string']);
	$text = $_POST['edit_text'];
	$dont_mark_fuzzy = ($_POST['dont_mark_fuzzy'] ? 1 : 0);

	$new_block = new DOMDocument();
	set_error_handler('xml_error_handler');
	$status = $new_block->loadXML('<?xml version="1.0" encoding="UTF-8"?>' .
		'<code>' . $text . '</code>');
	restore_error_handler();

	if (!$status || $xml_msg)
		die("badxml $xml_msg");

	// Update the document
	$req = db_query('SELECT path_original, name FROM ' . DB_DOCS . "
		WHERE doc_id = ?", array($doc_id));
	$row = db_fetch($req);
	db_free($req);

	if (!$row)
		exit;

	$path_original = $row['path_original'];
	$doc_name = $row['name'];

	// Load all the md5
	$blocks_md5 = array();
	$req = db_query('SELECT string_id, source_md5 FROM ' . DB_STRINGS . "
		WHERE doc_id = ?", array($doc_id));
	while ($row = db_fetch($req)) {
		$blocks_md5[$row['source_md5']] = $row['string_id'];
	}
	db_free($req);


	$doc = new DOMDocument();
	$doc->load(REF_DIR . '/' . $path_original)
		or die('interr');

	$md5 = md5($text);

	if (isset($blocks_md5[$md5])) {
		// The block content is already in the DB
		if ($blocks_md5[$md5] == $string_id) // Nothing changed
			exit('ok');

		// The new block has the same content as another block
		replace_block_id($doc, $doc, $string_id, $new_block, $blocks_md5[$md5])
			or die("Unable to replace the block #$string_id with #$blocks_md5[$md5] !");

		db_query('UPDATE ' . DB_STRINGS . "
			SET unused_since = NULL
			WHERE string_id = ?", array($blocks_md5[$md5]));

		db_query('UPDATE ' . DB_STRINGS . "
			SET unused_since = ?
			WHERE string_id = ?", array($time, $string_id));
	} else {
		$r_norm = 'doc_id';
		$r_fuzzy = '';
		$r_to_fuzzy = '';
		$req = db_query('SELECT lang_code FROM ' . DB_LANGS);
		while ($row = db_fetch($req)) {
			$r_norm .= ', "translation_' . $row['lang_code'] . '"';
			$r_fuzzy .= ($r_fuzzy ? ', ' : '') . '"is_fuzzy_' .
				$row['lang_code'] . '"';
			$r_to_fuzzy .= ($r_to_fuzzy ? ', ' : '') . '1';
		}

		$fuzzy = !$_POST['dont_mark_fuzzy'];
		$update = 'source_md5' . ($r_norm ? ', ' : '') . $r_norm . (($fuzzy and $r_fuzzy) ? ', ' . $r_fuzzy : '');
		$up_to = "'$md5'" . ($r_norm ? ', ' : '') . $r_norm . (($fuzzy and $r_to_fuzzy) ? ', ' . $r_to_fuzzy : '');
		db_query('INSERT INTO ' . DB_STRINGS . " ($update)
			SELECT $up_to FROM " . DB_STRINGS . "
			WHERE string_id = ?", array($string_id));
		$new_id = db_insert_id();
		replace_block_id($doc, $doc, $string_id, $new_block, $new_id)
			or die('unable to replace');

		db_query('UPDATE ' . DB_STRINGS . "
			SET unused_since = ?
			WHERE string_id = ?", array($time, $string_id));
	}

	$doc->save(REF_DIR . '/' . $path_original);

	db_query('UPDATE ' . DB_DOCS . '
		SET is_dirty = 1 ' . "
		WHERE doc_id = ?", array($doc_id));

	// Log
	$delay = $time - 5 * 60;
	$result = db_query('SELECT log_id FROM ' . DB_LOG . "
		WHERE log_user = ? AND log_time > ? AND log_doc = ?
			AND log_action = 'ed_block' LIMIT 1", array($user_id, $delay, $doc_id));

	if (!db_num_rows($result)) {
		db_query('INSERT INTO ' . DB_LOG . '
			(log_user, log_time, log_action, log_doc, log_trans_number) ' . "
			VALUES (?, ?, ?, ?, ?)", array($user_id, $time, 'ed_block', $doc_id, 1));
	} else {
		$row = db_fetch($result);
		db_query('UPDATE ' . DB_LOG . '
			SET log_trans_number = log_trans_number + 1 WHERE log_id = ?',
			array($row['log_id']));
	}

	db_query('UPDATE ' . DB_USERS . '
		SET num_edits = num_edits + 1 ' . "
		WHERE user_id = ?", array($user_id));

	require_once('inc/git.php');
	git_pull(dirname(REF_DIR . '/' . $path_original));
	git_add(REF_DIR . '/' . $path_original);
	git_commit(dirname(REF_DIR . '/' . $path_original),
		'Block edit by ' . $user_name . ' in document ' . $doc_name . '.');
	git_push(dirname(REF_DIR . '/' . $path_original));

	exit('ok');
}

$doc_id = (isset($_GET['doc_id']) ? intval($_GET['doc_id']) : 0);

$row = lock_and_get($doc_id);

$path_original = $row['path_original'];

$doc = new DOMDocument();
$doc->load(REF_DIR . '/' . $path_original)
	or die('Unable to load the document.');

$js = '';
$js .= 'const base_url = \'' . str_replace("'", "\'", $base_url) . "';\n";
$js .= 'const base_local = \'' . str_replace("'", "\'", dirname($path_original)) . "';\n";
$js .= "const doc_id = $doc_id;\n";
$js .= "var source_strings = new Array();\n";
$js .= get_source_strings($doc);

$doc->loadXML(replace_placeholders($doc->saveXML()));

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
$base->setAttribute('href', $base_url . '/' . EXPORT_DIR . '/' . dirname($path_original) . '/');
$node = append_sibling($base, $node);
$node = append_sibling($doc->createTextNode("\t"), $node);

// Pass the script path to JavaScript
append_js_code($head, $js);

// Include the JavaScript translation helper
append_js_file($head, $base_url . '/shared/block_edit_tool.js');

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

function replace_block_id($doc, $node, $id, $new_block, $new_id) {
	$found = false;

	foreach ($node->childNodes as $child) {
		if ($child instanceOf DOMElement) {
			if ($child->hasAttribute(ATTR_TRANS_ID)
				and $id == $child->getAttribute(ATTR_TRANS_ID)) {
				while ($child->hasChildNodes())
					$child->removeChild($child->firstChild);

				foreach ($new_block->firstChild->childNodes as $sub_child) {
					$child->appendChild($doc->importNode($sub_child, true));
				}

				if ($new_id)
					$child->setAttribute(ATTR_TRANS_ID, $new_id);

				$found = true;
			} else {
				if (replace_block_id($doc, $child, $id, $new_block, $new_id))
					$found = true;
			}
		}
	}

	return $found;
}

function xml_error_handler($errno, $errstr, $errfile, $errline) {
	global $xml_msg;

	$err = (preg_match('/^DOMDocument::loadXML\(\) \[.*\]: (.*)$/',
		$errstr, $matches) ? $matches[1] : $errstr);

	$xml_msg .= htmlspecialchars_decode($err) . "\n";
}
