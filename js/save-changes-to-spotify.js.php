<?php
require '../autoload.php';
?>

function setupSaveChangesToSpotify() {
  let form = getPlaylistForm();
  let table = getPlaylistTable();
  let save_b = form.find('button[id=saveChangesToSpotifyBtn]');
  let name_input = form.find('input[name=new-playlist-name]');
  let overwrite_checkbox = form.find('input[name=overwrite-existing-playlist]');
  save_b.click(
    function() {
      let b = $(this);
      b.prop('disabled', true);
      b.addClass('loading');
      function restoreButton() {
        b.prop('disabled', false);
        b.removeClass('loading');
      };

      let name = name_input.val().trim();
      if (name.length == 0 && !overwrite_checkbox.is(':checked')) {
        alert('<?= LNG_INSTR_PLEASE_ENTER_NAME ?>');
        restoreButton();
        return false;
      }
      let overwrite_playlist = overwrite_checkbox.is(':checked');
      function success() {
        // TODO: load new playlist
        restoreButton();
        clearActionInputs();
      }
      function fail(msg) {
        alert('ERROR: <?= LNG_ERR_FAILED_TO_SAVE_CHANGES_TO_SPOTIFY ?>');
        restoreButton();
        clearActionInputs();
      }
      savePlaylistToSpotify(name, overwrite_playlist, success, fail);
    }
  );
  save_b.prop('disabled', true);

  function checkNameInput() {
    return name_input.val().trim().length > 0;
  }
  name_input.on( 'input'
                 , function() { save_b.prop('disabled', !checkNameInput()); }
               );
  overwrite_checkbox.on(
    'change'
  , function() {
      if (overwrite_checkbox.is(':checked')) {
        name_input.prop('disabled', true);
        save_b.prop('disabled', false);
      }
      else {
        name_input.prop('disabled', false);
        save_b.prop('disabled', !checkNameInput());
      }
    }
  );
}

function savePlaylistToSpotify(new_name, overwrite_playlist, success_f, fail_f) {
  let tracks = getTrackData(table);
  tracks = removePlaceholdersFromTracks(tracks);
  let track_ids = [];
  for (let i = 0; i < tracks.length; i++) {
      track_ids.push(tracks[i].trackId);
  }

  let data = { trackIdList: track_ids };
  let playlist_info = getCurrentPlaylistInfo();
  if (overwrite_playlist) {
    data.playlistId = playlist_info.id;
    data.overwritePlaylist = true;
  }
  else {
    data.playlistName = new_name;
    data.publicPlaylist = playlist_info.isPublic;
  }
  callApi( '/api/save-changes-to-spotify/'
         , data
         , success_f
         , fail_f
         );
}
