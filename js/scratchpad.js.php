<?php
require '../autoload.php';
?>

function setupScratchpad() {
  setupFormElementsForScratchpad();
}

function setupFormElementsForScratchpad() {
  var form = getPlaylistForm();
  var table = getPlaylistTable();
  form.find('button[id=showScratchpadBtn]').click(
    function() {
      $('div.scratchpad').show();
      clearActionInputs();
    }
  );
  form.find('button[id=hideScratchpadBtn]').click(
    function() {
      $('div.scratchpad').hide();
      clearActionInputs();
    }
  );
}
