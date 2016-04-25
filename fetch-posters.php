#!/usr/bin/env php
<?php
// Load requested year
if(!isset($argv[1]) || !preg_match('~^\d{4}$~', $argv[1])){
	throw new \UnexpectedValueException('Year must be specified');
}
$year	= $argv[1];
$overwriteExisting	= (isset($argv[2]) && $argv[2]);

define('DATA_FILE', __DIR__.'/data/'.$year.'/movies.csv');
define('OUTPUT_URL', '/img/posters');
define('COLUMN_title',			2);
define('COLUMN_year',			3);
define('COLUMN_posterSource',	9);
define('COLUMN_poster',			10);

define('POSTER_WIDTH', 100);

if(!file_exists(DATA_FILE)){
	throw new \InvalidArgumentException('Database for year "'.$year.'" does not exist ('.DATA_FILE.')');
}

$curlPool			= curl_multi_init();
$curlPoolHandles	= [];

function addPosterRequest($row, $url){
	global $curlPool;
	global $curlPoolHandles;

	// Initiate request
	$curl	= curl_init($url);
	curl_setopt($curl, \CURLOPT_RETURNTRANSFER, true);

	// Add to pool
	curl_multi_add_handle($curlPool, $curl);
	$curlPoolHandles[$row]	= $curl;
}

function getPosterPaths($row){
	global $year;

	$posterID	= strtolower($row[COLUMN_title].' '.$row[COLUMN_year]);
	$posterID	= trim(preg_replace('~[^a-zA-Z0-9]+~', '-', $posterID), '-');

	$url	= OUTPUT_URL.'/'.$year.'/'.$posterID.'.jpg';
	$path	= __DIR__.'/src'.$url;

	return [$path, $url];
}

function resizeImage($sourceData, $targetPath, $maxWidth = null, $maxHeight = null, $quality = null){
	list($sourceWidth, $sourceHeight)	= getimagesizefromstring($sourceData);

	$width	= $sourceWidth;
	$height	= $sourceHeight;

	if(!isset($maxWidth)){
		$maxWidth	= $width;
	}
	if(!isset($maxHeight)){
		$maxHeight	= $height;
	}

	if($height > $maxHeight){
		// Portrait
		$width	= ($maxHeight / $height) * $width;
		$height	= $maxHeight;
	} else if($width > $maxWidth){
		// Landscape
		$height	= ($maxWidth / $width) * $height;
		$width	= $maxWidth;
	}

	$sourceImage	= imagecreatefromstring($sourceData);
	$targetImage	= imagecreatetruecolor($width, $height);
	imagecopyresampled(
		$targetImage,
		$sourceImage,
		0, 0,	// Destination (x,y)
		0, 0,	// Source (x,y)
		$width, $height,			// Destination
		$sourceWidth, $sourceHeight	// Source
	);

	return imagejpeg($targetImage, $targetPath, $quality);
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

	$source	= $row[COLUMN_posterSource];
	if(!isset($source) || $source === ''){
		// No poster to load
		continue;
	}

	list($path, )	= getPosterPaths($row);
	if($overwriteExisting || !file_exists($path)){
		// Need to load poster
		addPosterRequest($i, $source);
	}
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
		$response	= curl_multi_getcontent($curl);
		curl_multi_remove_handle($curlPool, $curl);

		list($path, $url)	= getPosterPaths($row);
		$success	= $response && resizeImage($response, $path, POSTER_WIDTH, null, 70);

		if($success){
			// Poster loaded
			$row[COLUMN_poster]	= $url;
		} else {
			// Failed
			$failures[]	= $row[COLUMN_title].' ('.$row[COLUMN_year].')';
		}
	}

	fputcsv($handle, $row);
}
fclose($handle);

echo 'Posters for "'.$year.'" fetched'."\n";
if($failures){
	echo "\n".count($failures).' failures:'."\n";
	foreach($failures as $failure){
		echo '- '.$failure."\n";
	}
}
