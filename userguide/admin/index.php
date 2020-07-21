<?php
$path_prefix = '../';
require('../inc/common.php');

role_needed(ROLE_ADMIN);

$title = 'Administration Panel';
include('admin_top.php');
include('../inc/start_html.php');
?>
<dl>
<dt><a href="langs.php">Manage Languages</a></dt>
	<dd>Add and remove languages</dd>
<dt><a href="users.php">Manage Users</a></dt>
	<dd>Add new users, alter user roles</dd>
<dt><a href="import.php">Import Documents</a></dt>
	<dd>Import existing documents to the translation system.</dd>
<dt><a href="docs.php">Manage Documents</a></dt>
	<dd>Rename or remove documents.</dd>
<dt><a href="resources.php">Manage Resources</a></dt>
	<dd>Add, remove, modify external resources.</dd>
<dt><a href="export.php">Export Documents</a></dt>
	<dd>Export edited and translated documents to the repository.</dd>
</dl>
<?php
include('../inc/end_html.php');
?>
