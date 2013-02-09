<?php
/* modtime 2013-02-04 */

define('DEBUG', true);
define('XSAF_VERSION', 3);
define('AUTOBLOG_FILE_NAME', 'autoblog-0.3.php');

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
/* détection de ferme autoblog  */
	$json_import = file_get_contents($xsafremote);
	if(!empty($json_import)) {
		$to_update=array();
		$json_import = json_decode($json_import, true);

		if(!isset($json_import['meta']) || !isset($json_import['meta']['xsaf-version']) || $json_import['meta']['xsaf-version'] != XSAF_VERSION){
			if(DEBUG){
				echo "\nxsaf-version différentes !";
			}
			return false;
		}
		if($json_import['meta']['xsaf-db_transfer'] != "true") {$get_remote_db="0";} else {$get_remote_db="1";}
		if($json_import['meta']['xsaf-media_transfer'] != "true") {$get_remote_media="0";} else {$get_remote_media="1";}
		if(!empty($json_import['autoblogs'])) {
		 	foreach ($json_import['autoblogs'] as $value) {
		 		$infos="";
		 		if(count($value)==4 && !empty($value['SITE_TYPE']) && !empty($value['SITE_TITLE']) && !empty($value['SITE_URL']) && !empty($value['FEED_URL'])) {
					$sitetype = $value['SITE_TYPE'];
					$sitename = $value['SITE_TITLE'];
					$siteurl = escape($value['SITE_URL']);
		 			$rssurl = escape($value['FEED_URL']);
						if($sitetype == 'shaarli') { $articles_per_page = "20"; $update_interval = "1800"; $update_timeout = "30"; }
						else if($sitetype == 'microblog') { $articles_per_page = "20"; $update_interval = "300"; $update_timeout = "30"; }
						else { $articles_per_page = "5"; $update_interval = "3600"; $update_timeout = "30"; }

//						$foldername = $sitename;$foldername2 = $sitename;
		$foldername = sha1(NoProtocolSiteURL($siteurl));
		if(substr($siteurl, -1) == '/'){ $foldername2 = sha1(NoProtocolSiteURL(substr($siteurl, 0, -1))); }else{ $foldername2 = sha1(NoProtocolSiteURL($siteurl).'/');}

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
				                if( !fwrite($fp, "<?php require_once dirname(__DIR__) . '/".AUTOBLOG_FILE_NAME."'; ?>") ) {
				                    $infos = "\nImpossible d'écrire le fichier index.php dans ".$foldername;
				                    fclose($fp);
				                }else{
				              		fclose($fp);
					                $fp = fopen('./'. $foldername .'/vvb.ini', 'w+');
					                if( !fwrite($fp, '[VroumVroumBlogConfig]
SITE_TYPE="'. $sitetype .'"
SITE_TITLE="'. $sitename .'"
SITE_DESCRIPTION="source: <a href="'. $siteurl .'">'. $sitename .'</a>"
SITE_URL="'. $siteurl .'"
FEED_URL="'. $rssurl .'"
ARTICLES_PER_PAGE="'. $articles_per_page .'"
UPDATE_INTERVAL="'. $update_interval .'"
UPDATE_TIMEOUT="'. $update_timeout .'"') ){
				                    	fclose($fp);
				                		$infos = "\nImpossible d'écrire le fichier vvb.ini dans ".$foldername;
					               	}else{
					                	fclose($fp);
	/* ============================================================================================================================================================================== */
	/* récupération de la DB distante */
	if($get_remote_db == "1") {	$remote_db=str_replace("?export", $foldername."/articles.db", $xsafremote); copy($remote_db, './'. $foldername .'/articles.db'); }
	if($get_remote_media == "1")
		{
		$remote_media=str_replace("?export", $foldername."/?media", $xsafremote);
		$json_media_import = file_get_contents($remote_media);
		if(!empty($json_media_import))
			{
			mkdir('./'.$foldername.'/media/');
			$json_media_import = json_decode($json_media_import, true);
			$media_path=$json_media_import['url'];
			if(!empty($json_media_import['files']))
				{
				foreach ($json_media_import['files'] as $value)
					{
					copy($media_path.$value, './'.$foldername.'/media/'.$value);
					}
				}
			}
		}
	/* ============================================================================================================================================================================== */

										//TODO : tester si articles.db est une DB valide

										$infos = "\nautoblog crée avec succès (DB:$get_remote_db media:$get_remote_media) : $foldername";
										$to_update[]=serverUrl().preg_replace("/(.*)\/(.*)$/i","$1/".$foldername , $_SERVER['SCRIPT_NAME']); // url of the new autoblog
									}
								}
							} else {
								$infos = "\n$rssurl -> flux invalide"; unlink("./$foldername/index.php"); rmdir($foldername);
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
xsafimport('https://raw.github.com/mitsukarenai/xsaf-bootstrap/master/3.json', 5);
//xsafimport('https://www.ecirtam.net/autoblogs/?export', 5);
xsafimport('https://autoblog.suumitsu.eu/?export', 5);

if(DEBUG) {
	echo "\n\nXSAF import finished\n\n";
}
die;
?>
