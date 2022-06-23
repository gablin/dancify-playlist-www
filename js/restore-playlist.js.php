<?php
require '../autoload.php';
?>

function setupRestorePlaylist(playlist_id) {
  let form = getPlaylistForm();
  let restore_btn = form.find('button[id=restorePlaylistBtn]');
  restore_btn.click(
    function() {
      let info = getCurrentPlaylistInfo();
      restorePlaylist(info.id);
      clearActionInputs();
    }
  );
}

function restorePlaylist(playlist_id) {
  setStatus('<?= LNG_DESC_RESTORING ?>...');
  callApi( '/api/remove-playlist-snapshot/'
         , { playlistId: playlist_id }
         , function(d) {
             clearActionInputs();
             clearStatus();
             loadPlaylist(playlist_id);
           }
         , function(msg) {
             setStatus('<?= LNG_ERR_FAILED_TO_RESTORE ?>', true);
           }
         );
}
