<?php
define('IN_TRANSLATE', 1);

$path_prefix = '../';
require('../inc/common.php');

role_needed(ROLE_ADMIN);

$title = 'Users';
include('admin_top.php');

// Delete users
if (isset($_POST['delete_selection']) and is_array(@$_POST['del_list'])
	and !empty($_POST['del_list'])) {
	
	$del_list = array_map('intval', array_keys($_POST['del_list']));	
	$del_list = implode(', ', $del_list);
	
	confirm_box($title, 'Do you really want to delete the selected users ?',
		'Cancel', 'Delete',
		'<input type="hidden" name="del_list" value="' . $del_list . '" />');
} else if(isset($_POST['del_list']) and isset($_POST['confirm_ok'])) {
	$del_list = explode(', ', $_POST['del_list']);	
	$del_list = array_map('intval', $del_list);
	
	if (in_array($user_id, $del_list))
		error_box('Error', 'Sorry, I won\'t let you delete yourself.');
	
	foreach ($del_list as $delete_id) {
		$delete_id = intval($delete_id);
		
		db_query('DELETE FROM ' . DB_USERS . " WHERE user_id = $delete_id");
	}
}

// Change roles
if (isset($_POST['submit_roles']) and isset($_POST['user_role'])
	and is_array($_POST['user_role'])) {
	
	$new_roles = array_map('intval', $_POST['user_role']);
	
	if (isset($new_roles[$user_id]) and $new_roles[$user_id] != ROLE_ADMIN)
		error_box('Error', 'You would have troubles setting you back to Admin' .
			' after this...');
	
	$roles_to_db = array_flip($db_roles);
	
	foreach ($new_roles as $id => $new_role) {
		$new_user_role = $roles_to_db[$new_role];
		
		db_query('
			UPDATE ' . DB_USERS . " SET user_role = '$new_user_role'
			WHERE user_id = $id");
	}
}

// Password reset
if (isset($_GET['reset']) and intval($_GET['reset']) != 0) {
	$reset_id = intval($_GET['reset']);
	if ($reset_id == $user_id)
		error_box($title, 'That’s not a good idea...');

	$req = db_query('SELECT username FROM ' . DB_USERS . " WHERE user_id = $reset_id");
	$reset_name = db_fetch($req);
	db_free($req);
	if (!$reset_name)
		error_box($title, 'No such user !');
	$reset_name = $reset_name['username'];
	
	if (isset($_POST['reset_password']) and isset($_POST['reset_email'])
 		and strpos($_POST['reset_email'], '@', 1) !== false) {
 		$reset_email = unprotect_quotes($_POST['reset_email']);
 		$new_password = generate_password();
 		$hashed_pass = sha1($new_password);
 		
 		db_query('UPDATE ' . DB_USERS . "
 			SET user_password = '$hashed_pass'
 			WHERE user_id = $reset_id");
 		
 		if (reset_password_email($reset_name, $reset_email, $new_password)) {
 			include_once('../inc/start_html.php');
			echo '<div class="box-info"><b>' . htmlspecialchars($reset_name) .
				'</b>’s password was successfully reset.</div>' . "\n";
		} else {
			error_box($title, 'Unable to send the password reset email. <b>' .
				htmlspecialchars($reset_name) . '</b>’s new password is “' . $new_password);
		}
 		
	} else {
		include_once('../inc/start_html.php');
?>
<form action="?reset=<?=$reset_id?>" method="post">
<dl class="fieldset">
<dt><span>Reset password for “<?=$reset_name?>”</span></dt>
<dd>
<label for="reset_email">Send new password to:</label>
<input type="text" name="reset_email" id="reset_email"
	value="" size="32" /><br/>
<input type="submit" name="reset_password" value="Reset Password" />
</dd>
</dl>
</form>
<?php
		include_once('../inc/end_html.php');
		exit;
	}
}

include_once('../inc/start_html.php');

// Add users
$new_user_name = '';
$new_user_role = ROLE_UNDEF;
$new_user_email = '';

if (isset($_POST['new_user_name']) and isset($_POST['new_user_role'])
 	and isset($_POST['new_user_email'])) {
	$new_user_name = unprotect_quotes($_POST['new_user_name']);
	$new_user_role = intval($_POST['new_user_role']);
	$new_user_email = unprotect_quotes($_POST['new_user_email']);

	if (strlen($new_user_name) > 1 and $new_user_role >= ROLE_UNDEF
		and $new_user_role <= ROLE_MAX
		and ($new_user_email == '' or strpos($new_user_email, '@', 1) !== false)) {
		
		$new_user_name = db_esc($new_user_name);
		$new_user_pass = generate_password();
		$hashed_pass = sha1($new_user_pass);
		$roles_to_db = array_flip($db_roles);
		$new_user_role = $roles_to_db[$new_user_role];

		db_query('
			INSERT INTO ' . DB_USERS . '
			(username, user_password, user_role) ' . "
			VALUES ('$new_user_name', '$hashed_pass', '$new_user_role')");
		
		
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
		"”.\n$status</div>";

		$new_user_name = '';
		$new_user_role = ROLE_UNDEF;
		$new_user_email = '';

	} else {
		echo '<div class="box-stop">Adding user failed: Incorrect ' .
		'parameters.</div>';
	}
}
?>
<form action="" method="post">
<h1>Users</h1>
<table class="list">
<tr>
<th style="width:20px">&nbsp;</th><th>Name</th><th style="width:5%">Role</th>
<th style="width:5%">Edits</th><th style="width:5%">Translations</th><th style="width:10%">Options</th>
</tr>
<?php
$req = db_query('SELECT * FROM ' . DB_USERS . ' ORDER BY UCASE(username)');
while ($row = db_fetch($req)) {
	if ($row['user_id'] == $user_id) {
?>
<tr class="<?=alt_row()?>">
<td>&nbsp;</td>
<td><?=htmlspecialchars($row['username'])?></td>
<td>Administrator</td><td><?=$row['num_edits']?></td>
<td><?=$row['num_translations']?></td>
<td>&nbsp;</td>
</tr>
<?php
	} else {
?>
<tr class="<?=alt_row()?>">
<td><input type="checkbox" name="del_list[<?=$row['user_id']?>]" /></td>
<td><?=htmlspecialchars($row['username'])?></td>
<td>
<select name="user_role[<?=$row['user_id']?>]">
<?php
		foreach ($roles_names as $role_id => $role_name) {
			echo '<option value="' . $role_id . '"' . 
				($role_id == $db_roles[$row['user_role']] ? ' selected="selected"' 
				: '') . '>' . $role_name . "</option>\n";
		}
?>
</select>
</td>
<td><?=$row['num_edits']?></td>
<td><?=$row['num_translations']?></td>
<td><a href="?reset=<?=$row['user_id']?>">Reset password</a></td>
</tr>
<?php
	}
}
?>
<tr class="bottom">
<td colspan="6">
<input type="submit" name="submit_roles" value="Update roles" />
<input type="submit" name="delete_selection" value="Delete selection" />
</td>
</tr>
</table>
</form>
<br/>
<form action="" method="post">
<dl class="fieldset">
<dt><span>Add new user</span></dt>
<dd>
<label for="new_user_name">Username:</label>
<input type="text" name="new_user_name" id="new_user_name"
	value="<?=$new_user_name?>" maxlength="32" size="32" /><br/>
<label for="new_user_role">Role:</label>
<select name="new_user_role" id="new_user_role">
<?php
foreach ($roles_names as $role_id => $role_name) {
	echo '<option value="' . $role_id . '">' . $role_name . '</option>';
}
?>
</select><br/>
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
From: Haiku Documentation Translate Tool <noreply@haiku-os.org>
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
Haiku Documentation Translate Tool - http://i18n.haiku-os.org/userguide
EOM;

	return mail($to, $subject, $message, $headers);
}

function reset_password_email($username, $email, $password) {
	$to = "$username <$email>";
	$date = date('r');
	$subject = 'Haiku Documentation Translate Tool — Password Reset';
	$version = phpversion();

	$headers = <<<EOH
From: Haiku Documentation Translate Tool <noreply@userguide.haikuzone.net>
Date: $date
User-Agent: PHP/$version
EOH;

	$message = <<<EOM
Hello $username,
Your password on the Haiku Documentation Translate Tool has been reset by an administrator.

Your new login information is:
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
