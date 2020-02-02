<?php
// Load configuration variables
require (dirname(__FILE__) . '/config.php');

// Load spotify API
$files = array( 'SpotifyWebAPIException.php'
              , 'SpotifyWebAPIAuthException.php'
              , 'Request.php'
              , 'Session.php'
              , 'SpotifyWebAPI.php'
              );
foreach ($files as $f) {
  require (dirname(__FILE__) . "/spotify-web-api-php/src/{$f}");
}

// Load functions
require (dirname(__FILE__) . '/functions.php');
?>
