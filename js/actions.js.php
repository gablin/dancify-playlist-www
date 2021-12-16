<?php
require '../autoload.php';
?>

function showActionInput(name) {
  $('.action-input-area[name=' + name + ']').show();
}

function clearActionInputs() {
  $('.action-input-area').hide();
}

function setupUnloadWarning(form, table) {
  $(window).on(
    'beforeunload'
  , function() {
      // Check if there are any unsaved changes
      // TODO: implement
      // returning anything causes confirmation box to be shown
    }
  );
}

