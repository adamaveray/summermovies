<?php
date_default_timezone_set('America/New_York');

define('MAPS_KEY', 'AIzaSyBk_2x9TAGfnq_sroX2sgmq029kPSPGfjg');
define('ANALYTICS_ID', 'UA-76643227-1');

// Paths
define('CACHE_DIR', __DIR__.'/../_cache');
define('DATA_DIR', __DIR__.'/../../data');

// Setup environment
for($i = 1, $length = count($argv); $i < $length; $i++){
	if($argv[$i] !== '--environment'){
		continue;
	}
	if(!isset($argv[$i+1])){
		throw new \OutOfRangeException('Environment not specified');
	}
	$environment	= $argv[$i+1];
}
define('IS_DEV', isset($environment) && $environment === 'development');
define('IS_DEBUG', IS_DEV || (php_sapi_name() === 'cli'));
define('SKIP_CACHE', IS_DEV);
unset($environment, $length, $i);

if(php_sapi_name() === 'cli'){
	// Running as script - substitute missing request data
	$_SERVER	+= [
		'HTTP_HOST'		=> 'localhost',
		'REQUEST_URI'	=> str_replace($ROOT, '', $_SERVER['SCRIPT_NAME']),
		'SERVER_NAME'	=> 'localhost',
	];
}
