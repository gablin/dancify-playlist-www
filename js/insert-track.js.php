<?php
require '../autoload.php';
?>

function setupInsertTrack() {
  setupFormElementsForInsertTrack();
}

function setupFormElementsForInsertTrack() {
  var form = getPlaylistForm();

  form.find('button[id=insertTrackBtn]').click(
    function() {
      var b = $(this);
      b.prop('disabled', true);
      b.addClass('loading');
      var restoreButton = function() {
        b.prop('disabled', false);
        b.removeClass('loading');
      };

      if (!checkTrackInsertInput(form)) {
        restoreButton();
        return;
      }

      var data = getInsertData(form);
      var track_data = { trackUrl: data.trackUrl };
      $.post('/api/get-track-info/', { data: JSON.stringify(track_data) })
        .done(
          function(res) {
            json = JSON.parse(res);
            if (json.status == 'OK') {
              var playlist_entry = createPlaylistTrackObject( json.trackId
                                                            , json.artists
                                                            , json.name
                                                            , json.length
                                                            , json.bpm
                                                            , json.category
                                                            , json.preview_url
                                                            );
              var old_playlist = getPlaylistData();
              var new_playlist = [];
              for (var i = 0; i < old_playlist.length; i++) {
                  if (i > 0 && i % data.insertFreq == 0) {
                    new_playlist.push(playlist_entry);
                  }
                  new_playlist.push(old_playlist[i]);
              }
              updatePlaylist(new_playlist);
              // TODO: indicate unsaved changes
            }
            else if (json.status == 'FAILED') {
              alert('ERROR: ' + json.msg);
            }
            restoreButton();
            clearActionInputs();
          }
        )
        .fail(
          function(xhr, status, error) {
            alert('ERROR: ' + error);
            restoreButton();
            clearActionInputs();
          }
        );
    }
  );
}

function getInsertData() {
  var form = getPlaylistForm();
  return { trackUrl: form.find('input[name=track-to-insert]').val().trim()
         , insertFreq: form.find('input[name=insertion-freq]').val().trim()
         };
}

function checkTrackInsertInput() {
  var form = getPlaylistForm();
  var track_link = form.find('input[name=track-to-insert]').val().trim();
  if (track_link.length == 0) {
    alert('<?= LNG_ERR_SPECIFY_TRACK_TO_INSERT ?>');
    return false;
  }

  var freq_str = form.find('input[name=insertion-freq]').val().trim();
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
