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
                                                 , d.acousticness
                                                 , d.danceability
                                                 , d.energy
                                                 , d.instrumentalness
                                                 , d.valence
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

        let song_input = form.find('input[name=track-to-insert]');

        if (!checkTrackInsertInput(song_input)) {
          restoreButton();
          return;
        }

        let data = getTrackInsertData(song_input);
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

      let song_input = form.find('input[name=repeating-track-to-insert]');
      let freq_input = form.find('input[name=repeating-track-insertion-freq]');

      if (!checkTrackInsertInput(song_input, freq_input)) {
        restoreButton();
        return;
      }

      let data = getTrackInsertData(song_input, freq_input);
      loadTrack(
        data.trackUrl
      , function(track) {
          let tracks = getTrackData(getPlaylistTable());
          let new_tracks = [];
          for (let i = 0; i < tracks.length; i++) {
              if (i > 0 && i % data.insertFreq == 0) {
                new_tracks.push(track);
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

function getTrackInsertData(song_input, freq_input) {
  let form = getPlaylistForm();
  return { trackUrl: song_input.val().trim()
         , insertFreq: freq_input ? freq_input.val().trim() : null
         };
}

function checkTrackInsertInput(song_input, freq_input) {
  console.log(song_input);
  console.log(freq_input);

  let track_link = song_input.val().trim();
  if (track_link.length == 0) {
    alert('<?= LNG_ERR_SPECIFY_TRACK_TO_INSERT ?>');
    return false;
  }

  if (freq_input) {
    let freq_str = freq_input.val().trim();
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
