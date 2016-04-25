#!/usr/bin/env php
<?php
// Load requested year
if(!isset($argv[1]) || !preg_match('~^\d{4}$~', $argv[1])){
	throw new \UnexpectedValueException('Year must be specified');
}
$year	= $argv[1];

define('DATA_FILE', __DIR__.'/data/'.$year.'/movies.csv');
define('API_URL', 'http://www.omdbapi.com/');

define('COLUMN_date', 0);
define('COLUMN_venue', 1);
define('COLUMN_title', 2);
define('COLUMN_year', 3);
define('COLUMN_synopsis', 4);
define('COLUMN_url', 5);
define('COLUMN_cost', 6);
define('COLUMN_details', 7);
define('COLUMN_rating', 8);
define('COLUMN_poster', 9);

if(!file_exists(DATA_FILE)){
	throw new \InvalidArgumentException('Database for year "'.$year.'" does not exist ('.DATA_FILE.')');
}

$curlPool			= curl_multi_init();
$curlPoolHandles	= [];

function addMovieRequest($row, $title, $year = null){
	global $curlPool;
	global $curlPoolHandles;

	// Initiate request
	$url	= API_URL.'?'.http_build_query([
		// Query
		't'		=> $title,
		'y'		=> isset($year) ? $year : '',
		'type'	=> 'movie',

		// Response
		'plot'	=> 'short',
		'r'		=> 'json',
		'v'		=> 1,
	]);
	$curl	= curl_init($url);
	curl_setopt($curl, \CURLOPT_RETURNTRANSFER, true);

	// Add to pool
	curl_multi_add_handle($curlPool, $curl);
	$curlPoolHandles[$row]	= $curl;
}

$data		= [];
$i			= -1;
$handle		= fopen(DATA_FILE, 'r');
$isFirst	= true;
$headings	= null;
while(!feof($handle)){
	$i++;
	$row	= fgetcsv($handle);
	if($row === false){
		// End of file
		break;
	}

	if($i === 0){
		// Separate headings row
		$headings	= $row;
		continue;
	}

	$data[$i]	= $row;
	addMovieRequest($i, $row[COLUMN_title], $row[COLUMN_year]);
}
fclose($handle);

if(!$headings){
	throw new \UnexpectedValueException('No rows found');
}

// Execute requests
do {
	curl_multi_exec($curlPool, $running);
	curl_multi_select($curlPool);
} while ($running > 0);

$failures	= [];

// Write changes
$handle	= fopen(DATA_FILE, 'w');
fputcsv($handle, $headings);
foreach($data as $rowID => $row){
	// Check for new data
	if(isset($curlPoolHandles[$rowID])){
		$curl	= $curlPoolHandles[$rowID];

		// Load response data
		$response	= @json_decode(curl_multi_getcontent($curl), true);
		curl_multi_remove_handle($curlPool, $curl);

		if(!$response || $response['Response'] === 'False'){
			// Failed
			$failures[]	= $row[COLUMN_title].' ('.$row[COLUMN_year].')';
		} else {
			// Request succeeded - update with available data
			$row[COLUMN_year]	= $response['Year'];
			if($response['Rated'] !== 'NOT RATED'){
				$row[COLUMN_rating]		= $response['Rated'];
			}
			if($response['Plot'] !== 'N/A'){
				$row[COLUMN_synopsis]	= $response['Plot'];
			}
			if($response['Poster'] !== 'N/A'){
				$row[COLUMN_poster]		= $response['Poster'];
			}
		}
	}

	fputcsv($handle, $row);
}
fclose($handle);

echo 'Sync "'.$year.'" complete'."\n";
if($failures){
	echo "\n".count($failures).' failures:'."\n";
	foreach($failures as $failure){
		echo '- '.$failure."\n";
	}
}
