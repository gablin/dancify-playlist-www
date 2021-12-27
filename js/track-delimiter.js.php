<?php
require '../autoload.php';
?>

function setupTrackDelimiter() {
  setupFormElementsForTrackDelimiter();
}

function setupFormElementsForTrackDelimiter() {
  var form = getPlaylistForm();
  var table = getPlaylistTable();
  form.find('button[id=showTrackDelimiterBtn]').click(
    function() {
      if (!checkTrackDelimiterInput()) {
        return;
      }
      var data = getTrackDelimiterData();
      setTrackDelimiter(data.delimiterFreq);
      updatePlaylist();
      clearActionInputs();
    }
  );
  form.find('button[id=hideTrackDelimiterBtn]').click(
    function() {
      setTrackDelimiter(0);
      updatePlaylist();
      clearActionInputs();
    }
  );
}

function getTrackDelimiterData() {
  var form = getPlaylistForm();
  return { delimiterFreq: form.find('input[name=delimiter-freq]').val().trim()
         };
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
