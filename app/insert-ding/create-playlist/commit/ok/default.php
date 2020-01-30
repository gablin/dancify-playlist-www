<?php
require '../../../../../autoload.php';

ensureSession();
$session = getSession();
$api = createWebApi($session);
ensureAuthorizedUser($api);

beginPage();
try {
  ensureGET('new_playlist_id');
  $new_playlist_id = $_GET['new_playlist_id'];
  $new_playlist = $api->getPlaylist($new_playlist_id);
  ?>
  <div>
  New playlist created: <a href="/app/insert-ding/?playlist_id=<?php echo($new_playlist_id); ?>"><?php echo($new_playlist->name); ?></a>
  </div>
<?php
}
catch (Exception $e) {
  showError($e);
}
endPage();
updateTokens($session);
?>
