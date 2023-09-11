<?php
require '../autoload.php';
?>

function setupSort() {
  let form = getPlaylistForm();
  function doSort(table) {
    let direction =
      form.find('select[name=order_direction] :selected').val().trim();
    direction = parseInt(direction);
    let field = form.find('select[name=order_field] :selected').val().trim();
    sortTracks(table, direction, field);
    clearActionInputs();
  }
  $('#sortPlaylistBtn').click(
    function() { doSort(getPlaylistTable()); }
  );
  $('#sortLocalScratchpadBtn').click(
    function() {
      let table = getLocalScratchpadTable();
      doSort(table);
      showScratchpad(table);
    }
  );
}

function sortTracks(table, direction, field) {
  let tracks = getTrackData(table);
  tracks = removePlaceholdersFromTracks(tracks);
  function getGenre(t) {
    if (t.genre.by_user == 0 && t.genre.by_others.length > 0) {
      return t.genre.by_others[0];
    }
    return t.genre.by_user;
  }
  function cmp(a, b) {
    let res = 0;
    if (field == 'bpm') {
      function bpm(t) {
        return t.bpm.custom >= 0 ? t.bpm.custom : t.bpm.spotify;
      }
      res = intcmp(bpm(a), bpm(b));
    }
    else if (field == 'genre') {
      s1 = genreToString(getGenre(a));
      s2 = genreToString(getGenre(b));
      res = strcmp(s1, s2);
    }
    else if (field == 'energy') {
      res = floatcmp(a.energy, b.energy);
    }
    return res * direction;
  }
  sorted_tracks = tracks.sort(cmp);
  replaceTracks(table, sorted_tracks);
  renderTable(table);
  indicateStateUpdate();
}
