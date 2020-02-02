<?php
require '../../../../../../autoload.php';
require '../../../../functions.php';

ensureSession();
$session = getSession();
$api = createWebApi($session);
ensureAuthorizedUser($api);

beginPage();
createMenu( mkMenuItemShowPlaylists($api)
          , mkMenuItemShowPlaylistTracks($api)
          , mkMenuItemNewPlaylist($api)
          , mkMenuItemNewPlaylistCreated($api)
          );
beginContent();
try {
  $new_playlist_id = fromGET('new_playlist_id');
  $new_playlist = $api->getPlaylist($new_playlist_id);
  ?>
  <div>
  New playlist added to your Spotify: <a href="/app/insert-ding/show-tracks/?playlist_id=<?php echo($new_playlist_id); ?>"><?php echo($new_playlist->name); ?></a>
  </div>
<?php
}
catch (Exception $e) {
  showError($e->getMessage());
}
endContent();
endPage();
updateTokens($session);
?>
