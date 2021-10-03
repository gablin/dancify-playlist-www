<?php
require '../autoload.php';

ensureSession();
$session = getSession();
$api = createWebApi($session);

beginPage();
createMenu();
beginContent();
try {
?>

<div class="instruction">
  <?php echo(LNG_INSTR_SELECT_APP); ?>:
</div>

<ul>
  <li><a href="./insert-ding/"><?php echo(LNG_APP_INSERT_DING); ?></a></li>
  <li><a href="./randomize-by-bpm/"><?php echo(LNG_APP_RANDOMIZE_BY_BPM); ?></a></li>
</ul>

<?php
}
catch (Exception $e) {
  showError($e->getMessage());
}
endContent();
endPage();
updateTokens($session);
?>
