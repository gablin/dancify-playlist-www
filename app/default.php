<?php
require '../autoload.php';
require 'functions.php';

ensureSession();
$session = getSession();
$api = createWebApi($session);

beginPage();
mkHtmlNavMenu([]);
beginContent();
try {
?>

<div class="instruction">
  <?php echo(LNG_INSTR_SELECT_PLAYLIST); ?>
</div>

<table id="playlists">
  <tbody>
  </tbod>
</table>

<script src="/js/utils.js.php"></script>
<script src="/js/status.js.php"></script>
<script src="/js/user.js.php"></script>
<script type="text/javascript">
$(document).ready(
  function() {
    loadUserPlaylists('<?= getThisUserId($api) ?>');
  }
);
</script>

<?php
}
catch (Exception $e) {
  showError($e->getMessage());
}
endContent();
endPage();
updateTokens($session);
?>
