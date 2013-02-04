<?php
/* modtime 2013-02-04 */

define('DEBUG', true);

header("HTTP/1.0 403 Forbidden");   /* Uncivilized method to prevent bot indexing, huh :) */
header('X-Robots-Tag: noindex');    /* more civilized method, but bots may not all take into account */
header('Content-type: text/plain');
$expire = time() -7200 ; $lockfile = ".xsaflock";  /* defaut delay: 7200 (2 hours) */

if (file_exists($lockfile)) {
	if (filemtime($lockfile) > $expire) {
  		echo "too early";
  		die;
  	}else{
  		unlink($lockfile);
  		file_put_contents($lockfile, '');
  	}
}else{
	file_put_contents($lockfile, '');	
} 

define('ROOT_DIR', __DIR__);
function escape($str) {
    return htmlspecialchars($str, ENT_COMPAT, 'UTF-8', false);
}

function serverUrl() {
    $https = (!empty($_SERVER['HTTPS']) && (strtolower($_SERVER['HTTPS'])=='on')) || $_SERVER["SERVER_PORT"]=='443'; // HTTPS detection.
    $serverport = ($_SERVER["SERVER_PORT"]=='80' || ($https && $_SERVER["SERVER_PORT"]=='443') ? '' : ':'.$_SERVER["SERVER_PORT"]);
    return 'http'.($https?'s':'').'://'.$_SERVER["SERVER_NAME"].$serverport;
}

function NoProtocolSiteURL($url) {
	$siteurlnoprototypes = array("http://", "https://");
	$siteurlnoproto = str_replace($siteurlnoprototypes, "", $url);
	return $siteurlnoproto;
}

libxml_use_internal_errors(true);
// $max_exec_time = temps max d'exécution en seconde
function xsafimport($xsafremote, $max_exec_time) {
	echo "\n*Traitement $xsafremote en maximum $max_exec_time secondes";
	$max_exec_time+=time()-1; // -1 car l'import prend environ 1 seconde
/* détection de ferme autoblog  */	if(strpos($xsafremote, '?export') === false) {$get_remote_db="0";} else {$get_remote_db="1";}
	$json_import = file_get_contents($xsafremote);
	if(!empty($json_import)) {
		$to_update=array();
	 	foreach (json_decode($json_import, true) as $value) {
	 		$infos="";
	 		if(count($value)==3 && !empty($value['SITE_TYPE']) && !empty($value['SITE_TITLE']) && !empty($value['SITE_URL']) && !empty($value['FEED_URL'])) {
				$sitetype = $value['SITE_TYPE'];
				$sitename = $value['SITE_TITLE'];
				$siteurl = escape($value['SITE_URL']);
	 			$rssurl = escape($value['FEED_URL']);

					$foldername = $sitename;$foldername2 = $sitename;

				if(!file_exists($foldername) && !file_exists($foldername2)) { 
					if ( mkdir('./'. $foldername, 0755, false) ) {
		                $fp = fopen('./'. $foldername .'/index.php', 'w+');

						$response = get_headers($rssurl, 1); // check for redirections
						if(!empty($response['Location'])) {
							$result="false";
						}else{
							$xml = simplexml_load_file($rssurl); // quick feed check

							if($xml === FALSE){
								$result="false";
							}elseif (isset($xml->entry)) { // ATOM feed.
								$result="true";
							}elseif (isset($xml->item)) { // RSS 1.0 /RDF
								$result="true";
							}elseif (isset($xml->channel->item)) { // RSS 2.0
								$result="true";
							}else{
								$result="false";
							} 
						}

						/* autoblog */
						if($result!=="false") {
			                if( !fwrite($fp, "<?php require_once dirname(__DIR__) . '/autoblog.php'; ?>") ) {
			                    $infos = "\nImpossible d'écrire le fichier index.php dans ".$foldername;
			                    fclose($fp);
			                }else{
			              		fclose($fp);
				                $fp = fopen('./'. $foldername .'/vvb.ini', 'w+');
				                if( !fwrite($fp, '[VroumVroumBlogConfig]
SITE_TYPE="'. $sitetype .'"
SITE_TITLE="'. $sitename .'"
SITE_DESCRIPTION="Ce site n\'est pas le site officiel de '. $sitename .'<br>C\'est un blog automatis&eacute; qui r&eacute;plique les articles de <a href="'. $siteurl .'">'. $sitename .'</a>"
SITE_URL="'. $siteurl .'"
FEED_URL="'. $rssurl .'"') ){
			                    	fclose($fp);
			                		$infos = "\nImpossible d'écrire le fichier vvb.ini dans ".$foldername;
				               	}else{
				                	fclose($fp);
/* ============================================================================================================================================================================== */
/* récupération de la DB distante */ $remote_db=str_replace("?export", $foldername."/articles.db", $xsafremote); copy($remote_db, './'. $foldername .'/articles.db');
/* ============================================================================================================================================================================== */
									$infos = "\nautoblog crée avec succès : $foldername";
									$to_update[]=serverUrl().preg_replace("/(.*)\/(.*)$/i","$1/".$foldername , $_SERVER['SCRIPT_NAME']); // url of the new autoblog
								}
							}
						} else {
							$infos = "\n$rssurl -> flux invalide";
						}
					/* end of file writing */
		            }else {
		                $infos = "\nImpossible de créer le répertoire ".$foldername;
					}
				}  else {
					/*$infos = "\nFin d'itération ou Le répertoire ".$foldername." existe déjà ($sitename;$siteurl;$rssurl)";*/
				} 
				if(DEBUG){
					echo $infos;
				}
	 		}
	 		echo "\n time : ".(time() - $max_exec_time);
	 		if(time() >= $max_exec_time){
	 			break;
	 		}
	 	}
	 	/*if(!empty($to_update)){
	 		if(DEBUG){
	 			echo "\nupdate of autoblogs ...";
	 		}
	 		// because it's could be very long, we finish by updating new autoblogs
	 		foreach ($to_update as $url) {
	 			get_headers($url);
	 		}
	 		if(DEBUG){
	 			echo "done\n\n";
	 		}
	 	}*/
	}
	return;
}

/* And now, the XSAF links to be imported, with maximal execusion time for import in second ! */
xsafimport('https://raw.github.com/mitsukarenai/xsaf-bootstrap/master/2.json', 5);
//xsafimport('https://www.ecirtam.net/autoblogs/?export', 5);
//xsafimport('https://autoblog.suumitsu.eu/?export', 5);

if(DEBUG) {
	echo "\n\nXSAF import finished\n\n";
}
die;
?>
