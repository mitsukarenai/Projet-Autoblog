<?php


function NoProtocolSiteURL($url) {
	$protocols = array("http://", "https://");
	$siteurlnoproto = str_replace($protocols, "", $url);

    // Remove the / at the end of string
    if ( $siteurlnoproto[strlen($siteurlnoproto) - 1] == '/' )
        $siteurlnoproto = substr($siteurlnoproto, 0, -1);

    // Remove index.php/html at the end of string
    if( strpos($url, 'index.php') || strpos($url, 'index.html') ) {
    	$siteurlnoproto = preg_replace('#(.*)/index\.(html|php)$#', '$1', $siteurlnoproto);
    }

	return $siteurlnoproto;
}


function DetectRedirect($url)
{
	if(parse_url($url, PHP_URL_HOST)==FALSE) {
		//die('Not a URL');
		return array( 'error' => 'Not a URL: '. escape ($url) );
	}
	$response = get_headers($url, 1);
	if(!empty($response['Location'])) {
		$response2 = get_headers($response['Location'], 1);
		if(!empty($response2['Location'])) {
			//die('too much redirection');
			return array( 'error' => 'too much redirection: '. escape ($url) );
		}
		else { return $response['Location']; }
	}
	else {
		return $url;
	}
}

function urlToFolder($url) {
    return sha1(NoProtocolSiteURL($url));
}

function urlToFolderSlash($url) {
    return sha1(NoProtocolSiteURL($url).'/');
}

function folderExists($url) {
	return file_exists(urlToFolder($url)) || file_exists(urlToFolderSlash($url));
}

function escape($str) {
    return htmlspecialchars($str, ENT_COMPAT, 'UTF-8', false);
}

function createAutoblog($type, $sitename, $siteurl, $rssurl, $error = array()) {
    if( $type == 'generic' || empty( $type )) {
        $var = updateType( $siteurl );
        $type = $var['type'];
        if( !empty( $var['name']) ) {
            if( !stripos($siteurl, $var['name'] === false) )
        	    $sitename = ucfirst($var['name']) . ' - ' . $sitename;
        }
    }
    
	if(folderExists($siteurl)) { 
		$error[] = 'Erreur : l\'autoblog '. $sitename .' existe déjà.'; 
		return $error;
	}

	$foldername = urlToFolderSlash($siteurl);	
	
	if ( mkdir('./'. $foldername, 0755, false) ) {
        
        /** 
         * RSS
         **/
        require_once('class_rssfeed.php');
        $rss = new AutoblogRSS(RSS_FILE);
        $rss->addNewAutoblog($sitename, $foldername, $siteurl, $rssurl);
         
        $fp = fopen('./'. $foldername .'/index.php', 'w+');
        if( !fwrite($fp, "<?php require_once dirname(__DIR__) . '/autoblog.php'; ?>") )
            $error[] = "Impossible d'écrire le fichier index.php";
        fclose($fp);

        $fp = fopen('./'. $foldername .'/vvb.ini', 'w+');
        if( !fwrite($fp, '[VroumVroumBlogConfig]
SITE_TYPE="'. $type .'"
SITE_TITLE="'. $sitename .'"
SITE_DESCRIPTION="Site original : <a href=\''. $siteurl .'\'>'. $sitename .'</a>"
SITE_URL="'. $siteurl .'"
FEED_URL="'. $rssurl .'"
ARTICLES_PER_PAGE="'. getArticlesPerPage( $type ) .'"
UPDATE_INTERVAL="'. getInterval( $type ) .'"
UPDATE_TIMEOUT="'. getTimeout( $type ) .'"') )
            $error[] = "Impossible d'écrire le fichier vvb.ini";
        fclose($fp);
    }
    else
        $error[] = "Impossible de créer le répertoire.";

    return $error;
}

function getArticlesPerPage( $type ) {
	switch( $type ) {
		case 'microblog':
			return 20;
		case 'shaarli':
			return 20;
		default:
			return 5;
	}
}

function getInterval( $type ) {
	switch( $type ) {
		case 'microblog':
			return 300;
		case 'shaarli':
			return 1800;
		default:
			return 3600;
	}
}

function getTimeout( $type ) {
	switch( $type ) {
		case 'microblog':
			return 30;
		case 'shaarli':
			return 30;
		default:
			return 30;
	}
}

function updateType($siteurl) {
    if( strpos($siteurl, 'twitter.com') !== FALSE ) {
        return array('type' => 'twitter', 'name' => 'twitter');
    }
    elseif ( strpos( $siteurl, 'identi.ca') !== FALSE ) {
        return array('type' => 'identica', 'name' => 'identica');
    }
    elseif( strpos( $siteurl, 'shaarli' ) !== FALSE ) { 
        return array('type' => 'shaarli', 'name' => 'shaarli');
    }
    else
        return array('type' => 'generic', 'name' => '');
}

function debug($data)
{
	echo '<pre>';
	var_dump($data);
	echo '</pre>';
}

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
?>
