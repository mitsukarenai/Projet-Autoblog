<?php

define('DEBUG', false);
define('XSAF_VERSION', 3);
define('AUTOBLOG_FILE_NAME', 'autoblog.php');
define('ALLOW_REMOTE_DB_DL', true);
define('ALLOW_REMOTE_MEDIA_DL', true);
define('EXEC_TIME', 10);

header("HTTP/1.0 403 Forbidden");   /* Uncivilized method to prevent bot indexing, huh :) */
header('X-Robots-Tag: noindex');    /* more civilized method, but bots may not all take into account */
//header('Content-type: text/plain');

$expire = time() -7200 ; 
$lockfile = ".xsaflock";  /* defaut delay: 7200 (2 hours) */

if (file_exists($lockfile) && filemtime($lockfile) > $expire) {
  		echo "too early";
  		die;
} 
else {
    if( file_exists($lockfile) )
        unlink($lockfile);
        
    if( file_put_contents($lockfile, date(DATE_RFC822)) ===FALSE) {
    	echo "Merci d'ajouter des droits d'écriture sur le dossier.";
		die;
	}	
}

define('ROOT_DIR', __DIR__);
if(file_exists("functions.php")){
	include "functions.php";
}else{
	echo "functions.php not found !";
	die;
}

if(file_exists("config.php")){
	include "config.php";
}else{
	echo "config.php not found !";
	die;
}

function serverUrl() {
    $https = (!empty($_SERVER['HTTPS']) && (strtolower($_SERVER['HTTPS'])=='on')) || $_SERVER["SERVER_PORT"]=='443'; // HTTPS detection.
    $serverport = ($_SERVER["SERVER_PORT"]=='80' || ($https && $_SERVER["SERVER_PORT"]=='443') ? '' : ':'.$_SERVER["SERVER_PORT"]);
    return 'http'.($https?'s':'').'://'.$_SERVER["SERVER_NAME"].$serverport;
}

libxml_use_internal_errors(true);

// $max_exec_time = temps max d'exécution en seconde
function xsafimport($xsafremote, $max_exec_time) {
	if( DEBUG )
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

		$get_remote_db = ($json_import['meta']['xsaf-db_transfer'] == "true") ? true : false;
		$get_remote_media = ($json_import['meta']['xsaf-media_transfer'] == "true") ? true : false;
		
		if(!empty($json_import['autoblogs'])) {
		 	foreach ($json_import['autoblogs'] as $remote_folder => $value) {
		 		if(DEBUG) debug('remote = '. $remote_folder);
		 		if(count($value)==4 && !empty($value['SITE_TYPE']) && !empty($value['SITE_TITLE']) && !empty($value['SITE_URL']) && !empty($value['FEED_URL'])) {
					$sitetype = escape($value['SITE_TYPE']);
					$sitename = escape($value['SITE_TITLE']);
					$siteurl = escape($value['SITE_URL']);
					// Do not use DetectRedirect because it's slow and it has been used when the feed was added
		 			//$rssurl = DetectRedirect(escape($value['FEED_URL']));
		 			$rssurl = escape($value['FEED_URL']);
				}


				/* TOO SLOW
				$xml = simplexml_load_file($rssurl); // quick feed check
				// ATOM feed && RSS 1.0 /RDF && RSS 2.0
				$result = (!isset($xml->entry) && !isset($xml->item) && !isset($xml->channel->item)) ? false : true;	*/			
				$result = true;

				/* autoblog */
				if( $result === true ) {
					$foldername = urlToFolder($siteurl, $rssurl);

					try {
						createAutoblog($sitetype, $sitename, $siteurl, $rssurl);

						if( DEBUG ) {
							echo '<p>autoblog '. $sitename .' crée avec succès (DL DB : '. var_dump($get_remote_db) .' - DL media : '. var_dump($get_remote_media) .') : '. $foldername .'</p>';
							if( !ALLOW_REMOTE_DB_DL && !ALLOW_REMOTE_MEDIA_DL )
								echo '<iframe width="1" height="1" frameborder="0" src="'. urlToFolder( $siteurl, $rssurl ) .'/index.php"></iframe>';
						}

						/* ============================================================================================================================================================================== */
						/* récupération de la DB distante */
						if($get_remote_db == true && ALLOW_REMOTE_DB_DL ) {
					        $remote_db = str_replace("?export", $remote_folder."/articles.db", $xsafremote); 
					        copy($remote_db, './'. $foldername .'/articles.db');         
					    }
						/* préparation à la récupération des médias distants */
						if($get_remote_media == true && ALLOW_REMOTE_MEDIA_DL ) {
                            $remote_media=str_replace("?export", $remote_folder."?media", $xsafremote);
                            if(DEBUG)
                                debug("Récupération de la liste des médias à $remote_media <br>");
							$json_media_import = file_get_contents($remote_media);
                            if(DEBUG)
                                debug($json_media_import);
							if(!empty($json_media_import) && !strpos($json_media_import, '[]}') && json_decode($json_media_import) !== null)
							{
							    file_put_contents('./'. $foldername .'/import.json', $json_media_import);
							}
						}

						/* ============================================================================================================================================================================== */
						//TODO : tester si articles.db est une DB valide
						//$to_update[] = serverUrl().preg_replace("/(.*)\/(.*)$/i","$1/".$foldername , $_SERVER['SCRIPT_NAME']); // url of the new autoblog
					}
					catch (Exception $e) {
						if( DEBUG )
		                	echo $e->getMessage();
		            }
				}

				if( DEBUG )
					echo '<p>time : '.($max_exec_time - time()) .'</p>';
				if(time() >= $max_exec_time) {
					if( DEBUG )
						echo "<p>Time out !</p>";
					break;				
				}
			}
		} 
		else {
			if( DEBUG )
				echo "Format JSON incorrect.";
			return false;
		}
	}
	return;	
}
	
if( DEBUG ) echo '<html><body>';
if( ALLOW_NEW_AUTOBLOGS and ALLOW_NEW_AUTOBLOGS_BY_XSAF && !empty($friends_autoblog_farm) ) {
	foreach( $friends_autoblog_farm AS $value ) {
		if( !empty($value) )
			xsafimport($value, EXEC_TIME);
	}
    if(DEBUG) echo "<p>XSAF import finished</p>";
}
elseif( DEBUG )
	echo "<p>XSAF désactivé. Positionnez les variables ALLOW_NEW_AUTOBLOGS et ALLOW_NEW_AUTOBLOGS_BY_XSAF à TRUE dans le fichier config.php pour l'activer.</p>";

if( DEBUG ) echo '</body></html>';
?>
