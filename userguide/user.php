<?php
require_once('inc/common.php');

if (!$user_logged_in)
	redirect('documents.php');

$title = 'Settings';
$top = '<a href="documents.php">Return to index</a>';
include('inc/start_html.php');

$req = db_query('SELECT real_name, email FROM ' . DB_USERS . " WHERE user_id = ?",
	array($user_id));
$row = db_fetch($req);
db_free($req);
$real_name = $row['real_name'];
$email = $row['email'];

if (isset($_POST['update_profile']) and (isset($_POST['real_name']) or isset($_POST['email']))) {
	$real_name = $_POST['real_name'];
	$email = $_POST['email'];
	db_query('UPDATE ' . DB_USERS . " SET real_name = ?, email = ? WHERE user_id = ?",
		array($real_name, $email, $user_id));
}

if (!isset($_SESSION['form_salt']) or !$_SESSION['form_salt'])
	$_SESSION['form_salt'] = uniqid();

$fs = $_SESSION['form_salt'];

if (isset($_POST['old_pass' . $fs]) and isset($_POST['new_pass' . $fs])
	and trim($_POST['new_pass' . $fs]) and isset($_POST['con_pass' . $fs])) {
	if ($_POST['new_pass' . $fs] == $_POST['con_pass' . $fs]) {
		$old_pass = sha1($_POST['old_pass' . $fs]);
		$new_pass = sha1($_POST['new_pass' . $fs]);

		$req = db_query('
			UPDATE ' . DB_USERS . "
			SET user_password = ?
			WHERE user_id = ? AND user_password = ?", array($new_pass, $user_id, $old_pass));
		if (!db_num_rows($req) and $old_pass != $new_pass) {
			box('warning', 'Incorrect old password.');
		} else {
			$_SESSION['user_pass'] = $new_pass;
			box('info', 'Password updated successfully.');
		}
	} else {
		box('warning', 'The new password and confirmation must match.');
	}
}
?>
<form action="" method="post">
<dl class="fieldset">
<dt><span>User Profile</span></dt>
<dd>
<label for="real_name">Real name (optional):</label>
<input type="text" name="real_name" id="real_name" value="<?=htmlspecialchars($real_name)?>" size="32" />
<br/>
<label for="real_name">Email:</label>
<input type="email" name="email" id="email" value="<?=htmlspecialchars($email)?>" size="32" />
<br/>
<input type="submit" name="update_profile" value="Update Profile" />
</dd>
</dl>
</form>
<br/>
<form action="" method="post">
<dl class="fieldset">
<dt><span>Change password</span></dt>
<dd>
<label for="old_pass<?=$fs?>">Old password:</label>
<input type="password" name="old_pass<?=$fs?>" id="old_pass<?=$fs?>" value="" />
<br/>
<label for="new_pass<?=$fs?>">New password:</label>
<input type="password" name="new_pass<?=$fs?>" id="new_pass<?=$fs?>" value="" />
<br/>
<label for="con_pass<?=$fs?>">Confirm new password:</label>
<input type="password" name="con_pass<?=$fs?>" id="con_pass<?=$fs?>" value="" />
<br/>
<input type="submit" name="change_pass" value="Change Password" />
</dd>
</dl>
</form>
<?php
include('inc/end_html.php');

function box($type, $message) {
	echo '<div class="box-' . $type . '">' . $message . '</div>' . "\n";
}
