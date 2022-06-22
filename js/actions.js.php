<?php
require '../autoload.php';
?>

function enableMenuPlaylistButtons() {
  $('.menu .dropdown-content a').removeClass('disabled');
}

function showActionInput(a, name, pre_f) {
  if ($(a).hasClass('disabled')) {
    return;
  }

  if (pre_f !== undefined) {
    pre_f();
  }

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
