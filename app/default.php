<?php
require '../autoload.php';

ensureSession();
$session = getSession();
$api = createWebApi($session);

beginPage();
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
        <a href="/app/insert-ding/?playlist_id=<?php echo($id); ?>"><?php echo($name); ?></a>
      </td>
    </tr>
    <?php
  }
  ?>
</table>

<?php
}
catch (Exception $e) {
  showError($e);
}
endPage();
updateTokens($session);
?>