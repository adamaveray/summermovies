<?php
require_once(__DIR__.'/config.php');

require_once(__DIR__.'/models/Venue.php');
require_once(__DIR__.'/models/Movie.php');


function loadData($year){
	/** @var Venue[] $venues */
	$venues	= loadCSV(DATA_DIR.'/'.$year.'/venues.csv', [
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
	$movies	= loadCSV(DATA_DIR.'/'.$year.'/movies.csv', [
		'date'		=> ['type' => 'date'],
		'venue'		=> [],
		'title'		=> [],
		'year'		=> ['optional' => true],
		'synopsis'	=> ['optional' => true],
		'url'		=> ['optional' => true, 'type' => 'url'],
		'cost'		=> ['optional' => true],
		'details'	=> ['optional' => true],
		'rating'	=> ['optional' => true],
		'poster'	=> ['optional' => true],
	], function($data) use($venues){
		// Load venue
		$venueID	= $data['venue'];
		if(!isset($venues[$venueID])){
			throw new \OutOfBoundsException('Unknown venue "'.$venueID.'"');
		}
		$data['venue']	= $venues[$venueID];

		return new Movie($data);
	});

	return [$venues, $movies];
}

// Default library
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
 * Retrieves a value from user input, ignoring empty values
 *
 * @param string $key		The key for the input
 * @param int|null $type	One of \INPUT_*
 * @return string|null		The input value, or NULL if not set
 */
function input($key, $type = null){
	if(!isset($type)){
		$type	= \INPUT_REQUEST;
	}

	if($type === \INPUT_GET){
		$container	= $_GET;
	} else if($type === \INPUT_POST){
		$container	= $_POST;
	} else if($type === \INPUT_REQUEST){
		$container	= $_REQUEST;
	} else {
		throw new \InvalidArgumentException('Unknown type "'.$type.'"');
	}

	if(!isset($container[$key]) || trim($container[$key]) === ''){
		return null;
	}
	return $container[$key];
}

/**
 * Generates a standard JSON response suitable for a basic API
 *
 * @param array $data		Additional data to be included with the response
 * @param int|null $status	A HTTP status to send with the response
 * @return string			The formatted JSON response
 */
function apiResponse(array $data, $status = null){
	header('Content-Type: text/javascript', true, $status);

	$data	= processAPIData($data);

	return json_encode(array_merge([
		'status'	=> (isset($status) ? $status : 200),
	], $data));
}

/**
 * @param mixed $data	The original API response data
 * @return mixed		Formatted data to be output in an API response
 */
function processAPIData($data){
	if(is_array($data)){
		foreach($data as $key => $item){
			$data[$key]	= processAPIData($item);
		}
	} else if($data instanceof \DateTimeInterface){
		$data	= $data->format('c');
	}
	return $data;
}

/**
 * @param string $target	The target URL to redirect to
 * @param int $status		The HTTP status code to redirect with (301: permanent, 302: temporary)
 */
function redirect($target, $status = 302){
	if((IS_DEV || IS_DEBUG) && headers_sent()){
		throw new \BadMethodCallException('Cannot redirect - headers already sent');
	}

	if((IS_DEV || IS_DEBUG) && !preg_match('~^(https?://www\.[^/]+)?/admin/~', $target)){
		echo $status.' Redirect: '.$target;
		exit;
	}

	if(!headers_sent()){
		header('Location: '.$target, true, $status);
	}

	$targetLink	= htmlspecialchars($target, \ENT_QUOTES);
	echo <<<HTML
<!doctype html><html><head><meta charset="UTF-8"><title>Redirecting</title></head><body>Redirecting&hellip; <a href="$targetLink">Click here if you are not redirected automatically</a></body></html>
HTML;
	exit;
}

/**
 * @param string $realm	A label for the restricted area
 * @param array $users	An array with usernames as keys and passwords as values
 * @return string|null	The authenticated user
 */
function authenticate($realm, array $users){
	$authenticationFailed	= function($message) use($realm){
		header('HTTP/1.0 401 Unauthorized');
		header('WWW-Authenticate: Basic realm="'.$realm.'"');
		echo $message;
		exit;
	};

	if(!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW'])){
		$authenticationFailed('Authentication required');
		return null;
	}

	$user	= $_SERVER['PHP_AUTH_USER'];
	if(!isset($users[$user])){
		$authenticationFailed('Authentication failed');
		return null;
	}

	if($users[$user] !== $_SERVER['PHP_AUTH_PW']){
		$authenticationFailed('Authentication failed');
		return null;
	}

	// Authenticated
	return $user;
}

/**
 * Triggers a download of a file for the client
 * @param string $path				The path to the file
 * @param string|null $downloadName	The name to send the file as
 */
function sendFile($path, $downloadName = null){
	$downloadName	= fallback($downloadName, basename($path));

	if(headers_sent()){
		throw new \BadMethodCallException('Cannot trigger download - headers already sent');
	}

	// Set download headers
	header('Content-Description: File Transfer');
	header('Content-Type: application/octet-stream');
	header('Content-Disposition: attachment; filename="'.$downloadName.'"');

	// Prevent caching
	header('Expires: 0');
	header('Cache-Control: must-revalidate');
	header('Pragma: public');
	header('Content-Length: '.filesize($path));

	// Send file
	readfile($path);
	exit;
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

/**
 * @param string $path
 * @param array $row
 */
function appendCSVRow($path, array $row){
	$row	= serialiseCSVRow($row);

	// Update CSV
	$handle	= fopen($path, 'a+');
	fseek($handle, -1, \SEEK_END);
	$lastChar	= fread($handle, 1);
	if($lastChar !== "\n"){
		// Missing newline
		fwrite($handle, "\n");
	}
	fputcsv($handle, $row);
	fclose($handle);
}

/**
 * @param array $row
 * @return array
 */
function serialiseCSVRow(array $row){
	foreach($row as $key => $value){
		if($value === null){
			// Blank cell for null values
			$value	= '';
		} else if(is_bool($value)){
			$value	= $value ? '1' : '';
		} else if($value instanceof \DateTimeInterface){
			$value	= $value->format('c');
		}

		// Quote string
		if(strpos($value, '"') !== false){
			$value	= '"'.str_replace('"', '""', $value).'"';
		}

		$row[$key]	= $value;
	}

	return $row;
}

/**
 * @param string $key			A unique key for the cache value
 * @param string $expiry		The time the cache is valid for, as a string (e.g. "12 hours"), or the path to a file to invalidate on changes
 * @param callable $function	A callback to invoke to generate the cached value
 * @param bool $raw				Whether the data is a raw string
 * @return mixed				The value, either from the cache or generated fresh
 */
function simpleCache($key, $expiry, callable $function, $raw = false){
	if(IS_DEV){
		// Skip cache
		return $function();
	}

	$extension	= pathinfo($key, \PATHINFO_EXTENSION);
	$path		= CACHE_DIR.'/data/'.sha1($key).($extension ? '.'.$extension : '');

	$output		= null;
	$cacheHit	= false;

	// Check cache
	$nowTimestamp	= time();
	$cacheExists	= (!SKIP_CACHE && file_exists($path));
	$cacheTimestamp	= $cacheExists ? filemtime($path) : 0;
	$cacheIsValid	= false;
	if(substr($expiry, 0, 1) === '/' || substr($expiry, 1, 2) === ':\\'){
		// Path - use timestamp
		if(file_exists($expiry)){
			$cacheIsValid	= filemtime($expiry) < $cacheTimestamp;
		}
	} else {
		// Time interval
		$expiry	= new \DateTimeImmutable('+'.ltrim($expiry, '+'));
		$expirySeconds	= $expiry->getTimestamp() - $nowTimestamp;
		$cacheIsValid	= ($cacheTimestamp+$expirySeconds >= $nowTimestamp);
	}

	if(!SKIP_CACHE && $cacheIsValid){
		// Cache valid
		$cacheHit	= true;
		$output		= file_get_contents($path);
		if(!$raw){
			$output	= @unserialize($output);
			if($output === false){
				// Unserializing failed - regenerate cache
				$cacheHit	= false;
			}
		}
	}

	if(!$cacheHit){
		// Not cached or expired - regenerate
		$output	= $function();

		// Format for cache
		$cacheValue	= $output;
		if(!$raw){
			$cacheValue	= serialize($cacheValue);
		} else if(!is_string($cacheValue)){
			throw new \UnexpectedValueException('Non-string value attempting to be cached ('.$key.')');
		}

		// Save to cache
		$directory	= dirname($path);
		if(!is_dir($directory)){
			mkdir($directory, 0766, true);
		} else if(!is_writeable($directory)){
			chmod($directory, 0766);
		}
		file_put_contents($path, $cacheValue);
	}

	return $output;
}

/**
 * @param \DateTimeInterface|string $date	The date to check
 * @param bool $includeToday				Whether today should count as being before
 * @return bool								Whether it is currently before the given date
 */
function isBeforeDate($date, $includeToday = true){
	if(!$date instanceof \DateTimeInterface){
		$date	= new \DateTimeImmutable($date);
	}

	$now	= new \DateTimeImmutable(($includeToday ? 'yesterday' : 'today').'23:59:59');
	return $date > $now;
}

/**
 * @param string $message		The message to send
 * @param string|null $color	One of ['yellow','green','red','purple','gray','random']
 * @param bool $notify			Whether to notify users of message
 * @param bool $isHTML			Whether the content is HTML instead of plain
 * @param string|null $name		The message author's name to display
 * @param string|null $room		A HipChat room ID
 * @param string|null $token	A HipChat API token
 */
function notifyHipchat($message, $color = null, $notify = false, $isHTML = false, $name = null, $room = null, $token = null){
	$room	= isset($room) ? $room : HIPCHAT_ROOM;
	$token	= isset($token) ? $token : HIPCHAT_TOKEN;

	$target	= 'https://walkerweb.hipchat.com/v2/room/'.urlencode($room).'/notification?auth_token='.urlencode($token);
	$data	= [
		'message'			=> $message,
		'notify'			=> $notify,
		'message_format'	=> ($isHTML ? 'html' : 'text'),
	];
	if(isset($color)){
		$data['color']	= $color;
	}
	if(isset($name)){
		$data['from']	= $name;
	}

	$data	= json_encode($data);
	$curl	= curl_init($target);
	curl_setopt_array($curl, [
		\CURLOPT_POST		=> true,
		\CURLOPT_POSTFIELDS	=> $data,
		\CURLOPT_HTTPHEADER	=> [
			'Content-Type: application/json',
			'Content-Length: '.strlen($data),
		],
	]);

	// Ignore any output
	ob_start();
	curl_exec($curl);
	ob_end_clean();

	curl_close($curl);
}

/**
 * Notifies of an error to alert services
 *
 * @param string $message	The error debug message
 * @param array $server		The values from $_SERVER
 */
function reportError($message, array $server = null){
	$notification	= '<code>'.$message.'</code>';

	if(isset($server)){
		// Include request details
		$fullRequest	= (isset($server['HTTPS']) ? 'https' : 'http').'://'.$server['HTTP_HOST'].$server['REQUEST_URI'];
		if($server['REQUEST_METHOD'] !== 'GET'){
			$fullRequest	.= ' ['.$server['REQUEST_METHOD'].']';
		}

		$notification	= '<a href="'.htmlspecialchars($fullRequest).'">'.htmlspecialchars($fullRequest).'</a>'
						   .'<br>'.$notification;
	}

	notifyHipchat($notification, 'red', true, true, 'Errors');
}

// Capture output
ob_start();

// Handle errors
$__errorOutput	= function($debug, callable $detailedDebug){
	ob_end_clean();

	if(IS_DEBUG){
		// Output detailed debug feedback
		if(isset($detailedDebug)){
			$debug	= $detailedDebug($debug);
		}

		if(!headers_sent()){
			header('Content-Type: text/plain');
		}
		echo $debug;
		exit;
	}

	// Notify of error
	reportError($debug, $_SERVER);

	// Display friendly error page
	include(__DIR__.'/../500.php');
	exit;
};
error_reporting(IS_DEV ? E_ALL : (E_ALL & ~E_NOTICE & ~E_STRICT));
$__errorHandler	= function($errorType, $errorMessage, $errorFile, $errorLine) use($__errorOutput){
	if(error_reporting() === 0){
		// Ignore supressed error
		return;
	}

	// Build debug message
	$errorTypes	= [
		\E_ERROR				=> 'E_ERROR',
		\E_WARNING				=> 'E_WARNING',
		\E_PARSE				=> 'E_PARSE',
		\E_NOTICE				=> 'E_NOTICE',
		\E_CORE_ERROR			=> 'E_CORE_ERROR',
		\E_CORE_WARNING			=> 'E_CORE_WARNING',
		\E_COMPILE_ERROR		=> 'E_COMPILE_ERROR',
		\E_USER_ERROR			=> 'E_USER_ERROR',
		\E_USER_WARNING			=> 'E_USER_WARNING',
		\E_USER_NOTICE			=> 'E_USER_NOTICE',
		\E_STRICT				=> 'E_STRICT',
		\E_RECOVERABLE_ERROR	=> 'E_RECOVERABLE_ERROR',
		\E_DEPRECATED			=> 'E_DEPRECATED',
	];
	$errorLabel	= (isset($errorTypes[$errorType]) ? $errorTypes[$errorType] : 'Unknown error');

	$debug		= $errorLabel.': '.$errorMessage.' in '.$errorFile.' on line '.$errorLine;
	$__errorOutput($debug, function($debug){
		$level	= 0;
		foreach(array_reverse(debug_backtrace()) as $item){
			$debug	.= "\n".'#'.$level.' '.(isset($item['file']) ? $item['file'] : '<unknown file>')
					   .' '.(isset($item['line']) ? 'on line '.$item['line'] : '<unknown line>')
					   .': '.$item['function'].'()';
			$level++;
		}
		return $debug;
	});
};
set_error_handler($__errorHandler, \E_ALL);
set_exception_handler(function($exception) use($__errorOutput){
	/** @var \Exception $exception */
	$debug	= '\\'.get_class($exception).' "'.$exception->getMessage().'" thrown on line '.$exception->getLine().' in '.$exception->getFile();
	$__errorOutput($debug, function($debug) use($exception){
		return $debug."\n".$exception->getTraceAsString();
	});
});
register_shutdown_function(function(){
	$error = error_get_last();
	if(isset($error) && $error['type'] === \E_ERROR){
		// Fatal error
		global $__errorHandler;
		$__errorHandler($error['type'], $error['message'], $error['file'], $error['line']);
	}
});
