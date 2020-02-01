<?php
require '../../autoload.php';
require 'functions.php';

ensureSession();
$session = getSession();
$api = createWebApi($session);
ensureAuthorizedUser($api);

beginPage();
createMenu(mkMenuItemShowPlaylists($api));
beginContent();
try {
?>

<?php
$playlists = loadPlaylists($api);
?>

<table>
  <tr>
    <th>Playlists</th>
  </tr>
  <?php
  foreach ($playlists as $p) {
    $name = $p->name;
    $id = $p->id;
    ?>
    <tr>
      <td>
        <a href="./show-tracks/?playlist_id=<?php echo($id); ?>"><?php echo($name); ?></a>
      </td>
    </tr>
    <?php
  }
  ?>
</table>

<?php
}
catch (Exception $e) {
  showError($e->getMessage());
}
endContent();
endPage();
updateTokens($session);
?>