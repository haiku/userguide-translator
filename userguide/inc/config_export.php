<?php
if (!defined('IN_TRANSLATE'))
	exit;

// These functions are used when generating the original and the translated
// documents. They allow making some tweaks to them
// Global variables defined :
// $trans_by_md5['md5'] => 'translation' (for the current language)
// $_SESSION['exp_langs_loc']['fr'] => 'Français' (do not modify!)

$langs = array();

$insert_credits = <<<EOI
 *
-->
EOI;

// This function is called once at the start of the export process.
function start_hook() {
	//$_SESSION['exp_doc_authors'] = array();
	$_SESSION['exp_doc_translators'] = array();	
	
	$req = db_query('SELECT doc_id FROM ' . DB_DOCS);
	while ($row = db_fetch($req)) {
		$id = intval($row['doc_id']);

		$req2 = db_query('
			SELECT DISTINCT u.username, u.real_name, l.log_trans_lang
			FROM translate_users u, translate_log l ' . "
			WHERE l.log_doc = $id
				AND l.log_action = 'trans'
				AND l.log_user = u.user_id
		");
		
		while ($row2 = db_fetch($req2)) {
			$lang = $row2['log_trans_lang'];
			$name = ($row2['real_name'] ? $row2['real_name'] : $row2['username']);

			if (!isset($_SESSION['exp_doc_translators'][$id]))
				$_SESSION['exp_doc_translators'][$id] = array();
			
			if (!isset($_SESSION['exp_doc_translators'][$id][$lang]))
				$_SESSION['exp_doc_translators'][$id][$lang] = array();
		
			$_SESSION['exp_doc_translators'][$id][$lang][] = $name;
		}
	}
}

// This function is called for each generated document.
// It receives the generated XML code, and can modify it at will.
function document_hook($path_original, $path_translations, $doc_id, $lang_code, $complete, &$generated_text) {
	global $insert_credits, $langs, $trans_by_md5;
	
	if (strpos($path_original, '__unused') === 0) {
		$generated_text = '';
		return;
	}
	
	if (empty($langs)) {
		$langs = $_SESSION['exp_langs_loc'];
		$langs['gb'] = 'English';
	}
	
	$orig_items = explode('/', $path_original);
	$trans_items = explode('/', $path_translations);
	$count = count($orig_items);
	$insert_pos = strpos($generated_text, '<div class="inner">');
	
	if ($insert_pos !== false and $count == count($trans_items)) {
		$flags_path = str_repeat('../', $count - 2) . 'images/flags/';
		
		while ($orig_items[0] == $trans_items[0]) {
			array_shift($orig_items);
			array_shift($trans_items);
			
			$count--;
		}
		
		$back = str_repeat('../', $count - 1);
		$orig_url = $back . implode('/', $orig_items);
		$trans_url = $back . implode('/', $trans_items);
		
		$generated_text = str_replace('{LANG_CODE}', ($lang_code ? $lang_code : 'en'), $generated_text);
		
		if ($lang_code) {
			$generated_text = preg_replace_callback('!<div class="inner"><span>.*?</span></div>!s', 'header_callback', $generated_text);
		} else {
			$lang_code = 'gb';
		}

		$menu = <<<EOD

<ul class="lang-menu">
<li class="now"><img src="$flags_path$lang_code.png" alt="" /> $langs[$lang_code]</li>

EOD;
		foreach ($langs as $current_code => $name) {
			if ($current_code == $lang_code)
				continue;

			if ($current_code == 'gb')
				$url = $orig_url;
			else
				$url = str_replace('{LANG}', $current_code, $trans_url);
		
			$menu .= <<<EOD
<li><a href="$url"><img src="$flags_path$current_code.png" alt="" />$langs[$current_code]</a></li>

EOD;
		}
	
		$menu .= "</ul>\n";
		
		$generated_text = substr_replace($generated_text, $menu,  $insert_pos + 19, 0);
		
		if (isset($_SESSION['exp_doc_translators'][$doc_id][$lang_code])) {
			$credits = " * Translators:\n *		";
			$credits .= implode("\n *		", $_SESSION['exp_doc_translators'][$doc_id][$lang_code]);
			$credits .= "\n" . $insert_credits;
			
			$generated_text = str_replace($insert_credits, $credits, $generated_text);
		}
		
		$md5 = md5('User guide');
		if (isset($trans_by_md5[$md5])) {
			$generated_text = str_replace('<div id="banner">' . "\n" . '<div><span>User guide</span></div>' . "\n" . '</div>', '<div id="banner">' . "\n" . '<div><span>' . $trans_by_md5[$md5] . '</span></div>' . "\n" . '</div>', $generated_text);
		}
	}
	
	if (!$complete) {
		$message = 'The translation of this page isn\'t yet complete. Until it is, unfinished parts use the English original.';
		if (isset($trans_by_md5[md5($message)]))
			$message = $trans_by_md5[md5($message)];
		$generated_text = str_replace('<div id="content">' . "\n" . '<div>', <<<EOS
<div id="content">
<div>
<div class="box-info">$message</div>
EOS
, $generated_text);
	}
}

// This function is called after every document was saved.
// If it returns a message, it will be displayed.
function final_status_hook() {
	$missing = '';
	if (isset($_SESSION['exp_missing_titles']) and is_array($_SESSION['exp_missing_titles'])) {
		foreach ($_SESSION['exp_missing_titles'] as $title => $is_missing) {
			if (!$is_missing)
				continue;
		
			$missing .= '<li>“' . $title . "”</li>\n";
		}
	}
	
	if ($missing)
		return <<<EOM
The following document titles were not translated in every language.<br/>
They may be untranslatable:
<ul>
$missing</ul>
EOM;

	return '';
}

function header_callback($matches) {
	return preg_replace_callback('!(<a href=".*?">)(.*?)(</a>)!', 'link_callback', $matches[0]);
}

function link_callback($matches) {
	global $trans_by_md5;
	
	$title = $matches[2];
	
	$md5 = md5($title);
	
	if (isset($trans_by_md5[$md5])) {
		$_SESSION['exp_missing_titles'][$title] = false;
		return $matches[1] . $trans_by_md5[$md5] . $matches[3];
	} else if (!isset($_SESSION['exp_missing_titles'][$title])) {
		$_SESSION['exp_missing_titles'][$title] = true;
	}

	return $matches[0];
}
