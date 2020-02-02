<?php
require '../../autoload.php';

beginPage();
beginContent();
?>

<div class="error">
  <?php echo(sprintf(LNG_ERR_UNAUTHORIZED_USER, 'gabriel [at] hjort.dev')); ?>
</div>

<?php
endContent();
endPage();
?>