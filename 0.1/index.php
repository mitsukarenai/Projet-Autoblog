<?php
/* VroumVroumBlog 0.1.32
   This blog automatically publishes articles from an external RSS 2.0, RSS 1.0/RDF or ATOM feed.
   For more information, see: http://sebsauvage.net/steisand.me/

   This program is public domain. COPY COPY COPY !
*/
// ==================================================================================================
// Settings:
error_reporting(0);  // Fail silentely.
if (!get_cfg_var('safe_mode')) { set_time_limit(240); } // More time to download (images, source feed)
date_default_timezone_set('Europe/Paris');

if (version_compare(PHP_VERSION, '5.2.0') >= 0) { libxml_disable_entity_loader(true); }

$CONFIG=parse_ini_file('vvb.ini') or die('Missing or bad config file vvb.ini'); // Read config file.
$CONFIG['ARTICLES_PER_PAGE']=10;
$CONFIG['DOWNLOAD_MEDIA_TYPES']=array('jpeg','jpg','gif','png','pdf','txt','odt'); // Media types which will be downloaded.
$CONFIG['MEDIA_TO_DOWNLOAD']=array(); // List of media to download in background.
// ==================================================================================================
/* Callback for the preg_replace_callback() function in remapImageUrls() which remaps URLs to point to local cache. 
   (src=... and href=...) */
function remap_callback($matches)
{
    global $CONFIG;
    $attr = $matches[1]; $url = $matches[2]; $srchost=parse_url($url,PHP_URL_HOST);
    if (!mediaAuthorized($url)) { return $attr.'="'.$url.'"'; } // Not authorized: do not remap URL.
    if (!file_exists('media/'.sanitize($url)) ) { $CONFIG['MEDIA_TO_DOWNLOAD'][] = $url; } // If media not present in the cache, add URL to list of media to download in background.
    return $attr.'="?m='.$url.'"'; // Return remapped URL.
}

/* Remaps image URL to point to local cache (src= and href=)
eg. src="http://toto.com/..."   --> src="?m=http://toto.com/..."
*/
function remapImageUrls($html)
{   
    return preg_replace_callback("@(src|href)=[\"\'](.+?)[\"\']@i",'remap_callback',$html);
}

/* updateFeed(): Update articles database from a RSS2.0 feed. 
   Articles deleted from the feed are not deleted from the database.
   You can force the refresh by passing ?force_the_refresh in URL.
*/
function updateFeed()
{
    global $CONFIG;
    // Only update feed if last check was > 60 minutes
    // but you can force it with force_the_refresh in GET parameters.
    if (@filemtime('store')>time()-(3600) && !isset($_GET['force_the_refresh'])) { return; }

    // Read database from disk
    $feed_items=(file_exists('store') ? unserialize(file_get_contents('store')) : array() );

    // Read the feed and update the database.
    $xml = simplexml_load_file($CONFIG['FEED_URL']);
    if (isset($xml->entry)) // ATOM feed.
    {
        foreach ($xml->entry as $item) 
        {
            $pubDate=$item->published; if (!$pubDate) { $pubDate=$item->updated; }
            $i=array('title'=>strval($item->title),'link'=>strval($item->link['href']),'guid'=>strval($item->id),'pubDate'=>strval($pubDate),
                     'description'=>'','content'=>remapImageUrls(strval($item->content)));
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
                     'description'=>strval($item->description),'content'=>remapImageUrls(strval($content)));      
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
                     'description'=>strval($item->description),'content'=>remapImageUrls($content));
            $i['dateiso'] = date('Ymd_His', strtotime($i['pubDate']));
            $feed_items[$i['dateiso']] = $i;
        } 
    }
    krsort($feed_items); // Sort array, latest articles first.
    file_put_contents('store', serialize($feed_items)); // Write database to disk
}

/* feed(): Returns the feed as an associative array (latest articles first).
     Key is timestamp in compact iso format (eg. '20110628_073208')
     Value is an associative array (title,link,content,pubDate...)
*/
function feed()
{
    $data=file_get_contents('store');
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
body { font-family:"Trebuchet MS",Verdana,Arial,Helvetica,sans-serif; font-size:10pt; background-color: #3E4B50; }
img { max-width: 100%;height: auto; }
h1 { margin: 0 0 0 0; font-size:24pt; text-shadow: 2px 2px 2px #000; /* FF3.5+, Opera 9+, Saf1+, Chrome */  }
.pagetitle
{
    padding: 10 30 10 30;
    color:#eee;
    margin-left:10%; 
    margin-right:10%;  
    border-bottom: 1px solid #aaa;    
    background-color: #6A6A6A;
    background-image: -webkit-gradient(linear, left top, left bottom, from(#6A6A6A), to(#303030)); /* Saf4+, Chrome */
    background-image: -webkit-linear-gradient(top, #6A6A6A, #303030); /* Chrome 10+, Saf5.1+ */
    background-image:    -moz-linear-gradient(top, #6A6A6A, #303030); /* FF3.6 */
    background-image:     -ms-linear-gradient(top, #6A6A6A, #303030); /* IE10 */
    background-image:      -o-linear-gradient(top, #6A6A6A, #303030); /* Opera 11.10+ */
    background-image:         linear-gradient(top, #6A6A6A, #303030);
    filter: progid:DXImageTransform.Microsoft.gradient(startColorStr='#6A6A6A', EndColorStr='#303030'); /* IE6-IE9 */
}
.pagetitle a:link { color:#bbb; text-decoration:none;}
.pagetitle a:visited { color:#bbb; text-decoration:none;}
.pagetitle a:hover { color:#FFFFC9; text-decoration:none;}
.pagetitle a:active { color:#bbb; text-decoration:none;}    
h2 { font-size:22pt; margin:0 0 0 0; color:#666; text-shadow: 1px 1px 1px #fff; /* FF3.5+, Opera 9+, Saf1+, Chrome */ }  
h2 a:link { color:#666; text-decoration:none;}
h2 a:visited { color:#666; text-decoration:none;}
h2 a:hover { color:#403976; text-decoration:none;}
h2 a:active { color:#666; text-decoration:none;}
.datearticle { font-size: 8pt; color:#666; }
.pagination 
{
    margin-left:10%; 
    margin-right:10%; 
    padding: 5 10 5 10;   
    background-color: #6A6A6A;
    background-image: -webkit-gradient(linear, left top, left bottom, from(#6A6A6A), to(#303030)); /* Saf4+, Chrome */
    background-image: -webkit-linear-gradient(top, #6A6A6A, #303030); /* Chrome 10+, Saf5.1+ */
    background-image:    -moz-linear-gradient(top, #6A6A6A, #303030); /* FF3.6 */
    background-image:     -ms-linear-gradient(top, #6A6A6A, #303030); /* IE10 */
    background-image:      -o-linear-gradient(top, #6A6A6A, #303030); /* Opera 11.10+ */
    background-image:         linear-gradient(top, #6A6A6A, #303030);
    filter: progid:DXImageTransform.Microsoft.gradient(startColorStr='#6A6A6A', EndColorStr='#303030'); /* IE6-IE9 */    
}
.pagination a:link { color:#ccc; text-decoration:none;}
.pagination a:visited { color:#ccc; text-decoration:none;}
.pagination a:hover { color:#FFFFC9; text-decoration:none;}
.pagination a:active { color:#ccc; text-decoration:none;}
.anciens { float:left; }
.recents { float:right; }
.article 
{
    margin-left:10%; 
    margin-right:10%; 
    padding:10 15 10 15;
    background-color: #cccccc;
    background-image: -webkit-gradient(linear, left top, left bottom, from(#cccccc), to(#ffffff)); /* Saf4+, Chrome */
    background-image: -webkit-linear-gradient(top, #cccccc, #ffffff); /* Chrome 10+, Saf5.1+ */
    background-image:    -moz-linear-gradient(top, #cccccc, #ffffff); /* FF3.6 */
    background-image:     -ms-linear-gradient(top, #cccccc, #ffffff); /* IE10 */
    background-image:      -o-linear-gradient(top, #cccccc, #ffffff); /* Opera 11.10+ */
    background-image:         linear-gradient(top, #cccccc, #ffffff);
    filter: progid:DXImageTransform.Microsoft.gradient(startColorStr='#cccccc', EndColorStr='#ffffff'); /* IE6-IE9 */
    border-bottom: 1px solid #888;
}
.search { float:right; }
.search input { border:1px solid black; color:#666; }
.powered { width:100%; text-align:center; font-size:8pt; color:#aaaaaa; }
.powered a:link { color:#cccccc; text-decoration:none;}
.powered a:visited { color:#cccccc; text-decoration:none;}
.powered a:hover { color:#FFFFC9; text-decoration:none;}
.powered a:active { color:#aaaaaa; text-decoration:none;}
.sourcelink a { color:#666; text-decoration:none; }
.sourcelink a:hover { color:#403976; text-decoration:none; }
@media handheld 
{
    html, body { font: 12px sans-serif; background: #fff; padding: 3px; color: #000; margin: 0; }
    img { max-width: 100%;height: auto; }
    .pagetitle { padding: 7 7 7 7; margin-left:0; margin-right:0; }
    .article { background-color: #eee; margin-left:0; margin-right:0; border-bottom: 3px solid #888; }  
    .pagination { margin-left:0; margin-right:0;}
    h1 { font-size:16pt; margin-bottom:10px;}
    h2 { font-size:14pt; line-height:120%; }
    ul { padding-left:10px; }
    blockquote{margin-left:12px; margin-right:3px; } 
    pre { width:100%; overflow:auto; }
}
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
    echo '<div class="articletitle"><h2><a href="?'.$article['dateiso'].'_'.sanitize($article['title']).'">'.$article['title'].'</a></h2><div class="datearticle">'.$article['pubDate'];
    if ($article['link']!='') { echo ' - <span class="sourcelink">(<a href="'.$article['link'].'">source</a>)</span>'; }
    echo '</div></div><div class="articlecontent">'.$article['content'].'</div>';
    echo '<br style="clear:both;"></div>';
}

function rssHeaderLink() { return '<link rel="alternate" type="application/rss+xml" title="RSS 2.0" href="?feed">'; }
function searchForm() { return '<div class="search"><form method="GET"><input type="text" name="s"><input type="submit" value="search"></form></div>'; }
function powered() { return '<div class="powered">Powered by <a href="http://sebsauvage.net/streisand.me/">VroumVroumBlog</a> 0.1.32 - <a href="?feed">RSS Feed</a><br>Download <a href="vvb.ini">config</a> <a href="store">articles</a></div>'; }
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
    echo '<html><head><title>'.$a['title'].' - '.$CONFIG['SITE_TITLE'].'</title>'.canonical_metatag($a['link']).css().rssHeaderLink().'</head><body>';    
    echo '<div class="pagetitle"><h1>'.$CONFIG['SITE_TITLE'].'</h1>'.$CONFIG['SITE_DESCRIPTION'].searchForm().'</div>';       
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
    echo '<html><head><title>'.$CONFIG['SITE_TITLE'].'</title>'.canonical_metatag($CONFIG['SITE_URL']).css().rssHeaderLink().'</head><body>';
    echo '<div class="pagetitle"><h1>'.$CONFIG['SITE_TITLE'].'</h1>'.$CONFIG['SITE_DESCRIPTION'].searchForm().'</div>';    
    $i = ($page-1)*$CONFIG['ARTICLES_PER_PAGE']; // Start index.
    $end = $i+$CONFIG['ARTICLES_PER_PAGE'];
    while ($i<$end && $i<count($keys))
    {
        renderArticle($feed[$keys[$i]]);    
        $i++;
    }  
    echo '<div class="pagination"><table width="100%"><tr><td>';
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
    echo '<div class="pagetitle"><h1>'.$CONFIG['SITE_TITLE'].'</h1>'.$CONFIG['SITE_DESCRIPTION'].searchForm().'</div>'; 
    echo '<div class="pagetitle">Search for <span style="font-weight:bold;color:#FFFFC9;">'.htmlspecialchars($txt).'</span> :</div>';  
    $feed=feed();
    foreach($feed as $article)
    {
        if (stripos($article['content'],$txt) || stripos($article['title'],$txt)) { renderArticle($article); }
    }
    echo '<div class="pagination"><table width="100%"><tr><td><a href="?page1">See all articles</a></td></tr></table></div>'.powered().'</body></html>';
}

/* Tells if a media URL should be downloaded or not.
   Input: $url = absolute URL of a media (jpeg,pdf...)
   Output: true= can download.  false= should not download (wrong host, wrong file extension)    */
function mediaAuthorized($url)
{
    global $CONFIG;
    $goodhost=false; $srchost=parse_url($url,PHP_URL_HOST);
    foreach( explode(',',$CONFIG['DOWNLOAD_MEDIA_FROM']) as $host) // Does the URL point to an authorized host ?
        { if ($srchost==$host) { $goodhost=true; } }
    if (!$goodhost) { return false; }  // Wrong host.
    $ext = pathinfo($url, PATHINFO_EXTENSION); // Get file extension (eg.'png','gif'...)
    if (!in_array(strtolower($ext),$CONFIG['DOWNLOAD_MEDIA_TYPES'])) { return false; } // Not in authorized file extensions.
    return true;
}

// Returns the MIME type corresponding to a file extension.
// (I do not trust mime_content_type() because of some dodgy hosting providers with ill-configured magic.mime file.)
function mime_type($filename)
{
    $MIME_TYPES=array('.jpg'=>'image/jpeg','.jpeg'=>'image/jpeg','.png'=>'image/png','.gif'=>'image/gif',
                     '.txt'=>'text/plain','.odt'=>'application/vnd.oasis.opendocument.text');
    foreach($MIME_TYPES as $extension=>$mime_type)  { if (endswith($filename,$extension,false)) { return $mime_type; } }
    return 'application/octet-stream'; // For an unkown extension.  
}
// Returns a media from the local cache (and download it if not available).
function showMedia($imgurl)
{
    if (!mediaAuthorized($imgurl)) { header('HTTP/1.1 404 Not Found'); return; } 
    downloadMedia($imgurl); // Will only download if necessary.
    $filename = 'media/'.sanitize($imgurl);
    header('Content-Type: '.mime_type($filename));
    readfile($filename);        
}

// Download a media to local cache (if necessary)
function downloadMedia($imgurl)
{
    $filename = 'media/'.sanitize($imgurl);
    if (!file_exists($filename) ) // Only download image if not present 
    { 
        if (!is_dir('media')) { mkdir('media',0705); file_put_contents('media/index.html',' '); }
        file_put_contents($filename, file_get_contents($imgurl,NULL, NULL, 0, 4000000)); // We download at most 4 Mb from source.
    }      
}

/* Output the whole feed in RSS 2.0 format with article content (BIG!) */
function outputFeed()
{
    global $CONFIG;
    header('Content-Type: application/xhtml+xml; charset=utf-8');
    echo '<?xml version="1.0" encoding="UTF-8"?><rss version="2.0" xmlns:content="http://purl.org/rss/1.0/modules/content/">';
    echo '<channel><title>'.htmlspecialchars($CONFIG['SITE_TITLE']).'</title><link>'.htmlspecialchars($CONFIG['SITE_URL']).'</link>';
    echo '<description></description><language></language><copyright>'.htmlspecialchars($CONFIG['SITE_URL']).'</copyright>'."\n\n";
    $feed=feed();
    foreach($feed as $a)
    {
        echo '<item><title>'.$a['title'].'</title><guid>'.$a['guid'].'</guid><link>http://'.$_SERVER["HTTP_HOST"].$_SERVER["SCRIPT_NAME"].'?'.$a['dateiso'].'_'.sanitize($a['title']).'</link><pubDate>'.$a['pubDate'].'</pubDate>';
        echo '<description><![CDATA['.$a['description'].']]></description><content:encoded><![CDATA['.$a['content'].']]></content:encoded></item>'."\n\n";        
    }
    echo '</channel></rss>';
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
ob_end_flush();
flush();

// Now we've finised rendering the page and sending to the user,
// it's time for some background tasks: Are there media to download ?
foreach($CONFIG['MEDIA_TO_DOWNLOAD'] as $url) { downloadMedia($url); }

exit;
?>