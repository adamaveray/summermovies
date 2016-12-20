<?php
$ROOT=__DIR__;
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

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="summer-movies.ics"');

$lineLength	= 75;
$lineBreak	= "\n";
$lineSplit	= $lineBreak."\t";

function formatICalDate(\DateTimeInterface $date, $time = true){
	$dateFormat	= 'Ymd';
	if($time){
		$dateFormat	= 'Ymd\THis\Z';
		$timezone	= new \DateTimeZone('UTC');
		$date		= $date->setTimezone($timezone);
	}
	return str_replace('/', '-', $date->format($dateFormat));
}

/** @var Movie[] $movies */
/** @var Venue[] $venues */
list($venues, $movies)	= loadData($year);

$output = <<<'ICAL'
BEGIN:VCALENDAR
METHOD:PUBLISH
VERSION:2.0
PRODID:-//Summer Movies NYC//Calendar Feed//EN
ICAL;
$output	.= "\n";

$creation	= formatICalDate(new \DateTimeImmutable());
foreach ($movies as $movie){
	$status	= 'CONFIRMED';
	$start	= formatICalDate($movie->date, false);
	$end	= formatICalDate($movie->date, false);
	$venue	= $movie->venue;
	$location	= $venue->name;
	if($venue->location && $venue->location != $location){
		$location	.= ' ('.$venue->location.')';
	}

	// Additional fields:
	// `LAST-MODIFIED:`
	$output	.= <<<ICAL
BEGIN:VEVENT
SUMMARY:$movie->title ($movie->year)
UID:$movie->id
STATUS:$status
DTSTAMP:$creation
DTSTART;VALUE=DATE:$start
DTEND;VALUE=DATE:$end
LOCATION:$location
GEO:$venue->lat;$venue->lng
DESCRIPTION:(via summermovies.nyc)
END:VEVENT
ICAL;
	$output	.= "\n";
}

// close calendar
$output .= <<<ICAL
END:VCALENDAR
ICAL;
$output	.= "\n";

/*
// Enforce line length
$output	= preg_replace_callback('/(?<=\n)([^\n]{'.($lineLength+1).',})(?=\n)/', function(array $matches) use($lineLength, $lineSplit){
	return wordwrap($matches[1], $lineLength, $lineSplit, true);
}, $output);
*/

echo $output;
