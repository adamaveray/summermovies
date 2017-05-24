<?php
require_once(__DIR__.'/../lib.php');
$siteTitle	= 'Summer Movies NYC';
if(!isset($pageTitle)){
	// Use default
	$pageTitle = $siteTitle;
} else if(strpos($pageTitle, $siteTitle) === false){
	// Include site title
	$pageTitle	.= ' – '.$siteTitle;
}
?><!doctype html>
<!--[if lt IE 7]><html class="no-js lt-ie10 lt-ie9 lt-ie8 lt-ie7"><![endif]-->
<!--[if IE 7]><html class="no-js lt-ie10 lt-ie9 lt-ie8"><![endif]-->
<!--[if IE 8]><html class="no-js lt-ie10 lt-ie9"><![endif]-->
<!--[if IE 9]><html class="no-js lt-ie10"><![endif]-->
<!--[if gt IE 9]><!--><html class="no-js" lang="en-US"><!--<![endif]-->
<head>
	<meta charset="utf-8" />
	<meta http-equiv="x-ua-compatible" content="ie=edge" />
	
	<title><?=e($pageTitle);?></title>
	<?php if(isset($pageDescription)){ ?>
		<meta name="description" content="<?=e($pageDescription)?>" />
	<?php } ?>
	
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<!--
	<link rel="icon" type="image/png" href="/favicon.png" sizes="32x32" />
	<link rel="apple-touch-icon" href="/apple-touch-icon.png" />
	-->
	<link rel="stylesheet" href="/css/main.css" />
	<?=(isset($stylesheets) ? $stylesheets : '');?>
	<!--[if lt IE 9]><script src="/js/vendor/html5shiv-3.7.3.min.js"></script><![endif]-->
	<script src="/js/vendor/modernizr-3.2.0.min.js"></script>
	<?=(isset($head) ? $head : '');?>
</head>
<body>
	<!--[if lte IE 8]><p class="browserupgrade">You are using an <strong>outdated</strong> browser. Please <a href="http://browsehappy.com/">upgrade your browser</a> to improve your experience.</p><![endif]-->
	<a href="#main" tabindex="1" class="accessibility-aid" id="nav-skip">Skip to content</a>
