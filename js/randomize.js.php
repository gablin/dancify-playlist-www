<?php
require '../autoload.php';
?>

function setupRandomize() {
  let form = getPlaylistForm();
  $('#randomizePlaylistBtn').click(
    function() {
      randomizeTrackOrder(getPlaylistTable());
      clearActionInputs();
    }
  );
  $('#randomizeLocalScratchpadBtn').click(
    function() {
      let table = getLocalScratchpadTable()
      randomizeTrackOrder(table);
      showScratchpad(table);
      clearActionInputs();
    }
  );
}

function randomizeTrackOrder(table) {
  let tracks = getTrackData(table);
  tracks = removePlaceholdersFromTracks(tracks);
  randomized_tracks = shuffle(tracks);
  replaceTracks(table, randomized_tracks);
  renderTable(table);
  indicateStateUpdate();
}
