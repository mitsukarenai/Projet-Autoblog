<?php
/**
 * config.php - User configuration file
 * ---
 * If you uncomment a setting in this file, it will override default option
 * 
 * See how to configure your Autoblog farm at
 * https://github.com/mitsukarenai/Projet-Autoblog/wiki/Configuration
 **/

// define( 'LOGO', 'icon-logo.svg' );
// define( 'HEAD_TITLE', '');
// define( 'FOOTER', 'D\'après les premières versions de <a href="http://sebsauvage.net">SebSauvage</a> et <a href="http://bohwaz.net/">Bohwaz</a>.');

// define( 'ALLOW_FULL_UPDATE', TRUE );
// define( 'ALLOW_CHECK_UPDATE', TRUE );

/**
 * If you set ALLOW_NEW_AUTOBLOGS to FALSE, the following options do not matter.
 **/
// define( 'ALLOW_NEW_AUTOBLOGS', TRUE );
// define( 'ALLOW_NEW_AUTOBLOGS_BY_LINKS', TRUE );
// define( 'ALLOW_NEW_AUTOBLOGS_BY_SOCIAL', TRUE );
// define( 'ALLOW_NEW_AUTOBLOGS_BY_BUTTON', TRUE );
// define( 'ALLOW_NEW_AUTOBLOGS_BY_OPML_FILE', TRUE );
// define( 'ALLOW_NEW_AUTOBLOGS_BY_OPML_LINK', TRUE );
// define( 'ALLOW_NEW_AUTOBLOGS_BY_XSAF', TRUE );

/**
 * More about TwitterBridge : https://github.com/mitsukarenai/twitterbridge
 **/
// define( 'API_TWITTER', FALSE );

/**
 * Import autoblogs from friend's autoblog farm - Add a link to the JSON export
 **/
$friends_autoblog_farm = array(
    'https://raw.github.com/mitsukarenai/xsaf-bootstrap/master/3.json',
    // 'https://www.ecirtam.net/autoblogs/?export',
    // 'https://autoblog.suumitsu.eu/?export',
    // 'http://streisand.hoa.ro/?export',
);
?>
