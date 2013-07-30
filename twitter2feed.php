<?php
/*
 * Récupération du flux RSS d'un compte Twitter
 * Passez le nom du compte en Paramètre. Exemple: http://monsite.com/twit2rss.php?name=Craftbukkit
 * 2013 - by Tronics
 */

// ----------------------------
//  Autoblog Project inclusion
// ----------------------------
if(file_exists("config.php")) require_once "config.php";
if(file_exists("functions.php")) require_once "functions.php";
if( !defined('API_TWITTER')) {header("HTTP/1.1 404 Not Found"); die('API_TWITTER is undefined: make it LOCAL in "config.php" if you want me to work for you.');}
if ('API_TWITTER' === FALSE) {header("HTTP/1.1 404 Not Found"); die('Twitter support disabled in "config.php". Sorry.');}
$api = API_TWITTER;
if ($api === 'LOCAL') { } else {header("HTTP/1.1 404 Not Found"); die('Custom twitterbridge defined in "config.php": leave me alone.');}
if(!isset($_GET['u'])) {header("HTTP/1.1 404 Not Found"); die('no username provided');}
$exclude_reply = '@'; // if you want twitter2feed to return replies too:  $exclude_reply = '';  
// ----------------------------
// Let's rock !

  $name = $_GET["u"];
  $str = file_get_contents("https://twitter.com/$name");

  $nb = preg_match_all('%<div class="tweet original-tweet(.*)'.
    'data-tweet-id="(?P<id>\d+)"(.*)'.
    '(data-retweet-id="(?P<retweetid>\d+)"(.*))?'.
    'data-screen-name="(?P<name>[^"]+)"(.*)'.
    '<img class="avatar js-action-profile-avatar" src="(?P<avatar>[^"]+)" alt="(?P<fullname>[^"]*)">(.*)'.
    'data-time="(?P<created>\d+)"(.*)'.
    '<p class="js-tweet-text tweet-text">(?P<message>.*)</p>'.
    '%sU', $str, $arr);
  
  function parsemessage($message)
  {
    $message = preg_replace('%<a href="/([^"]+)"([^>]+)>%', '<a href="https://twitter.com/$1" class="twitter_account">', $message);
    $message = preg_replace('%<s>(@|#)</s><b>([^<]+)</b>%', '$1$2', $message);
    $message = preg_replace('%<a href="http://t.co/[^"]+" rel="nofollow" dir="ltr" data-expanded-url="([^"]+)" class="twitter-timeline-link" target="_blank" title="[^"]+"%', '<a href="$1" class="twitter_link"', $message);
    $message = preg_replace('%<span class="[^"]+">([^<]+)</span>%', '$1', $message);
    $message = preg_replace('%<span class="tco-ellipsis">&nbsp;…</span>%', '', $message);

    return $message;
  }

  function parsetitle($message)
  {
    $message = preg_replace('%<a ([^>]+)>https?://([^/]+)/[^<]*</a>%', '[$2]', $message);
    $message = strip_tags($message);
    return $message;
  }

  $fullname = "?";
  $updated = "";
  if ($nb !== false)
  {
    for ($i = 0; $i < $nb; $i++)
    {
      $mname = $arr["name"][$i];
      $mfullname = $arr["fullname"][$i];
      if ($updated == "")
        $updated = date(DATE_ATOM, $arr["created"][$i]);

      if ($mname == $name)
      {
        $fullname = $mfullname;
        break;
      }
    }
  }
  
  header('Content-type: application/xml; charset=UTF-8', true);
  echo '<?xml version="1.0" encoding="utf-8"?' . '>' . PHP_EOL;
?>
<feed xml:lang="en-US" xmlns="http://www.w3.org/2005/Atom">
  <title>Twitter / <?php echo $name ?></title>
  <id>tag:twitter.com,2013:Status:<?php echo $name ?></id>
  <link type="text/html" rel="alternate" href="http://twitter.com/<?php echo $name ?>"/>
  <link type="application/atom+xml" rel="self" href="http://<?php echo $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"] ?>"></link>
  <updated><?php echo $updated ?></updated>
  <subtitle>Twitter de <?php echo $fullname ?>.</subtitle>
<?php
  if ($nb !== false)
  {
    for ($i = 0; $i < $nb; $i++)
    {
      $message = parsemessage($arr["message"][$i]);
      $mname = $arr["name"][$i];
      $mfullname = $arr["fullname"][$i];
      $avatar = $arr["avatar"][$i];
      $id = $arr["id"][$i];
      $created = date(DATE_ATOM, $arr["created"][$i]);

      $rt = "";
      if ($arr["retweetid"][$i] != "")
        $rt = "RT <a href=\"https://twitter.com/$mname\">@$mname</a> : ";

      $title = htmlspecialchars(parsetitle($rt . $message));
      
      $header = "<img src=\"$avatar\" alt=\"$mname\"/> $mfullname (<a href=\"https://twitter.com/$mname\">@$mname</a>)<br/>\r\n";
      $footer = "<br/>\r\n<a href=\"https://twitter.com/$mname/status/$id\">Afficher la conversation</a>";
      $message = "$header$rt$message$footer";
      $message = htmlspecialchars($message);
	if(substr($title, 0, 1) !== $exclude_reply) {
      echo <<<HTML
  <entry>
    <title>$title</title>
    <content type="html">$message</content>
    <id>tag:twitter.com,2013:https://twitter.com/$name/status/$id</id>
    <published>$created</published>
    <updated>$created</updated>
    <link type="text/html" rel="alternate" href="https://twitter.com/$name/status/$id"/>
    <link type="image/png" rel="image" href="$avatar"/>
    <author>
      <name>$name</name>
      <uri>https://twitter.com/$name</uri>
    </author>
  </entry>
HTML;
				}
    }
  }

?>
</feed>

