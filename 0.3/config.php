<?php
if(!defined('ROOT_DIR'))
{
    define('ROOT_DIR', dirname($_SERVER['SCRIPT_FILENAME']));
}
define('LOCAL_URI', '');
date_default_timezone_set('Europe/Paris');
setlocale(LC_TIME, 'fr_FR.UTF-8', 'fr_FR', 'fr');

function __($str)
{
    switch ($str)
    {
        case 'Search':
            return 'Recherche';
        case 'Update':
            return 'Mise à jour';
        case 'Updating database... Please wait.':
            return 'Mise à jour de la base de données, veuillez patienter...';
        case '<b>%d</b> results for <i>%s</i>':
            return '<b>%d</b> résultats pour la recherche <i>%s</i>';
        case 'Not Found':
            return 'Introuvable';
        case 'Article not found.':
            return 'Cet article n\'a pas été trouvé.';
        case 'Older':
            return 'Plus anciens';
        case 'Newer':
            return 'Plus récents';
        case 'RSS Feed':
            return 'Flux RSS';
        case 'Update complete!':
            return 'Mise à jour terminée !';
        case 'Click here to reload this webpage.':
            return 'Cliquez ici pour recharger cette page.';
        case 'Source:':
            return 'Source :';
        case '_date_format':
            return '%A %e %B %Y à %H:%M';
        case 'configuration':
        case 'articles':
            return $str;
        case 'Media export':
            return 'Export fichiers media';
        default:
            return $str;
    }
}

define( 'ALLOW_NEW_AUTOBLOGS', TRUE );
// If you set ALLOW_NEW_AUTOBLOGS to FALSE, the following options do not matter.
// Generic RSS
define( 'ALLOW_NEW_AUTOBLOGS_BY_LINKS', TRUE );
// Twitter, Identica, Statusnet, Shaarli
define( 'ALLOW_NEW_AUTOBLOGS_BY_SOCIAL', TRUE );
// Bookmark button
define( 'ALLOW_NEW_AUTOBLOGS_BY_BUTTON', TRUE );
// OPML file
define( 'ALLOW_NEW_AUTOBLOGS_BY_OPML', TRUE );

define( 'ALLOW_FULL_UPDATE', TRUE );


// Logo à utiliser
$logo="./icon-logo.svg";

// Marquez ici votre propre message qui apparaîtra en bas de page.
// exemple : 
//$HTML_footer="<br/><a href='http://datalove.me/'>Love data</a><br/>Data is essential<br/>Data must flow<br/>Data must be used<br/>Data is neither good nor bad<br/>There is no illegal data<br/>Data is free<br/>Data can not be owned<br/>No man, machine or system shall interrupt the flow of data<br/>Locking data is a crime against datanity";
$HTML_footer='D\'après les premières versions de <a href="http://sebsauvage.net">SebSauvage</a> et <a href="http://bohwaz.net/">Bohwaz</a>.';

$head_title = "";
?>
