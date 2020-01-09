<?php
require '../autoload.php';

$session = getSession();
$api = createWebApi($session);

$user_uri = $api->me()->uri;
$playlists = $api->getUserPlaylists($user_uri);

// TODO: finish implementation
foreach ($playlists->items as $playlist) {
  echo '<a href="' . $playlist->external_urls->spotify . '">' . $playlist->name . '</a> <br>';
}

updateTokens($session);
?>