<?php
global $base_url;

echo '<?xml version="1.0" encoding="UTF-8" ?>';
if (!isset($path_prefix))
	$path_prefix = '';
if (!isset($head))
	$head = '';
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd"> 
<html xmlns="http://www.w3.org/1999/xhtml" lang="en-US" xml:lang="en-US">
<head>
	<meta http-equiv="content-type" content="text/html; charset=utf-8" />
	<meta http-equiv="Content-Style-Type" content="text/css" />
	<meta name="robots" content="all" />
	<title><?php echo $title; ?></title>
	<link rel="stylesheet" type="text/css" href="<?php echo $path_prefix; ?>shared/styles.css" />
<?=$head?></head>
<body>
<div id="banner">
<div><span>Ùser Đocumentation Ťranslator / Éditor</span></div>
</div>

<div class="nav">
<div>
<?php if (isset($ltop)) { ?>
<span style="float:left">
&nbsp;<?=$ltop?>
</span>
<?php
}
?>
<span>
<?=(isset($top) ? $top: '&nbsp;')?>
</span>
</div>
</div>

<div id="content">
<div>
