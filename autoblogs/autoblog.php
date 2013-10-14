<?php
/*
    VroumVroumBlog 0.3.0
    This blog automatically publishes articles from an external RSS 2.0 or ATOM feed.

    Requirement for the source RSS feed:
    - Source feed MUST be a valid RSS 2.0, RDF 1.0 or ATOM 1.0 feed.
    - Source feed MUST be valid UTF-8
    - Source feed MUST contain article body

    This program is public domain. COPY COPY COPY!
*/
$vvbversion = '0.3.0';
if (!version_compare(phpversion(), '5.3.0', '>='))
    die("This software requires PHP version 5.3.0 at least, yours is ".phpversion());

if (!class_exists('SQLite3'))
    die("This software requires the SQLite3 PHP extension, and it can't be found on this system!");

libxml_disable_entity_loader(true);

// Config and data file locations

if (file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
}
//else die("Configuration file not found.");

if (file_exists(__DIR__ . '/../functions.php')){
    require_once __DIR__ . '/../functions.php';
}
else die("Functions file not found.");

if (!defined('ROOT_DIR'))
    define('ROOT_DIR', __DIR__);

if (!defined('CONFIG_FILE'))        define('CONFIG_FILE', ROOT_DIR . '/vvb.ini');
if (!defined('ARTICLES_DB_FILE'))   define('ARTICLES_DB_FILE', ROOT_DIR . '/articles.db');
if (!defined('MEDIA_DIR'))          define('MEDIA_DIR', ROOT_DIR . '/media');

if (!defined('LOCAL_URL'))
{
    // Automagic URL discover
    define('LOCAL_URL', 'http' . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' ? 's' : '')."://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}");
}

if (!defined('LOCAL_URI'))
{
    // filename
    define('LOCAL_URI', (basename($_SERVER['SCRIPT_FILENAME']) == 'index.php' ? '' : basename($_SERVER['SCRIPT_FILENAME'])) . '?');
}

if (!function_exists('__'))
{
    // Translation?
    function __($str)
    {
        if ($str == '_date_format')
            return '%A %e %B %Y at %H:%M';
        else
            return $str;
    }
}

// ERROR MANAGEMENT

class VroumVroum_User_Exception extends Exception {}

class VroumVroum_Feed_Exception extends Exception
{
    static public function getXMLErrorsAsString($errors)
    {
        $out = array();

        foreach ($errors as $error)
        {
            $return  = $xml[$error->line - 1] . "\n";
            $return .= str_repeat('-', $error->column) . "^\n";

            switch ($error->level) {
                case LIBXML_ERR_WARNING:
                    $return .= "Warning ".$error->code.": ";
                    break;
                 case LIBXML_ERR_ERROR:
                    $return .= "Error ".$error->code.": ";
                    break;
                case LIBXML_ERR_FATAL:
                    $return .= "Fatal Error ".$error->code.": ";
                    break;
            }

            $return .= trim($error->message) .
                       "\n  Line: ".$error->line .
                       "\n  Column: ".$error->column;

            if ($error->file) {
                $return .= "\n  File: ".$error->file;
            }

            $out[] = $return;
        }

        return $out;
    }
}

error_reporting(E_ALL);

function exception_error_handler($errno, $errstr, $errfile, $errline )
{
    // For @ ignored errors
    if (error_reporting() === 0) return;
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
}

function exception_handler($e)
{
    if ($e instanceOf VroumVroum_User_Exception)
    {
        echo '<h3>'.$e->getMessage().'</h3>';
        exit;
    }

    $error = "Error happened!\n\n".
        $e->getCode()." - ".$e->getMessage()."\n\nIn: ".
        $e->getFile() . ":" . $e->getLine()."\n\n";

    if (!empty($_SERVER['HTTP_HOST']))
        $error .= 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']."\n\n";

    $error .= $e->getTraceAsString();
    //$error .= print_r($_SERVER, true);

    echo $error;
    exit;
}

set_error_handler("exception_error_handler");
set_exception_handler("exception_handler");

// CONFIGURATION

class VroumVroum_Config
{
    public $site_type = '';
    public $site_title = '';
    public $site_description = '';
    public $site_url = '';
    public $feed_url = '';
    public $articles_per_page = 10;
    public $update_interval = 3600;
    public $update_timeout = 10;

    public function __construct()
    {
        if (!file_exists(CONFIG_FILE))
            throw new VroumVroum_User_Exception("Missing configuration file '".basename(CONFIG_FILE)."'.");

        $ini = parse_ini_file(CONFIG_FILE);

        foreach ($ini as $key=>$value)
        {
            $key = strtolower($key);

            if (!property_exists($this, $key))
                continue; // Unknown config

            if (is_string($this->$key) || is_null($this->$key))
                $this->$key = trim((string) $value);
            elseif (is_int($this->$key))
                $this->$key = (int) $value;
            elseif (is_bool($this->$key))
                $this->$key = (bool) $value;
        }

        // Check that all required values are filled
        $check = array('site_type', 'site_title', 'site_url', 'feed_url', 'update_timeout', 'update_interval', 'articles_per_page');
        foreach ($check as $c)
        {
            if (!trim($this->$c))
                throw new VroumVroum_User_Exception("Missing or empty configuration value '".$c."' which is required!");
        }

    }

    public function __set($key, $value)
    {
        return;
    }
}

// BLOG

class VroumVroum_Blog
{
    protected $articles = null;
    protected $local = null;

    public $config = null;

    static public function removeHTML($str)
    {
        $str = strip_tags($str);
        $str = html_entity_decode($str, ENT_QUOTES, 'UTF-8');
        return $str;
    }

    static public function toURI($str)
    {
        $uri = self::removeHTML(trim($str));
        $uri = substr($uri, 0, 70);
        $uri = preg_replace('/[^\w\d()\p{L}]+/u', '-', $uri);
        $uri = preg_replace('/-{2,}/', '-', $uri);
        $uri = preg_replace('/^-|-$/', '', $uri);
        return $uri;
    }

    public function __construct()
    {
        $this->config = new VroumVroum_Config;

        $create_articles_db = file_exists(ARTICLES_DB_FILE) ? false : true;

        $this->articles = new SQLite3(ARTICLES_DB_FILE);

        if ($create_articles_db)
        {
            $this->articles->exec('
                CREATE TABLE articles (
                    id INTEGER PRIMARY KEY,
                    feed_id TEXT,
                    title TEXT,
                    uri TEXT,
                    url TEXT,
                    date INT,
                    content TEXT
                );
                CREATE TABLE update_log (
                    date INT PRIMARY KEY,
                    success INT,
                    log TEXT
                );
                CREATE UNIQUE INDEX feed_id ON articles (feed_id);
                CREATE INDEX date ON articles (date);
                ');
        }

        $this->articles->createFunction('countintegers', array($this, 'sql_countintegers'));
    }

    public function getLocalURL($in)
    {
        return "./?".(is_array($in) ? $in['uri'] : $in);
    }

    protected function log_update($success, $log = '')
    {
        $this->articles->exec('INSERT INTO update_log (date, success, log) VALUES (\''.time().'\', \''.(int)(bool)$success.'\',
            \''.$this->articles->escapeString($log).'\');');

        // Delete old log
        $this->articles->exec('DELETE FROM update_log WHERE date > (SELECT date FROM update_log ORDER BY date DESC LIMIT 100,1);');

        return true;
    }

    public function insertOrUpdateArticle($feed_id, $title, $url, $date, $content)
    {
        $exists = $this->articles->querySingle('SELECT date, id, title, content FROM articles WHERE feed_id = \''.$this->articles->escapeString($feed_id).'\';', true);

        if (empty($exists))
        {
            $uri = self::toURI($title);

            if ($this->articles->querySingle('SELECT 1 FROM articles WHERE uri = \''.$this->articles->escapeString($uri).'\';'))
            {
                $uri = date('Y-m-d-') . $uri;
            }

                $content = $this->mirrorMediasForArticle($content, $url);

            $this->articles->exec('INSERT INTO articles (id, feed_id, title, uri, url, date, content) VALUES (NULL,
                \''.$this->articles->escapeString($feed_id).'\', \''.$this->articles->escapeString($title).'\',
                \''.$this->articles->escapeString($uri).'\', \''.$this->articles->escapeString($url).'\',
                \''.(int)$date.'\', \''.$this->articles->escapeString($content).'\');');

            $id = $this->articles->lastInsertRowId();

            $title = self::removeHTML($title);
            $content = self::removeHTML($content);

        }
        else
        {
            // Doesn't need update
            if ($date == $exists['date'] && $content == $exists['content'] && $title == $exists['title'])
            {
                return false;
            }

            $id = $exists['id'];

            if ($content != $exists['content'])
                $content = $this->mirrorMediasForArticle($content, $url);

            $this->articles->exec('UPDATE articles SET title=\''.$this->articles->escapeString($title).'\',
                url=\''.$this->articles->escapeString($url).'\', content=\''.$this->articles->escapeString($content).'\',
                date=\''.(int)$date.'\' WHERE id = \''.(int)$id.'\';');

            $title = self::removeHTML($title);
            $content = self::removeHTML($content);

        }

        return $id;
    }

    public function mustUpdate()
    {
        if (isset($_GET['update']))
            return true;

        $last_update = $this->articles->querySingle('SELECT date FROM update_log ORDER BY date DESC LIMIT 1;');

        if (!empty($last_update) && (int) $last_update > (time() - $this->config->update_interval))
            return false;

        return true;
    }
    
    public function mustUpdateXsaf()
    {          
        return file_exists('import.json');
    }

    protected function _getStreamContext()
    {
        return stream_context_create(
            array(
                'http'  =>  array(
                    'method'    =>  'GET',
                    'timeout'   =>  $this->config->update_timeout,
                    'header'    =>  "User-Agent: Mozilla/5.0 (X11; Linux x86_64; rv:20.0; Autoblogs; +https://github.com/mitsukarenai/Projet-Autoblog/) Gecko/20100101 Firefox/20.0\r\n",
                )
            )
        );
    }

    public function update()
    {
        if (!$this->mustUpdate())
            return false;

        try {
            $body = file_get_contents($this->config->feed_url, false, $this->_getStreamContext());
        }
        catch (ErrorException $e)
        {
            $this->log_update(false, $e->getMessage() . "\n\n" . (!empty($http_response_header) ? implode("\n", $http_response_header) : ''));
            throw new VroumVroum_Feed_Exception("Can't retrieve feed: ".$e->getMessage());
        }

        libxml_use_internal_errors(true);
        $xml = @simplexml_load_string($body);

        if (!$xml)
        {
            $errors = VroumVroum_Feed_Exception::getXMLErrorsAsString(libxml_get_errors());
            $this->log_update(false, implode("\n", $errors) . "\n\n" . $body);
            throw new VroumVroum_Feed_Exception("Feed is invalid - XML error: ".implode(" - ", $errors));
        }

        $updated = 0;
        $this->articles->exec('BEGIN TRANSACTION;');

        if (isset($xml->entry)) // ATOM feed
        {
            foreach ($xml->entry as $item)
            {
                $date = isset($item->published) ? (string) $item->published : (string) $item->updated;
                $guid = !empty($item->id) ? (string)$item->id : (string)$item->link['href'];
                
                if( count($item->content->children()) > 0 ) $content = (string)$item->content->asXML();
                else $content = (string)$item->content;
                
                $id = $this->insertOrUpdateArticle($guid, (string)$item->title,
                    (string)$item->link['href'], strtotime($date), $content );

                if ($id !== false)
                    $updated++;
            }
        }
        elseif (isset($xml->item)) // RSS 1.0 /RDF
        {
            foreach ($xml->item as $item)
            {
                $guid = (string) $item->attributes('http://www.w3.org/1999/02/22-rdf-syntax-ns#')->about ?: (string)$item->link;
                $date = (string) $item->children('http://purl.org/dc/elements/1.1/')->date;

                $id = $this->insertOrUpdateArticle($guid, (string)$item->title, (string)$item->link,
                    strtotime($date), (string) $item->children('http://purl.org/rss/1.0/modules/content/'));

                if ($id !== false)
                    $updated++;
            }
        }
        elseif (isset($xml->channel->item)) // RSS 2.0
        {
            foreach ($xml->channel->item as $item)
            {
                $content = (string) $item->children('http://purl.org/rss/1.0/modules/content/');
                $guid = !empty($item->guid) ? (string) $item->guid : (string) $item->link;

                if (empty($content) && !empty($item->description))
                    $content = (string) $item->description;

                $id = $this->insertOrUpdateArticle($guid, (string)$item->title, (string)$item->link,
                    strtotime((string) $item->pubDate), $content);

                if ($id !== false)
                    $updated++;
            }
        }
        else
        {
            throw new VroumVroum_Feed_Exception("Unknown feed type?!");
        }

        $this->log_update(true, $updated . " elements updated");

        $this->articles->exec('END TRANSACTION;');

        return $updated;
    }
    
    public function updateXsaf() {
        if($this->mustUpdateXsaf()) {
    	    $json = json_decode(file_get_contents('import.json'), true);
    		$count = count($json['files']);
    		file_put_contents('import.lock', $count); /* one-process locking */
    		$remoteurl = $json['url'];
    		if (!file_exists('media'))  { 
                mkdir('media'); 
            }
    		$time = time();
    		$maxtime = $time + 3;   /* max exec time: 3 seconds */
            
    		while ($time <= $maxtime) {
    			$file = array_shift($json['files']);  /* get first element while unstacking */
    			if(!empty($file)) { 
                    $this->_copy($remoteurl.$file, "media/$file"); 
                    file_put_contents('import.json', json_encode($json)); 
                }
    			else { 
                    unlink('import.json'); 
                    break; 
                }  /* first element empty: import finished */
    			$time = time();
			}            
    		unlink('import.lock');
		}
    }

    public function listArticlesByPage($page = 1)
    {
        $nb = $this->config->articles_per_page;
        $begin = ($page - 1) * $nb;
        $res = $this->articles->query('SELECT * FROM articles ORDER BY date DESC LIMIT '.(int)$begin.','.(int)$nb.';');

        $out = array();

        while ($row = $res->fetchArray(SQLITE3_ASSOC))
        {
            $out[] = $row;
        }

        return $out;
    }

    public function listLastArticles()
    {
        return array_merge($this->listArticlesByPage(1), $this->listArticlesByPage(2));
    }

    public function countArticles()
    {
        return $this->articles->querySingle('SELECT COUNT(*) FROM articles;');
    }

    public function getArticleFromURI($uri)
    {
        return $this->articles->querySingle('SELECT * FROM articles WHERE uri = \''.$this->articles->escapeString($uri).'\';', true);
    }

    public function sql_countintegers($in)
    {
        return substr_count($in, ' ');
    }

    public function searchArticles($query)
    {
        $res = $this->articles->query('SELECT id, uri, title, content
            FROM articles
            WHERE content LIKE \'%'.$this->articles->escapeString($query).'%\'
            OR title LIKE \'%'.$this->articles->escapeString($query).'%\'
            ORDER BY id DESC
            LIMIT 0,100;');

        $out = array();

        while ($row = $res->fetchArray(SQLITE3_ASSOC))
        {
            $row['url'] = $this->getLocalURL($this->articles->querySingle('SELECT uri FROM articles WHERE id = \''.(int)$row['id'].'\';'));
            $out[] = $row;
        }

        return $out;
    }

    public function mirrorMediasForArticle($content, $url)
    {
        if (!file_exists(MEDIA_DIR))
        {
            mkdir(MEDIA_DIR);
        }

        $schemes = array('http', 'https');
		$extensions = explode(',', preg_quote('jpg,jpeg,png,apng,gif,svg,pdf,odt,ods,epub,webp,wav,mp3,ogg,aac,wma,flac,opus,mp4,webm', '!'));
        $extensions = implode('|', $extensions);

        $from = parse_url($url);
        if( isset($from['path']) ) { // not exist if http://exemple.com
            $from['path'] = preg_replace('![^/]*$!', '', $from['path']);
        }else{
            $from['path'] = '';
        }

        preg_match_all('!(src|href)\s*=\s*[\'"]?([^"\'<>\s]+\.(?:'.$extensions.')[\'"])[\'"]?!i', $content, $match, PREG_SET_ORDER);

        foreach ($match as $m)
        {
            $url = parse_url(substr($m[2], 0, -1));

            if (empty($url['scheme']))
                $url['scheme'] = $from['scheme'];

            if (empty($url['host']))
                $url['host'] = $from['host'];

            if (!in_array(strtolower($url['scheme']), $schemes))
                continue;

            if ($url['path'][0] != '/')
                $url['path'] = $from['path'] . $url['path'];

            $filename = basename($url['path']);
            $url = $url['scheme'] . '://' . $url['host'] . $url['path'];

            $filename = substr(sha1($url), -8) . '.' . substr(preg_replace('![^\w\d_.-]!', '', $filename), -64);
            $copied = false;

            if (!file_exists(MEDIA_DIR . '/' . $filename))
            {
                try {
                    $copied = $this->_copy($url, MEDIA_DIR . '/' . $filename);
                }
                catch (ErrorException $e)
                {
                    // Ignore copy errors
                }
            }
                $content = str_replace($m[0], $m[1] . '="'.'media/'.$filename.'" data-original-source="'.$url.'"', $content);
        }
        return $content;
    }
    
    public function getXsafCounter() {
        if($this->mustUpdateXsaf()) {
            $json = json_decode(file_get_contents('import.json'), true);
            return count($json['files']);
        }
    }

    /* copy() is buggy with http streams and safe_mode enabled (which is bad), so here's a workaround */
    protected function _copy($from, $to)
    {
        $in = fopen($from, 'r', false, $this->_getStreamContext());
        $out = fopen($to, 'w', false);
        $size = stream_copy_to_stream($in, $out);
        fclose($in);
        fclose($out);
        return $size;
    }
}

// DISPLAY AND CONTROLLERS

$vvb = new VroumVroum_Blog;
$config = $vvb->config;
$site_type = escape($config->site_type);

if (isset($_GET['feed'])) // FEED
{
    header('Content-Type: application/atom+xml; charset=UTF-8');
    echo '<?xml version="1.0" encoding="UTF-8"?>
	<feed xmlns="http://www.w3.org/2005/Atom" xmlns:thr="http://purl.org/syndication/thread/1.0" xml:lang="fr-FR">
        <title type="text">'.escape($config->site_title).'</title>
        <subtitle type="text">'.escape(html_entity_decode(strip_tags($config->site_description), ENT_COMPAT, 'UTF-8')).'</subtitle>
		<updated>'.date(DATE_ATOM, filemtime(ARTICLES_DB_FILE)).'</updated>
        <link rel="alternate" type="text/html" href="'.str_replace('?feed./', '', LOCAL_URL).'" />
		<id>'.LOCAL_URL.'</id>
		<link rel="self" type="application/atom+xml" href="'.LOCAL_URL.'" />
		<generator uri="https://github.com/mitsukarenai/Projet-Autoblog" version="3">Projet Autoblog</generator>';

    foreach($vvb->listLastArticles() as $art)
    {
        echo '
            <entry>
					<author>
					<name>'.escape($config->site_title).'</name>
					<uri>'.escape($config->site_url).'</uri>
					</author>
                <title type="html"><![CDATA['.escape($art['title']).']]></title>
				<link rel="alternate" type="text/html" href="'.str_replace('?feed', '?', LOCAL_URL).urlencode(str_replace('./?', '', $vvb->getLocalURL($art))).'" />
                <id>'.str_replace('?feed', '?', LOCAL_URL).urlencode(str_replace('./?', '', $vvb->getLocalURL($art))).'</id>
                <updated>'.date(DATE_ATOM, $art['date']).'</updated>

                <content type="html">
                    <![CDATA[(<a href="'.escape($art['url']).'">source</a>)<br />'.escape_content($art['content']).']]>
                </content>
            </entry>';
    }

    echo '
    </feed>';
    exit;
}

if (isset($_GET['opml'])) // OPML
{
    //header('Content-Type: application/octet-stream');
    header('Content-type: text/xml');
	header('Content-Disposition: attachment; filename="'.escape($config->site_title).'.xml"');
    $opmlfile = new SimpleXMLElement('<opml></opml>');
    $opmlfile->addAttribute('version', '1.0');
    $opmlhead = $opmlfile->addChild('head');
    $opmlhead->addChild('title', escape($config->site_title));
    $opmlhead->addChild('dateCreated', date('r', time()));
    $opmlbody = $opmlfile->addChild('body');
    $outline = $opmlbody->addChild('outline');
    $outline->addAttribute('title', escape($config->site_title));
    $outline->addAttribute('text', escape($config->site_type));
    $outline->addAttribute('htmlUrl', escape($config->site_url));
    $outline->addAttribute('xmlUrl', escape($config->feed_url));

    echo $opmlfile->asXML();
    exit;
}

if (isset($_GET['media'])) // MEDIA
{
    header('Content-Type: application/json');
    if(is_dir(MEDIA_DIR))
    {
		$url = str_replace('?media', 'media/', LOCAL_URL);
        $files = scandir(MEDIA_DIR);
        unset($files[0]); // .
        unset($files[1]); // ..
        echo json_encode(array("url"=> $url, "files" => $files));
    }
    exit;
}

if (isset($_GET['update']))
{
    $_SERVER['QUERY_STRING'] = '';
}

// CONTROLLERS
$search = !empty($_GET['q']) ? trim($_GET['q']) : '';
$article = null;

if (!$search && !empty($_SERVER['QUERY_STRING']) && !is_numeric($_SERVER['QUERY_STRING']))
{
    $uri = rawurldecode($_SERVER['QUERY_STRING']);
    $article = $vvb->getArticleFromURI($uri);

    if (!$article)
    {
        header('HTTP/1.1 404 Not Found', true, 404);
    }
}

//  common CSS
$css='* { margin: 0; padding: 0; }
      body { font-family:sans-serif; background-color: #efefef; padding: 1%; color: #333; }
      img { max-width: 100%; height: auto; }
      a { text-decoration: none; color: #000;font-weight:bold; } 
      body > header a { text-decoration: none; color: #000;font-weight:bold; }
      body > header { text-align:center; padding: 30px 3%; max-width:70em;margin:0 auto; }
      body > article > header { margin-bottom: 1em; }
      body > article > header h2 a:hover { color:#403976; }
      body > article h4 { font-weight: normal; font-size: small; color: #666; }
      body > article .source a { color: #666; }
      body > header > form { float:right; }
      body > header > form input { }
      body > nav {  background-color:white;padding: 12px 10px 12px 10px;border:1px solid #aaa;max-width:70em;margin:1em auto;box-shadow:0px 5px 7px #aaa; }
      body > nav strong { font-size: 1.2em; color: #333; }
      body > nav a { color:#000; margin: 0 0.5em; }
      body > nav a:hover { color:#333; }
      body > footer a { color:#000; }
      body > footer a:hover { color:#333; }
      .content ul, .content ol { margin-left: 2em; }
      .content h1, .content h2, .content h3, .content h4, .content h5, .content h6,
      .content ul, .content ol, .content p, .content object, .content div, .content blockquote,
      .content dl, .content pre { margin-bottom: 0.8em; }
      .content pre, .content blockquote { background: #ddd; border: 1px solid #999; padding: 0.2em; max-width: 100%; overflow: auto; }
      .content h1 { font-size: 1.5em; }
      .content h2 { font-size: 1.4em;color:#000; }
      .result h3 a { color: darkblue; text-decoration: none; text-shadow: 1px 1px 1px #fff; }
      #error { position: fixed; top: 0; left: 0; right: 0; padding: 1%; background: #fff; border-bottom: 2px solid red; color: darkred; }
';

switch($site_type) {
    case 'microblog':
    case 'twitter':
        $css .= "\n".'      /* twitter/microblog style */
      body > header h1 a { color: #333;font-size:40pt;text-shadow: #ccc 0px 5px 5px; }
      body > article > header h2 { width: 10em;white-space: nowrap;overflow: hidden;text-overflow: ellipsis;font-size: 0.7em;margin: 0; color:#333; text-shadow: 1px 1px 1px #fff; }
      body > article > header h2 a { color:#333; text-decoration:none; }
      body > article { background-color:white;padding: 12px 10px 33px;border:1px solid #aaa;max-width:70em;margin:0 auto;box-shadow:0px 5px 7px #aaa; }
      body > article .source { font-size: 0.8em; color: #666; }
      body > footer { margin-top:1em;text-align:center; font-size: small; color:#333; clear: both; }
      .content {font-size:0.9em;white-space: nowrap;overflow: hidden;text-overflow: ellipsis;}';
        break;
    case 'shaarli':
        $css .= "\n".'      /* shaarli style */
      body > header h1 a { color: #333;font-size:40pt;text-shadow: #ccc 0px 5px 5px; }
      body > article > header title h2 { margin: 0; color:#333; text-shadow: 1px 1px 1px #fff; }
      body > article > header h2 a { color:#000; text-decoration:none; }
      body > article { background-color:white;padding: 12px 10px 33px;border:1px solid #aaa;max-width:70em;margin:1em auto;box-shadow:0px 5px 7px #aaa; }
      body > article footer.source { margin-top:1em;font-size: 0.8em; color: #666; }
      body > footer { text-align:center; font-size: small; color:#333; clear: both; }';
        break;
    case 'generic':
    case 'youtube':
    default:
        $css .= "\n".'      /* youtube style */
      body > header h1 a { color: #333;font-size:40pt;text-shadow: #ccc 0px 5px 5px;text-transform:uppercase; }
      body > article > header h2 { margin: 0; color:#333; text-shadow: 1px 1px 1px #fff; }
      body > article > header h2 a { color:#000; text-decoration:none; }
      body > article footer.source { font-size: 0.8em; color: #666; }
      body > article { background-color:white;padding: 12px 10px 33px;border:1px solid #aaa;max-width:70em;margin:1em auto;box-shadow:0px 5px 7px #aaa; }
      body > footer { text-align:center; font-size: small; color:#333; clear: both; }';
}

// HTML HEADER
echo '<!DOCTYPE html>
<html lang="fr" dir="ltr">
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>'.escape($config->site_title).'</title>
    <link rel="canonical" href="'.escape($config->site_url).'">
    <link rel="alternate" type="application/atom+xml" title="'.__('ATOM Feed').'" href="?feed">
    <style type="text/css" media="screen,projection">
      '.$css.'
    </style>';
    if( $vvb->mustUpdateXsaf()) {
      echo '    <meta http-equiv="Refresh" content="1">';
    }
echo '
  </head>
  <body>
    <header>
      <h1><a href="../../" style="font-size:0.8em;">PROJET AUTOBLOG'. (strlen(HEAD_TITLE) > 0 ? ' ~ '. HEAD_TITLE : '') .'</a></h1>
      <hr />
      <h1><a href="./">'.escape($config->site_title).'</a></h1>';
       
       if (!empty($config->site_description))
         echo '
      <p>'.$config->site_description.'</p>
      <p><a href="../../">&lArr; retour index</a></p>';

echo '
      <form method="get" action="'.escape(LOCAL_URL).'">
        <input type="search" name="q" value="'.escape($search).'" />
        <input type="submit" value="'.__('Search').'" />
      </form>
    </header>
';

if( $vvb->mustUpdateXsaf()) {
    echo '
    <article>
      <header>
        <h2>'.__('Update').'</h2>
      </header>
      <div class="content" id="update">
        '.__('Import running: '). $vvb->getXsafCounter() . __(' files remaining').'<br>
        '.__('The page should refresh every second. If not, <a href="javascript:window.location.reload()">refresh manually</a>.').'
      </div>
    </article>';
}
elseif ($vvb->mustUpdate())
{
    echo '
    <article>
      <header>
        <h2>'.__('Update').'</h2>
      </header>
      <div class="content" id="update">
        '.__('Updating database... Please wait.').'
      </div>
    </article>';
}

if (!empty($search))
{
    $results = $vvb->searchArticles($search);
    $text = sprintf(__('<strong>%d</strong> results for <em>%s</em>'), count($results), escape($search));
    echo '
    <article id="results">
      <header>
        <h2>'.__('Search').'</h2>
        '.$text.'
      </header>
    <article>';

    foreach ($results as $art)
    {
        echo '
    <article class="result">
      <header>
        <h3><a href="./?'.escape($art['uri']).'">'.escape($art['title']).'</a></h3>
      </header>
      <p>'.$art['content'].'</p>
    </article>';
    }
}
elseif (!is_null($article))
{
    if (!$article)
    {
      echo '
    <article>
      <header>
        <h2>'.__('Not Found').'</h2>
        '.(!empty($uri) ? '<p><tt>'.escape($vvb->getLocalURL($uri)) . '</tt></p>' : '').'
        '.__('Article not found.').'
      </header>
    </article>';
    }
    else
    {
      display_article($article);
    }
}
else
{
    if (!empty($_SERVER['QUERY_STRING']) && is_numeric($_SERVER['QUERY_STRING']))
        $page = (int) $_SERVER['QUERY_STRING'];
    else
        $page = 1;

    $list = $vvb->listArticlesByPage($page);

    foreach ($list as $article)
    {
        display_article($article);
    }

    $max = $vvb->countArticles();
    if ($max > $config->articles_per_page)
    {
        echo "\n".'    <nav>'."\n";

        if ($page > 1)
            echo '      <a href="'.$vvb->getLocalURL($page - 1).'">&larr; '.__('Newer').'</a>'."\n";

        $last = ceil($max / $config->articles_per_page);
        for ($i = 1; $i <= $last; $i++)
        {
            echo '      <a href="'.$vvb->getLocalURL($i).'">'.($i == $page ? '<strong>'.$i.'</strong>' : $i).'</a>'."\n";
        }

        if ($page < $last)
            echo '      <a href="'.$vvb->getLocalURL($page + 1).'">'.__('Older').' &rarr;</a>'."\n";

        echo '    </nav>';
    }
}

echo '
    <footer>
      <p>Propulsé par <a href="https://github.com/mitsukarenai/Projet-Autoblog">Projet Autoblog '.$vvbversion.'</a> - <a href="?feed">'.__('ATOM Feed').'</a></p>
      <p>'.__('Download:').' <a href="./'.basename(CONFIG_FILE).'">'.__('configuration').'</a> (<a href="?opml">OPML</a>)
        — <a href="./'.basename(ARTICLES_DB_FILE).'">'.__('articles').'</a></p>
      <p><a href="./?media">'.__('Media export').' <sup> JSON</sup></a></p>
    </footer>';

if( $vvb->mustUpdateXsaf() ) {
    try {
        ob_end_flush();
        flush();
    }
    catch (Exception $e)
    {
        // Silent, not critical
    }

    try {
        $updated = $vvb->updateXsaf();
    }
    catch (VroumVroum_Feed_Exception $e)
    {
        echo '
    <div id="error">
      '.escape($e->getMessage()).'
    </div>';
        $updated = 0;
    }
}
elseif ($vvb->mustUpdate())
{
    try {
        ob_end_flush();
        flush();
    }
    catch (Exception $e)
    {
        // Silent, not critical
    }

    try {
        $updated = $vvb->update();
    }
    catch (VroumVroum_Feed_Exception $e)
    {
        echo '
    <div id="error">
            '.escape($e->getMessage()).'
    </div>';
        $updated = 0;
    }

    if ($updated > 0)
    {
        echo '
    <script type="text/javascript">
      window.onload = function () {
        document.getElementById("update").innerHTML = "'.__('Update complete!').' <a href=\\"#reload\\" onclick=\\"window.location.reload();\\">'.__('Click here to reload this webpage.').'</a>";
      };
    </script>';
    }
    else
    {
        echo '
     <script type="text/javascript">
       window.onload = function () {
         document.body.removeChild(document.getElementById("update").parentNode);
       };
     </script>';
    }
}

echo '
  </body>
</html>';


function escape_content($str)
{
    $str = preg_replace('!<\s*(style|script|link)!', '&lt;\\1', $str);
    $str = str_replace('="media/', '="./media/', $str);
    return $str;
}

// ARTICLE HTML CODE
function display_article($article)
{
    global $vvb, $config;
    echo '
    <article>
      <header>
        <h2><a href="'.$vvb->getLocalURL($article).'">'.escape($article['title']).'</a></h2>
        '.strftime(__('_date_format'), $article['date']).'
      </header>
      <div class="content">'.escape_content($article['content']).'</div>
      <footer class="source"><p>'.__('Source:').' <a href="'.escape($article['url']).'">'.escape($article['url']).'</a></p></footer>
    </article>';
}

?>
