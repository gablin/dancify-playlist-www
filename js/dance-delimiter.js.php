<?php
require '../autoload.php';
?>

function setupDanceDelimiter() {
  setupFormElementsForDanceDelimiter();
}

function getShowDelimiterButton() {
  return getPlaylistForm().find('button[id=showDanceDelimiterBtn]');
}

function getHideDelimiterButton() {
  return getPlaylistForm().find('button[id=hideDanceDelimiterBtn]');
}

function setupFormElementsForDanceDelimiter() {
  let form = getPlaylistForm();
  let table = getPlaylistTable();
  let show_btn = getShowDelimiterButton();
  let hide_btn = getHideDelimiterButton();
  show_btn.click(
    function() {
      if (!checkDanceDelimiterInput()) {
        return;
      }
      let data = getDanceDelimiterData();
      setDanceDelimiter(data.delimiterFreq);
      renderTable(getPlaylistTable());
      savePlaylistSnapshot(function() {}, function() {}); // Do not invoke
                                                          // indicateStateUpdate()
                                                          // here
      setDelimiterAsShowing();
      clearActionInputs();
    }
  );
  form.find('button[id=hideDanceDelimiterBtn]').click(
    function() {
      setDanceDelimiter(0);
      renderTable(getPlaylistTable());
      savePlaylistSnapshot(function() {}, function() {}); // Do not invoke
                                                          // indicateStateUpdate()
                                                          // here
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

function getDanceDelimiterData() {
  let form = getPlaylistForm();
  return { delimiterFreq: form.find('input[name=delimiter-freq]').val().trim() };
}

function checkDanceDelimiterInput() {
  let form = getPlaylistForm();
  let freq_str = form.find('input[name=delimiter-freq]').val().trim();
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
