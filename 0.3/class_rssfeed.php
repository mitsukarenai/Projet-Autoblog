<?php
/**
 * class_rssfeed.php uses:
 * RSSFeed: This class has methods for making a RSS 2.0 feed.
 * RSSMerger: This class has the ability to merge different RSS feeds and sort them after the date the feed items were posted.
 * @author David Laurell <david.laurell@gmail.com>
 *
 * + 03/2013
 * Few changes, AutoblogRSS and FileRSSFeed
 * @author Arthur Hoaro <http://hoa.ro>
 */
class RSSFeed {
    protected $xml;
    
    /**
     * Construct a RSS feed
     */
    public function __construct() {
		$template = <<<END
<?xml version="1.0" encoding="UTF-8" ?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
</channel>
</rss>
END;
        
        $this->xml = new SimpleXMLElement($template);
    }

    /**
     * Set RSS Feed headers
     * @param $title the title of the feed
     * @param $link link to the website where you can find the RSS feed
     * @param $description a description of the RSS feed
     * @param $rsslink the link to this RSS feed
     */
    public function setHeaders($title, $link, $description, $rsslink) {
		$atomlink = $this->xml->channel->addChild("atom:link","","http://www.w3.org/2005/Atom");
        $atomlink->addAttribute("href",$rsslink);
        $atomlink->addAttribute("rel","self");
        $atomlink->addAttribute("type","application/rss+xml");
        
        $this->xml->channel->title = $title;
        $this->xml->channel->link = $link;
        $this->xml->channel->description = $description;
    }

    /**
     * Set the language of the RSS feed
     * @param $lang the language of the RSS feed
     */
    public function setLanguage($lang) {
        $this->xml->channel->addChild("language",$lang);
    }
    /**
     * Adds a picture to the RSS feed
     * @param $url URL to the image
     * @param $title The image title. Usually same as the RSS feed's title
     * @param $link Where the image should link to. Usually same as the RSS feed's link
     */
	public function setImage($url, $title, $link) {
		$image = $this->xml->channel->addChild("image");
		$image->url = $url;
		$image->title = $title;
		$image->link = $link;
	}
	/**
	 * Add a item to the RSS feed
	 * @param $title The title of the RSS feed
	 * @param $link Link to the item's url
	 * @param $description The description of the item
	 * @param $author The author who wrote this item
	 * @param $guid Unique ID for this post
	 * @param $timestamp Unix timestamp for making a date
	 */
	public function addItem($title, $link, $description, $author, $guid, $timestamp) {
	    $item = $this->xml->channel->addChild("item");
	    $item->title = $title;
	    $item->description = $description;
	    $item->link = $link;
	    $item->guid = $guid;
	    if( isset($guid['isPermaLink']))
	    	$item->guid['isPermaLink'] = $guid['isPermaLink'];
	    if( !empty( $author) )
	    	$item->author = $author;
	    $item->pubDate = date(DATE_RSS,intval($timestamp));
	}
	/**
	 * Displays the RSS feed 
	 */
	public function displayXML() {
	    header('Content-type: application/rss+xml; charset=utf-8');
	    echo $this->xml->asXML();
	    exit;
	}

    public function getXML() {
        return $this->xml;
    }
}

class RSSMerger {
    private $feeds = array();

    /**
     * Constructs a RSSmerger object
     */
    function __construct() {

    }

    /**
     * Populates the feeds array from the given url which is a rss feed
     * @param $url
     */
    function add($xml) {

        foreach($xml->channel->item as $item) {
            $item->sitetitle = $xml->channel->title;
            $item->sitelink = $xml->channel->link;
             
            preg_match("/^[A-Za-z]{3}, ([0-9]{2}) ([A-Za-z]{3}) ([0-9]{4}) ([0-9]{2}):([0-9]{2}):([0-9]{2}) ([\+|\-]?[0-9]{4})$/", $item->pubDate, $match);
            $item->time = time($match[4]+($match[6]/100),$match[5],$match[6],date("m",strtotime($match[2])),$match[1],$match[3]);

            $this->feeds[] = $item;
        }
    }
    /**
     * Comparing function for sorting the feeds
     * @param $value1
     * @param $value2
     */
    function feeds_cmp($value1,$value2) {
        if(intval($value1->time) == intval($value2->time))
            return 0;

        return (intval($value1->time) < intval($value2->time)) ? +1 : -1;
    }

    /**
     * Sorts the feeds array using the Compare function feeds_cmp
     */
    function sort() {
        usort($this->feeds,Array("RssMerger","feeds_cmp"));
    }

    /**
     * This function return the feed items.
     * @param $limit how many feed items that should be returned
     * @return the feeds array
     */
    function getFeeds($limit) {
        return array_slice($this->feeds,0,$limit);
    }
}

class FileRSSFeed extends RSSFeed {
	protected $filename;

    public function __construct($filename) {    
    	parent::__construct();	
    	$this->filename = $filename;

    	$this->load();
    }

    public function load() {
    	if ( file_exists( $this->filename )) {
    		$this->xml = simplexml_load_file($this->filename);
    	}
    }

    public function create($title, $link, $description, $rsslink) {
    	parent::setHeaders($title, $link, $description, $rsslink);
    	$this->write();    	
    }

    public function addItem($title, $link, $description, $author, $guid, $timestamp) {
    	parent::addItem($title, $link, $description, $author, $guid, $timestamp);
    	$this->write();
    }

    private function write() {
    	if ( file_exists( $this->filename )) {
    		unlink($this->filename);
    	}
       
        $outputXML = new RSSFeed();
        foreach($this->xml->channel->item as $f) {
            $item = $outputXML->addItem($f->title,$f->link,$f->description,$f->author,$f->guid, strtotime($f->pubDate));
        }
        
    	$merger = new RssMerger();
    	$merger->add($outputXML->getXML());
    	$merger->sort(); 

        unset($this->xml->channel->item);
    	foreach($merger->getFeeds(20) as $f) {
            parent::addItem($f->title,$f->link,$f->description,$f->author,$f->guid,$f->time);
        }
        
    	file_put_contents( $this->filename, $this->xml->asXML(), LOCK_EX );
    }
}

class AutoblogRSS extends FileRSSFeed {
    public function __construct($filename) {    
        parent::__construct($filename);  
    }

    public function addUnavailable($title, $folder, $siteurl, $rssurl) {
        $path = pathinfo( $_SERVER['PHP_SELF'] );
        $autobHref = 'http'.(!empty($_SERVER['HTTPS'])?'s':'').'://'.
                $_SERVER["SERVER_NAME"].':'.$_SERVER["SERVER_PORT"]. $path['dirname'].'/'.$folder;

        parent::addItem( 'L\'autoblog "'. $title.'" est indisponible', $autobHref, 
            'Autoblog: <a href="'. $autobHref .'">'.$title.'</a><br>
                Site: <a href="'. $siteurl .'">'. $siteurl .'</a><br>
                RSS: <a href="'.$rssurl.'">'.$rssurl.'</a><br>
                Folder: '. $folder ,
            'admin@'.$_SERVER['SERVER_NAME'],
            $autobHref,
            time()
        );
    }

    public function addAvailable($title, $folder, $siteurl, $rssurl) {
        $path = pathinfo( $_SERVER['PHP_SELF'] );
        $autobHref = 'http'.(!empty($_SERVER['HTTPS'])?'s':'').'://'.
                $_SERVER["SERVER_NAME"].':'.$_SERVER["SERVER_PORT"]. $path['dirname'].'/'.$folder;

        parent::addItem( 'L\'autoblog "'. $title.'" est de nouveau disponible', $autobHref, 
            'Autoblog : <a href="'. $autobHref .'">'.$title.'</a><br>
                Site: <a href="'. $siteurl .'">'. $siteurl .'</a><br>
                RSS: <a href="'.$rssurl.'">'.$rssurl.'</a><br>
                Folder: '. $folder ,
            'admin@'.$_SERVER['SERVER_NAME'],
            $autobHref,
            time()
        );
    }

    public function addCodeChanged($title, $folder, $siteurl, $rssurl, $code) {
        $path = pathinfo( $_SERVER['PHP_SELF'] );
        $autobHref = 'http'.(!empty($_SERVER['HTTPS'])?'s':'').'://'.
            $_SERVER["SERVER_NAME"].':'.$_SERVER["SERVER_PORT"]. $path['dirname'].'/'.$folder;

        parent::addItem( 'L\'autoblog "'. $title.'" a renvoyé un code imprévu', $autobHref, 
            'Code: '. $code .'<br>
                Autoblog : <a href="'. $autobHref .'">'.$title.'</a><br>
                Site: <a href="'. $siteurl .'">'. $siteurl .'</a><br>
                RSS: <a href="'.$rssurl.'">'.$rssurl.'</a><br>
                Folder: '. $folder ,
            'admin@'.$_SERVER['SERVER_NAME'],
            $autobHref,
            time()
        );
    }
    
    public function addNewAutoblog($title, $folder, $siteurl, $rssurl) {
        $path = pathinfo( $_SERVER['PHP_SELF'] );
        $autobHref = 'http'.(!empty($_SERVER['HTTPS'])?'s':'').'://'.
            $_SERVER["SERVER_NAME"].':'.$_SERVER["SERVER_PORT"]. $path['dirname'].'/'.$folder;

        parent::addItem( 'L\'autoblog "'. $title.'" a été ajouté à la ferme', $autobHref, 
            'Autoblog : <a href="'. $autobHref .'">'.$title.'</a><br>
                Site: <a href="'. $siteurl .'">'. $siteurl .'</a><br>
                RSS: <a href="'.$rssurl.'">'.$rssurl.'</a><br>
                Folder: '. $folder ,
            'admin@'.$_SERVER['SERVER_NAME'],
            $autobHref,
            time()
        );
    }
}

?>
