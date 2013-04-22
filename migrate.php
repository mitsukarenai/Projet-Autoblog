<?php
$dir="./";
$liste_blog = scandir($dir);
unset($liste_blog[0]);
unset($liste_blog[1]);

foreach($liste_blog as $blog) {
    if(is_dir($dir.$blog) && is_file($dir.$blog."/vvb.ini")) {
        $articles = new SQLite3($dir.$blog."/articles.db");

        $articles->exec('
            CREATE TABLE update_log (
                date INT PRIMARY KEY,
                success INT,
                log TEXT
            );
        ');

        $vals=array();
        $ini = parse_ini_file($dir.$blog."/vvb.ini");

        if(is_dir(urlToFolderWithTrailingSlash( $ini['SITE_URL'] )) || is_dir(urlToFolder( $ini['SITE_URL'] )))
            continue;

        $foldername = urlToFolderWithTrailingSlash($ini['SITE_URL']);
        mkdir( $dir . $foldername );
        mkdir( $dir . $foldername . '/media');
        recursiveMove( $dir . $blog . '/media', $dir . $foldername .'/media' );
        copy($dir . $blog . '/index.php', $dir . $foldername .'/index.php');
        copy($dir . $blog . '/articles.db', $dir . $foldername .'/articles.db');
        deleteDir($dir . $blog );

        
        if( strpos($ini['SITE_TITLE'], 'Autoblog de') !== false ) {            
            $ini['SITE_TITLE'] = preg_replace('#^Autoblog de (.*)$#', '$1', $ini['SITE_TITLE']);
        }

        switch(substr($ini['SITE_TITLE'], 0, 7)) {
            case 'twitter':
            case 'statusn':
            case 'identic':
                $ini['SITE_TYPE']="microblog";
                $ini['ARTICLES_PER_PAGE'] = "20";
                $ini['UPDATE_INTERVAL'] = "300";
                $ini['UPDATE_TIMEOUT'] = "30";
                break;          
            default:
                $ini['SITE_TYPE']="generic";
                $ini['ARTICLES_PER_PAGE'] = "5";
                $ini['UPDATE_INTERVAL'] = "3600";
                $ini['UPDATE_TIMEOUT'] = "30";
                break;
        }     

        $fp = fopen($dir.$foldername."/vvb.ini", 'w+');
        fwrite($fp, <<<EOF
[VroumVroumBlogConfig]
SITE_TYPE="{$ini['SITE_TYPE']}"
SITE_TITLE="{$ini['SITE_TITLE']}"
SITE_DESCRIPTION="source: <a href='{$ini['SITE_URL']}'>{$ini['SITE_TITLE']}</a>"
SITE_URL="{$ini['SITE_URL']}"
FEED_URL="{$ini['FEED_URL']}"
ARTICLES_PER_PAGE="{$ini['ARTICLES_PER_PAGE']}"
UPDATE_INTERVAL="{$ini['UPDATE_INTERVAL']}"
UPDATE_TIMEOUT="{$ini['UPDATE_TIMEOUT']}"
EOF
        );
        fclose($fp);
    }
}
echo "\nend ".date("d/m/Y H:i:s")."\n\n";


function NoProtocolSiteURL($url) {
    $siteurlnoprototypes = array("http://", "https://");
    $siteurlnoproto = str_replace($siteurlnoprototypes, "", $url);
    
    // Remove the / at the end of string
    if ( $siteurlnoproto[strlen($siteurlnoproto) - 1] == '/' )
        $siteurlnoproto = substr($siteurlnoproto, 0, -1);

    // Remove index.php/html at the end of string
    if( strpos($url, 'index.php') || strpos($url, 'index.html') ) {
        $siteurlnoproto = preg_replace('#(.*)/index\.(html|php)$#', '$1', $siteurlnoproto);
    }

    return $siteurlnoproto;
}

function urlToFolderWithTrailingSlash($url) {
    return sha1(NoProtocolSiteURL($url).'/');
}

function urlToFolder($url) {
    return sha1(NoProtocolSiteURL($url));
}

/* http://stackoverflow.com/questions/2082138/move-all-files-in-a-folder-to-another */
function recursiveMove($source, $dest) {
    // Get array of all source files
    $files = scandir($source);
    $delete = array(); 

    if(substr($source, strlen($source) - 1, 1) != '/') 
        $source .= '/';
    if(substr($dest, strlen($dest) - 1, 1) != '/') 
        $dest .= '/';

    // Cycle through all source files
    foreach ($files as $file) {
      if (in_array($file, array(".",".."))) continue;
      // If we copied this successfully, mark it for deletion
      if (copy($source.$file, $dest.$file)) {
        $delete[] = $source.$file;
      }
    }
    // Delete all successfully-copied files
    foreach ($delete as $file) {
      unlink($file);
    }
}

/* http://stackoverflow.com/questions/3349753/delete-directory-with-files-in-it */
function deleteDir($dirPath) {
    if (substr($dirPath, strlen($dirPath) - 1, 1) != '/') {
        $dirPath .= '/';
    }
    $files = glob($dirPath . '*', GLOB_MARK);
    foreach ($files as $file) {
        if (is_dir($file)) {
            deleteDir($file);
        } else {
            unlink($file);
        }
    }
    rmdir($dirPath);
}
?>