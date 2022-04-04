<?php
require '../autoload.php';
?>

function setupBpmOverview() {
  setupFormElementsForBpmOverview();
}

function getBpmOverviewShowButton() {
  var form = getPlaylistForm();
  return form.find('button[id=showBpmOverviewBtn]');
}

function getBpmOverviewHideButton() {
  var form = getPlaylistForm();
  return form.find('button[id=hideBpmOverviewBtn]');
}

function setupFormElementsForBpmOverview() {
  var show_btn = getBpmOverviewShowButton();
  var hide_btn = getBpmOverviewHideButton();
  show_btn.click(
    function() {
      showBpmOverview();
      clearActionInputs();
    }
  );
  hide_btn.click(
    function() {
      hideBpmOverview();
      clearActionInputs();
    }
  );
  hide_btn.prop('disabled', true);
}

function showBpmOverview() {
  $('div.bpm-overview').show();
  getBpmOverviewShowButton().prop('disabled', true);
  getBpmOverviewHideButton().prop('disabled', false);
  setPlaylistHeight();
  renderBpmOverview();
}

function hideBpmOverview() {
  $('div.bpm-overview').hide();
  getBpmOverviewShowButton().prop('disabled', false);
  getBpmOverviewHideButton().prop('disabled', true);
  setPlaylistHeight();
}
