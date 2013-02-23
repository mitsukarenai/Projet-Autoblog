<?php

define('DEBUG', true);
define('XSAF_VERSION', 3);
define('AUTOBLOG_FILE_NAME', 'autoblog.php');
define('ALLOW_REMOTE_DB_DL', false);
define('ALLOW_REMOTE_MEDIA_DL', false);
define('EXEC_TIME', 5);
define( 'ALLOW_XSAF', TRUE );

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
		 	foreach ($json_import['autoblogs'] as $value) {
		 		
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
					$foldername = urlToFolderSlash($siteurl);

					$errors = createAutoblog($sitetype, $sitename, $siteurl, $rssurl);
					foreach( $errors AS $value) {
						if( DEBUG )
							echo '<p>'. $value .'</p>';
					}
					if( empty($errors) && DEBUG ) {
						echo '<p>autoblog '. $sitename .' crée avec succès (DL DB : '. var_dump($get_remote_db) .' - DL media : '. var_dump($get_remote_media) .') : '. $foldername .'</p>';
						if( !ALLOW_REMOTE_DB_DL && !ALLOW_REMOTE_MEDIA_DL )
							echo '<iframe width="1" height="1" frameborder="0" src="'. $foldername .'/index.php"></iframe>';
					}

					/* ============================================================================================================================================================================== */
					/* récupération de la DB distante */
					if($get_remote_db == true && ALLOW_REMOTE_DB_DL ) {	
				        $remote_db = str_replace("?export", $foldername."/articles.db", $xsafremote); 
				        copy($remote_db, './'. $foldername .'/articles.db');         
				    }

					if($get_remote_media == true && ALLOW_REMOTE_MEDIA_DL ) {
						$remote_media=str_replace("?export", $foldername."/?media", $xsafremote);
						$json_media_import = file_get_contents($remote_media);
						if(!empty($json_media_import))
							{
							mkdir('./'.$foldername.'/media/');
							$json_media_import = json_decode($json_media_import, true);
							$media_path=$json_media_import['url'];
							if(!empty($json_media_import['files'])) {
								foreach ($json_media_import['files'] as $value)	{
									copy($media_path.$value, './'.$foldername.'/media/'.$value);
								}
							}
						}
					}

					/* ============================================================================================================================================================================== */
					//TODO : tester si articles.db est une DB valide
					//$to_update[] = serverUrl().preg_replace("/(.*)\/(.*)$/i","$1/".$foldername , $_SERVER['SCRIPT_NAME']); // url of the new autoblog
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
	
/* And now, the XSAF links to be imported, with maximal execusion time for import in second ! 
	You should add only trusted sources. */
$autoblog_farm = array( 
	'https://raw.github.com/mitsukarenai/xsaf-bootstrap/master/3.json' /*,
	'https://www.ecirtam.net/autoblogs/?export',
	'https://autoblog.suumitsu.eu/?export', */
);
if( DEBUG ) echo '<html><body>';
if( ALLOW_XSAF ) {
	foreach( $autoblog_farm AS $value ) {
		if( !empty($value) )
			xsafimport($value, EXEC_TIME);
	}
}
elseif( DEBUG )
	echo "<p>XSAF désactivé. Positionnez la variable ALLOW_XSAF à TRUE dans le fichier xsaf3.php pour l'activer.</p>";

if(DEBUG) {
	echo "<p>XSAF import finished</p>";
}
if( DEBUG ) echo '</body></html>';
?>
