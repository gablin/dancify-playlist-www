<?php
require '../autoload.php';

ensureSession();
$session = getSession();
$api = createWebApi($session);
?>

function setupInsertTrack() {
  setupFormElementsForInsertTrack();
}

function setupFormElementsForInsertTrack() {
  let form = getPlaylistForm();

  function loadTrack(track_url, success_f, fail_f) {
    let track_data = { trackUrl: track_url };
    callApi( '/api/get-track-info/'
           , track_data
           , function(d) {
               let to = createPlaylistTrackObject( d.trackId
                                                 , d.artists
                                                 , d.name
                                                 , d.length
                                                 , d.bpm
                                                 , 0
                                                 , 0
                                                 , 0
                                                 , 0
                                                 , 0
                                                 , d.genre.by_user
                                                 , d.genre.by_others
                                                 , d.comments
                                                 , d.preview_url
                                                 , '<?= getThisUserId($api) ?>'
                                                 );
               success_f(to);
             }
           , fail_f
           );
  }

  function setupSingleInsert(button_id, get_table_f) {
    form.find('button[id=' + button_id + ']').click(
      function() {
        let b = $(this);
        b.prop('disabled', true);
        b.addClass('loading');
        function restoreButton() {
          b.prop('disabled', false);
          b.removeClass('loading');
        };

        if (!checkTrackInsertInput(form, false)) {
          restoreButton();
          return;
        }

        let data = getTrackInsertData(form);
        loadTrack(
          data.trackUrl
        , function(track) {
            let table = get_table_f();
            let tracks = getTrackData(table);
            tracks.push(track);
            replaceTracks(table, tracks);
            renderTable(table);
            indicateStateUpdate();
            restoreButton();
            clearActionInputs();

            if (isScratchpadTable(table)) {
              showScratchpad(table);
            }
          }
        , function(msg) {
            alert('ERROR: <?= LNG_ERR_FAILED_INSERT_TRACK ?>');
            restoreButton();
            clearActionInputs();
          }
        );
      }
    );
  }

  setupSingleInsert('insertTrackInPlaylistBtn', () => getPlaylistTable());
  setupSingleInsert( 'insertTrackInLocalScratchpadBtn'
                   , () => getLocalScratchpadTable()
                   );

  form.find('button[id=insertTrackAtIntervalBtn]').click(
    function() {
      let b = $(this);
      b.prop('disabled', true);
      b.addClass('loading');
      function restoreButton() {
        b.prop('disabled', false);
        b.removeClass('loading');
      };

      if (!checkTrackInsertInput(form, true)) {
        restoreButton();
        return;
      }

      let data = getTrackInsertData(form);
      loadTrack(
        data.trackUrl
      , function(track) {
          let tracks = getTrackData(getPlaylistTable());
          let new_tracks = [];
          for (let i = 0; i < tracks.length; i++) {
              if (i > 0 && i % data.insertFreq == 0) {
                new_tracks.push(to);
              }
              new_tracks.push(tracks[i]);
          }
          let table = getPlaylistTable();
          replaceTracks(table, new_tracks);
          renderTable(table);
          indicateStateUpdate();
          restoreButton();
          clearActionInputs();
        }
      , function(msg) {
          alert('ERROR: <?= LNG_ERR_FAILED_INSERT_TRACK ?>');
          restoreButton();
          clearActionInputs();
        }
      );
    }
  );
}

function getTrackInsertData() {
  let form = getPlaylistForm();
  return { trackUrl: form.find('input[name=track-to-insert]').val().trim()
         , insertFreq:
             form.find('input[name=track-insertion-freq]').val().trim()
         };
}

function checkTrackInsertInput(form, require_freq) {
  let track_link = form.find('input[name=track-to-insert]').val().trim();
  if (track_link.length == 0) {
    alert('<?= LNG_ERR_SPECIFY_TRACK_TO_INSERT ?>');
    return false;
  }

  if (require_freq) {
    let freq_str = form.find('input[name=track-insertion-freq]').val().trim();
    freq = parseInt(freq_str);
    if (isNaN(freq)) {
      alert('<?= LNG_ERR_FREQ_NOT_INT ?>');
      return false;
    }
    if (freq <= 0) {
      alert('<?= LNG_ERR_FREQ_MUST_BE_GT ?>');
      return false;
    }
  }

  return true;
}
