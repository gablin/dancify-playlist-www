<?php
require '../autoload.php';
?>

function setupTrackDelimiter() {
  setupFormElementsForTrackDelimiter();
}

function getShowDelimiterButton() {
  return getPlaylistForm().find('button[id=showTrackDelimiterBtn]');
}

function getHideDelimiterButton() {
  return getPlaylistForm().find('button[id=hideTrackDelimiterBtn]');
}

function setupFormElementsForTrackDelimiter() {
  var form = getPlaylistForm();
  var table = getPlaylistTable();
  var show_btn = getShowDelimiterButton();
  var hide_btn = getHideDelimiterButton();
  show_btn.click(
    function() {
      if (!checkTrackDelimiterInput()) {
        return;
      }
      var data = getTrackDelimiterData();
      setTrackDelimiter(data.delimiterFreq);
      renderPlaylist();
      savePlaylistSnapshot();
      setDelimiterAsShowing();
      clearActionInputs();
    }
  );
  form.find('button[id=hideTrackDelimiterBtn]').click(
    function() {
      setTrackDelimiter(0);
      renderPlaylist();
      savePlaylistSnapshot();
      setDelimiterAsHidden();
      clearActionInputs();
    }
  );
  hide_btn.prop('disabled', true);
  form.find('input[name=delimiter-freq]').on(
    'input'
  , function() {
      show_btn.prop('disabled', false);
    }
  );
}

function getTrackDelimiterData() {
  var form = getPlaylistForm();
  return { delimiterFreq: form.find('input[name=delimiter-freq]').val().trim() };
}

function checkTrackDelimiterInput() {
  var form = getPlaylistForm();
  var freq_str = form.find('input[name=delimiter-freq]').val().trim();
  freq = parseInt(freq_str);
  if (isNaN(freq)) {
    alert('<?= LNG_ERR_FREQ_NOT_INT ?>');
    return false;
  }
  if (freq <= 0) {
    alert('<?= LNG_ERR_FREQ_MUST_BE_GT ?>');
    return false;
  }

  return true;
}

function setDelimiterAsShowing() {
  getShowDelimiterButton().prop('disabled', true);
  getHideDelimiterButton().prop('disabled', false);
}

function setDelimiterAsHidden() {
  getShowDelimiterButton().prop('disabled', false);
  getHideDelimiterButton().prop('disabled', true);
}
