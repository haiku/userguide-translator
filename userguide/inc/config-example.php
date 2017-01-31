<?php
// Database access
$db_server = '';
$db_username = '';
$db_password = '';
$db_base_name = '';

// Common settings
$base_url = 'http://i18n-dev.haiku-os.org/userguide';

$use_system_glob = true; // Disable this if it does not work

// Debug options
define('ENABLE_DEBUGGING', false);
define('DEBUG_FILE', '/var/log/userguide/userguide.debug');

// Apache log files
$a2_logs = array(
					'Debug log'=>'/var/log/userguide/userguide.debug',
					'Error log'=>'/var/log/apache2/i18n.haiku-os.org-error_log',
					'Access log'=>'/var/log/apache2/i18n.haiku-os.org-access_log'
				);

define('EXPORT_DIR', 'export/docs');
define('REF_DIR', 'source_docs');
define('REP_DIR', 'source_repos');
define('IMPORT_DIR', 'import');

// HTML tags whose inner HTML has to be translated
$translate_tags = array(
	'html'					=> array(
		'head'				=> array(
			'title'			=> true,
		),
		'body'				=> array(
			'div'			=> array(
				'div'		=> array(
					'table'			=> array(
						'tr'		=> array(
							'td'	=> true,
							'th'	=> true,
						),
					),
					'div'		=> true,
					'h1'		=> true,
					'h2'		=> true,
					'h3'		=> true,
					'h4'		=> true,
					'h5'		=> true,
					'p'			=> true,
					'pre'		=> true,
					'ul'		=> array(
						'li'	=> true,
					),
					'ol'		=> array(
						'li'	=> true,
					),
				),
			),
		),
	),
);

// Allows some differences between the original HTML code and the translated one.

// If a tag name is indicated in this list, the translator will be able to change some of
// its attributes. However, adding/removing some of these tags will not be allowed.
$relaxed_parsing_attributes = array(
	'a'	=> array(
		'title',
		'href',
	),

);

// These tags can be added or removed without any restrictions.
$relaxed_parsing_complete = array(
	'i',
	'b',
	'br'
);


// Base document -- used for new documents
// WARNING : MUST BE WELL FORMED XML !
$base_document = '<?xml version="1.0" encoding="UTF-8"?>';
$base_document .= <<<EOD
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en">
<head>
<!--
 *
 * Copyright 2008, Haiku. All rights reserved.
 * Distributed under the terms of the MIT License.
 *
 * Authors:
 *		[Insert your name and address here]
 *
-->
	<meta http-equiv="content-type" content="text/html; charset=utf-8" />
	<meta http-equiv="Content-Style-Type" content="text/css" />
	<meta name="robots" content="all" />
	<title>{TITLE}</title>
	<link rel="stylesheet" type="text/css" href="../../Haiku-doc.css" />
</head>
<body>

<div id="banner">
<div><span>User guide</span></div>
</div>

<div class="nav">
<div class="inner">
<span>
 «  <a href="prev.html">[Previous page]</a> 
::  <a href="up.html" class="uplink">[Up]</a> 
::  <a href="next.html">[Next page]</a>  »
</span></div>
</div>

<div id="content">
<div>

<h2>{TITLE}</h2>

[Insert content here!]

</div>
</div>
<!--
<div class="nav">
<div class="inner"><span>
 «  <a href="prev.html">[Previous page]</a> 
::  <a href="up.html" class="uplink">[Up]</a> 
::  <a href="next.html">[Next page]</a>  »
</span></div>
</div>
-->
</body>
</html>

EOD;
