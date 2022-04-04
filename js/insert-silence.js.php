<?php
require '../autoload.php';
?>

function setupInsertSilence() {
  var form = getPlaylistForm();

  form.find('button[id=insertSilenceBtn]').click(
    function() {
      var b = $(this);
      b.prop('disabled', true);
      b.addClass('loading');
      function restoreButton() {
        b.prop('disabled', false);
        b.removeClass('loading');
      };

      if (!checkSilenceInsertInput(form)) {
        restoreButton();
        return;
      }

      var data = getSilenceInsertData(form);
      var track_data = { trackId: data.trackId };
      callApi( '/api/get-track-info/'
             , track_data
             , function(d) {
                 var to = createPlaylistTrackObject( d.trackId
                                                   , d.artists
                                                   , d.name
                                                   , d.length
                                                   , d.bpm
                                                   , d.genre.by_user
                                                   , d.genre.by_others
                                                   , d.comments
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
                 indicateStateUpdate();
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

function getSilenceInsertData() {
  var form = getPlaylistForm();
  return { trackId:
             form.find('select[name=silence-to-insert] :selected').val().trim()
         , insertFreq: form.find('input[name=silence-insertion-freq]').val().trim()
         };
}

function checkSilenceInsertInput() {
  var form = getPlaylistForm();

  var freq_str = form.find('input[name=silence-insertion-freq]').val().trim();
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
