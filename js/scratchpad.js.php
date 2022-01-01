<?php
require '../autoload.php';
?>

function setupScratchpad() {
  setupFormElementsForScratchpad();
}

function getScratchpadShowButton() {
  var form = getPlaylistForm();
  return form.find('button[id=showScratchpadBtn]');
}

function getScratchpadHideButton() {
  var form = getPlaylistForm();
  return form.find('button[id=hideScratchpadBtn]');
}

function setupFormElementsForScratchpad() {
  var show_btn = getScratchpadShowButton();
  var hide_btn = getScratchpadHideButton();
  show_btn.click(
    function() {
      showScratchpad();
      clearActionInputs();
    }
  );
  hide_btn.click(
    function() {
      hideScratchpad();
      clearActionInputs();
    }
  );
  hide_btn.prop('disabled', true);
}

function showScratchpad() {
  $('div.scratchpad').show();
  getScratchpadShowButton().prop('disabled', true);
  getScratchpadHideButton().prop('disabled', false);
}

function hideScratchpad() {
  $('div.scratchpad').hide();
  getScratchpadShowButton().prop('disabled', false);
  getScratchpadHideButton().prop('disabled', true);
}
