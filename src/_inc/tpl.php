<?php
require_once(__DIR__.'/lib.php');
/* Templating helpers */


// Default library
/**
 * @var mixed|null $content 	The value to output
 * @var mixed|null $fallback	The default value to use if `$content` is not set
 * @var bool $discardInvalid	Whether to discard invalid characters. If false, will be replaced by invalid character marker.
 * @return string	The escaped value to output
 */
function e($content, $fallback = null, $discardInvalid = true){
	$output	= (string)fallback($content, $fallback);
	return htmlspecialchars($output, ENT_QUOTES | ENT_HTML5 | ($discardInvalid ? ENT_IGNORE : ENT_SUBSTITUTE), 'UTF-8');
}

/**
 * @param mixed|null &$value	A value to test
 * @param mixed $default		The value to return if $value is empty
 * @param bool $emptyStrings	If true, empty strings will not be substituted
 * @return mixed
 */
function fallback(&$value, $default = null, $emptyStrings = false){
	if(!isset($value)){
		return $default;
	}

	if(!$emptyStrings && is_string($value) && trim($value) === ''){
		return $default;
	}

	return $value;
}

/**
 * Generates a stylesheet `link` tag, with an IE-only version with a special querystring
 *
 * @var string $url   The URL of the stylesheet
 * @var string $extra Additional attributes for the `link` tag
 * @return string     The HTML for the `link` tags
 */
function stylesheetLink($url, $extra = ''){
	if(substr($url, 0, 5) === '/css/'){
		$url	= substr($url, 5);
	}
	$inlineKey	= $url;

	$output	= inlineAsset($inlineKey, 'css');

	// Format URL for output
	if(SKIP_CACHE){
		// Pass-through no-cache URL
		$url	.= '?__nocache';
	}
	$url = e($url);
	if(isset($output)){
		$output		= '<style>'.$output.'</style>';
	} else {
		// Cannot inline stylesheet
		$output	= <<<HTML
<link rel="stylesheet" href="/css/index.php/${url}" ${extra} />
HTML;
	}

	// Disable media-queries for old IE
	$ieExtra	= (SKIP_CACHE ? '&' : '?').'mq=0';
	return <<<HTML
<!--[if gte IE 9]><!-->${output}<!--<![endif]-->
<!--[if lt IE 9]><link rel="stylesheet" href="/css/index.php/${url}${ieExtra}" ${extra} /><![endif]-->
HTML;
}

/**
 * Generates a `script` tag, routing through the minifier
 *
 * @var string $url   The URL of the script
 * @var string $extra Additional attributes for the `script` tag
 * @return string     The HTML for the `script` tag
 */
function scriptLink($url, $extra = ''){
	if(substr($url, 0, 4) === '/js/'){
		$url	= substr($url, 4);
	}
	$inlineKey	= $url;

	if(SKIP_CACHE){
		// Pass-through no-cache URL
		$url	.= '?__nocache';
	}
	$url = e($url);

	$output	= inlineAsset($inlineKey, 'js');
	if(isset($output)){
		// Inline script
		return <<<HTML
<script>${output}</script>
HTML;
	} else {
		return <<<HTML
<script src="/js/index.php/${url}" ${extra}></script>
HTML;
	}
}

/**
 * Loads the specified asset's rendered content if cached and below a maximum character length
 *
 * @param string $url		The requested URL
 * @param string $type		The asset type (one of [css,js])
 * @param int $threshold	The maximum number of characters allowed for inlining
 * @return null|string					The asset's content, or null if unavailable
 * @throws \InvalidArgumentException	An unknown $type was given
 */
function inlineAsset($url, $type, $threshold = 5000){
	if(IS_DEV){
		// No inlining on dev
		return null;
	}

	global $ROOT;

	$types	= [
		'css'	=> 'Stylesheet',
		'js'	=> 'Script',
	];
	if(!isset($types[$type])){
		throw new \InvalidArgumentException('Type must be one of ['.implode(',', array_keys($types)).']');
	}

	$file		= $types[$type];
	$class		= '\\AssetOutput\\'.$file;
	$cacheDir	= $ROOT.'/_cache/'.$type;

	/** @noinspection PhpIncludeInspection */
	require_once($ROOT.'/_inc/src/AssetOutput/'.$file.'.php');

	try {
		/** @var \AssetOutput\AssetOutput $class */
		/** @var \AssetOutput\AssetOutput $asset */
		$asset	= new $class($class::getAssetDir(), '/'.$url);
	} catch(\Exception $e){
		return null;
	}

	$asset->setCacheDir($cacheDir);
	if(!$asset->isCached()){
		// Not cached - cannot inline
		return null;
	}

	$output	= $asset->loadCachedAsset();
	if(strlen($output) >= $threshold){
		// Too large - cannot inline
		return null;
	}

	// Type-specific processing
	switch($type){
		case 'css':
			// Remove @charset
			$stripPrefix		= '@charset "utf-8";';
			$stripPrefixLength	= 17;
			if(strtolower(substr($output, 0, $stripPrefixLength)) === $stripPrefix){
				// Remove prefix
				$output	= substr($output, $stripPrefixLength);
			}
			break;
	}

	// Able to inline
	if(IS_DEBUG){
		$output	= '/* '.$url.' */'.$output;
	}
	return $output;
}

/**
 * Generates a `picture` element for a responsive image
 *
 * @param string $pathBase			The base prefixed to all paths in `$pathSizes`
 * @param string $alt				The image alt text
 * @param array $pathSizes			An array with paths relative to `$pathBase` as keys, and sizes as values (integers for width, or true for maximum image)
 * @param string|null $attributes	Additional HTML attributes for the generated `picture` element
 * @param string|null $imageSize	The size the image will be shown at (defaults to `100vw`)
 * @param string|null $default		The default image to use for unsupported browsers
 * @param bool $hasWebp				Whether a webp version should be included
 * @param bool $generateWebp		Whether to automatically generate webps when needed
 * @return string					The full `picture` element
 */
function responsiveImage($pathBase, $alt, array $pathSizes, $attributes = null, $imageSize = '100vw', $default = null, $hasWebp = true, $generateWebp = true){
	global $ROOT;

	$defaultType	= 'default';
	$sources		= [];
	$addSourceItem	= function($url, $size = null, $type = null) use(&$sources, $defaultType){
		$type = (isset($type) ? $type : $defaultType);

		if(!isset($sources[$type])){
			$sources[$type]	= [];
		}

		$sourceItem	= $url;
		if(isset($size)){
			$sourceItem	.= ' '.$size;
		}
		$sources[$type][]	= $sourceItem;
	};

	$smallestImageURL	= null;
	$smallestImageSize	= null;

	foreach($pathSizes as $url => $size){
		$jpgURL		= $pathBase.$url;
		$jpgPath	= $ROOT.$jpgURL;

		if(is_numeric($size)){
			// Generate default from image
			if(!isset($smallestImageURL) || $size < $smallestImageSize){
				$smallestImageURL	= $jpgURL;
				$smallestImageSize	= $size;
			}

			// Assume width
			$size	.= 'w';
		}
		if(isset($size) && !preg_match('~^\d+[w|h]$~', $size)){
			throw new \UnexpectedValueException('Size for image "'.$url.'" is invald');
		}

		// Add default format source
		$addSourceItem($jpgURL, $size);

		if(!$hasWebp){
			// Do not add webp
			continue;
		}

		$webpType	= 'image/webp';
		$webpURL	= preg_replace('~\.jpe?g$~i', '.webp', $jpgURL);
		$webpPath	= $ROOT.$webpURL;

		$shouldGenerate	=
			IS_DEV
			&& file_exists($jpgPath)
			&& (
				!file_exists($webpPath)							// ...and no webp exists yet - generate
				|| filemtime($webpPath) < filemtime($jpgPath)	// ...or webp is expired - regenerate
			);

		if($generateWebp && ($shouldGenerate || SKIP_CACHE)){
			// Generate webp
			require_once(__DIR__.'/src/WebpConverter.php');
			try {
				(new WebpConverter())->convert($jpgPath, $webpPath);
			} catch(\Exception $e){
				if(IS_DEV || IS_DEBUG){
					throw new \RuntimeException('Webp conversion failed', 0, $e);
				}

				// Suppress error on other environments
			}
		}

		if(file_exists($webpPath)){
			// Add source item
			$addSourceItem($webpURL, $size, $webpType);
		}
	}

	if(!isset($default)){
		if(!isset($smallestImageURL)){
			throw new \BadMethodCallException('Default image must be set');
		}

		$default	= $smallestImageURL;
	}

	$sourceHTML	= [];
	foreach($sources as $type => $srcset){
		if($type === $defaultType){
			continue;
		}

		$srcset	= implode(', ', $srcset);

		$sourceAttrs	= [
			'type="'.e($type).'"',
			'srcset="'.e($srcset).'"',
		];
		if(isset($imageSize)){
			$sourceAttrs[]	= 'sizes="'.e($imageSize).'"';
		}

		$sourceHTML[]	= '<source '.implode(' ', $sourceAttrs).' />';
	}
	$sourceHTML	= implode("\n", $sourceHTML);

	$imageSources		= implode(', ', $sources[$defaultType]);
	$imageAttributes	= [
		'src="'.e($default).'"',
		'alt="'.e($alt).'"',
	];
	if($imageSources !== $default){
		$imageAttributes[]	= 'srcset="'.e($imageSources).'"';
	}
	if(isset($imageSize)){
		$imageAttributes[]	= 'sizes="'.e($imageSize).'"';
	}

	$html	= '<picture '.$attributes.'>'."\n"
				  .$sourceHTML."\n"
				  .'<img '.implode(' ', $imageAttributes).' />'."\n"
			  .'</picture>'."\n";
	return $html;
}

/**
 * Generates a `picture` element for a jpeg image with a webp equivalent
 *
 * @param string $path	The web path to the jpeg version
 * @param string $alt	The alt text for the image
 * @param string|null $attributes	HTML attributes for the wrapping `picture` element
 * @return string		The full HTML for the image
 * @see responsiveImage
 */
function webpImage($path, $alt, $attributes = null){
	$extension	= pathinfo($path, \PATHINFO_EXTENSION);
	$pathBase	= substr($path, 0, -(strlen($extension)+1));
	return responsiveImage($pathBase, $alt, ['.'.$extension => null], $attributes, null, $path);
}

/**
 * @param int $width			The width of the image
 * @param int $height			The height of the image
 * @param string|null $label	The label for the image
 * @param string $format		The image format ('jpg' or 'png')
 * @return string				A URL for the requested placeholder image
 */
function placeholderImage($width, $height, $label = null, $format = 'jpg'){
	$url	= '/img/index.php?placeholder='.urlencode($width).'x'.urlencode($height).'.'.urlencode($format);
	if(isset($label)){
		$url	.= '&label='.urlencode($label);
	}
	return $url;
}

/**
 * Generates a mailto anchor tag, heavily encoding the email address to be decrypted in Javascript
 *
 * @param string $address		The email address to encode
 * @param string|null $label	The text to show in the `<a>` tag
 * @param bool $showNoscript	Whether to include a `<noscript>` notice
 * @param string $attributes	Additional attributes to include in the `<a>` tag
 * @return string				The encoded `<a href="mailto:">` link
 */
function mailto($address, $label = null, $showNoscript = false, $attributes = ''){
	$encode	= function($address){
		// via stackoverflow.com/a/3005240/626682
		$address	= mb_convert_encoding($address, 'UTF-32', 'UTF-8'); // Big endian
		$encoded	= '';
		$chars	= 26;
		$offset	= 64;
		foreach (str_split($address, 4) as $c) {
			$cur = 0;
			for ($i = 0; $i < 4; $i++) {
				$cur |= ord($c[$i]) << (8 * (3 - $i));
			}
			$encoded .=  chr(rand($offset, $offset+$chars-1)+1)					// Any uppercase character -> #
						.chr(rand($offset, $offset+$chars-1)+1 + $chars + 6)	// Any lowercase character -> &
						.$cur
						.chr(rand(35, 47));	// Any non-alphanumeric character -> ;
		}

		$decodeOperations	= "replace(/[^a-zA-Z0-9]/g,';').replace(/[A-Z]/g,'&').replace(/[a-z]/g,'#')";

		$decode	= '"'.$encoded.'".'.$decodeOperations;
		return $decode;
	};

	if(isset($label)){
		$label	= '"'.e($label).'"';
	} else {
		// Use email address
		$label	= explode('?', $address, 2)[0];	// Remove querystring components
		$label	= $encode($label);
	}

	$encodedEmail	= $encode($address);

	$attributes	= addcslashes($attributes, "'/");
	$output	= <<<HTML
<script>document.write('<'+'a $attributes h'+(0 ? 'nul' : 'ref')+'="m'+'o,t,l,i,a'.split(',').reverse().join('')+':'+$encodedEmail+'">'+$label+'<\\/a>');</script>
HTML;
	if($showNoscript){
		$output	.= '<noscript><span class="noscript-notice">Javascript is required to view email addresses</span></noscript>';
	}
	return $output;
}

/**
 * @param string $phone	The unformatted phone number
 * @param bool $tel		Whether to format the number for use in a `tel:` link
 * @param bool $escape	Whether to HTML escape the outputted phone number
 * @return string		The formatted phone number
 */
function formatPhone($phone, $tel = false, $escape = true){
	$phone	= preg_replace('~[^\d\+]~', '', $phone);
	if($phone === ''){
		return '';
	}

	// Strip non-numeric characters
	if($tel){
		$patterns	= [
			// Australian numbers
			'0(\d+)'	=> '+61$1',
		];
	} else {
		$patterns	= [
			// Australian mobiles
			'(04\d{2})(\d{3})(\d{3})'	=> '$1 $2 $3',
			// Australian landlines
			'(0\d)(\d{4})(\d{4})'		=> '($1) $2 $3',
			// Australian landlines (no area code)
			'(\d{4})(\d{4})'			=> '$1 $2',
			// 1X00 numbers
			'(1\d00)(\d{3})(\d{3})'		=> '$1 $2 $3',
			// US numbers
			'(\+1)?(\d{3})(\d{3})(\d{4})'	=> '+1 ($2) $3-$4',
			// Malaysia numbers
			'\+?(60)(\d)(\d{3})(\d{4})'	=> '+$1 $2 $3 $4',
			// 13XXXX numbers
			'(13\d)(\d{3})'				=> '$1 $2',
		];
	}

	$processed	= $phone;	// Fallback to phone if no match
	foreach($patterns as $pattern => $replacement){
		$result	= preg_replace('~^'.$pattern.'$~', $replacement, $phone, -1, $count);
		if($count){
			// Match
			$processed	= $result;
			break;
		}
	}

	if($escape){
		// Escape characters
		$processed	= e($processed);

		if(!$tel){
			// Prevent line breaks within number
			$processed = str_replace(' ', '&nbsp;', $processed);
		}
	}

	return $processed;
}

/**
 * @param string|null $pageTitle	The title for the current page
 * @param string $siteTitle			The title for the whole site
 * @param string $separator			The separator to put between the components
 * @return string					The full page title
 */
function pageTitle(&$pageTitle, $siteTitle, $separator = '–'){
	if($siteTitle === ''){
		return e($pageTitle);
	}

	$siteTitle	= e($siteTitle);
	if(!isset($pageTitle) || $pageTitle === '' || e($pageTitle) === $siteTitle){
		return $siteTitle;
	}

	return e($pageTitle).' '.e($separator).' '.$siteTitle;
}

/**
 * @param string $pageURL		The nav item's page URL
 * @param string|null $class	The CSS class name to use if the nav item matches, or null to use the default
 * @return string				Additional classes to append to the nav item
 */
function navCurrentPageClass($pageURL, $class = null){
	if(strpos($_SERVER['REQUEST_URI'], $pageURL) !== 0){
		// Different page
		return '';
	}
	return (isset($class) ? $class : 'nav-item--current');
}

/**
 * @param string[] $content	An array of paragraphs
 * @param bool $escape		Whether to HTML escape each paragraph
 * @return string			The combined HTML paragraph output
 */
function arrayToParagraphs(array $content, $escape = true){
	$output	= '';
	foreach($content as $row){
		$row	= trim($row);
		if($row === ''){
			// Ignore empty line
			continue;
		}

		if($escape){
			$row	= e($row);
		}
		$output	.= '<p>'.$row.'</p>';
	}
	return $output;
}

/**
 * @param string $content		A string with newline paragraph separators
 * @param bool $escape			Whether to HTML escape each paragraph
 * @param bool $ignoreSingle	Whether to ignore single line breaks (only two or more will create paragraphs if true)
 * @return string				The processed HTML paragraph output
 */
function newlinesToParagraphs($content, $escape = true, $ignoreSingle = false){
	$content	= trim($content);
	if($content === ''){
		return '';
	}

	if($escape){
		$content	= e($content);
	}

	$pattern	= '~'.($ignoreSingle ? '\R\R' : '\R').'+~';
	$output		= '<p>'.preg_replace($pattern, '</p>$1<p>', $content).'</p>';
	return $output;
}

/**
 * @param string $markdown		The raw Markdown to process
 * @param bool $extraProcessing	Whether to perform additional processing on the generated HTML
 * @return string				The converted HTML
 */
function convertMarkdown($markdown, $extraProcessing = true){
	require_once(__DIR__.'/src/lib/MarkdownExtra.php');

	$output	= \Michelf\MarkdownExtra::defaultTransform($markdown);

	if($extraProcessing){
		// Make external links open in new tab
		$output	= preg_replace('~(<a\\s)([^>]*href="https?://)~i', '$1target="_blank" $2', $output);

		// Detect unlinked-links
		$output	= wrapLinks($output);
	}

	return $output;
}

/**
 * Locates links and wraps then with `a` tags.
 *
 * @param string $html	The HTML to process
 * @param string|null $attributes	Additional attributes for each link
 * @param bool $handleExisting		Whether to check for and accommodate existing links
 * @return string		The HTML with links processed
 */
function wrapLinks($html, $attributes = 'target="_blank"', $handleExisting = true){
	if($handleExisting){
		// Split on existing links
		$html	= preg_split('~(<a(?:\s[^>]+)?>(?:.*?)</a>)~i', $html, -1, \PREG_SPLIT_DELIM_CAPTURE);
		$count	= count($html);
		for($i = 0; $i < $count; $i += 2){
			// Wrap links in link-free sections
			$html[$i]	= wrapLinks($html[$i], $attributes, false);
		}
		// Combine processed and existing
		$html	= implode('', $html);

	} else {
		// Detect and wrap links
		$attributes	= (isset($attributes) ? ' '.$attributes : '');

		$pattern	= <<<'PREG'
\b	# Word boundary
(
	# Host/protocol
	(?:
		https?://
		| www\d{0,3}[.]
		| [a-z0-9.\-]+[.][a-z]{2,4}/
	)
	# URL path
	(?:
		[^ \s ( ) < > ]+
		| \(([^ \s ( ) < > ]+
		| (\([^ \s ( ) < > ]+\)))*\))+(?:\(([^\s()<>]+
		| (\([^ \s ( ) < > ]+\)))*\)
		| [^
			\s ` ! ( ) \[ \] { } ; : \ ' " . , < > ? « » “ ” ‘ ’
		  ]
	)
)
PREG;
		$html	= preg_replace_callback('~'.$pattern.'~ix', function(array $matches) use ($attributes){
			$raw		= $matches[0];

			$fullURL	= $raw;
			if(!preg_match('~^https?://~i', $fullURL)){
				// Missing protocol
				$fullURL	= 'http://'.$fullURL;
			}

			return '<a href="'.e($fullURL).'"'.$attributes.'>'.$raw.'</a>';
		}, $html);
	}

	return $html;
}

/**
 * Locates email addresses and wraps then with `mailto` links. Note existing `mailto` links will be wrapped again.
 *
 * @param string $html			The HTML to process
 * @param string $attributes	Additional attributes for each link
 * @return string				The HTML with email addresses processed
 */
function wrapEmails($html, $attributes = ''){
	$attributes	= (isset($attributes) ? ' '.$attributes : '');

	$emailPattern	= '\w[\w\._-]+@[\w\._-]+\w';

	// Links with querystrings
	$html	= preg_replace_callback('~(<|&lt;)('.$emailPattern.'(?:\?[^>\n]+)?)(>)~', function(array $matches) use ($attributes){
		return mailto($matches[2], null, false, $attributes);
	}, $html);

	// Normal links
	$html	= preg_replace_callback('~(\W)('.$emailPattern.')(\W)~', function(array $matches) use ($attributes, $html){
		return $matches[1].mailto($matches[2], null, false, $attributes).$matches[3];
	}, $html);

	return $html;
}

/**
 * @param string $content	The CSS code to minify
 * @return string			The minified CSS
 * @throws \RuntimeException	Minification failed (thrown only on dev)
 */
function minifyCSS($content){
	return $content;
	require_once(__DIR__.'/src/lib/CssMin.php');

	try {
		$result	= CssMin::minify($content);
	} catch(\Exception $e){
		if(IS_DEV){
			// Pass exception through
			throw new \RuntimeException('Minification failed', 0, $e);
		}
		return $content;
	}

	if(IS_DEV){
		// Use unminified
		return $content;
	}
	return $result;
}

/**
 * @param string $content	The Javascript code to minify
 * @return string			The minified Javascript
 * @throws \RuntimeException	Minification failed (thrown only on dev)
 */
function minifyJS($content){
	return $content;
	require_once(__DIR__.'/src/lib/JShrink.php');

	try {
		$result	= JShrink\Minifier::minify($content);
		if($result === false){
			throw new \UnexpectedValueException('Could not minify');
		}

	} catch(\Exception $e){
		if(IS_DEV){
			// Pass exception through
			throw new \RuntimeException('Minification failed', 0, $e);
		}
		return $content;
	}

	if(IS_DEV){
		// Use unminified
		return $content;
	}
	return $result;
}

/**
 * @param bool $domain	Whether to include the site's domain
 * @return string	The URL to the current page
 */
function currentURL($domain = false){
	// Remove querystring
	$result	= $_SERVER['REQUEST_URI'];
	if(($pos = strpos($result, '?')) !== false){
		$result	= substr($result, 0, $pos);
	}

	if(substr($result, -10) === '/index.php'){	// "/index.php" = 10 characters
		// Strip index
		$result	= substr($result, 0, -9);	// 9 characters - retain trailing slash
	}

	if(IS_DEBUG && !IS_DEV){
		// Retain debug flag
		$result	.= '?__debug';
	}

	if($domain){
		// Include domain
		$protocol	= (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
							? 'https'
							: 'http';
		$result	= $protocol.'://'.$_SERVER['SERVER_NAME'].$result;
	}

	return $result;
}

/**
 * @param string $url	The URL to mark the current page as for Google Analytics
 */
function setAnalyticsURL($url){
	global $analyticsURL;
	$analyticsURL	= $url;
}

/*
// Enforce `www.` prefix
$host	= strtolower($_SERVER['HTTP_HOST']);
if(!IS_DEV && !preg_match('~^www\.~', $host)){
	// Missing `www.` prefix
	$protocol	= (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
	redirect($protocol.'://www.'.$host.$_SERVER['REQUEST_URI'], 301);
}
unset($host);
// Remove unnecessary index file names
$requestURI	= preg_replace('~/(default\.asp|index\.php|index\.html?)(\?.*)?$~', '/$2', $_SERVER['REQUEST_URI'], -1, $matchesCount);
if($matchesCount > 0){
	redirect($requestURI, 301);
}
unset($requestURI, $matchesCount);
*/
