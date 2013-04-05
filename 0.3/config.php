<?php
if(!defined('ROOT_DIR'))
{
    define('ROOT_DIR', dirname($_SERVER['SCRIPT_FILENAME']));
}
define('LOCAL_URI', '');
if (!defined('RSS_FILE')) define('RSS_FILE', 'rss.xml');
if (!defined('DOC_FOLDER')) define('DOC_FOLDER', 'docs/');
date_default_timezone_set('Europe/Paris');
setlocale(LC_TIME, 'fr_FR.UTF-8', 'fr_FR', 'fr');

define( 'ALLOW_FULL_UPDATE', TRUE );
// Check new version on Github
define( 'ALLOW_CHECK_UPDATE', TRUE );
define( 'ALLOW_NEW_AUTOBLOGS', TRUE );
// If you set ALLOW_NEW_AUTOBLOGS to FALSE, the following options do not matter.
// Generic RSS
define( 'ALLOW_NEW_AUTOBLOGS_BY_LINKS', TRUE );
// Twitter, Identica, Statusnet, Shaarli
define( 'ALLOW_NEW_AUTOBLOGS_BY_SOCIAL', TRUE );
// Bookmark button
define( 'ALLOW_NEW_AUTOBLOGS_BY_BUTTON', TRUE );
// OPML file
define( 'ALLOW_NEW_AUTOBLOGS_BY_OPML_FILE', TRUE );
// OPML Link
define( 'ALLOW_NEW_AUTOBLOGS_BY_OPML_LINK', TRUE );
// XSAF
define( 'ALLOW_NEW_AUTOBLOGS_BY_XSAF', TRUE );


// URL to Twitterbridge API - Set FALSE to disable Twitter (default).
$apitwitter = FALSE;

// Logo à utiliser
$logo="./icon-logo.svg";

// Marquez ici votre propre message qui apparaîtra en bas de page.
// exemple : 
//$HTML_footer="<br/><a href='http://datalove.me/'>Love data</a><br/>Data is essential<br/>Data must flow<br/>Data must be used<br/>Data is neither good nor bad<br/>There is no illegal data<br/>Data is free<br/>Data can not be owned<br/>No man, machine or system shall interrupt the flow of data<br/>Locking data is a crime against datanity";
$HTML_footer='D\'après les premières versions de <a href="http://sebsauvage.net">SebSauvage</a> et <a href="http://bohwaz.net/">Bohwaz</a>.';

$head_title = "";

/* And now, the XSAF links to be imported, with maximal execusion time for import in second !
You should add only trusted sources. */
$autoblog_farm = array(
    'https://raw.github.com/mitsukarenai/xsaf-bootstrap/master/3.json' /*,
    'https://www.ecirtam.net/autoblogs/?export',
    'https://autoblog.suumitsu.eu/?export', */
);
?>
