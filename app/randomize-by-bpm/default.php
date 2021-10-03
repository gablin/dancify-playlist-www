<?php
require '../../autoload.php';
require 'functions.php';

ensureSession();
$session = getSession();
$api = createWebApi($session);

beginPage();
createMenu(mkMenuItemShowPlaylists($api));
beginContent();
try {
?>

<?php
$playlists = loadPlaylists($api);
?>

<div class="instruction">
  <?php echo(LNG_INSTR_SELECT_PLAYLIST_TO_RANDOMIZE); ?>:
</div>

<table>
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
