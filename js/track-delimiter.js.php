<?php
require '../autoload.php';
?>

function setupTrackDelimiter() {
  setupFormElementsForTrackDelimiter();
}

function setupFormElementsForTrackDelimiter() {
  var form = PLAYLIST_FORM;
  var table = PLAYLIST_TABLE;
  form.find('button[id=showTrackDelimiterBtn]').click(
    function() {
      if (!checkTrackDelimiterInput()) {
        return;
      }
      var data = getTrackDelimiterData();
      PLAYLIST_TRACK_DELIMITER = data.delimiterFreq;
      updatePlaylist();
      clearActionInputs();
    }
  );
  form.find('button[id=hideTrackDelimiterBtn]').click(
    function() {
      PLAYLIST_TRACK_DELIMITER = 0;
      updatePlaylist();
      clearActionInputs();
    }
  );
}

function getTrackDelimiterData() {
  var form = PLAYLIST_FORM;
  return { delimiterFreq: form.find('input[name=delimiter-freq]').val().trim()
         };
}

function checkTrackDelimiterInput() {
  var form = PLAYLIST_FORM;
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
