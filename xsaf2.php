<?php
// update 17/01/2013

define('DEBUG', true);

header("HTTP/1.0 403 Forbidden");   /* Uncivilized method to prevent bot indexing, huh :) */
header('X-Robots-Tag: noindex');    /* more civilized method, but bots may not all take into account */
header('Content-type: text/plain');
$expire = time() -7200 ; $lockfile = ".xsaflock";  /* defaut delay: 7200 (2 hours) */


if (file_exists($lockfile)) 
{
  if (filemtime($lockfile) > $expire)
	{ echo "too early"; die; }
	else
	{ unlink($lockfile); }
}
else file_put_contents($lockfile, '');

define('ROOT_DIR', __DIR__);
function escape($str)
{
    return htmlspecialchars($str, ENT_COMPAT, 'UTF-8', false);
}
function serverUrl()
{
    $https = (!empty($_SERVER['HTTPS']) && (strtolower($_SERVER['HTTPS'])=='on')) || $_SERVER["SERVER_PORT"]=='443'; // HTTPS detection.
    $serverport = ($_SERVER["SERVER_PORT"]=='80' || ($https && $_SERVER["SERVER_PORT"]=='443') ? '' : ':'.$_SERVER["SERVER_PORT"]);
    return 'http'.($https?'s':'').'://'.$_SERVER["SERVER_NAME"].$serverport;
}
function xsafimport($xsafremote)
{ 
	$json_import = file_get_contents($xsafremote);
	if(!empty($json_import)){
		$to_update=array();
	 	foreach (json_decode($json_import) as $value) {
	 		$infos="";
	 		if(count($value)==3 && !empty($value[0]) && !empty($value[1]) && !empty($value[2])){

	 			$rssurl = escape($value[2]);

				// check done, writing out
				$siteurl = escape($value[1]);
				/*
				// suppression du http et https
				if(substr($siteurl, 0, 5)== 'https'){
					$siteurl2 = substr($siteurl, 5);
				}elseif(substr($siteurl, 0, 4)== 'http'){
					$siteurl2 = substr($siteurl, 4);
				}else{
					$siteurl2 = $siteurl;
				}
				*/
				$foldername = sha1($siteurl);

				if(substr($siteurl, -1) == '/'){
					$foldername2 = sha1(substr($siteurl, 0, -1));
				}else{
					$foldername2 = sha1($siteurl.'/');
				}

				$sitename = $value[0];
				$sitedomain1 = preg_split('/\//', $siteurl, 0);
				$sitedomain2=$sitedomain1[2];
				$sitedomain3=explode(".", $sitedomain2);
				$sitedomain3=array_reverse($sitedomain3);
				$sitedomain = $sitedomain3[1].'.'.$sitedomain3[0];
				if(!file_exists($foldername) && !file_exists($foldername2)) { 
					if ( mkdir('./'. $foldername, 0755, false) ) {
		                $fp = fopen('./'. $foldername .'/index.php', 'w+');
		                if( !fwrite($fp, "<?php require_once dirname(__DIR__) . '/autoblog.php'; ?>") ){
		                    $infos = "Impossible d'écrire le fichier index.php dans ".$foldername;
		                    fclose($fp);
		                }else{
			                fclose($fp);
			                $fp = fopen('./'. $foldername .'/vvb.ini', 'w+');
			                if( !fwrite($fp, '[VroumVroumBlogConfig]
SITE_TITLE="'. $sitename .'"
SITE_DESCRIPTION="Ce site n\'est pas le site officiel de '. $sitename .'<br>C\'est un blog automatis&eacute; qui r&eacute;plique les articles de <a href="'. $siteurl .'">'. $sitename .'</a>"
SITE_URL="'. $siteurl .'"
FEED_URL="'. $rssurl .'"
DOWNLOAD_MEDIA_FROM='.$sitedomain) ){
			                    fclose($fp);
			                	$infos = "Impossible d'écrire le fichier vvb.ini dans ".$foldername;
			               	}else{
			                	fclose($fp);
								$infos = "autoblog crée avec succès : $foldername";
								$to_update[]=serverUrl().preg_replace("/(.*)\/(.*)$/i","$1/".$foldername , $_SERVER['SCRIPT_NAME']); // url of the new autoblog
							}
						}
		            }else {
		                $infos = "Impossible de créer le répertoire ".$foldername;
					}
				}
				if(DEBUG){ echo $infos."\n"; }
	 		}
	 	}
	 	if(!empty($to_update)){
	 		if(DEBUG){ echo "update of autoblogs ..."; }
	 		// because it's could be very long, we finish by updating new autoblogs
	 		foreach ($to_update as $url) {
	 			file_get_contents($url);
	 		}
	 		if(DEBUG){ echo "done"; }
	 	}
	}
}

/* And now, the XSAF links to be imported ! */

#xsafimport('https://www.ecirtam.net/autoblogs/?export');
#xsafimport('http://autoblog.suumitsu.eu/?export');
if(DEBUG){ echo "\n\nXSAF import finished\n\n"; }

?>
