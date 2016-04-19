<?php
header('HTTP/1.0 404 Not Found', null, 404);	// Ensure 404 error sent

ob_start();	// Capture for IE (see bottom)

$ROOT=__DIR__;
require_once($ROOT.'/_inc/lib.php');

$pageTitle	= 'Page Not Found';
$pageID		= '404';

include($ROOT.'/_inc/layout/header.php');
?>

<main id="main" role="main">
	<h1>Page Not Found</h1>
	<p>Sorry, but the page you were trying to view does not exist.</p>
	<form id="404-search" class="site-search" method="get" action="https://www.google.com.au/search" onsubmit="notFoundForm();">
		<?php
		// Prefill with search based on URL
		$requestURI	= $_SERVER['REQUEST_URI'];
		if(strpos($requestURI, '?') !== false){
			// Strip querystring from request URI
			$requestURI	= substr($requestURI, 0, strpos($requestURI, '?'));
		}
		$defaultSearchString	= trim(preg_replace('~(-|/|\.php)+~', ' ', $requestURI), ' ');
		?>
		<span class="input">
			<label for="input-404-search">Search:</label>
			<input id="input-404-search" type="search" name="q" value="site:<?=e($_SERVER['SERVER_NAME']);?> <?=e($defaultSearchString);?>" />
		</span>
		<script><?php ob_start();?>
		window.notFoundForm	= (function(){
			var input	= document.getElementById('input-404-search'),
				prefix	= "site:<?=preg_replace('~[^0-9a-zA-Z\.\:]~', '', e($_SERVER['SERVER_NAME']));?> ";
			input.value = input.value.substr(prefix.length);

			return function(){
				var originalValue	= input.value;
				input.value	= prefix+originalValue;
				window.setTimeout(function(){
					input.value	= originalValue;
				}, 0);
			};
		}());
		<?=minifyJS(ob_get_clean());?></script>
		<button type="submit">Search</button>
	</form>
	<p><a href="/">Return to homepage</a></p>
</main>

<?php
include($ROOT.'/_inc/layout/footer.php');

$pageSize = strlen(ob_get_flush());
if($pageSize < 512){
	// IE needs 512+ bytes - pad page if necessary (http://j.mp/1Erzsvt)
	echo '<!-- '.str_repeat('.', 512 - $pageSize).' -->';
}
