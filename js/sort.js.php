<?php
require '../autoload.php';
?>

function setupSort() {
  var form = getPlaylistForm();
  function doSort(table) {
    var direction =
      form.find('select[name=order_direction] :selected').val().trim();
    var direction = parseInt(direction);
    var field = form.find('select[name=order_field] :selected').val().trim();
    sortTracks(table, direction, field);
    clearActionInputs();
  }
  $('#sortPlaylistBtn').click(
    function() { doSort(getPlaylistTable()); }
  );
  $('#sortScratchpadBtn').click(
    function() {
      doSort(getScratchpadTable());
      showScratchpad();
    }
  );
}

function sortTracks(table, direction, field) {
  var tracks = getTrackData(table);
  tracks = removePlaceholdersFromTracks(tracks);
  function cmp(a, b) {
    var res = 0;
    if (field == 'bpm') {
      res = intcmp(a.bpm, b.bpm);
    }
    else if (field == 'genre') {
      s1 = genreToString(a.genre);
      s2 = genreToString(b.genre);
      res = strcmp(s1, s2);
    }
    return res * direction;
  }
  sorted_tracks = tracks.sort(cmp);
  replaceTracks(table, sorted_tracks);
  renderTable(table);
  indicateStateUpdate();
}
