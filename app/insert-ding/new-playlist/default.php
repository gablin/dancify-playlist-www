<?php
require '../../../autoload.php';

ensureSession();
$session = getSession();
$api = createWebApi($session);

// Check if 'save' button was pushed. If so, forward GET to next page
if (hasGET('commit') && hasGET('new_name')) {
  header('Location: ./commit/?' . $_SERVER['QUERY_STRING']);
  die();
}

beginPage();
try {
?>

<?php
if (hasGET('commit') && !hasGET('new_name')) {
  ?>
  <div>
  Please enter a name.
  </div>
  <?php
}
?>

<form action="." method="GET">
  <?php
  foreach ($_GET as $k => $v) {
    if (!in_array($k, ['playlist_id', 'track_id', 'freq'])) continue;
    ?>
    <input type="hidden" name="<?php echo($k); ?>" value="<?php echo($v); ?>"></input>
    <?php
  }
  ?>
  <input type="hidden" name="commit" value="true"></input>
  <div>
    Name of new playlist: 
    <input type="text" name="new_name"></input>
  </div>
  <div>
    <input type="submit" value="Save"></input>
  </div>
</form>

<?php
}
catch (Exception $e) {
  showError($e);
}
endPage();
updateTokens($session);
?>
