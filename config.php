<?php
define('ROOT_DIR', dirname($_SERVER['SCRIPT_FILENAME']));
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
    }
}

?>
