<?php
$path_prefix = '../';
require('../inc/common.php');

role_needed(ROLE_LANG_MANAGER);

$title = 'Users Management';

$top = '<a href="../documents.php">Return to the main page</a>';

include_once('../inc/start_html.php');

// Add users
$new_user_name = '';
$new_user_email = '';

if (isset($_POST['new_user_name'])  	and isset($_POST['new_user_email'])) {
	$new_user_name = unprotect_quotes($_POST['new_user_name']);
	$new_user_email = unprotect_quotes($_POST['new_user_email']);

	if (strlen($new_user_name) > 1
		and ($new_user_email == '' or strpos($new_user_email, '@', 1) !== false)) {

		$new_user_name = db_esc($new_user_name);
		$new_user_pass = generate_password();
		$hashed_pass = sha1($new_user_pass);

		db_query('
			INSERT INTO ' . DB_USERS . '
			(username, user_password, user_role) ' . "
			VALUES ('$new_user_name', '$hashed_pass', 'translator')");

		$status = '';
		if ($new_user_email) {
			if (new_account_email($new_user_name, $new_user_email, $new_user_pass)) {
				$status = 'Email sent.';
			} else {
				$status = 'Error sending email !';
			}
		}

		echo '<div class="box-info"><b>' . htmlspecialchars($new_user_name) .
		'</b> added successfully. His password is “' . $new_user_pass .
		"”.\n<br/>$status</div>";

		$new_user_name = '';
		$new_user_email = '';


	} else {
		echo '<div class="box-stop">Adding user failed: Incorrect ' .
		'parameters.</div>';
	}
}
?>
<h1>Users</h1>
<table class="list">
<tr>
<th>Name</th><th style="width:15%">Role</th>
<th style="width:5%">Edits</th><th style="width:5%">Translations</th>
</tr>
<?php
$req = db_query('SELECT * FROM ' . DB_USERS . ' ORDER BY UCASE(username)');
while ($row = db_fetch($req)) {
?>
<tr class="<?=alt_row()?>">
<td><?=htmlspecialchars($row['username'])?></td>
<td><?=$roles_names[$db_roles[$row['user_role']]]?></td><td><?=$row['num_edits']?></td>
<td><?=$row['num_translations']?></td>
</tr>
<?php
}
?>
<tr class="bottom">
<td colspan="4">
&nbsp;
</td>
</tr>
</table>
</form>
<br/>
<form action="" method="post">
<dl class="fieldset">
<dt><span>Add New User</span></dt>
<dd>
<label for="new_user_name">Username:</label>
<input type="text" name="new_user_name" id="new_user_name"
	value="<?=$new_user_name?>" maxlength="32" size="32" /><br/>
<label for="new_user_email">E-mail:</label>
<input type="text" name="new_user_email" id="new_user_email"
	value="<?=$new_user_email?>" size="32" /><br/>
<em>(Optional) If filled, the login information will be emailed to the new user.</em><br/>
<input type="submit" name="add_user" value="Add" />
</dd>
</dl>
</form>

<?php
include_once('../inc/end_html.php');

function generate_password() {
	$characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890;@&!=/*+';

	$length = strlen($characters);

	$pass = '';

	for($i = 0 ; $i < 8 ; $i++) {
		$char = $characters[rand() % $length];
		if (rand() % 2)
			$char = strtolower($char);
		$pass .= $char;
	}

	return $pass;
}


function new_account_email($username, $email, $password) {
	$to = "$email";
	$date = date('r');
	$subject = 'Your account on the Haiku Documentation Translate Tool';
	$version = phpversion();

	$headers = <<<EOH
From: Haiku Documentation Translate Tool <noreply@userguide.haikuzone.net>
Date: $date
User-Agent: PHP/$version
EOH;

	$message = <<<EOM
Hello $username,
An administrator (or a language manager) created you an account on the Haiku Documentation
Translate Tool.

Your login information is:
---------------------------------------
Username: $username
Password: $password
---------------------------------------

You can change your password in the settings panel.

-------------------
Haiku Documentation Translate Tool - userguide.haikuzone.net
EOM;

	return mail($to, $subject, $message, $headers);
}

?>
