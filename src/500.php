<?php
$protocol	= (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.1');
header($protocol.' 500 Server Error', null, 404);	// Ensure 500 error sent

ob_start();	// Capture for IE (see bottom)

$ROOT=__DIR__.'/../src';
require_once($ROOT.'/_inc/lib.php');

$pageTitle	= 'Error';
$pageID		= '500';

include($ROOT.'/_inc/layout/header.php');
?>

<main id="main" role="main">
	<h1>Something Went Wrong</h1>
	<p>Sorry, there was an error loading this page. Please try again, or <a href="/">return to the homepage</a>.</p>
</main>

<?php
include($ROOT.'/_inc/layout/footer.php');

$pageSize = strlen(ob_get_flush());
if($pageSize < 512){
	// IE needs 512+ bytes - pad page if necessary (http://j.mp/1Erzsvt)
	echo '<!-- '.str_repeat('.', 512 - $pageSize).' -->';
}
