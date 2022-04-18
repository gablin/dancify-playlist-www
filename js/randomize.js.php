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
  $('#randomizeScratchpadBtn').click(
    function() {
      randomizeTrackOrder(getScratchpadTable());
      showScratchpad();
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
