<?php
require '../../../../autoload.php';
require '../../functions.php';

ensureSession();
$session = getSession();
$api = createWebApi($session);
ensureAuthorizedUser($api);

// Check if 'save' button was pushed. If so, forward GET to next page
if (hasGET('commit') && hasGET('new_name')) {
  header("Location: ./commit/?{$_SERVER['QUERY_STRING']}");
  die();
}

beginPage();
createMenu( mkMenuItemShowPlaylists($api)
          , mkMenuItemShowPlaylistTracks($api)
          , mkMenuItemNewPlaylist($api)
          );
beginContent();
try {
?>

<?php
if (hasGET('commit') && !hasGET('new_name')) {
  ?>
  <div>
    <?php echo(LNG_INSTR_PLEASE_ENTER_NAME); ?>.
  </div>
  <?php
}
?>

<form action="." method="GET">
  <?php
  foreach ($_GET as $k => $v) {
    if (!in_array($k, ['playlist_id', 'track', 'track_id', 'freq'])) continue;
    ?>
    <input type="hidden" name="<?php echo($k); ?>" value="<?php echo($v); ?>"></input>
    <?php
  }
  ?>
  <input type="hidden" name="commit" value="true"></input>
  <div class="input">
    <?php echo(LNG_INSTR_ENTER_NAME_OF_NEW_PLAYLIST); ?>:
    <input type="text" name="new_name"></input>
  </div>
  <div>
    <input class="button" type="submit" value="<?php echo(LNG_BTN_SAVE); ?>"></input>
  </div>
</form>

<?php
}
catch (Exception $e) {
  showError($e->getMessage());
}
endContent();
endPage();
updateTokens($session);
?>
