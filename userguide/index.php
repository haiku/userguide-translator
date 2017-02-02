<?php
require_once('inc/common.php');

$title = 'Haiku User Documentation';
$top = ' ';
include('inc/start_html.php');

if ($user_logged_in)
	redirect('documents.php');
?>
<h1>Haiku User Guide Translations</h1>
<p>If you're looking for the actual documentation for Haiku, please visit the pages of the
<a href="https://www.haiku-os.org/docs/userguide/en/contents.html">Official Haiku User Guide</a>
of the Haiku project.</p>
<p>If you would like to help with translation, visit our
<a href="https://dev.haiku-os.org/wiki/i18n">Wiki</a> and learn more on how to
<a href="https://dev.haiku-os.org/wiki/i18n/UserGuide">get involved</a>.</p>
<p>Or you can also browse the
<a href="export/docs/userguide/en/contents.html">work-in-progress version of the User Guide</a> here.</p>
<br/>
<form action="documents.php?login" method="post">
<dl class="fieldset">
<dt><span>Login</span></dt>
<dd>
<label for="username">Username:</label>
<input type="text" name="username" id="username" value="" maxlength="32"
	size="32" /><br/>
<label for="password">Password:</label>
<input type="password" name="password" id="password" value="" /><br/>
<input type="submit" name="send_login" value="Login" />
<input type="hidden" name="redirect" value="documents.php" />
</dd>
</dl>
</form>
<?php
include('inc/end_html.php');
?>
