<?php
header($_SERVER['SERVER_PROTOCOL'].' 404 Not Found', null, 404);	// Ensure 404 error sent

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
	<p><a href="/">Return to homepage</a></p>
</main>

<?php
include($ROOT.'/_inc/layout/footer.php');

$pageSize = strlen(ob_get_flush());
if($pageSize < 512){
	// IE needs 512+ bytes - pad page if necessary (http://j.mp/1Erzsvt)
	echo '<!-- '.str_repeat('.', 512 - $pageSize).' -->';
}
