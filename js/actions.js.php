<?php
require '../autoload.php';
?>

function showActionInput(name) {
  $('.action-input-area[name=' + name + ']').show();
  function clear(e) {
    if (e.key == 'Escape') {
      clearActionInputs();
      $(document).unbind('keyup', clear);
    }
  }
  $(document).on('keyup', clear);
}

function clearActionInputs() {
  $('.action-input-area').hide();
}
