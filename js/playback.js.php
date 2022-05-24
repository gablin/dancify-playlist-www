<?php
require '../autoload.php';

ensureSession();
$session = getSession();
$api = createWebApi($session);
?>

var PLAYBACK_PLAYER = null;
var PLAYBACK_DEVICE_ID = null;
var PLAYBACK_SEEK_TIMER = null;
var PLAYBACK_HAS_TRACK = false;
var PLAYBACK_LAST_PLAYED_TRACK_ID = null;
var PLAYBACK_IGNORE_NEXT_STATE_CHANGES = 0;

const PLAYBACK_SEEK_UPDATE_FREQ_MS = 1000;
const PLAYBACK_PLAY_TRACK_ATTEMPTS = 3;

function setupPlayback() {
  mkPlaybackHtml();
  $.getScript('https://sdk.scdn.co/spotify-player.js', function() {});
  window.onSpotifyWebPlaybackSDKReady = initPlayer;
}

function showPlaybackError(msg) {
  let old_area = $('.playback-error');
  if (old_area.length> 0) {
    old_area.remove();
  }
  let area = $('<div class="playback-error"></div>');
  $(document.body).append(area);
  area.text('<?= LNG_DESC_ERROR ?>: ' + msg);
  let offset = $('.playback').outerHeight(false);
  area.css('bottom', offset + 'px');
}

function mkPlaybackHtml() {
  let playback_html =
    $( '<div class="playback">' +
       '  <div class="x-container">' +
       '    <button class="controller"></button>' +
       '    <div class="playing">' +
       '      <div class="name">' +
       '        <?= LNG_DESC_DOUBLE_CLICK_TRACK_TO_PLAY ?>' +
       '      </div>' +
       '      <div class="artists"></div>' +
       '    </div>' +
       '    <div class="seek">' +
       '      <div class="length-pos"></div>' +
       '      <div class="bar-wrapper">' +
       '        <div class="bar">' +
       '          <div class="active-bar"></div>' +
       '          <div class="knob"></div>' +
       '        </div>' +
       '      </div>' +
       '      <div class="length-end"></div>' +
       '    </div>' +
       '    <div class="volume">' +
       '      <div class="bar-wrapper">' +
       '        <div class="bar">' +
       '          <div class="active-bar"></div>' +
       '          <div class="knob"></div>' +
       '        </div>' +
       '      </div>' +
       '    </div>' +
       '  </div>' +
       '</div>'
     );
  $('.body-wrapper').append(playback_html);
  getPlaybackButton().click(
    function() {
      PLAYBACK_PLAYER.getCurrentState().then(
        state => {
          if (!state) {
            // Got nothing to play; pick first track in playlist
            let track_data =
              removePlaceholdersFromTracks(getPlaylistTrackData());
            if (track_data.length > 0) {
              playTrack( track_data[0].trackId
                       , 0
                       , function() {}
                       , function() {
                           showPlaybackError(
                             '<?= LNG_ERR_PLAYBACK_TRACK_COULD_NOT_PLAY ?>'
                           );
                         }
                       );
            }
            return;
          }

          if (state.paused) {
            triggerResume();
          }
          else {
            triggerPause();
          }
        }
      );
    }
  );
  setupSeekController();
  setupVolumeController();
  renderPaused();
}

function getPlaybackArea() {
  return $('.playback');
}

function getPlaybackButton() {
  return getPlaybackArea().find('button.controller');
}

function renderPlaying() {
  getPlaybackButton().html(
    '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"' +
    '    viewBox="0 -6 24 26">' +
    '  <path fill="currentColor" d="M10 24h-6v-24h6v24zm10-24h-6v24h6v-24z"/>' +
    '</svg>'
  );
}

function renderPaused() {
  getPlaybackButton().html(
    '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"' +
    '    viewBox="-2 -6 24 30">' +
    '  <path fill="currentColor" d="M2 24v-24l20 12-20 12z"/>' +
    '</svg>'
  );
}

function renderSeek(position, duration) {
  let area = getPlaybackArea().find('.seek');
  area.data('duration', duration);
  area.find('.length-pos').text(formatTrackLength(position));
  area.find('.length-end').text(formatTrackLength(duration));

  let percent = (position / duration) * 100;
  area.find('.active-bar').css('width', percent + '%');
  area.find('.knob').css('left', percent + '%');
}

function renderVolume(pos) {
  let area = getPlaybackArea();
  let percent = pos * 100;
  area.find('.volume .active-bar').css('width', percent + '%');
  area.find('.volume .knob').css('left', percent + '%');
}

function initPlayer() {
  const token = '<?= getAccessToken() ?>';
  let volume = Cookies.get('playback-volume');
  if (volume === undefined) {
    volume = 0.5;
    saveVolume(volume);
  }
  const player = new Spotify.Player(
    { name: 'Dancify'
    , getOAuthToken: cb => { cb(token); }
    , volume: volume
    }
  );

  function fail(msg) { showPlaybackError(msg); }
  player.addListener(
    'ready'
  , function({ device_id }) {
      PLAYBACK_PLAYER = player;
      PLAYBACK_DEVICE_ID = device_id;
      getPlaybackArea().show();
      setPlaylistHeight();
      $('.playlist a.preview').hide();
      player.getVolume().then(
        volume => { renderVolume(volume); }
      );
    }
  );
  player.addListener(
    'not_ready'
  , function({ device_id }) {
      fail('<?= LNG_ERR_PLAYBACK_DEVICE_DISCONNECTED ?>');
    }
  );
  player.addListener(
    'player_state_changed'
  , function(state) {
      if (!state) {
        return;
      }

      // Update playback info
      let name = state.track_window.current_track.name;
      let artists = state.track_window.current_track.artists.map((a) => a.name);
      let area = getPlaybackArea();
      area.find('.playing .name').text(name);
      area.find('.playing .artists').text(artists.join(', '));
      if (state.paused) {
        renderPaused();
        stopSeek();
      }
      else {
        renderPlaying();
        startSeek();
      }
      renderSeek(state.position, state.duration);
      PLAYBACK_HAS_TRACK = true;

      if (PLAYBACK_IGNORE_NEXT_STATE_CHANGES > 0) {
        PLAYBACK_IGNORE_NEXT_STATE_CHANGES--;
        return;
      }

      // If reached end of current track, play next in playlist
      if (state.paused && state.position == 0) {
        let just_played_track_id = PLAYBACK_LAST_PLAYED_TRACK_ID;
        let track_data =
          [ removePlaceholdersFromTracks(getPlaylistTrackData())
          , removePlaceholdersFromTracks(getScratchpadTrackData())
          ];
        for (let i = 0; i < track_data.length; i++) {
          for (let j = 0; j < track_data[i].length; j++) {
            let t = track_data[i][j];
            if (t.trackId === just_played_track_id) {
              let next_j = j + 1;
              if (next_j < track_data[i].length) {
                PLAYBACK_IGNORE_NEXT_STATE_CHANGES = 1;
                playTrack( track_data[i][next_j].trackId
                         , 0
                         , function() {}
                         , function() {
                             showPlaybackError(
                               '<?= LNG_ERR_PLAYBACK_TRACK_COULD_NOT_PLAY ?>'
                             );
                           }
                         );
                return;
              }
            }
          }
        }
      }
    }
  );
  player.addListener(
    'playback_error'
  , function(e) {
      fail('<?= LNG_ERR_PLAYBACK_TRACK_COULD_NOT_PLAY ?>');
    }
  );
  player.addListener('initialization_error', (e) => { fail(e.message); });
  player.addListener('authentication_error', (e) => { fail(e.message); });
  player.addListener('account_error', (e) => {});

  player.connect();
}

function triggerResume() {
  PLAYBACK_PLAYER.getCurrentState().then(
    state => {
      if (!state) return;

      PLAYBACK_PLAYER.resume().then(
        () => {
          // Check if resume was successful
          PLAYBACK_PLAYER.getCurrentState().then(
            state => {
              if (!state) return;
              let current_track = state.track_window.current_track;
              if (!current_track) return;
              renderPlaying();
              startSeek();
            }
          );
        }
      );
    }
  );
}

function triggerPause() {
  PLAYBACK_PLAYER.pause().then();
  renderPaused();
  stopSeek();
}

function hasPlayback() {
  return PLAYBACK_PLAYER != null;
}

function playTrack( track_id
                  , pos_ms
                  , success_f
                  , fail_f
                  , attempts = PLAYBACK_PLAY_TRACK_ATTEMPTS
                  ) {
  if (!PLAYBACK_PLAYER) {
    fail_f('<?= LNG_ERR_PLAYBACK_NOT_POSSIBLE ?>');
  }
  callApi( '/api/player/'
         , { 'action': 'play'
           , 'device': PLAYBACK_DEVICE_ID
           , 'track': track_id
           , 'positionMs': pos_ms
           }
         , function() {
             PLAYBACK_LAST_PLAYED_TRACK_ID = track_id;
             success_f()
           }
         , // Sometimes this fails due to "bad gateway". If that's the case,
           // we retry
           function(msg) {
             if (msg === 'Bad gateway.' && attempts > 0) {
               playTrack(track_id, pos_ms, success_f, fail_f, attempts-1);
             }
             else {
               fail_f();
             }
           }
         );
  // Controller and seek will be updated when player reacts to state change
}

function startSeek() {
  updateSeek();
  if (!PLAYBACK_SEEK_TIMER) {
    PLAYBACK_SEEK_TIMER = setInterval(updateSeek, PLAYBACK_SEEK_UPDATE_FREQ_MS);
  }
}

function stopSeek() {
  clearInterval(PLAYBACK_SEEK_TIMER);
  PLAYBACK_SEEK_TIMER = null;
}

function updateSeek() {
  PLAYBACK_PLAYER.getCurrentState().then(
    state => {
      if (!state) return;
      PLAYBACK_PLAYER.getCurrentState().then(
        state => {
          if (!state) return;
          renderSeek(state.position, state.duration);
        }
      );
    }
  );
}

function setupSeekController() {
  let seek_area = getPlaybackArea().find('.seek');
  let knob = seek_area.find('.knob');
  let knob_state = 0;
  function hideKnob() {
    knob_state--;
    if (knob_state == 0) {
      knob.hide();
    }
  }
  seek_area.hover(
    function() {
      if (!PLAYBACK_HAS_TRACK) return;
      knob.show();
      knob_state++;
    }
  , function() {
      if (!PLAYBACK_HAS_TRACK) return;
      hideKnob();
    }
  );
  seek_area.mousedown(
    function(e) {
      if (!PLAYBACK_HAS_TRACK) return;

      let bar = seek_area.find('.bar');
      let position_ms = 0;
      function move(e) {
        const bar_left = bar.position().left;
        const bar_right = bar_left + bar.width();
        let pos = 0;
        if (bar_left <= e.pageX && e.pageX <= bar_right) {
          pos = (e.pageX - bar_left) / bar.width();
        }
        else if (e.pageX > bar_right) {
          pos = 1;
        }
        const duration = seek_area.data('duration');
        position_ms = Math.round(duration * pos);

        renderSeek(position_ms, duration);
      }
      function up() {
        PLAYBACK_PLAYER.getCurrentState().then(
          state => {
            if (!state) return;
            let current_track = state.track_window.current_track;
            if (!current_track) return;
            playTrack( PLAYBACK_LAST_PLAYED_TRACK_ID
                     , position_ms
                     , function() {}
                     , function() {
                         showPlaybackError(
                           '<?= LNG_ERR_PLAYBACK_TRACK_COULD_NOT_PLAY ?>'
                         );
                         startSeek(); // Restore seek
                       }
                     );
          }
        );

        hideKnob();
        $(document).unbind('mousemove', move).unbind('mouseup', up);
      }

      stopSeek();
      move(e);
      knob_state++;
      $(document).mousemove(move).mouseup(up);
    }
  );
}

function setupVolumeController() {
  let vol_area = getPlaybackArea().find('.volume');
  let knob = vol_area.find('.knob');
  let knob_state = 0;
  function hideKnob() {
    knob_state--;
    if (knob_state == 0) {
      knob.hide();
    }
  }
  vol_area.hover(
    function() {
      knob.show();
      knob_state++;
    }
  , function() {
      hideKnob();
    }
  );
  vol_area.mousedown(
    function(e) {
      let bar = vol_area.find('.bar');
      function move(e) {
        const bar_left = bar.position().left;
        const bar_right = bar_left + bar.width();
        let volume = 0;
        if (bar_left <= e.pageX && e.pageX <= bar_right) {
          volume = (e.pageX - bar_left) / bar.width();
        }
        else if (e.pageX > bar_right) {
          volume = 1;
        }

        PLAYBACK_PLAYER.setVolume(volume).then();
        renderVolume(volume);
        saveVolume(volume);
      }
      function up() {
        hideKnob();
        $(document).unbind('mousemove', move).unbind('mouseup', up);
      }

      move(e);
      knob_state++;
      $(document).mousemove(move).mouseup(up);
    }
  );
}

function saveVolume(volume) {
  Cookies.set('playback-volume', volume, { expires: 365*5 });
}
