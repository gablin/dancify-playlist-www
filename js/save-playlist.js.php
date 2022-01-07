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
      var restoreButton = function() {
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
      var playlist_data = getPlaylistData();
      var track_ids = [];
      for (var i = 0; i < playlist_data.length; i++) {
          track_ids.push(playlist_data[i].trackId);
      }
      var data = { trackIdList: track_ids
                 , playlistName: name
                 , publicPlaylist: make_public
                 };
      $.post('/api/save-new-playlist/', { data: JSON.stringify(data) })
        .done(
          function(res) {
            json = JSON.parse(res);
            if (json.status == 'OK') {
              alert('<?= LNG_DESC_NEW_PLAYLIST_ADDED ?>');
              window.location.href = './?id=' + json.newPlaylistId;
            }
            else if (json.status == 'FAILED') {
              alert('ERROR: ' + json.msg);
            }
            restoreButton();
          }
        )
        .fail(
          function(xhr, status, error) {
            alert('ERROR: ' + error);
            restoreButton();
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
