<?php
require '../autoload.php';
?>

function setupSort() {
  var form = getPlaylistForm();
  var btn = $('#sortBtn');
  btn.click(
    function() {
      var direction = form.find('select[name=order] :selected').val().trim();
      var direction = parseInt(direction);
      sortByBpm(getPlaylistTable(), direction);
      clearActionInputs();
    }
  );
}

function sortByBpm(table, direction) {
  var tracks = getTrackData(table);
  tracks = removePlaceholdersFromTracks(tracks);
  function cmp(a, b) {
    var res = 0;
    if (a.bpm < b.bpm) {
      res = -1;
    }
    else if (a.bpm > b.bpm) {
      res = +1;
    }
    return res * direction;
  }
  sorted_tracks = tracks.sort(cmp);
  replaceTracks(table, sorted_tracks);
  renderTable(table);
  indicateStateUpdate();
}
