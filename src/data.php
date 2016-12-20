<?php
$ROOT=__DIR__.'/../src';
require_once($ROOT.'/_inc/lib.php');

$year	= @date('Y');
if(IS_CLI){
	$args	= array_slice($argv, 1);
	for($i = 0, $max = count($args); $i < $max; $i += 2){
		$key	= substr($args[$i], 2);
		if($key === 'year'){
			$year	= $args[$i+1];
		}
	}
}

$data	= loadData($year);
echo serialize($data);
