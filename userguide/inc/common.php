<?php
if (PHP_VERSION_ID < 50500) {
	print("<h1>Error: PHP version too old.</h1>Need <code>5.5.0</code>, have <code>" . phpversion() .
		"</code>.<br>Please contact a sysadmin.");
	exit(1);
}

if (!isset($path_prefix))
	$path_prefix = '';

require($path_prefix . 'inc/config.php');

define('DB_PREFIX', 'translate_');
define('DB_LANGS', DB_PREFIX . 'langs');
define('DB_DOCS', DB_PREFIX . 'docs');
define('DB_STRINGS', DB_PREFIX . 'strings');
define('DB_RESOURCES', DB_PREFIX . 'resources');
define('DB_USERS', DB_PREFIX . 'users');
define('DB_LOG', DB_PREFIX . 'log');

define('ATTR_TRANS_ID', '_translation_id'); // /!\ Also used in translate_tool

define('ROLE_UNDEF', 0);
define('ROLE_TRANSLATOR', 1);
define('ROLE_LANG_MANAGER', 2);
define('ROLE_AUTHOR', 3);
define('ROLE_ADMIN', 4);
define('ROLE_MAX', ROLE_ADMIN);

$db_roles = array(
	'undef'			=> ROLE_UNDEF,
	'translator'	=> ROLE_TRANSLATOR,
	'lmanager'		=> ROLE_LANG_MANAGER,
	'author'		=> ROLE_AUTHOR,
	'admin'			=> ROLE_ADMIN,
);

$roles_names = array(
	ROLE_UNDEF			=> 'Not assigned',
	ROLE_TRANSLATOR		=> 'Translator',
	ROLE_LANG_MANAGER	=> 'Language manager',
	ROLE_AUTHOR			=> 'Author',
	ROLE_ADMIN			=> 'Administrator',
);

error_reporting(E_ALL);

$user_role = ROLE_UNDEF;
$user_logged_in = false;
$user_name = 'Anonymous';
$user_id = 0;

// Connect to the DB
$db_h = null;
try {
	$db_h = new PDO("pgsql:host=$db_server;dbname=$db_base_name;options='--client-encoding=UTF8'",
		$db_username, $db_password);
	$db_h->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // TODO: REMOVE!
} catch (PDOException $e) {
	error_box('Failed to connect to the database!',
		'<b>Failed to connect to the database!</b><br>' . $e->getMessage());
}

// Be paranoid
unset($db_server, $db_username, $db_password, $db_base_name);

// Init user management
session_start();

if (isset($_POST['username']) and isset($_POST['password'])
	and isset($_POST['redirect'])) {

	$user_name = $_POST['username'];
	$password = sha1($_POST['password']);

	$req = db_query('SELECT user_id FROM ' . DB_USERS . "
		WHERE username = ? AND user_password = ?", array($user_name, $password));

	$row = db_fetch($req);
	db_free($req);

	if (!$row)
		login_box('The entered username or password is incorrect. ' .
			'Please retry.', $_POST['redirect']);

	$_SESSION['user_id'] = $row['user_id'];
	$_SESSION['user_name'] = $user_name;
	$_SESSION['user_pass'] = $password;

	// Redirect the user to avoid breaking the Back button
	redirect($_POST['redirect']);
}

if (isset($_SESSION['user_id']) and isset($_SESSION['user_name'])
	and isset($_SESSION['user_pass'])) {

	global $user_role, $user_logged_in, $user_name, $user_id;

	$db_user_name = $_SESSION['user_name'];
	$db_password = $_SESSION['user_pass'];
	$user_id = intval($_SESSION['user_id']);

	$req = db_query('SELECT user_role FROM ' . DB_USERS . "
		WHERE username = ? AND user_password = ? AND user_id = ?",
		array($db_user_name, $db_password, $user_id));

	$row = db_fetch($req);
	db_free($req);

	if ($row) {
		$user_role = $db_roles[$row['user_role']];
		$user_logged_in = true;
		$user_name = $_SESSION['user_name'];
	}
}


function role_needed($role) {
	global $path_prefix, $user_role, $user_logged_in;
	if ($user_role < $role) {
		if (!$user_logged_in)
			login_box('You need to be logged in in order to access this page.', '/');

		header('Status: 403 Forbidden');
		error_box('Access Denied',
			'You do not have the right privileges to access this page.');
	}
}

function validate_lang($lang) {
	$aValid = array('-', '_');
	if (!ctype_alnum(str_replace($aValid, '', $lang))) {
		return '';
	}
	return $lang;
}

function html_set_lang($doc, $lang) {
	// Set the lang attributes in the <html> tag
	$htmllang = str_replace('_', '-', $lang);
	$doc->getElementsByTagName('html')->item(0)->setAttribute('lang', $htmllang);
	$doc->getElementsByTagName('html')->item(0)->setAttribute('xml:lang', $htmllang);
}

function append_sibling(DOMNode $new_node, DOMNode $ref) {
	if ($ref->nextSibling)
		return $ref->parentNode->insertBefore($new_node, $ref->nextSibling);

	return $ref->parentNode->appendChild($newnode);
}

function html_inject_viewport($doc) {
	// Inject <meta viewport>
	$node = $doc->getElementsByTagName('link')->item(0);
	$node = append_sibling($doc->createTextNode("\n\t"), $node->previousSibling->previousSibling);
	$viewport = $doc->createElement('meta');
	$viewport->setAttribute('name', 'viewport');
	$viewport->setAttribute('content', 'width=device-width, initial-scale=1.0');
	$node = append_sibling($viewport, $node);
}

function db_query($query, $args = array(), $die_if_error = true) {
	global $db_h;
	$result = $db_h->prepare($query);

	if (!$result->execute($args) && $die_if_error)
		die('SQL error: ' . $db_h->errorInfo()[2] . '(' . $query . ')');

	return $result;
}

function db_fetch($result) {
	return $result->fetch();
}

function db_insert_id() {
	global $db_h;
	return $db_h->lastInsertId();
}

function db_num_rows($result) {
	return $result->rowCount();
}

function db_free($result) {
	$result->closeCursor();
}

function redirect($url) {
	header("Location: $url");
	die('<html><body><h1>Redirect</h1>The document has moved <a href="' . $url .
		'">here</a>.</body></html>');
}

function login_box($message = '', $redirect = '.') {
	global $path_prefix;
	$title = 'Log In';
	include($path_prefix . 'inc/start_html.php');

	if ($message)
		echo '<div class="box-info">' . $message . '</div>';
?>

<form action="" method="post">
<dl class="fieldset">
<dt><span>Login</span></dt>
<dd>
<label for="username">Username:</label>
<input type="text" name="username" id="username" value="" maxlength="32"
	size="32" /><br/>
<label for="password">Password:</label>
<input type="password" name="password" id="password" value="" /><br/>
<input type="submit" name="send_login" value="Login" />
<input type="hidden" name="redirect" value="<?=htmlspecialchars($redirect)?>" />
</dd>
</dl>
</form>

<?php
	include($path_prefix . 'inc/end_html.php');

	exit;
}

function confirm_box($title, $message, $cancel, $ok, $hidden_forms = '') {
	global $path_prefix;
	include($path_prefix . 'inc/start_html.php');
?>
<form action="" method="post">
<div class="box-warning">
<?=$message?><br/>
<input type="submit" name="confirm_cancel" value="<?=$cancel?>" />
<input type="submit" name="confirm_ok" value="<?=$ok?>" />
<?=$hidden_forms?>
</div>
</form>
<?php
	include($path_prefix . 'inc/end_html.php');

	exit;
}

function error_box($title, $message) {
	global $path_prefix;
	include($path_prefix . 'inc/start_html.php');
?>
<div class="box-stop">
<?=$message?><br/>
</div>
<?php
	include($path_prefix . 'inc/end_html.php');

	die();
}

function count_str($needle, $haystack) {
	$count = 0;
	$pos = -1;

	while (true) {
		$pos = strpos($haystack, $needle, $pos + 1);
		if ($pos === false)
			return $count;
		$count++;
	}
}

function replace_placeholders($text, $lang = null) {
	return str_replace('{LANG_CODE}', ($lang ? $lang : 'en'), $text);
}

function sub_glob($path, $expr, $continue) {
	$expr = preg_quote($expr, '=');
	$expr = '=' . str_replace('\*', '.*', $expr) . '=';

	$matches = array();

	$dir = opendir($path ? $path : '.');

	if (!$dir)
		return array();

	if ($path)
		$path .= '/';

	while ($item = readdir($dir)) {
		if ($item != '.' and $item != '..' and preg_match($expr, $item)
			and (is_dir($path . $item) or $continue == ''))
			$matches[] = $path . $item;
	}

	closedir($dir);

	if ($continue == '')
		return $matches;

	@list($new_expr, $new_cont) = explode('/', $continue, 2);

	$new_matches = array();
	foreach ($matches as $match) {
		$new_matches = array_merge($new_matches, sub_glob($match, $new_expr,
			$new_cont));
	}

	return $new_matches;
}

function my_glob($to_search) {
	global $use_system_glob;

	if ($use_system_glob)
		return glob($to_search);

	list($expr, $cont) = explode('/', $to_search, 2);
	return sub_glob('', $expr, $cont);
}

function DOMinnerHTML($domElement) {
	$innerHTML = '';
	foreach ($domElement->childNodes as $child)
		$innerHTML .= $domElement->ownerDocument->saveXML($child);

	return $innerHTML;
}

function DOMouterHTML($domElement) {
	return $domElement->ownerDocument->saveXML($domElement);
}

function DEBUG_LOG($message){
	if(defined('ENABLE_DEBUGGING') && ENABLE_DEBUGGING==true){
		if(defined('DEBUG_FILE')){
			if($filehandle=fopen(DEBUG_FILE, 'a')){
				if(fwrite($filehandle, '['.date('Y-m-d H:i:s').'] '.$message."\n")){
					fclose($filehandle);
					return true;
				}
				fclose($filehandle);
			}
		}
	}

	return false;
}
