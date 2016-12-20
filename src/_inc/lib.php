<?php
require_once(__DIR__.'/config.php');

require_once(__DIR__.'/models/Venue.php');
require_once(__DIR__.'/models/Movie.php');

function loadData($year, $useCache = true){
	$dir	= DATA_DIR.'/'.$year;
	if(!is_dir($dir)){
		// Year not available
		throw new \OutOfBoundsException('Undefined year');
	}

	// Try cache
	$cachePath	= $dir.'/compiled.raw';
	if($useCache && file_exists($cachePath)){
		return unserialize(file_get_contents($cachePath));
	}

	/** @var Venue[] $venues */
	$venues	= loadCSV($dir.'/venues.csv', [
		'id'		=> [],
		'name'		=> [],
		'location'	=> ['optional' => true],
		'borough'	=> [],
		'image'		=> ['type' => 'bool', 'default' => false],
		'website'	=> ['optional' => true],
		'facebook'		=> ['optional' => true],
		'twitter'		=> ['optional' => true],
		'foursquare'	=> ['optional' => true],
		'lat'		=> ['type' => 'float'],
		'lng'		=> ['type' => 'float'],
	], function($data){
		return new Venue($data);
	});
	$venuesLookup	= [];
	foreach($venues as $venue){
		$venuesLookup[$venue->id]	= $venue;
	}
	$venues	= $venuesLookup;

	/** @var Movie[] $movies */
	$movies	= loadCSV($dir.'/movies.csv', [
		'date'			=> ['type' => 'date'],
		'venue'			=> [],
		'title'			=> [],
		'year'			=> ['optional' => true],
		'synopsis'		=> ['optional' => true],
		'url'			=> ['optional' => true, 'type' => 'url'],
		'cost'			=> ['optional' => true],
		'details'		=> ['optional' => true],
		'rating'		=> ['optional' => true],
		'posterSource'	=> ['optional' => true],
		'poster'		=> ['optional' => true],
	], function($data) use($venues){
		// Load venue
		$venueID	= $data['venue'];
		if(!isset($venues[$venueID])){
			throw new \OutOfBoundsException('Unknown venue "'.$venueID.'"');
		}
		$data['venue']	= $venues[$venueID];

		return new Movie($data);
	});

	usort($movies, function(Movie $a, Movie $b){
		if($a->date->format('c') !== $b->date->format('c')){
			return (($a->date > $b->date) ? 1 : -1);
		}

		return strcmp($a->title, $b->title);
	});

	return [$venues, $movies];
}

/**
 * @var mixed|null $content 	The value to output
 * @return string				The escaped value to output
 */
function e($content){
	$output	= (string)$content;
	return htmlspecialchars($output, ENT_QUOTES | ENT_HTML5 | ENT_IGNORE, 'UTF-8');
}

/**
 * Converts a string to a "slug", suitable for using in URLs, etc (eg "A test string" -> "a-test-string")
 *
 * @param string $string	The raw string to slugify
 * @return string			The slugified string
 */
function slugify($string){
	$output	= $string;

	// Remove apostrophes
	$output	= preg_replace('~(\w)[\'’](\w)+~u', '$1$2', $output);
	if($output === null){
		// Cannot process - invalid UTF characters
		$output	= $string;
	}
	// Make lowercase
	$output	= strtolower($output);
	// Remove HTML entities
	$output	= preg_replace('~&#?[0-9a-z]+;~', '', $output);
	// Convert all non-alphanumeric characters to hyphens
	$output	= preg_replace('~[^a-z0-9]+~', '-', $output);
	// Remove leading or trailing hyphens
	$output	= trim($output, '-');

	return $output;
}

/**
 * @param string $path				The path to a CSV file
 * @param array|null $headers		Array restrictions
 * @param callable|null $callback	A callback to run on each row, with the arguments ($row, $index)
 * @return array				The processed CSV data, as an array of associative arrays
 * @throws \RuntimeException	Errors occurred while parsing CSV, or CSV could not be found
 */
function loadCSV($path, array $headers = null, callable $callback = null){
	if(!file_exists($path)){
		throw new \UnexpectedValueException('File not found ('.$path.')');
	}

	// Detect line endings
	$previousLineEndings	= ini_get('auto_detect_line_endings');
	ini_set('auto_detect_line_endings', true);

	$rows	= [];

	try {
		// Parse CSV
		$i		= 0;
		$handle	= fopen($path, 'r');
		while(($data = fgetcsv($handle)) !== false){
			$i++;
			if($i === 1){
				// First row - headers
				if(isset($headers)){
					// Confirm headers
					$comparableHeaders	= function($headers){
						return array_map(function($header){
							return strtolower(str_replace('-', '', slugify($header)));
						}, $headers);
					};
					if(count($headers) !== count($data) || $comparableHeaders(array_keys($headers)) !== $comparableHeaders($data)){
						$debug	= 'Expected: ['.implode(',',$comparableHeaders(array_keys($headers)))
								  .'], Actual: ['.implode(',',$comparableHeaders($data)).']';
						throw new \UnexpectedValueException('Headers do not match expected values ('.$debug.')');
					}
				} else {
					$headers	= $data;
				}

				// Normalise headers
				$processedHeaders	= [];
				foreach($headers as $header => $config){
					if(is_numeric($header)){
						// No config provided
						$header	= $config;
						$config	= [];
					}
					$config	= array_merge([
						'optional'	=> true,
						'type'		=> null,
						'default'	=> null,
					], $config);
					$processedHeaders[$header]	= $config;
				}
				$headers	= $processedHeaders;
				continue;
			}

			try {
				$row	= processCSVRow($data, $headers, $i);
				if(isset($callback)){
					$break	= false;
					$row	= $callback($row, $i, $break);
					if($break){
						break;
					}
				}
				if(isset($row)){
					$rows[]	= $row;
				}
			} catch(\RuntimeException $e){
				if(!isset($emptyRow)){
					$emptyRow	= array_pad([], count($headers), '');
				}
				if($data == $emptyRow){
					if(IS_DEBUG){
						throw new \UnexpectedValueException('Empty row on row '.$i);
					}

					// Ignore empty rows
					continue;
				}

				// Row is not empty - pass exception through
				throw $e;
			}
		}
	} catch(\RuntimeException $exception){
		// Delay for config restoration
	}

	// Restore config
	ini_set('auto_detect_line_endings', $previousLineEndings);

	// Re-throw exception
	if(isset($exception)){
		throw $exception;
	}

	return $rows;
}

/**
 * @param array $row			The raw row data
 * @param array $headers		The headers to validate against
 * @param int|null $line		The current line number, for error feedback
 * @return array				The processed array
 * @internal	Do not call directly - only used by loadCSV
 */
function processCSVRow(array $row, array $headers, $line = null){
	$processed	= [];
	$i			= 0;
	foreach($headers as $header => $config){
		$value	= (isset($row[$i]) ? $row[$i] : null);
		$value	= processCSVValue($value, $config, $header, $line);

		$levels	= explode(':', $header);
		$level	= &$processed;
		foreach($levels as $levelKey){
			if(!isset($level[$levelKey])){
				$level[$levelKey]	= [];
			}
			$level	= &$level[$levelKey];
		}
		$level	= $value;
		$i++;
	}
	return $processed;
}

/**
 * @param mixed $value			The raw cell value
 * @param array $config			The header definition to validate against
 * @param string $header		The column name, for error feedback
 * @param int|null $line		The current line number, for error feedback
 * @return array				The processed value
 * @internal	Do not call directly - only used by loadCSV
 */
function processCSVValue($value, array $config, $header, $line = null){
	$fail	= function($message, $extra = '') use($line){
		if($extra !== ''){
			$extra	= ' ('.$extra.')';
		}
		throw new \UnexpectedValueException($message.(isset($line) ? ' on row '.$line : '').$extra);
	};
	$targetEncoding	= 'UTF-8';

	if(!isset($value) || trim($value) === ''){
		// No value
		if(!$config['optional']){
			$fail('Required value "'.$header.'" not set');
		}

		// Use default value
		return $config['default'];
	}

	// Validate value
	$value	= trim($value);

	// Handle incorrect character encoding
	$encoding	= mb_detect_encoding($value, [$targetEncoding, 'ISO-8859-1'], true);
	if($encoding !== false && $encoding !== $targetEncoding){
		$value	= mb_convert_encoding($value, $targetEncoding, $encoding);
	}

	$type	= (isset($config['type']) ? $config['type'] : null);
	switch($type){
		case 'int':
			if(!is_numeric($value)){
				$fail('Value "'.$header.'" must be numeric');
			}
			$value	= (int)$value;
			break;

		case 'bool':
		case 'boolean':
			$value	= (bool)$value;
			break;

		case 'date':
		case 'datetime':
			// Handle reverse-order dates
			$value	= preg_replace_callback('~^(\d{1,2})/(\d{1,2})/(\d{4})$~', function(array $matches){
				return $matches[3].'/'.str_pad($matches[2], 2, '0', \STR_PAD_LEFT).'/'.str_pad($matches[1], 2, '0', \STR_PAD_LEFT);
			}, $value);

			try {
				$value	= new \DateTimeImmutable($value, (isset($config['timezone']) ? $config['timezone'] : null));
			} catch(\Exception $e){
				$fail('Value "'.$header.'" must be a date');
			}
			break;

		case 'time':
			try {
				$value	= new \DateTimeImmutable('2000/01/01 '.$value, (isset($config['timezone']) ? $config['timezone'] : null));
			} catch(\Exception $e){
				$fail('Value "'.$header.'" must be a time');
			}
			break;

		case 'url':
			if(!preg_match('~^https?://.+~i', $value)){
				$fail('Value "'.$header.'" must be a URL');
			}
			break;

		case 'latlng':
			$components	= explode(',', $value);
			if(count($components) !== 2){
				$fail('Value "'.$header.'" must be coordinates');
			}
			$value	= [
				'lat'	=> (float)$components[0],
				'lng'	=> (float)$components[1],
			];
			break;
	}

	return $value;
}
