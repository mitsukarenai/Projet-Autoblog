<?php

function NoProtocolSiteURL($url) {
	$siteurlnoprototypes = array("http://", "https://");
	$siteurlnoproto = str_replace($siteurlnoprototypes, "", $url);
    
    // Remove the / at the end of string
    if ( $siteurlnoproto[strlen($siteurlnoproto) - 1] == '/' )
        $siteurlnoproto = substr($siteurlnoproto, 0, -1);
	return $siteurlnoproto;
}


function DetectRedirect($url)
{
	$response = get_headers($url, 1);
	if(!empty($response['Location'])) {
		$response2 = get_headers($response['Location'], 1);
		if(!empty($response2['Location'])) {
			die('too much redirection');
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

function urlToFolderWithTrailingSlash($url) {
    return sha1(NoProtocolSiteURL($url).'/');
}

function escape($str) {
    return htmlspecialchars($str, ENT_COMPAT, 'UTF-8', false);
}

function createAutoblog($type, $sitename, $siteurl, $rssurl, $error = array()) {
    if( $type == 'generic' || empty( $type )) {
        $var = updateType( $siteurl );
        $type = $var['type'];
        if( !empty( $var['name']) )
        	$sitename = ucfirst($var['name']) . ' - ' . $sitename;
    }
    
	$foldername = urlToFolder($siteurl);
	if(file_exists($foldername)) { 
		$error[] = 'Erreur: l\'autoblog <a href="./'.$foldername.'/">'. $sitename .'</a> existe déjà.'; 
		return $error;
	}

	$foldername = urlToFolderWithTrailingSlash($siteurl);
	if(file_exists($foldername)) { 
		$error[] = 'Erreur: l\'autoblog <a href="./'.$foldername.'/">'. $sitename .'</a> existe déjà.'; 
		return $error;
	}
	
	if ( mkdir('./'. $foldername, 0755, false) ) {
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
ARTICLES_PER_PAGE="5"
UPDATE_INTERVAL="'. getInterval( $type ) .'"
UPDATE_TIMEOUT="30"') )
            $error[] = "Impossible d'écrire le fichier vvb.ini";
        fclose($fp);
    }
    else
        $error[] = "Impossible de créer le répertoire.";

    return $error;
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

function updateType($siteurl) {
    if( strpos($siteurl, 'twitter.com') !== FALSE ) {
        return array('type' => 'microblog', 'name' => 'twitter');
    }
    elseif ( strpos( $siteurl, 'identi.ca') !== FALSE ) {
        return array('type' => 'microblog', 'name' => 'identica');
    }
    elseif( strpos( $siteurl, 'shaarli' ) !== FALSE ) { 
        return array('type' => 'shaarli', 'name' => 'shaarli');
    }
    else
        return array('type' => 'generic', 'name' => '');
}
?>
