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
var PLAYBACK_IS_STOPPED = false;
var PLAYBACK_LAST_PLAYED_TRACK_ID = null;
var PLAYBACK_LAST_PLAYED_INDEX = null;
var PLAYBACK_LAST_PLAYED_TABLE = null;
var PLAYBACK_IGNORE_NEXT_STATE_CHANGES = 0;

var PLAYBACK_MAX_PLAY_LENGTH_MS = 0;
var PLAYBACK_CHECK_PLAY_POS_TIMER = null;

var PLAYBACK_FADE_OUT_TIMER = null;
var PLAYBACK_FADE_OUT_LENGTH_MS = 0;

const PLAYBACK_SEEK_UPDATE_FREQ_MS = 1000;
const PLAYBACK_PLAY_TRACK_ATTEMPTS = 3;
const PLAYBACK_DEFAULT_VOLUME = 0.5;
const PLAYBACK_CHECK_PLAY_POS_UPDATE_FREQ_MS = 500;
const PLAYBACK_FADE_OUT_STEP_MS = 100;

function setupPlayback() {
  mkPlaybackHtml();
  $.getScript('https://sdk.scdn.co/spotify-player.js', function() {});
  window.onSpotifyWebPlaybackSDKReady = initPlayer;

  $(document).on( 'keydown'
                , function(e) {
                    if (e.key == ' ') {
                      let target = $(e.target);
                      if ( !target.is('input') &&
                           !target.is('textarea') &&
                           !target.is('select')
                         ) {
                        togglePlay();
                        return false;
                      }
                    }
                  }
                );
}

function getMaxPlayLength() {
  return PLAYBACK_MAX_PLAY_LENGTH_MS;
}

function setMaxPlayLength(len_ms) {
  PLAYBACK_MAX_PLAY_LENGTH_MS = len_ms;
}

function getFadeOutLength() {
  return PLAYBACK_FADE_OUT_LENGTH_MS;
}

function setFadeOutLength(len_ms) {
  PLAYBACK_FADE_OUT_LENGTH_MS = len_ms;
}

function showPlaybackError(msg) {
  let old_area = $('.playback-error');
  if (old_area.length> 0) {
    old_area.remove();
  }
  let area = $('<div class="playback-error"></div>');
  $(document.body).append(area);
  area.text('<?= LNG_DESC_ERROR ?>: ' + msg);
  let offset = $('.playback').outerHeight(false) + $('.footer').outerHeight(false);
  area.css('bottom', offset + 'px');
}

function mkPlaybackHtml() {
  let playback_html =
    $( '<div class="playback">' +
       '  <div class="x-container">' +
       '    <div class="playing">' +
       '      <div class="name">' +
       '        <?= LNG_DESC_DOUBLE_CLICK_TRACK_TO_PLAY ?>' +
       '      </div>' +
       '      <div class="artists"></div>' +
       '    </div>' +
       '    <div class="middle">' +
       '      <div class="controllers">' +
       '        <button class="prev" />' +
       '        <button class="play" />' +
       '        <button class="next" />' +
       '      </div>' +
       '      <div class="seek">' +
       '        <div class="length-pos"></div>' +
       '        <div class="bar-wrapper">' +
       '          <div class="bar">' +
       '            <div class="active-bar"></div>' +
       '            <div class="knob"></div>' +
       '          </div>' +
       '        </div>' +
       '        <div class="length-end"></div>' +
       '      </div>' +
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
  $('.footer').before(playback_html);
  setupControllerButtons();
  setupSeekController();
  setupVolumeController();
  renderPaused();
}

function getPlaybackArea() {
  return $('.playback');
}

function getPlayButton() {
  return getPlaybackArea().find('.controllers button.play');
}

function setupPrevButton() {
  let button = getPlaybackArea().find('.controllers button.prev');
  button.html(
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 22 24">' +
    '  <path fill="currentColor" d="M 22 24 L 22 0 L 2 12 L 20 24 Z"/>' +
    '  <path fill="currentColor" d="M 0 24 L 0 0 L 2 0 L 2 24 Z"/>' +
    '</svg>'
  );
  button.click(rewindOrPlayPrevTrack);
}

function setupNextButton() {
  let button = getPlaybackArea().find('.controllers button.next');
  button.html(
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 22 24">' +
    '  <path fill="currentColor" d="M 0 24 L 0 0 L 20 12 L 0 24 Z"/>' +
    '  <path fill="currentColor" d="M 22 24 L 22 0 L 20 0 L 20 24 Z"/>' +
    '</svg>'
  );
  button.click(playNextTrack);
}

function renderPlaying() {
  getPlayButton().html(
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 18 24">' +
    '  <path fill="currentColor" ' +
    '    d="M 6 24 L 0 24 L 0 0 L 6 0 L 6 24 Z"/>' +
    '  <path fill="currentColor" ' +
    '    d="M 18 0 L 12 0 L 12 24 L 18 24 L 18 0 Z"/>' +
    '</svg>'
  );
}

function renderPaused() {
  getPlayButton().html(
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 24" class="triangle">' +
    '  <path fill="currentColor" d="M 0 24 L 0 0 L 20 12 L 0 24 Z"/>' +
    '</svg>'
  );
}

function renderSeek(position, duration) {
  let area = getPlaybackArea().find('.seek');
  area.data('duration', duration);
  area.find('.length-pos').text(
    position !== null ? formatTrackLength(position) : ''
  );
  area.find('.length-end').text(
    duration !== null ? formatTrackLength(duration) : ''
  );

  let percent = position !== null ? (position / duration) * 100 : 0;
  area.find('.active-bar').css('width', percent + '%');
  area.find('.knob').css('left', percent + '%');
}

function renderVolume(pos) {
  let area = getPlaybackArea();
  let percent = pos * 100;
  area.find('.volume .active-bar').css('width', percent + '%');
  area.find('.volume .knob').css('left', percent + '%');
}

function adjustDuration(duration) {
  if (PLAYBACK_MAX_PLAY_LENGTH_MS > 0 && duration > PLAYBACK_MAX_PLAY_LENGTH_MS) {
    return PLAYBACK_MAX_PLAY_LENGTH_MS;
  }
  return duration;
}

function initPlayer() {
  const token = '<?= getAccessToken() ?>';
  const player = new Spotify.Player(
    { name: 'Dancify'
    , getOAuthToken: cb => { cb(token); }
    , volume: getSavedVolume()
    }
  );

  function fail(msg) {
    stopSeek();
    stopPlayPosCheck();
    clearFadeOut();
    showPlaybackError(msg);
  }
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

      if (PLAYBACK_IGNORE_NEXT_STATE_CHANGES > 0) {
        PLAYBACK_IGNORE_NEXT_STATE_CHANGES--;
        return;
      }

      clearFadeOut();
      if (state.paused) {
        renderPaused();
        stopSeek();
        stopPlayPosCheck();
      }
      else {
        renderPlaying();
        startSeek();
        setupFadeOut(state.position, adjustDuration(state.duration));
        startPlayPosCheck();
      }
      renderSeek(state.position, adjustDuration(state.duration));

      if (state.track_window.current_track === null) {
        PLAYBACK_HAS_TRACK = false;
        renderTrackInfo(null, null);
        renderSeek(null, null);
        markPlayingTrackInPlaylist(null, null, null);
        return;
      }
      PLAYBACK_HAS_TRACK = true;

      let name = state.track_window.current_track.name;
      let artists = state.track_window.current_track.artists.map((a) => a.name);
      renderTrackInfo(name, artists);

      if (state.paused && state.position == 0) {
        checkPlayPos();
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
  loadPlaybackSettings(noop, showPlaybackError);
}

function startPlayPosCheck() {
  if (!PLAYBACK_CHECK_PLAY_POS_TIMER) {
    PLAYBACK_CHECK_PLAY_POS_TIMER =
      setInterval( checkPlayPos
                 , PLAYBACK_CHECK_PLAY_POS_UPDATE_FREQ_MS
                 );
  }
}

function stopPlayPosCheck() {
  clearInterval(PLAYBACK_CHECK_PLAY_POS_TIMER);
  PLAYBACK_CHECK_PLAY_POS_TIMER = null;
}

function checkPlayPos() {
  PLAYBACK_PLAYER.getCurrentState().then(
    state => {
      if (!PLAYBACK_HAS_TRACK) return;
      // Check if we are at the end of the track
      if ( ( state.paused && state.position == 0 ) ||
           state.position > adjustDuration(state.duration)
         ) {
        stopSeek();
        playNextTrack();
      }
    }
  );
}

function togglePlay() {
  if (!PLAYBACK_PLAYER) {
    return;
  }

  PLAYBACK_PLAYER.getCurrentState().then(
    state => {
      if (!state) {
        // Got nothing to play; pick first track in playlist
        let table = getPlaylistTable();
        let track_data = removePlaceholdersFromTracks(getTrackData(table));
        if (track_data.length > 0) {
          playTrack(track_data[0].trackId, 0, table, 0);
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

function getBestPlayingTrackWindow() {
  const playlist_table = getPlaylistTable();
  const local_scratchpad_table = getLocalScratchpadTable();
  const global_scratchpad_table = getGlobalScratchpadTable();
  const track_data =
    [ [ playlist_table
      , removePlaceholdersFromTracks(getTrackData(playlist_table))
      ]
    , [ local_scratchpad_table
      , removePlaceholdersFromTracks(getTrackData(local_scratchpad_table))
      ]
    , [ global_scratchpad_table
      , removePlaceholdersFromTracks(getTrackData(global_scratchpad_table))
      ]
    ];
  for (let i = 0; i < track_data.length; i++) {
    const table = track_data[i][0];
    const tracks = track_data[i][1];

    // Find best index to consider as last played track
    let best_playing_index = -1;
    let best_playing_index_diff = Number.MAX_SAFE_INTEGER;
    for (let j = 0; j < tracks.length; j++) {
      let t = tracks[j];
      let diff = Math.abs(MARKED_AS_PLAYING_INDEX - j);
      if ( table.is(MARKED_AS_PLAYING_TABLE) &&
           t.trackId === PLAYBACK_LAST_PLAYED_TRACK_ID &&
           diff < best_playing_index_diff
         ) {
        best_playing_index = j;
        best_playing_index_diff = diff;
      }
    }

    if (best_playing_index >= 0) {
      let idx = best_playing_index;
      let current = [idx, tracks[idx].trackId];
      let prev = idx > 0 ? [idx-1, tracks[idx-1].trackId] : [null, null];
      let next = idx < tracks.length-1 ? [idx+1, tracks[idx+1].trackId]
                                       : [null, null];
      return [prev, current, next];
    }
  }

  return [null, null, null];
}

function rewindOrPlayPrevTrack() {
  if (!PLAYBACK_PLAYER) {
    return;
  }

  PLAYBACK_PLAYER.activateElement();

  function playPrevTrack() {
    let [[prev_i, prev_track_id], current, next] = getBestPlayingTrackWindow();
    if (prev_track_id) {
      playTrack( prev_track_id
               , prev_i
               , PLAYBACK_LAST_PLAYED_TABLE
               , 0
               );
    }
  }

  PLAYBACK_PLAYER.getCurrentState().then(
    state => {
      if (!state) {
        playPrevTrack();
        return;
      }

      if (state.position < 1000) {
        playPrevTrack();
      }
      else {
        playTrack( PLAYBACK_LAST_PLAYED_TRACK_ID
                 , PLAYBACK_LAST_PLAYED_INDEX
                 , PLAYBACK_LAST_PLAYED_TABLE
                 , 0
                 );
      }
    }
  );
}

function playNextTrack() {
  if (!PLAYBACK_PLAYER) {
    return;
  }

  if (PLAYBACK_IS_STOPPED) {
    return
  }

  PLAYBACK_PLAYER.activateElement();

  let [prev, current, [next_i, next_track_id]] = getBestPlayingTrackWindow();

  if (next_track_id) {
    playTrack( next_track_id
             , next_i
             , PLAYBACK_LAST_PLAYED_TABLE
             , 0
             );
  }
  else {
    // This is the last track; skip to end of it
    PLAYBACK_IS_STOPPED = true;
    PLAYBACK_PLAYER.getCurrentState().then(
      state => {
        if (!state) return;
        // Must skip to almost end of track, or else it will try to play it from
        // the beginning.
        let end_pos = adjustDuration(state.duration) - 200;
        playTrack( PLAYBACK_LAST_PLAYED_TRACK_ID
                 , PLAYBACK_LAST_PLAYED_INDEX
                 , PLAYBACK_LAST_PLAYED_TABLE
                 , end_pos
                 );
      }
    );
  }
}

function triggerResume() {
  PLAYBACK_PLAYER.activateElement();

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
  PLAYBACK_IS_STOPPED = true;
}

function hasPlayback() {
  return PLAYBACK_PLAYER != null;
}

function playTrack( track_id
                  , index
                  , table
                  , pos_ms
                  , success_f = function() {}
                  , fail_f = function() {
                      showPlaybackError(
                        '<?= LNG_ERR_PLAYBACK_TRACK_COULD_NOT_PLAY ?>'
                      );
                      startSeek(); // Restore seek
                    }
                  , attempts = PLAYBACK_PLAY_TRACK_ATTEMPTS
                  ) {
  if (!PLAYBACK_PLAYER) {
    fail_f('<?= LNG_ERR_PLAYBACK_NOT_POSSIBLE ?>');
  }

  PLAYBACK_PLAYER.activateElement();

  // Invoking the player API to play will cause two state changes, the first of
  // which may cause unintended skipping to the next song if this was invoked
  // when the player was put in pause. By ignoring the next state change, we
  // circumvent that problem.
  PLAYBACK_IGNORE_NEXT_STATE_CHANGES = 1;

  stopPlayPosCheck();
  callApi( '/api/player/'
         , { 'action': 'play'
           , 'device': PLAYBACK_DEVICE_ID
           , 'track': track_id
           , 'positionMs': pos_ms
           }
         , function() {
             PLAYBACK_LAST_PLAYED_TRACK_ID = track_id;
             PLAYBACK_LAST_PLAYED_TABLE = table;
             markPlayingTrackInPlaylist(track_id, index, table);
             success_f()
           }
         , // Sometimes this fails due to "bad gateway". If that's the case,
           // we retry
           function(msg) {
             if (msg === 'Bad gateway.' && attempts > 0) {
               playTrack( track_id
                        , index
                        , table
                        , pos_ms
                        , success_f
                        , fail_f
                        , attempts-1
                        );
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
          renderSeek(state.position, adjustDuration(state.duration));
        }
      );
    }
  );
}

function renderTrackInfo(name, artists) {
  let area = getPlaybackArea();
  area.find('.playing .name').text(name !== null ? name : '');
  area.find('.playing .artists').text(artists !== null ? artists.join(', ') : '');
}

function setupControllerButtons() {
  setupPrevButton();
  setupNextButton();
  getPlayButton().click(togglePlay);
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
                     , PLAYBACK_LAST_PLAYED_INDEX
                     , PLAYBACK_LAST_PLAYED_TABLE
                     , position_ms
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

function getSavedVolume() {
  let volume = Cookies.get('playback-volume');
  if (volume === undefined) {
    return PLAYBACK_DEFAULT_VOLUME;
  }
  return volume;
}

function setupFadeOut(position, duration) {
  if (PLAYBACK_FADE_OUT_LENGTH_MS <= 0) return;

  function fadeOut() {
    PLAYBACK_PLAYER.getCurrentState().then(
      state => {
        let position = state.position;
        let duration = adjustDuration(state.duration);
        if (position >= duration) return;

        const orig_volume = getSavedVolume();
        let vol_ratio = (duration - position) / PLAYBACK_FADE_OUT_LENGTH_MS;
        if (vol_ratio < 0) vol_ratio = 0;
        if (vol_ratio > 1) vol_ratio = 1;
        //let new_volume = orig_volume * vol_ratio;
        let new_volume = orig_volume * Math.log10(vol_ratio*10 + 1);
        PLAYBACK_PLAYER.setVolume(new_volume).then();
        PLAYBACK_FADE_OUT_TIMER = setTimeout(fadeOut, PLAYBACK_FADE_OUT_STEP_MS);
      }
    );
  }

  clearFadeOut();
  let trigger_time =  duration - position - PLAYBACK_FADE_OUT_LENGTH_MS;
  let expected_pos = duration - PLAYBACK_FADE_OUT_LENGTH_MS;
  if (trigger_time > 0) {
    PLAYBACK_FADE_OUT_TIMER = setTimeout(fadeOut, trigger_time);
  }
  else {
    fadeOut(position);
  }
}

function clearFadeOut() {
  if (PLAYBACK_FADE_OUT_TIMER) {
    clearInterval(PLAYBACK_FADE_OUT_TIMER);
  }
  PLAYBACK_FADE_OUT_TIMER = null;
  PLAYBACK_PLAYER.setVolume(getSavedVolume()).then();
}

function loadPlaybackSettings(success_f, fail_f) {
  callApi( '/api/get-playback/'
         , {}
         , function(d) {
             if (d.status == 'OK') {
               PLAYBACK_MAX_PLAY_LENGTH_MS = d.trackPlayLength*1000;
               FADE_OUT_LENGTH_MS = d.fadeOutLength*1000;
             }
             success_f();
           }
         , function(msg) {
             fail_f('<?= LNG_ERR_FAILED_LOAD_PLAYBACK_SETTINGS ?>');
           }
         );
}
