<?php

/**
 * DO NOT EDIT THESE LINES
 * You can override these options by setting them in config.php
 **/
if(!defined('ROOT_DIR'))
{
    define('ROOT_DIR', dirname($_SERVER['SCRIPT_FILENAME']));
}
define('LOCAL_URI', '');
if (!defined('AUTOBLOGS_FOLDER')) define('AUTOBLOGS_FOLDER', './autoblogs/');
if (!defined('DOC_FOLDER')) define('DOC_FOLDER', './docs/');
if (!defined('RESOURCES_FOLDER')) define('RESOURCES_FOLDER', './resources/');
if (!defined('RSS_FILE')) define('RSS_FILE', RESOURCES_FOLDER.'rss.xml');
date_default_timezone_set('Europe/Paris');
setlocale(LC_TIME, 'fr_FR.UTF-8', 'fr_FR', 'fr');

if( !defined('ALLOW_FULL_UPDATE')) define( 'ALLOW_FULL_UPDATE', TRUE );
if( !defined('ALLOW_CHECK_UPDATE')) define( 'ALLOW_CHECK_UPDATE', TRUE );

// If you set ALLOW_NEW_AUTOBLOGS to FALSE, the following options do not matter.
if( !defined('ALLOW_NEW_AUTOBLOGS')) define( 'ALLOW_NEW_AUTOBLOGS', TRUE );
if( !defined('ALLOW_NEW_AUTOBLOGS_BY_LINKS')) define( 'ALLOW_NEW_AUTOBLOGS_BY_LINKS', TRUE );
if( !defined('ALLOW_NEW_AUTOBLOGS_BY_SOCIAL')) define( 'ALLOW_NEW_AUTOBLOGS_BY_SOCIAL', TRUE );
if( !defined('ALLOW_NEW_AUTOBLOGS_BY_BUTTON')) define( 'ALLOW_NEW_AUTOBLOGS_BY_BUTTON', TRUE );
if( !defined('ALLOW_NEW_AUTOBLOGS_BY_OPML_FILE')) define( 'ALLOW_NEW_AUTOBLOGS_BY_OPML_FILE', TRUE );
if( !defined('ALLOW_NEW_AUTOBLOGS_BY_OPML_LINK')) define( 'ALLOW_NEW_AUTOBLOGS_BY_OPML_LINK', TRUE );
if( !defined('ALLOW_NEW_AUTOBLOGS_BY_XSAF')) define( 'ALLOW_NEW_AUTOBLOGS_BY_XSAF', TRUE );

// More about TwitterBridge : https://github.com/mitsukarenai/twitterbridge
if( !defined('API_TWITTER')) define( 'API_TWITTER', FALSE );

if( !defined('LOGO')) define( 'LOGO', 'icon-logo.svg' );
if( !defined('HEAD_TITLE')) define( 'HEAD_TITLE', '');
if( !defined('FOOTER')) define( 'FOOTER', 'D\'après les premières versions de <a href="http://sebsauvage.net">SebSauvage</a> et <a href="http://bohwaz.net/">Bohwaz</a>.');

/**
 * Functions
 **/
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
		throw new Exception('Not a URL: '. escape ($url) );
	}
    
    try { $response = get_headers($url, 1); }
    catch (Exception $e) { throw new Exception('RSS URL unreachable: '. escape($url) ); } 
	if(!empty($response['Location'])) {
        try { $response2 = get_headers($response['Location'], 1); }
        catch (Exception $e) { throw new Exception('RSS URL unreachable: '. escape($url) ); } 
        
		if(!empty($response2['Location'])) {
			throw new Exception('Too much redirection: '. escape ($url) );
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
	return file_exists(AUTOBLOGS_FOLDER . urlToFolder($url)) || file_exists(AUTOBLOGS_FOLDER . urlToFolderSlash($url));
}

function escape($str) {
    return htmlspecialchars($str, ENT_COMPAT, 'UTF-8', false);
}

function createAutoblog($type, $sitename, $siteurl, $rssurl) {
    if( $type == 'generic' || empty( $type )) {
        $var = updateType( $siteurl );
        $type = $var['type'];
        if( !empty( $var['name']) ) {
            if( !stripos($siteurl, $var['name'] === false) )
        	    $sitename = ucfirst($var['name']) . ' - ' . $sitename;
        }
    }
    
	if(folderExists($siteurl)) { 
		throw new Exception('Erreur : l\'autoblog '. $sitename .' existe déjà.');
	}

	$foldername = AUTOBLOGS_FOLDER . urlToFolderSlash($siteurl);	
	
	if ( mkdir($foldername, 0755, false) ) {
        
        /** 
         * RSS
         */
        try { // à déplacer après la tentative de création de l'autoblog crée avec succès ?
            require_once('class_rssfeed.php');
            $rss = new AutoblogRSS(RSS_FILE);
            $rss->addNewAutoblog($sitename, $foldername, $siteurl, $rssurl);
        }
        catch (Exception $e) {
            ; // DO NOTHING
        }
         
        $fp = fopen($foldername .'/index.php', 'w+');
        if( !fwrite($fp, "<?php require_once '../autoblog.php'; ?>") )
        	throw new Exception('Impossible d\'écrire le fichier index.php');
        fclose($fp);

        $fp = fopen($foldername .'/vvb.ini', 'w+');
        if( !fwrite($fp, '[VroumVroumBlogConfig]
SITE_TYPE="'. $type .'"
SITE_TITLE="'. $sitename .'"
SITE_DESCRIPTION="Site original : <a href=\''. $siteurl .'\'>'. $sitename .'</a>"
SITE_URL="'. $siteurl .'"
FEED_URL="'. $rssurl .'"
ARTICLES_PER_PAGE="'. getArticlesPerPage( $type ) .'"
UPDATE_INTERVAL="'. getInterval( $type ) .'"
UPDATE_TIMEOUT="'. getTimeout( $type ) .'"') )
        	throw new Exception('Impossible d\'écrire le fichier vvb.ini');
        fclose($fp);
    }
    else
    	throw new Exception('Impossible de créer le répertoire.');

    /* @Mitsu: Il faudrait remonter les erreurs d'I/O */
	/* Comme ça ? :) */
	if(updateXML('new_autoblog_added', 'new', $foldername, $sitename, $siteurl, $rssurl) === FALSE)
		{ throw new Exception('Impossible d\'écrire le fichier rss.json'); }
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
        case 'ATOM Feed':
            return 'Flux ATOM';
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

function updateXML($status, $response_code, $autoblog_url, $autoblog_title, $autoblog_sourceurl, $autoblog_sourcefeed)
{
$json = json_decode(file_get_contents(RESOURCES_FOLDER.'rss.json'), true);
$json[] = array(
	'timestamp'=>time(),
	'autoblog_url'=>$autoblog_url,
	'autoblog_title'=>$autoblog_title,
	'autoblog_sourceurl'=>$autoblog_sourceurl,
	'autoblog_sourcefeed'=>$autoblog_sourcefeed,
	'status'=>$status,
	'response_code'=>$response_code
	);
if(file_put_contents(RESOURCES_FOLDER.'rss.json', json_encode($json), LOCK_EX) === FALSE)
	{ return FALSE; }
	else { return TRUE; }
}

function displayXMLstatus_tmp($status, $response_code, $autoblog_url, $autoblog_title, $autoblog_sourceurl, $autoblog_sourcefeed) {
    switch ($status)
	{
	case 'unavailable':
		return 'Autoblog "'.$autoblog_title.'": site distant inaccessible (code '.$response_code.')<br>Autoblog: <a href="'. serverUrl(false).AUTOBLOGS_FOLDER.$autoblog_url.'">'.$autoblog_title.'</a><br>Site: <a href="'. $autoblog_sourceurl .'">'. $autoblog_sourceurl .'</a><br>RSS: <a href="'.$autoblog_sourcefeed.'">'.$autoblog_sourcefeed.'</a>';
	case 'moved':
		return 'Autoblog "'.$autoblog_title.'": site distant redirigé (code '.$response_code.')<br>Autoblog: <a href="'. serverUrl(false).AUTOBLOGS_FOLDER.$autoblog_url.'">'.$autoblog_title.'</a><br>Site: <a href="'. $autoblog_sourceurl .'">'. $autoblog_sourceurl .'</a><br>RSS: <a href="'.$autoblog_sourcefeed.'">'.$autoblog_sourcefeed.'</a>';
	case 'not_found':
		return 'Autoblog "'.$autoblog_title.'": site distant introuvable (code '.$response_code.')<br>Autoblog: <a href="'. serverUrl(false).AUTOBLOGS_FOLDER.$autoblog_url.'">'.$autoblog_title.'</a><br>Site: <a href="'. $autoblog_sourceurl .'">'. $autoblog_sourceurl .'</a><br>RSS: <a href="'.$autoblog_sourcefeed.'">'.$autoblog_sourcefeed.'</a>';
	case 'remote_error':
		return 'Autoblog "'.$autoblog_title.'": site distant a problème serveur (code '.$response_code.')<br>Autoblog: <a href="'. serverUrl(false).AUTOBLOGS_FOLDER.$autoblog_url.'">'.$autoblog_title.'</a><br>Site: <a href="'. $autoblog_sourceurl .'">'. $autoblog_sourceurl .'</a><br>RSS: <a href="'.$autoblog_sourcefeed.'">'.$autoblog_sourcefeed.'</a>';
	case 'available':
		return 'Autoblog "'.$autoblog_title.'": site distant à nouveau opérationnel (code '.$response_code.')<br>Autoblog: <a href="'. serverUrl(false).AUTOBLOGS_FOLDER.$autoblog_url.'">'.$autoblog_title.'</a><br>Site: <a href="'. $autoblog_sourceurl .'">'. $autoblog_sourceurl .'</a><br>RSS: <a href="'.$autoblog_sourcefeed.'">'.$autoblog_sourcefeed.'</a>';
	case 'new_autoblog_added':
		return 'Autoblog "'.$autoblog_title.'" ajouté (code '.$response_code.')<br>Autoblog: <a href="'. serverUrl(false).AUTOBLOGS_FOLDER.$autoblog_url.'">'.$autoblog_title.'</a><br>Site: <a href="'. $autoblog_sourceurl .'">'. $autoblog_sourceurl .'</a><br>RSS: <a href="'.$autoblog_sourcefeed.'">'.$autoblog_sourcefeed.'</a>';
	}
}

function displayXML_tmp() {
header('Content-type: application/rss+xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8" ?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom"><channel><link>'.serverUrl(true).'</link>';
echo '<atom:link href="'.serverUrl(false) . '/?rss_tmp" rel="self" type="application/rss+xml"/><title>Projet Autoblog'. ((strlen(HEAD_TITLE)>0) ? ' | '. HEAD_TITLE : '').'</title><description>'.serverUrl(true),"Projet Autoblog - RSS : Ajouts et changements de disponibilité.".'</description>';
if(file_exists(RESOURCES_FOLDER.'rss.json'))
{
	$json = json_decode(file_get_contents(RESOURCES_FOLDER.'rss.json'), true);
	foreach ($json as $item)
	{
	$description = displayXMLstatus_tmp($item['status'],$item['response_code'],$item['autoblog_url'],$item['autoblog_title'],$item['autoblog_sourceurl'],$item['autoblog_sourcefeed']);
	$link = serverUrl(true).AUTOBLOGS_FOLDER.$item['autoblog_url'];
	$date = date("r", $item['timestamp']);
	print <<<EOT

<item>
	<title>{$item['autoblog_title']}</title>
	<description><![CDATA[{$description}]]></description>
	<link>{$link}</link>
	<guid isPermaLink="false">{$item['timestamp']}</guid>
	<author>admin@{$_SERVER['SERVER_NAME']}</author>
	<pubDate>{$date}</pubDate>
</item>
EOT;
	}
}
echo '</channel></rss>';
}
?>
