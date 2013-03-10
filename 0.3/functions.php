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

function debug($data)
{
	if(is_array($data))
	{
		echo '<p>Array <br/>{<br/>';
		foreach ( $data AS $Key => $Element )
		{
			echo '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;['. $Key .'] =>';
			debug($Element);
		}
		echo '}</p>';
	}
	else if(is_bool($data))
	{
		if($data === 1)
			echo 'true<br/>';
		else
			echo 'false<br/>';
	}	
	else
		echo $data.'<br />';
}
?>
