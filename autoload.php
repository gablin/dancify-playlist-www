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

// Load languages
$lang = 'en';
if (isLangSet()) {
  $lang_usr = getLang();
  if (in_array($lang_usr, ['en', 'sv'])) {
    $lang = $lang_usr;
  }
  saveLang($lang);
}
require (dirname(__FILE__) . "/lang_{$lang}.php");
?>
