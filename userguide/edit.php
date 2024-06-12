<?php
require_once('inc/common.php');
require_once('inc/lock.php');

role_needed(ROLE_AUTHOR);

$time = time();
$blocks_md5 = array();

$doc_id = (isset($_GET['doc_id']) ? intval($_GET['doc_id']) : 0);

$row = lock_and_get($doc_id);

$title = 'Edit “' . htmlspecialchars($row['name']) . '”';
$ltop = '<a href="documents.php">Return to index</a>';

$doc_name = $row['name'];

$base_dir = dirname($base_url . '/' . EXPORT_DIR . '/' . $row['path_original']);

$head = <<<EOD
	<script type="text/JavaScript">
	//<![CDATA[
		const base_url = '$base_url';
		const doc_id = $doc_id;
		const base_dir = '$base_dir/';
	//]]>
	</script>
	<script type="text/JavaScript" src="shared/common.js"></script>
	<script type="text/JavaScript" src="shared/edit_tool.js"></script>

EOD;

$r_norm = $r_fuzzy = $r_to_fuzzy = '';

$error = '';

$file_path = REF_DIR . '/' . $row['path_original'];

$rev_id = (isset($_GET['rev']) ? $_GET['rev'] : '');

if ($rev_id == 'list') {
	require_once('inc/git.php');
	$log = git_log($file_path);

	include('inc/start_html.php');
	echo "Revisions for this document:\n<dl>\n";

	foreach ($log as $data) {
		echo "<dt><a href=\"edit.php?doc_id=$doc_id&rev=$data[commit]\">Revision $data[commit]</a> ($data[date])</dt>\n";
		echo '<dd>' . htmlspecialchars($data['msg']) . "</dd>\n";
	}

	echo "</dl>\n";
	include('inc/end_html.php');
	exit;
}

$rev_id = ctype_alnum($rev_id) ? $rev_id : '';

if (isset($_POST['text'])) {
	$text = $_POST['text'];

	$blocks = array();

	validate_edit($text, $file_path, $blocks);

	if (!$error) {
		include('inc/start_html.php');
?>
<h1>Review Changes</h1>
<form action="" method="post">
<?php
		if (!empty($blocks['add'])) {
?>
<table class="list">
<thead>
<tr>
<th>Added blocks</th>
</tr>
</thead>
<tbody>
<?php
			foreach ($blocks['add'] as $added_block) {
?>
<tr>
<td><code><?=nl2br(htmlspecialchars($added_block))?></code></td>
</tr>
<?php
			}
?>
</tbody>
</table>
<br/>
<?php
		}
		if (!empty($blocks['mod'])) {
?>
<table class="list" style="overflow-x:auto">
<thead>
<tr>
<th>Modified blocks - Original</th><th>New</th>
</tr>
</thead>
<tbody>
<?php
			foreach ($blocks['mod'] as $id => $modif_block) {
				$original_block = nl2br(htmlspecialchars($modif_block['p']));
				$modified_block = nl2br(htmlspecialchars($modif_block['n']));
?>
<tr>
<td><code><?=$original_block?></code></td>
<td><code><?=$modified_block?></code></td>
</tr>
<tr class="bottom">
<td colspan="2"><label><input type="checkbox" name="noinval[<?=$id?>]" />
Do not invalidate translations for this item</label>
</tr>
<?php
			}
?>
</tbody>
</table>
<br/>
<?php
		}
		if (!empty($blocks['del'])) {
?>
<table class="list">
<thead>
<tr>
<th>Deleted blocks</th>
</tr>
</thead>
<tbody>
<?php
			foreach ($blocks['del'] as $del_block) {
?>
<tr>
<td><code><?=nl2br(htmlspecialchars($del_block))?></code></td>
</tr>
<?php
			}
?>
</tbody>
</table>
<br/>
<?php
		}

		$encoded = base64_encode($text);
		$sum = md5($text);
?>
<label for="comment">Comment:</label>
<input type="text" id="comment" name="comment" value="" size="100" />
<div style="text-align:right">
<input type="submit" name="cont_edit" value="Return to Edit Page" />
<input type="submit" name="submit_edit" value="Submit Edit" />
</div>
<input type="hidden" name="text64" value="<?=$encoded?>" />
<input type="hidden" name="sum" value="<?=$sum?>" />
</form>
<?php

		include('inc/end_html.php');
		exit;
	}
} else if (isset($_POST['cont_edit']) and isset($_POST['text64'])) {
	$text = $_POST['text64'];
	$text = base64_decode($text);

} else if (isset($_POST['submit_edit']) and isset($_POST['text64'])
	and isset($_POST['sum'])) {
	ignore_user_abort(true);

	$text = $_POST['text64'];
	$text = base64_decode($text);

	if (md5($text) != $_POST['sum'])
		die('The XML document was corrupt.');

	$blocks_md5 = array();
	$req = db_query('
		SELECT string_id, source_md5 FROM ' . DB_STRINGS . "
		WHERE doc_id = ?", array($doc_id));
	while ($row = db_fetch($req)) {
		$blocks_md5[$row['source_md5']] = $row['string_id'];
	}
	db_free($req);

	$edited_doc = new DOMDocument();

	if (!@$edited_doc->loadXML($text))
		die('Error parsing the XML document !');

	$req = db_query('SELECT lang_code FROM ' . DB_LANGS);
	$r_norm = ', doc_id';
	$r_fuzzy = '';
	$r_to_fuzzy = '';
	while ($row = db_fetch($req)) {
		$r_norm .= ', "translation_' . $row['lang_code'] . '"';
		$r_fuzzy .= ', "is_fuzzy_' . $row['lang_code'] . '"';
		$r_to_fuzzy .= ', 1';
	}
	db_free($req);

	// Mark all blocks as unused (used blocks will be reenabled later)
	db_query('
		UPDATE ' . DB_STRINGS . "
		SET unused_since = ?
		WHERE doc_id = ? AND unused_since IS NULL", array($time, $doc_id));

	update_translations($edited_doc, $translate_tags);

	$edited_doc->save($file_path) or die('Unable to save the XML document !');

	$req = db_query('SELECT COUNT(*) FROM ' . DB_STRINGS . "
		WHERE doc_id = ? AND unused_since IS NULL", array($doc_id));

	$row = db_fetch($req);
	$count = $row['count'];
	db_free($req);

	db_query('UPDATE ' . DB_DOCS . "
		SET strings_count = ?, is_dirty = 1 WHERE doc_id = ?", array($count, $doc_id));

	// Log
	db_query('INSERT INTO ' . DB_LOG . '
		(log_user, log_time, log_action, log_doc) ' . "
		VALUES (?, ?, ?, ?)", array($user_id, $time, 'mod', $doc_id));

	db_query('UPDATE ' . DB_USERS . '
		SET num_edits = num_edits + 1 ' . "
		WHERE user_id = ?", array($user_id));

	// Commit
	$comment = 'Document ' . $doc_name . ' edited by ' . $user_name . ':';
	if (isset($_POST['comment']) && $_POST['comment'])
		$comment .= $_POST['comment'];
	else
		$comment .= '[No log message]';
	require_once('inc/git.php');
	git_pull(dirname($file_path));
	git_add($file_path);
	git_commit(dirname($file_path), $comment);
	git_push(dirname($file_path));

	redirect('update_stats.php?redir_to=block_edit.php%3Fdoc_id%3D' . $doc_id);
	exit;

} else {
	$text = false;
	require_once('inc/git.php');
	$revs = git_log($file_path);
	$current = $revs[0]['commit'];

	if ($rev_id and $rev_id != $current and array_search($rev_id, array_column($revs, 'commit'))) {
		$text = git_cat($file_path, $rev_id);
	} else {
		$rev_id = "$current (current)";
	}

	$top = 'Revision: <b>' . $rev_id . '</b>. <a href="edit.php?doc_id=' . $doc_id . '&rev=list">Load another…</a>';

	if ($text === false)
		$text = file_get_contents($file_path);

	if ($text === false)
		error_box($title, 'Error: Unable to open the document !');
}

include('inc/start_html.php');

$class = ($error ? 'warning' : 'no_warning');

?>
</div>
</div>
<form action="" method="post">
<?php
if ($error) {
?>
<div id="warning_box">
<div style="float:right">
<a href="#" onclick="closeAlert();return false">[X]</a>
</div>
<?=$error?>
</div>
<?php
}
?>
<div id="edit_top" class="<?=$class?>">
<button onclick="return insertText('&lt;b&gt;', '&lt;/b&gt;');"><b>B</b></button>
<button onclick="return insertText('&lt;i&gt;', '&lt;/i&gt;')"><em>I</em></button>
<button onclick="return insertText('&lt;p&gt;\n', '&lt;/p&gt;\n')">¶</button>
<select onchange="selectTitle(this);">
<option value="" disabled="" selected="">Title</option>
<option value="h1">Level 1</option>
<option value="h2">Level 2</option>
<option value="h3">Level 3</option>
<option value="h4">Level 4</option>
</select>
<select onchange="selectBox(this);">
<option value="" disabled="" selected="">Box</option>
<option value="box-info">Info (Little note)</option>
<option value="box-warning">Warning (Don't mess this up)</option>
<option value="box-stop">Stop (Danger, Will Robinson!)</option>
</select>
<select onchange="selectPref(this);">
<option value="" disabled="" selected="">Preformatted</option>
<option value="text">Text</option>
<option value="term">Terminal</option>
</select>
<select onchange="selectSpan(this);">
<option value="" disabled="" selected="">Inline</option>
<option value="path">Path</option>
<option value="cli">Command-line Application</option>
<option value="menu">Menu</option>
<option value="button">Button</option>
<option value="key">Key</option>
</select>
</div>
<div id="edit_middle" class="<?=$class?>">
<textarea name="text" rows="40" cols="120">
<?=htmlspecialchars($text)?>
</textarea>
</div>
<div id="edit_bottom" class="<?=$class?>">
<input type="text" id="row" value="1" size="3" readonly="readonly" />
<input type="text" id="col" value="1" size="3" readonly="readonly" />
<input type="button" name="preview" id="preview"
	value="Show/Hide Preview Window" />
<div style="float:right">
<input type="submit" name="submit_edit" value="Validate Edit" />

<?php
include('inc/end_html.php');

function validate_edit($text, $file_path, &$blocks) {
	global $error, $translate_tags;

	// Check XML well-formedness
	$edited_doc = new DOMDocument();
	set_error_handler('catch_messages');
	$status = $edited_doc->loadXML($text);
	restore_error_handler();

	if ($error or !$status) {
		if (!$error)
			$error = 'DOMDocument::loadXML returned failure status.';

		$error = "Error parsing the edited XML document:<br/>\n$error";
		return;
	}

	// Load the original document
	$orig_doc = new DOMDocument();
	@$orig_doc->load($file_path) or die('Unable to load the original file!');

	$orig_inners = array();
	$orig_outers = array();
	get_translate_ids($orig_doc, $orig_inners, $orig_outers);

	$blocks = array(
		'add'	=> array(),
		'mod'	=> array(),
		'del'	=> array(),
		'rev'	=> array(),
	);

	$used_ids = array();

	validate($edited_doc, $orig_inners, $orig_outers, $translate_tags, $blocks,
		$used_ids);

	// Search for now unused translation IDs
	foreach ($used_ids as $used_id) {
		unset($orig_outers[$used_id]);
	}
	$blocks['del'] = $orig_outers;
}

function get_translate_ids($node, &$orig_inners, &$orig_outers) {
	foreach ($node->childNodes as $child) {
		if ($child instanceOf DOMElement) {
			if ($child->hasAttribute(ATTR_TRANS_ID)) {
				$id = intval($child->getAttribute(ATTR_TRANS_ID));
				$orig_inners[$id] = DOMinnerHTML($child);
				$orig_outers[$id] = DOMouterHTML($child);
			} else {
				get_translate_ids($child, $orig_inners, $orig_outers);
			}
		}
	}
}

function validate($node, &$orig_inners, &$orig_outers, $tags, &$blocks,
	&$used_ids) {
	global $error;

	foreach ($node->childNodes as $child) {
		if ($child instanceOf DOMElement) {
			if (isset($tags[$child->tagName])) {
				if (is_array($tags[$child->tagName])) {
					validate($child, $orig_inners, $orig_outers,
						$tags[$child->tagName], $blocks, $used_ids);
				} else {
					// This block should be tagged
					if ($child->hasAttribute(ATTR_TRANS_ID)) { // It is already
						$id = intval($child->getAttribute(ATTR_TRANS_ID));
						$inner_html = DOMinnerHTML($child);
						$ok = true;

						if (!isset($orig_inners[$id])) {
							// It refers to an ID that is not used in the current document
							// So it is probably a revert

							$blocks['add'][] = DOMouterHTML($child);

						} else if ($inner_html != $orig_inners[$id]) {
							// This block was already existing and was modified

							if ((isset($blocks['mod'][$id])	and $blocks['mod'][$id]['i'] != $inner_html)
								or (!isset($blocks['mod'][$id]) and in_array($id, $used_ids)))
								$ok = false;

							$blocks['mod'][$id] = array(
								'p' => $orig_outers[$id],
								'n' => DOMouterHTML($child),
								'i' => $inner_html,
							);
						} else if (isset($blocks['mod'][$id])) {
							$ok = false;
						}

						if (!$ok) {
							$error = <<<EOD
The block with translate ID $id is used more than once in the page, but has not
the same content everywhere.
<ul>
You have two options:
<li>Make the contents of this block uniform</li>
<li>Remove the translate id attribute of modified blocks with this ID</li>
</ul>
EOD;
							break;
						}

						$used_ids[] = $id;
					} else if (trim($child->nodeValue, "  \t\n\r\0\x0B") != '') {
						// It is a new block
						$blocks['add'][] = DOMouterHTML($child);
					}
				}
			} else if ($child->hasAttribute(ATTR_TRANS_ID)) {
				// The block is tagged but not translatable !
				$tag_name = $child->tagName;
				$id = $child->getAttribute(ATTR_TRANS_ID);
				$line = '?';

				if (method_exists($child, 'getLineNo'))
					$line = $child->getLineNo();

				$error .= "The &lt;$tag_name&gt; block at line $line has " .
					'a translation ID (' . ATTR_TRANS_ID . '), but is not ' .
					"translatable.<br/>\n";
			}
		}
	}
}

function update_translations($node, $tags) {
	global $r_norm, $r_fuzzy, $r_to_fuzzy, $doc_id, $blocks_md5;
	foreach ($node->childNodes as $child) {
		if ($child instanceOf DOMElement && isset($tags[$child->tagName])) {
			if (is_array($tags[$child->tagName])) {
				update_translations($child, $tags[$child->tagName]);
			} else if (trim($child->nodeValue, "  \t\n\r\0\x0B") != '') {
				// This is a translatable block
				$innerHTML = DOMinnerHTML($child);
				$md5 = md5($innerHTML);
				$id_attr = ($child->hasAttribute(ATTR_TRANS_ID) ?
					intval($child->getAttribute(ATTR_TRANS_ID)) : 0);
				$id_cont = (isset($blocks_md5[$md5]) ? $blocks_md5[$md5] : 0);

				if ($id_attr == 0 && $id_cont == 0) {
					// New block
					db_query('INSERT INTO '. DB_STRINGS . ' (doc_id, source_md5) ' . "
						VALUES (?, ?)", array($doc_id, $md5));
					$id = db_insert_id();
					$blocks_md5[$md5] = $id;
					$child->setAttribute(ATTR_TRANS_ID, $id);
				} else if ($id_cont != 0) {
					// Block text already in the DB
					$child->setAttribute(ATTR_TRANS_ID, $id_cont);
					db_query('UPDATE ' . DB_STRINGS . " SET unused_since = NULL
						WHERE string_id = ?", array($id_cont));
				} else {
					// ID in the DB, but the block was modified
					$fuzzy = !isset($_POST['noinval'][$id_attr]);
					$update = 'source_md5' . $r_norm . $r_fuzzy;
					$up_to = "'$md5'" . $r_norm .
						($fuzzy ? $r_to_fuzzy : $r_fuzzy);

					db_query('INSERT INTO ' . DB_STRINGS . " ($update)
						SELECT $up_to FROM " . DB_STRINGS . "
						WHERE string_id = ?", array($id_attr));
					$id = db_insert_id();
					$blocks_md5[$md5] = $id;
					$child->setAttribute(ATTR_TRANS_ID, $id);
				}
			}
		}
	}
}

function catch_messages($errno, $errstr, $errfile, $errline) {
	global $error;

	if (preg_match('/^DOMDocument::loadXML\(\) \[.*\]: (.*) in .*?(,.*)$/',
		$errstr, $matches)) {
		$error .= $matches[1] . ' in current document'.
			$matches[2] . "<br/>\n";
	} else {
		$error .= $errstr;
	}
}

function replace_innerHTML($node, $new_inner) {
	while ($node->hasChildNodes())
		$node->removeChild($node->firstChild);

		$temp_doc = new DOMDocument();
		@$temp_doc->loadXML('<?xml version="1.0" encoding="UTF-8"?>'
			. '<code>' . $new_inner . '</code>')
			or die('Error replacing inner HTML!');

		foreach ($temp_doc->firstChild->childNodes as $sub_child) {
			$node->appendChild($doc->importNode($sub_child, true));
		}
}
?>
