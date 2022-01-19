<?php
require 'autoload.php';

beginPage();
beginContent();
?>

<p class="intro centered">
  <?php echo(LNG_TXT_INTRO_PART1); ?>
</p>
<p class="intro centered">
  <?php echo(LNG_TXT_INTRO_PART2); ?>
</p>

<div class="login">
  <a href="/auth/" class="button"><?php echo(LNG_BTN_LOGIN); ?></a>
</div>

<?php
endContent();
endPage();
?>
