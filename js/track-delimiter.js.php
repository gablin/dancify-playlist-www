<?php
require '../autoload.php';
?>

function setupTrackDelimiter(form, table) {
  setupFormElementsForTrackDelimiter(form, table);
}

function setupFormElementsForTrackDelimiter(form, table) {
  form.find('button[id=showTrackDelimiterBtn]').click(
    function() {
      if (!checkTrackDelimiterInput(form)) {
        return;
      }
      var data = getTrackDelimiterData(form);
      PLAYLIST_TRACK_DELIMITER = data.delimiterFreq;
      updatePlaylist(form, table);
      clearActionInputs();
    }
  );
  form.find('button[id=hideTrackDelimiterBtn]').click(
    function() {
      PLAYLIST_TRACK_DELIMITER = 0;
      updatePlaylist(form, table);
      clearActionInputs();
    }
  );
}

function getTrackDelimiterData(form) {
  return { delimiterFreq: form.find('input[name=delimiter-freq]').val().trim()
         };
}

function checkTrackDelimiterInput(form) {
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
