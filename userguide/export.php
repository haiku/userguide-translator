<?php
define('IN_TRANSLATE', 1);

require('inc/common.php');
role_needed(ROLE_TRANSLATOR);

if(file_exists($_SERVER['DOCUMENT_ROOT'].$_SERVER['REQUEST_URI']))
	$fname=$_SERVER['DOCUMENT_ROOT'].$_SERVER['REQUEST_URI'];
	$fp=fopen($fname,'r');
	header("Content-Type: " . mime_content_type($fname));
	header("Content-Length: " . filesize($fname));
	fpassthru($fp);
	exit;