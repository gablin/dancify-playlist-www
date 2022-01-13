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
      function restoreButton() {
        b.prop('disabled', false);
        b.removeClass('loading');
      };

      if (!checkTrackInsertInput(form)) {
        restoreButton();
        return;
      }

      var data = getInsertData(form);
      var track_data = { trackUrl: data.trackUrl };
      callApi( '/api/get-track-info/'
             , track_data
             , function(d) {
                 var to = createPlaylistTrackObject( d.trackId
                                                   , d.artists
                                                   , d.name
                                                   , d.length
                                                   , d.bpm
                                                   , d.genre
                                                   , d.preview_url
                                                   );
                 var tracks = getPlaylistTrackData();
                 var new_tracks = [];
                 for (var i = 0; i < tracks.length; i++) {
                     if (i > 0 && i % data.insertFreq == 0) {
                       new_tracks.push(to);
                     }
                     new_tracks.push(tracks[i]);
                 }
                 replaceTracks(getPlaylistTable(), new_tracks);
                 renderPlaylist();
                 savePlaylistSnapshot();
                 restoreButton();
                 clearActionInputs();
               }
             , function fail(msg) {
                 alert('ERROR: <?= LNG_ERR_FAILED_INSERT_TRACK ?>');
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
