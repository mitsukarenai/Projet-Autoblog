<?php

/* modtime 2013-01-23 */

define('ROOT_DIR', __DIR__);
if(file_exists("config.php")){
	include "config.php";
}

function get_title_from_feed($url)
  {
	// get site title from feed
	$data = file_get_contents("$url");
	if($data === false) { die('url inaccessible');  }
	$dom = new DOMDocument;
	$dom->loadXML($data) or die('xml malformé');
	$title = $dom->getElementsByTagName('title');
	return $title->item(0)->nodeValue;
	}

function get_link_from_feed($url)
	{
	// get site link from feed
	$data = file_get_contents("$url");
		$check = substr($data, 0, 5);
		if($check !== '<?xml') { die('n\'est pas un flux valide'); }
	$xml = new SimpleXmlElement($data);
	$channel['link'] = $xml->channel->link;
		if($channel['link'] === NULL)
			{
			$dom = new DOMDocument;
			$dom->loadXML($data) or die('xml malformé');
			$link = $dom->getElementsByTagName('uri');
			return $link->item(0)->nodeValue;
			}
		else
			{
			return $channel['link'];
			}
	}

function serverUrl()
{
    $https = (!empty($_SERVER['HTTPS']) && (strtolower($_SERVER['HTTPS'])=='on')) || $_SERVER["SERVER_PORT"]=='443'; // HTTPS detection.
    $serverport = ($_SERVER["SERVER_PORT"]=='80' || ($https && $_SERVER["SERVER_PORT"]=='443') ? '' : ':'.$_SERVER["SERVER_PORT"]);
    return 'http'.($https?'s':'').'://'.$_SERVER["SERVER_NAME"].$serverport;
}

function NoProtocolSiteURL($url)
	{
	$siteurlnoprototypes = array("http://", "https://");
	$siteurlnoproto = str_replace($siteurlnoprototypes, "", $url);
	return $siteurlnoproto;
	}

function DetectRedirect($url)
{
$response = get_headers($url, 1);
if(!empty($response['Location']))
	{
	$response2 = get_headers($response['Location'], 1);
	if(!empty($response2['Location']))
		{die('too much redirection');}
	else { return $response['Location']; }
	}
else
	{
	return $url;
	}
}

if (isset($_GET['check']))
{
$randomtime=rand(86400, 259200); /* intervalle de mise à jour: de 1 à 3 jours  (pour éviter que le statut de tous les autoblogs soit rafraichi en bloc et bouffe le CPU) */
$expire=time() -$randomtime ;

/* SVG minimalistes */
$svg_vert='<?xml version="1.0" encoding="UTF-8" standalone="no"?><svg xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:cc="http://creativecommons.org/ns#" xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns:svg="http://www.w3.org/2000/svg" xmlns="http://www.w3.org/2000/svg" version="1.1" width="15" height="15"><g><rect width="15" height="15" x="0" y="0" style="fill:#00ff00;stroke:#008000"/></g></svg>';
$svg_jaune='<?xml version="1.0" encoding="UTF-8" standalone="no"?><svg xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:cc="http://creativecommons.org/ns#" xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns:svg="http://www.w3.org/2000/svg" xmlns="http://www.w3.org/2000/svg" version="1.1" width="15" height="15"><g><rect width="15" height="15" x="0" y="0" style="fill:#ffff00;stroke:#ffcc00"/></g></svg>';
$svg_rouge='<?xml version="1.0" encoding="UTF-8" standalone="no"?><svg xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:cc="http://creativecommons.org/ns#" xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns:svg="http://www.w3.org/2000/svg" xmlns="http://www.w3.org/2000/svg" version="1.1" width="15" height="15"><g><rect width="15" height="15" x="0" y="0" style="fill:#ff0000;stroke:#800000"/></g></svg>';
$svg_twitter='<?xml version="1.0" encoding="UTF-8" standalone="no"?><svg xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:cc="http://creativecommons.org/ns#" xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns:svg="http://www.w3.org/2000/svg" xmlns="http://www.w3.org/2000/svg" version="1.1" width="15" height="15"><path d="m 11.679889,7.6290431 a 4.1668792,3.7091539 0 1 1 -8.3337586,0 4.1668792,3.7091539 0 1 1 8.3337586,0 z" style="fill:none;stroke:#3aaae1;stroke-width:4;stroke-miterlimit:4" /></svg>';
$svg_identica='<?xml version="1.0" encoding="UTF-8" standalone="no"?><svg xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:cc="http://creativecommons.org/ns#" xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns:svg="http://www.w3.org/2000/svg" xmlns="http://www.w3.org/2000/svg" version="1.1" width="15" height="15"><path d="m 11.679889,7.6290431 a 4.1668792,3.7091539 0 1 1 -8.3337586,0 4.1668792,3.7091539 0 1 1 8.3337586,0 z" style="fill:none;stroke:#800000;stroke-width:4;stroke-miterlimit:4" /></svg>';
$svg_statusnet='<?xml version="1.0" encoding="UTF-8" standalone="no"?><svg xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:cc="http://creativecommons.org/ns#" xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns:svg="http://www.w3.org/2000/svg" xmlns="http://www.w3.org/2000/svg" version="1.1" width="15" height="15"><path d="m 11.679889,7.6290431 a 4.1668792,3.7091539 0 1 1 -8.3337586,0 4.1668792,3.7091539 0 1 1 8.3337586,0 z" style="fill:none;stroke:#829d25;stroke-width:4;stroke-miterlimit:4" /></svg>';
		
		if(strpos($_GET['check'], 'twitter') !== FALSE) { header('Content-type: image/svg+xml');die($svg_twitter); }
		if(strpos($_GET['check'], 'identica') !== FALSE) { header('Content-type: image/svg+xml');die($svg_identica); }
		if(strpos($_GET['check'], 'statusnet') !== FALSE) { header('Content-type: image/svg+xml');die($svg_statusnet); }

	$errorlog="./".$_GET['check']."/error.log";
	if(file_exists($errorlog) && filemtime($errorlog) < $expire) { unlink($errorlog); } /* errorlog périmé ? Suppression. */
	if(file_exists($errorlog)) /* errorlog existe encore ? se contenter de lire sa taille pour avoir le statut */
		{
		header('Content-type: image/svg+xml');
		if(filesize($errorlog) == "0") {die($svg_vert);}
		else if(filesize($errorlog) == "1") {die($svg_jaune);}
		else {die($svg_rouge);}
		}
	else /* ..sinon, lancer la procédure de contrôle */
		{
		$ini = parse_ini_file("./".$_GET['check']."/vvb.ini") or die;
		header('Content-type: image/svg+xml');
		$headers = get_headers("$ini[FEED_URL]");
		if(empty($headers)) { file_put_contents($errorlog, '..'); die($svg_rouge); } /* le flux est indisponible (typiquement: erreur DNS ou possible censure) - à vérifier */
		$code=explode(" ", $headers[0]);
		if($code[1] == "200")	{ file_put_contents($errorlog, ''); die($svg_vert);}  /* code retour 200: flux disponible */
		else {file_put_contents($errorlog, '.'); die($svg_jaune);}  /* autre code retour: un truc a changé (redirection, changement de CMS, .. bref vvb.ini doit être corrigé) */
		}
}

if (isset($_GET['export']))
// autoblog exporting
{
header('Content-Type: application/json');
$directory = "./";
$subdirs = glob($directory . "*");
foreach($subdirs as $unit)
		{
 		if(is_dir($unit) && strpos($unit, 'twitter') === FALSE && strpos($unit, 'identica') === FALSE && strpos($unit, 'statusnet') === FALSE) /* Exclusion des automicroblogs, en attendant un traitement spécifique par XSAF  */
 			{
			$unit=substr($unit, 2);
			$ini = parse_ini_file($unit.'/vvb.ini');
			$config = new stdClass;
			foreach ($ini as $key=>$value)
       			{
				$key = strtolower($key);
				$config->$key = $value;
				}
			unset($ini);
			$title=$config->site_title;
			$url=$config->site_url;
			$feed=$config->feed_url;
			$reponse[$unit] = array("$title", "$url", "$feed");
 			}
		}
echo json_encode($reponse);
die;
}

if (isset($_GET['feedexport']))
// autoblog exporting -feed only
{
header('Content-Type: application/json');
$directory = "./";
$reponse="";
$subdirs = glob($directory . "*");
foreach($subdirs as $unit)
		{
 		if(is_dir($unit))
 			{
			$unit=substr($unit, 2);
			$ini = parse_ini_file($unit.'/vvb.ini');
			$config = new stdClass;
			foreach ($ini as $key=>$value)
       			{
				$key = strtolower($key);
				$config->$key = $value;
				}
			unset($ini);
			$feed=$config->feed_url;
			$reponse=$reponse.";$feed";
 			}
		}
$reponse=substr($reponse, 1);
echo json_encode(explode(';', $reponse));
die;
}

if (isset($_GET['sitemap']))
// url-list sitemap
{
header('Content-Type: text/plain');
$directory = "./";
$subdirs = glob($directory . "*");
foreach($subdirs as $unit)
		{
 		if(is_dir($unit))
 			{
			$unit=substr($unit, 2);
			$proto=$_SERVER['HTTPS']?"https://":"http://";
			echo $proto.$_SERVER['SERVER_NAME'].substr($_SERVER['PHP_SELF'], 0, -9)."$unit/"."\n";
 			}
		}
die;
}

function escape($str)
{
    return htmlspecialchars($str, ENT_COMPAT, 'UTF-8', false);
}

$form = '<form method="POST"><input placeholder="Adresse du flux RSS/ATOM" type="text" name="rssurl" id="rssurl"><br>
            <input placeholder="Antibot: \'dix sept\' en chiffre" type="text" name="number" id="number"><br><input type="submit" value="Vérifier"></form>';

if(!empty($_GET['via_button']) && !empty($_GET['rssurl']) && $_GET['number'] === '17')
{
	if(isset($_GET['add']) && $_GET['add'] === '1' && !empty($_GET['siteurl']) && !empty($_GET['sitename']))
		{
		$rssurl = DetectRedirect(escape($_GET['rssurl']));
		$siteurl = escape($_GET['siteurl']);
		$foldername = sha1(NoProtocolSiteURL($siteurl));
		if(substr($siteurl, -1) == '/'){ $foldername2 = sha1(NoProtocolSiteURL(substr($siteurl, 0, -1))); }else{ $foldername2 = sha1(NoProtocolSiteURL($siteurl).'/');}
		$sitename = escape($_GET['sitename']);
		$sitedomain1 = preg_split('/\//', $siteurl, 0);
		$sitedomain2=$sitedomain1[2];
		$sitedomain3=explode(".", $sitedomain2);
		$sitedomain3=array_reverse($sitedomain3);
		$sitedomain = $sitedomain3[1].'.'.$sitedomain3[0];
			if(file_exists($foldername) || file_exists($foldername2)) { die('Erreur: l\'autoblog <a target="_blank" href="./'.$foldername.'/">existe déjà</a>.'); }
	if ( mkdir('./'. $foldername, 0755, false) ) {
	$fp = fopen('./'. $foldername .'/index.php', 'w+');
	if( !fwrite($fp, "<?php require_once dirname(__DIR__) . '/autoblog.php'; ?>") )
		{die("Impossible d'écrire le fichier index.php");}
	fclose($fp);
	$fp = fopen('./'. $foldername .'/vvb.ini', 'w+');
	if( !fwrite($fp, '[VroumVroumBlogConfig]
SITE_TITLE="'. $sitename .'"
SITE_DESCRIPTION="Ce site n\'est pas le site officiel de '. $sitename .'<br>C\'est un blog automatis&eacute; qui r&eacute;plique les articles de <a href="'. $siteurl .'">'. $sitename .'</a>"
SITE_URL="'. $siteurl .'"
FEED_URL="'. $rssurl .'"
DOWNLOAD_MEDIA_FROM='.$sitedomain) )
	{die("Impossible d'écrire le fichier vvb.ini");}
	fclose($fp);
	{die('<iframe width="1" height="1" frameborder="0" src="'.$foldername.'"></iframe><b style="color:darkgreen">autoblog crée avec succès.</b> &rarr; <a target="_blank" href="'.$foldername.'">afficher l\'autoblog</a>');}
	}
else 
	 {die("Impossible de créer le répertoire.");}

		}
	else
		{
		// checking procedure
		$rssurl = DetectRedirect($_GET['rssurl']);
		$siteurl = get_link_from_feed($rssurl);
		$foldername = sha1(NoProtocolSiteURL($siteurl));
		if(substr($siteurl, -1) == '/'){ $foldername2 = sha1(NoProtocolSiteURL(substr($siteurl, 0, -1))); }else{ $foldername2 = sha1(NoProtocolSiteURL($siteurl).'/');}
		$sitename = get_title_from_feed($rssurl);
		$sitedomain1 = preg_split('/\//', $siteurl, 0);$sitedomain2=$sitedomain1[2];$sitedomain3=explode(".", $sitedomain2);$sitedomain3=array_reverse($sitedomain3);$sitedomain = $sitedomain3[1].'.'.$sitedomain3[0];
				if(file_exists($foldername) || file_exists($foldername2)) { die('Erreur: l\'autoblog <a href="./'.$foldername.'/">existe déjà</a>.'); }
		$form = '<html><head></head><body><span style="color:blue">Merci de vérifier les informations suivantes, corrigez si nécessaire.</span><br>
		<form method="GET">
		<input type="hidden" name="via_button" value="1"><input type="hidden" name="add" value="1"><input type="hidden" name="number" value="17">
		<input style="width:30em;" type="text" name="sitename" id="sitename" value="'.$sitename.'"><label for="sitename">&larr; titre du site (auto)</label><br>		
		<input style="width:30em;" placeholder="Adresse du site" type="text" name="siteurl" id="siteurl" value="'.$siteurl.'"><label for="siteurl">&larr; page d\'accueil (auto)</label><br>
        <input style="width:30em;" placeholder="Adresse du flux RSS/ATOM" type="text" name="rssurl" id="rssurl" value="'.$rssurl.'"><label for="rssurl">&larr; adresse du flux</label><br>
        <input type="submit" value="Créer"></form></body></html>';
		echo $form; die;
		}
}

if(!empty($_POST['socialaccount']) && !empty($_POST['socialinstance']))
{
$socialaccount = strtolower(escape($_POST['socialaccount']));
        if(escape($_POST['socialinstance']) === 'twitter') { $socialinstance = 'twitter'; }
        if(escape($_POST['socialinstance']) === 'identica') { $socialinstance = 'identica'; }
        if(escape($_POST['socialinstance']) === 'statusnet') { $socialinstance = 'statusnet'; }
	$folder = "$socialinstance-$socialaccount";if(file_exists($folder)) { die('Erreur: l\'autoblog <a href="./'.$folder.'/">existe déjà</a>.'); }
		if($socialinstance === 'twitter') { $siteurl = "http://twitter.com/$socialaccount"; $rssurl = "http://api.twitter.com.nyud.net/1/statuses/user_timeline.rss?screen_name=$socialaccount"; } 
		if($socialinstance === 'identica') { $siteurl = "http://identi.ca/$socialaccount"; $rssurl = "http://identi.ca.nyud.net/api/statuses/user_timeline/$socialaccount.atom"; } 
		if($socialinstance === 'statusnet' && !empty($_POST['socialurl'])) { $siteurl = "http://".escape($_POST['socialurl'])."/$socialaccount"; $rssurl = "http://".escape($_POST['socialurl'])."/api/statuses/user_timeline/$socialaccount.atom"; } 
		$headers = get_headers($rssurl, 1);
		if (strpos($headers[0], '200') == FALSE) {$error[] = "Flux inaccessible (compte inexistant ?)";} else {  }
if( empty($error) ) {
	if( !preg_match('#\.\.|/#', $folder) ) {
		if ( mkdir('./'. $folder, 0755, false) ) {
			$fp = fopen('./'. $folder .'/index.php', 'w+');
			if( !fwrite($fp, "<?php require_once dirname(__DIR__).'/automicroblog.php'; ?>") )
				$error[] = "Impossible d'écrire le fichier index.php";
			fclose($fp);
			$fp = fopen('./'. $folder .'/vvb.ini', 'w+');
			if( !fwrite($fp, '[VroumVroumBlogConfig]
SITE_TITLE="'.$socialinstance.'-'.$socialaccount.'"
SITE_DESCRIPTION="AutoMicroblog automatis&eacute; de "
SITE_URL='. $siteurl .'
FEED_URL="'. $rssurl .'"') )
			$error[] = "Impossible d'écrire le fichier vvb.ini";
			fclose($fp);
				$error[] = '<iframe width="1" height="1" frameborder="0" src="'.$folder.'"></iframe><b style="color:darkgreen">AutoMicroblog <a href="'.$folder.'">ajouté avec succès</a>.</b>';
            }
            else 
                $error[] = "Impossible de créer le répertoire.";
        }
        else 
            $error[] = "Nom de site invalide.";
    }

}


if( !empty($_POST) && empty($_POST['socialinstance']) ) {
    $error = array();
	if(empty($_POST['rssurl']))
		{$error[] = "Veuillez entrer l'adresse du flux.";}
	if(empty($_POST['number']))
		{$error[] = "Le chiffre. Écrivez le chiffre.";}
    if($_POST['number'] !== '17')
		{$error[] = "C'est pas le bon chiffre.";} 

	if(empty($error))
		{
		$rssurl = DetectRedirect(escape($_POST['rssurl']));
		if(!empty($_POST['siteurl']))
			{
			// check done, writing out
			$siteurl = escape($_POST['siteurl']);
			$foldername = sha1(NoProtocolSiteURL($siteurl));$sitename = get_title_from_feed($rssurl);
			if(substr($siteurl, -1) == '/'){ $foldername2 = sha1(NoProtocolSiteURL(substr($siteurl, 0, -1))); }else{ $foldername2 = sha1(NoProtocolSiteURL($siteurl).'/');}
			$sitedomain1 = preg_split('/\//', $siteurl, 0);$sitedomain2=$sitedomain1[2];$sitedomain3=explode(".", $sitedomain2);$sitedomain3=array_reverse($sitedomain3);$sitedomain = $sitedomain3[1].'.'.$sitedomain3[0];
				if(file_exists($foldername) || file_exists($foldername2)) { die('Erreur: l\'autoblog <a href="./'.$foldername.'/">existe déjà</a>.'); }
			if ( mkdir('./'. $foldername, 0755, false) ) {
                $fp = fopen('./'. $foldername .'/index.php', 'w+');
                if( !fwrite($fp, "<?php require_once dirname(__DIR__) . '/autoblog.php'; ?>") )
                    $error[] = "Impossible d'écrire le fichier index.php";
                fclose($fp);
                $fp = fopen('./'. $foldername .'/vvb.ini', 'w+');
                if( !fwrite($fp, '[VroumVroumBlogConfig]
SITE_TITLE="'. $sitename .'"
SITE_DESCRIPTION="Ce site n\'est pas le site officiel de '. $sitename .'<br>C\'est un blog automatis&eacute; qui r&eacute;plique les articles de <a href="'. $siteurl .'">'. $sitename .'</a>"
SITE_URL="'. $siteurl .'"
FEED_URL="'. $rssurl .'"
DOWNLOAD_MEDIA_FROM='.$sitedomain) )
                    $error[] = "Impossible d'écrire le fichier vvb.ini";
                fclose($fp);
			$error[] = '<iframe width="1" height="1" frameborder="0" src="'.$foldername.'"></iframe><b style="color:darkgreen">autoblog crée avec succès.</b> &rarr; <a target="_blank" href="'.$foldername.'">afficher l\'autoblog</a>';
            }
            else 
                $error[] = "Impossible de créer le répertoire.";

			
			}
		else
			{
			// checking procedure
			$rssurl = DetectRedirect($rssurl);
			$siteurl = get_link_from_feed($rssurl);
			$foldername = sha1(NoProtocolSiteURL($siteurl));
			$sitename = get_title_from_feed($rssurl);
			if(substr($siteurl, -1) == '/'){ $foldername2 = sha1(NoProtocolSiteURL(substr($siteurl, 0, -1))); }else{ $foldername2 = sha1(NoProtocolSiteURL($siteurl).'/');}
			$sitedomain1 = preg_split('/\//', $siteurl, 0);$sitedomain2=$sitedomain1[2];$sitedomain3=explode(".", $sitedomain2);$sitedomain3=array_reverse($sitedomain3);$sitedomain = $sitedomain3[1].'.'.$sitedomain3[0];
				if(file_exists($foldername) || file_exists($foldername2)) { die('Erreur: l\'autoblog <a href="./'.$foldername.'/">existe déjà</a>.'); }
			$form = '<span style="color:blue">Merci de vérifier les informations suivantes, corrigez si nécessaire.</span><br>
			<form method="POST"><input style="color:black" type="text" id="sitename" value="'.$sitename.'" disabled><label for="sitename">&larr; titre du site (auto)</label><br>		
			<input placeholder="Adresse du site" type="text" name="siteurl" id="siteurl" value="'.$siteurl.'"><label for="siteurl">&larr; page d\'accueil (auto)</label><br>
            <input placeholder="Adresse du flux RSS/ATOM" type="text" name="rssurl" id="rssurl" value="'.$rssurl.'"><label for="rssurl">&larr; adresse du flux</label><br>
            <input placeholder="Antibot: \'dix sept\' en chiffre" type="text" name="number" id="number" value="17"><label for="number">&larr; antibot</label><br><input type="submit" value="Créer"></form>';
			}

		}
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
	<head>
		<meta charset="utf-8">
    <title>Le Projet Autoblog</title>
		<style type="text/css">
			body {background-color:#efefef;text-align:center;color:#333;}
			a {color:black;text-decoration:none;font-weight:bold;}
			a:hover {color:darkred;}
			h1 { text-align:center;font-size:40pt;text-shadow: #ccc 0px 5px 5px; }
			h2 { text-align:center;font-size: 16pt;margin:0 0 1em 0;font-style:italic;text-shadow: #ccc 0px 5px 5px; }
			.pbloc {background-color:white;padding: 12px 10px 12px 10px;border:1px solid #aaa;max-width:70em;margin:1em auto;text-align:justify;box-shadow:0px 5px 7px #aaa;}
			input {width:30em;}
			input#socialaccount, input#socialurl, input#socialsub {width:12em;}
			.vignette { width:20em;height:2em;float:left;margin:0; padding:20px;background-color:#eee;border: 1px solid #888;}
			.vignette:hover { background-color:#fff;}
			.vignette .title { font-size: 14pt;text-shadow: #ccc 0px 5px 5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
			.vignette .title a:hover { color:darkred; text-decoration:none;}
			.vignette .source { font-size:x-small;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
			.vignette .source a:hover { color:darkred; text-decoration:none;}
			.clear {clear:both;text-align:right;font-size:small;}
			#logo {float: right;}
			.bouton{background: -moz-linear-gradient(center top , #EDEDED 5%, #DFDFDF 100%) repeat scroll 0 0 #EDEDED;border: 1px none;padding: 10px;border: 1px solid #7777777;border-radius: 8px 8px 8px 8px;box-shadow: 0 1px 0 0 #FFFFFF inset;display: inline-block;}

		</style>
</head>
	<body>
		<h1>Le Projet Autoblog</h1>
		<div class="pbloc">
			<img id="logo" src="<?php if(isset($logo)) { echo $logo; }else{ echo './icon-logo.svg'; } ?>" alt="">
			<b>Note</b><br>
			Voici une liste d'autoblogs hébergés sur <i><?php echo $_SERVER['SERVER_NAME']; ?></i> (<a href="http://sebsauvage.net/streisand.me/fr/">plus d'infos sur le projet</a>).<br><br>
			<b>Autres fermes</b><br>
			&rarr; <a href="https://duckduckgo.com/?q=!g %22Voici une liste d'autoblogs hébergés%22">Rechercher</a><br><br>
			<b>Ajouter un compte social</b><br><br>
        <form method="POST">
            <input class="text" placeholder="identifiant compte" type="text" name="socialaccount" id="socialaccount"><br>
			<input type="radio" name="socialinstance" value="twitter">Twitter<br>
			<input type="radio" name="socialinstance" value="identica">Identica<br>
			<input type="radio" name="socialinstance" value="statusnet"><input placeholder="statusnet.personnel.com" type="text" name="socialurl" id="socialurl"><br>
            <input id="socialsub" type="submit" value="Créer">
        </form><br>
			<b>Ajouter un site web</b><br>
<?php
if( !empty( $error )) {
    echo '<p>Erreur(s) :</p><ul>';
    foreach ( $error AS $value ) {
        echo '<li>'. $value .'</li>';
    }
    echo '</ul>';
}
?>
Si vous souhaitez que <i><?php echo $_SERVER['SERVER_NAME']; ?></i> héberge un autoblog d'un site,<br/>remplissez le formulaire suivant:<br><br>
		<?php echo $form; ?>
<br>Pour ajouter facillement un autoblog, glissez ce bouton dans votre barre de marque-pages =&gt; <a class="bouton" onclick="alert('Glissez ce lien dans votre barre de marque-pages ou clic-droit puis choisiez d\'ajouter ce lien aux marque-pages.');return false;" href="javascript:(function(){var%20autoblog_url=&quot;<?php echo serverUrl().$_SERVER["REQUEST_URI"]; ?>&quot;;var%20popup=window.open(&quot;&quot;,&quot;Add%20autoblog&quot;,'height=180,width=670');popup.document.writeln('<html><head></head><body><form%20action=&quot;'+autoblog_url+'&quot;%20method=&quot;GET&quot;>');popup.document.write('Url%20feed%20%20:%20<br/>');var%20feed_links=new%20Array();var%20links=document.getElementsByTagName('link');if(links.length>0){for(var%20i=0;i<links.length;i++){if(links[i].rel==&quot;alternate&quot;){popup.document.writeln('<label%20for=&quot;feed_'+i+'&quot;><input%20id=&quot;feed_'+i+'&quot;%20type=&quot;radio&quot;%20name=&quot;rssurl&quot;%20value=&quot;'+links[i].href+'&quot;/>'+links[i].title+&quot;%20(%20&quot;+links[i].href+&quot;%20)</label><br/>&quot;);}}}popup.document.writeln(&quot;<input%20id='number'%20type='hidden'%20name='number'%20value='17'>&quot;);popup.document.writeln(&quot;<input%20type='hidden'%20name='via_button'%20value='1'>&quot;);popup.document.writeln(&quot;<br/><input%20type='submit'%20value='Vérifier'%20name='Ajouter'%20>&quot;);popup.document.writeln(&quot;</form></body></html>&quot;);})();">Projet Autoblog</a>
</div>
<div class="pbloc">
<h2>Autoblogs hébergés</h2>
<div class="clear"><a href="?export">export<sup> JSON</sup></a></div>
<?php
$directory = "./";
$subdirs = glob($directory . "*");
$autoblogs = array();
foreach($subdirs as $unit)
{
	if(is_dir($unit))
	{
		$ini = parse_ini_file(ROOT_DIR . '/' . $unit . '/vvb.ini');
		if($ini)
		{
			$config = new stdClass;
			$unit=substr($unit, 2);
			foreach ($ini as $key=>$value)
		    {
		            $key = strtolower($key);
		            $config->$key = $value;
		    }
		    $autoblogs[$unit] = '
				<div class="vignette">
					<div class="title"><a title="'.escape($config->site_title).'" href="'.$unit.'/"><img width="15" height="15" alt="" src="./?check='.$unit.'"> '.escape($config->site_title).'</a></div>
					<div class="source"><a href="'.$unit.'/vvb.ini">config</a> | source: <a href="'.escape($config->site_url).'">'.escape($config->site_url).'</a></div>
				</div>';
		        unset($ini);
		}
	}
}
if(!empty($autoblogs)){
	sort($autoblogs, SORT_STRING);
	foreach ($autoblogs as $autoblog) {
		echo $autoblog;
	}
}
?>
<div class="clear"></div>
<?php echo "<br/>".count($autoblogs)." autoblogs d'hébergés"; ?>
</div>
Autoblogs propulsés par <a href="http://autoblog.kd2.org/source.txt">VroumVroumBlog 0.2.10</a> [SQLite] (Domaine Public)<br>index2 inspiré par <a href="http://wiki.hoa.ro/doku.php?id=web%3Aferme-autoblog">Arthur</a> et développé par <a href="https://www.suumitsu.eu/">Mitsu</a> et <a href="https://www.ecirtam.net/">Oros</a> (Domaine Public)
<br/><a href='https://github.com/mitsukarenai/ferme-autoblog'>Code source du projet</a>
<?php if(isset($HTML_footer)){ echo "<br/>".$HTML_footer; } ?>
<iframe width="1" height="1" frameborder="0" src="xsaf2.php"></iframe>
</body>
</html>
