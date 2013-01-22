<?php
/* VroumVroumBlog 0.1.31
   This blog automatically publishes articles from an external RSS 2.0, RSS 1.0/RDF or ATOM feed.
   For more information, see: http://sebsauvage.net/steisand.me/

   This program is public domain. COPY COPY COPY !

// ==================================================================================================

VroumVroumBlog 0.1 µ1 edition
http://wiki.suumitsu.eu/doku.php?id=php:automicroblog

*/
// ==================================================================================================
// Settings:
 error_reporting(E_ALL);
 ini_set("display_errors", 1);
if (!get_cfg_var('safe_mode')) { set_time_limit(240); } // More time to download (images, source feed)
date_default_timezone_set('Europe/Paris');
$CONFIG=parse_ini_file('vvb.ini') or die('Missing or bad config file vvb.ini'); // Read config file.
$CONFIG['ARTICLES_PER_PAGE']=20;
// ==================================================================================================
/* Callback for the preg_replace_callback() function in remapImageUrls() which remaps URLs to point to local cache. 
   (src=... and href=...) */


/* Remaps image URL to point to local cache (src= and href=)
eg. src="http://toto.com/..."   --> src="?m=http://toto.com/..."
*/


/* updateFeed(): Update articles database from a RSS2.0 feed. 
   Articles deleted from the feed are not deleted from the database.
   You can force the refresh by passing ?force_the_refresh in URL.
*/
function updateFeed()
{
    global $CONFIG;
    // Only update feed if last check was > 60 minutes
    // but you can force it with force_the_refresh in GET parameters.
    if (@filemtime('store.bin')>time()-(3600) && !isset($_GET['force_the_refresh'])) { return; }

    // Read database from disk
    $feed_items=(file_exists('store.bin') ? unserialize(gzuncompress(file_get_contents('store.bin'))) : array() );

    // Read the feed and update the database.
    $xml = simplexml_load_file($CONFIG['FEED_URL']);
    if (isset($xml->entry)) // ATOM feed.
    {
        foreach ($xml->entry as $item) 
        {
            $pubDate=$item->published; if (!$pubDate) { $pubDate=$item->updated; }
            $i=array('title'=>strval($item->title),'link'=>strval($item->link['href']),'guid'=>strval($item->id),'pubDate'=>strval($pubDate),
                     'description'=>'','content'=>'');
            $i['dateiso'] = date('Ymd_His', strtotime($i['pubDate']));
            $feed_items[$i['dateiso']] = $i;
        } 
    }        
    elseif (isset($xml->item)) // RSS 1.0 /RDF
    {
        foreach ($xml->item as $item)
        {
            $guid =$item->attributes('http://www.w3.org/1999/02/22-rdf-syntax-ns#')->about;
            $date =$item->children('http://purl.org/dc/elements/1.1/')->date;
            $content = $item->children('http://purl.org/rss/1.0/modules/content/');
            $i=array('title'=>strval($item->title),'link'=>strval($item->link),'guid'=>strval($guid),'pubDate'=>strval($date),
                     'description'=>'','content'=>'');      
            $i['dateiso'] = date('Ymd_His', strtotime($i['pubDate']));
            $feed_items[$i['dateiso']] = $i;                     
        }
    }
    elseif (isset($xml->channel->item)) // RSS 2.0
    {
        foreach ($xml->channel->item as $item) 
        {
            $content = strval($item->children('http://purl.org/rss/1.0/modules/content/')); // Get <content:encoded>
            if (!$content) { $content = strval($item->description); }  // Some feeds put content in the description.
            $pubDate = $item->pubDate;
            if (!$pubDate) { $pubDate=$item->children('http://purl.org/dc/elements/1.1/')->date; }  // To read the <dc:date> tag content.  
            $i=array('title'=>strval($item->title),'link'=>strval($item->link),'guid'=>strval($item->guid),'pubDate'=>strval($pubDate),
                     'description'=>'','content'=>'');
            $i['dateiso'] = date('Ymd_His', strtotime($i['pubDate']));
            $feed_items[$i['dateiso']] = $i;
        } 
    }
    krsort($feed_items); // Sort array, latest articles first.
    file_put_contents('store.bin', gzcompress(serialize($feed_items), 9)); // Write database to disk
}

/* feed(): Returns the feed as an associative array (latest articles first).
     Key is timestamp in compact iso format (eg. '20110628_073208')
     Value is an associative array (title,link,content,pubDate...)
*/
function feed()
{
    $data=gzuncompress(file_get_contents('store.bin'));
    if ($data===FALSE) {  $feed_items=array(); } else { $feed_items = unserialize($data); }
    return $feed_items;
}

/* Remove accents (é-->e) */
function replace_accents($str) {
  $str = htmlentities($str, ENT_COMPAT, "UTF-8");
  $str = preg_replace('/&([a-zA-Z])(uml|acute|grave|circ|tilde);/','$1',$str);
  return html_entity_decode($str);
}

// Sanitize strings for use in filename or URLs
function sanitize($name)
{
    $fname=replace_accents($name);
    $replace="_";
    $pattern="/([[:alnum:]_\.-]*)/";  // The autorized characters.
    $fname=str_replace(str_split(preg_replace($pattern,$replace,$fname)),$replace,$fname);
    return $fname;
}

// Tells if a string start with a substring or not.
function startsWith($haystack,$needle,$case=true) {
    if($case){return (strcmp(substr($haystack, 0, strlen($needle)),$needle)===0);}
    return (strcasecmp(substr($haystack, 0, strlen($needle)),$needle)===0);
}
// Tells if a string ends with a substring or not.
function endsWith($haystack,$needle,$case=true) {
    if($case){return (strcmp(substr($haystack, strlen($haystack) - strlen($needle)),$needle)===0);}
    return (strcasecmp(substr($haystack, strlen($haystack) - strlen($needle)),$needle)===0);
}

/* Returns the CSS stylesheet to include in HTML document */
function css()
{
    return <<<HTML
<style type="text/css">
<!--
body { font-family:sans-serif;background-color: #efefef; }
h1 { margin: 0 0 0 0; font-size:24pt; text-shadow: 2px 2px 2px #000; /* FF3.5+, Opera 9+, Saf1+, Chrome */  }
.pagetitle {padding-bottom:1em;color:#eee;width:50%;border: 1px solid #333;background-color: #6A6A6A;text-align:center;margin:0 auto;}
h2 { font-size:small; margin:0 0 0 0; padding:0.5em;color:#333; text-shadow: 1px 1px 1px #fff; /* FF3.5+, Opera 9+, Saf1+, Chrome */ }  
.datearticle { font-size:x-small; color:#666;padding:0 0.5em 0 0.5em; }
table {width:100%}
.pagination {width:50%;margin:0 auto;background-color: #6A6A6A;border: 1px solid #333;}
.pagination a:link { padding:0.5em;color:#ccc; text-decoration:none;}
.anciens { float:left; }
.recents { float:right; }
.article {margin:0 auto;width:50%;background-color: #eee;border-bottom:1px solid #333;border-left:1px solid #333;border-right:1px solid #333;}
.search { float:right; }
.search input { border:1px solid black; color:#666; }
.powered { width:100%; text-align:center; font-size:8pt; color:#000; }
.powered a:link { color:#333; text-decoration:none;}
.sourcelink a { color:#666; text-decoration:none; }
.sourcelink a:hover { color:#403976; text-decoration:none; }

-->
</style>
HTML;
}

/* Render a single article
   $article : the article itself (associative array with title,pubDate,content,dateiso keys.)
*/
function renderArticle($article)
{
    echo '<div class="article">';
    echo '<div class="articletitle"><h2>'.$article['title'].'</h2><div class="datearticle">'.$article['pubDate'];
    if ($article['link']!='') { echo ' - <span class="sourcelink">(<a href="'.$article['link'].'">source</a>)</span>'; }
    echo '</div></div><div class="articlecontent">'.$article['content'].'</div>';
    echo '<br style="clear:both;"></div>';
}

function rssHeaderLink() { return '<link rel="alternate" type="application/rss+xml" title="RSS 2.0" href="?feed">'; }
function searchForm() { return '<div class="search"><form method="GET"><input type="text" name="s"><input type="submit" value="search"></form></div>'; }
function powered() { return '<div class="powered">Powered by <a href="http://sebsauvage.net/streisand.me/">VroumVroum-&micro;Blog</a> 0.1.31x - <a href="?feed">ATOM Feed</a><br>Download <a href="vvb.ini">config</a> <a href="store.bin">articles</a></div>'; }
function canonical_metatag($url) { return '<link rel="canonical" href="'.$url.'" />'; }

/* Show a single article
   $articleid = article identifier (eg.'20110629_010334')
*/
function showArticle($articleid)
{
    global $CONFIG;
    header('Content-Type: text/html; charset=utf-8');
    $feed=feed();if (!array_key_exists($articleid,$feed)) { die('Article not found.'); }
    $a=$feed[$articleid];
    echo '<!doctype html><html><head><title>'.$a['title'].' - '.$CONFIG['SITE_TITLE'].'</title>'.canonical_metatag($a['link']).css().rssHeaderLink().'</head><body>';    
    echo '<div class="pagetitle"><h1>'.$CONFIG['SITE_TITLE'].'</h1>'.$CONFIG['SITE_DESCRIPTION'].$CONFIG['SITE_URL'].searchForm().'</div>';       
    renderArticle($a);
    echo '<div class="pagination"><table width="100%"><tr><td><a href="?page1">See all articles</a></td></tr></table></div>'.powered().'</body></html>';
}

/* Show a list of articles, starting at a specific page.
   $page = start page. First page is page 1.
*/
function showArticles($page)
{
    global $CONFIG;
    header('Content-Type: text/html; charset=utf-8');
    $feed=feed();
    $keys=array_keys($feed);
    echo '<!doctype html><html><head><title>'.$CONFIG['SITE_TITLE'].'</title>'.canonical_metatag($CONFIG['SITE_URL']).css().rssHeaderLink().'</head><body>';
    echo '<div class="pagetitle"><h1>'.$CONFIG['SITE_TITLE'].'</h1>'.$CONFIG['SITE_DESCRIPTION'].$CONFIG['SITE_URL'].searchForm().'</div>';    
    $i = ($page-1)*$CONFIG['ARTICLES_PER_PAGE']; // Start index.
    $end = $i+$CONFIG['ARTICLES_PER_PAGE'];
    while ($i<$end && $i<count($keys))
    {
        renderArticle($feed[$keys[$i]]);    
        $i++;
    }  
    echo '<div class="pagination"><table><tr><td>';
    if ($i!=count($keys)) { echo '<div class="anciens"><a href="?page'.($page+1).'">&lt; Older</a></div>'; } 
    echo '</td><td>';
    if ($page>1) { echo '<div class="recents"><a href="?page'.($page-1).'">Newer &gt;</a></div>'; } 
    echo '</td></tr></table></div>'.powered().'</body></html>';
}

/* Search for text in articles content and title.
   $textpage = text to search.
*/
function search($text)
{
    global $CONFIG;
    header('Content-Type: text/html; charset=utf-8');
    $txt = urldecode($text); 
    echo '<html><head><title>'.$CONFIG['SITE_TITLE'].'</title>'.css().rssHeaderLink().'</head><body>';
    echo '<div class="pagetitle"><h1>'.$CONFIG['SITE_TITLE'].'</h1>'.$CONFIG['SITE_DESCRIPTION'].$CONFIG['SITE_URL'].searchForm().'</div>'; 
    echo '<div class="pagetitle">Search for <span style="font-weight:bold;color:#FFFFC9;">'.htmlspecialchars($txt).'</span> :</div>';  
    $feed=feed();
    foreach($feed as $article)
    {
        if (stripos($article['content'],$txt) || stripos($article['title'],$txt)) { renderArticle($article); }
    }
    echo '<div class="pagination"><table width="100%"><tr><td><a href="?page1">See all articles</a></td></tr></table></div>'.powered().'</body></html>';
}




/* Output the whole feed in RSS 2.0 format with article content (BIG!) */
function outputFeed()
{
    global $CONFIG;
    header('Content-Type: application/atom+xml; charset=utf-8');
    echo '<?xml version="1.0" encoding="UTF-8"?><feed xmlns="http://www.w3.org/2005/Atom"><title>'.htmlspecialchars($CONFIG['SITE_TITLE']).'</title><updated>'.date('c').'</updated><id>'.htmlspecialchars($CONFIG['SITE_URL']).'</id>';
    echo '<link rel="self" type="application/atom+xml" href="http://'.$_SERVER['SERVER_NAME'].$_SERVER['SCRIPT_NAME'].'?feed" />'."\n\n";
    $feed=feed();
    foreach($feed as $a)
    {
        echo '<entry><title>'.$a['title'].'</title><link href="'.$a['link'].'" /><id>'.$a['link'].'</id><updated>'.$a['pubDate'].'</updated>';
  	echo '<author><name>Auto-microblog</name><uri>'.$_SERVER['SERVER_NAME'].'</uri></author>';
        echo '<content> </content></entry>'."\n\n";        
    }
    echo '</feed>';
}

// ==================================================================================================
// Update feed if necessary. (you can force refresh with ?force_the_refresh in URL)
updateFeed(); 

// Handle media download requests (eg. http://myserver.com/?m=http___anotherserver.net_images_myimage.jpg)
if (startswith($_SERVER["QUERY_STRING"],'m=')) { showMedia(substr($_SERVER["QUERY_STRING"],2)); }

// Handle single article URI (eg. http://myserver.com/?20110506_224455-chit-chat)
elseif (preg_match('/^(\d{8}_\d{6})/',$_SERVER["QUERY_STRING"],$matches)) { showArticle($matches[1]); }

// Handle page URI (eg. http://myserver.com/?page5)
elseif (preg_match('/^page(\d+)/',$_SERVER["QUERY_STRING"],$matches)) { showArticles($matches[1]); }

// Handle RSS 2.0 feed request (http://myserver.com/?feed)
elseif (startswith($_SERVER["QUERY_STRING"],'feed')) { outputFeed(); }

// Handle search request (eg. http://myserver.com/?s=tuto4pc)
elseif (startswith($_SERVER["QUERY_STRING"],'s=')) { search(substr($_SERVER["QUERY_STRING"],2)); }

// Nothing ? Then render page1.
else { showArticles(1); }

// Force flush, rendered page is fully sent to browser.
flush();

// Now we've finised rendering the page and sending to the user,
// it's time for some background tasks: Are there media to download ?


exit;
?>
