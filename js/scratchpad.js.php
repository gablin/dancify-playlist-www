<?php
require '../autoload.php';
?>

function setupScratchpad() {
  setupFormElementsForScratchpad();
}

function setupFormElementsForScratchpad() {
  var form = getPlaylistForm();
  var table = getPlaylistTable();
  var show_btn = form.find('button[id=showScratchpadBtn]');
  var hide_btn = form.find('button[id=hideScratchpadBtn]');
  show_btn.click(
    function() {
      $('div.scratchpad').show();
      show_btn.prop('disabled', true);
      hide_btn.prop('disabled', false);
      clearActionInputs();
    }
  );
  hide_btn.click(
    function() {
      $('div.scratchpad').hide();
      hide_btn.prop('disabled', true);
      show_btn.prop('disabled', false);
      clearActionInputs();
    }
  );
  hide_btn.prop('disabled', true);
}
