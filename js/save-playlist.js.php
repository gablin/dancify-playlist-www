<?php
require '../autoload.php';
?>

function setupSaveNewPlaylist(make_public) {
  var form = getPlaylistForm();
  var table = getPlaylistTable();
  var save_b = form.find('button[id=saveAsNewPlaylistBtn]');
  var name_input = form.find('input[name=new-playlist-name]');
  save_b.click(
    function() {
      var b = $(this);
      b.prop('disabled', true);
      b.addClass('loading');
      function restoreButton() {
        b.prop('disabled', false);
        b.removeClass('loading');
      };

      // Check new playlist name
      var name = name_input.val().trim();
      if (name.length == 0) {
        alert('<?= LNG_INSTR_PLEASE_ENTER_NAME ?>');
        restoreButton();
        return false;
      }

      // Save new playlist
      var tracks = getPlaylistTrackData();
      var track_ids = [];
      for (var i = 0; i < tracks.length; i++) {
          track_ids.push(tracks[i].trackId);
      }
      var data = { trackIdList: track_ids
                 , playlistName: name
                 , publicPlaylist: make_public
                 };
      callApi( '/api/save-new-playlist/'
             , data
             , function(d) {
                 alert('<?= LNG_DESC_NEW_PLAYLIST_ADDED ?>');
                 window.location.href = './?id=' + d.newPlaylistId;
               }
             , function(msg) {
                 alert('ERROR: <?= LNG_ERR_FAILED_INSERT_TRACK ?>');
                 restoreButton();
                 clearActionInputs();
               }
             );
      return false;
    }
  );
  save_b.prop('disabled', true);
  name_input.on(
    'input'
  , function() {
      save_b.prop('disabled', name_input.val().trim().length == 0);
    }
  );
}
