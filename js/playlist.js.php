<?php
require '../autoload.php';

ensureSession();
$session = getSession();
$api = createWebApi($session);
?>

var PLAYLIST_INFO = null;
var PREVIEW_AUDIO = $('<audio />');
var PLAYLIST_DANCE_DELIMITER = 0;
var PLAYLIST_DELIMITERS = [];
var TRACK_DRAG_STATE = 0;
const UNDO_STACK_LIMIT = 100;
var UNDO_STACK = Array(UNDO_STACK_LIMIT).fill(null);
var UNDO_STACK_OFFSET = -1;
var LAST_SPOTIFY_PLAYLIST_HASH = '';
var LATEST_TRACK_CLICK_TR = null;
var LATEST_TRACK_CLICK_TIMESTAMP = 0;
var MARKED_AS_PLAYING_TRACK = null;
var MARKED_AS_PLAYING_INDEX = null;
var MARKED_AS_PLAYING_TABLE = null;
var LOADED_GLOBAL_SCRATCHPAD = false;
var IS_LOADING_PLAYLIST_CONTENT = false;
var ABORT_LOAD_PLAYLIST_CONTENT = false;
var ABORT_LOAD_PLAYLIST_CALLBACK = null;
var DELETE_BEHAVIOR_INHIBITED = false;

const BPM_MIN = 0;
const BPM_MAX = 255;

const TRACK_AREA_HEIGHT = 10; // In rem
const TRACK_AREA_HEIGHT_REDUCTION = 1; // In rem

const DOUBLECLICK_RANGE_MS = 400;

function setupPlaylist() {
  let is_ctrl_pressed = false;
  $(document).on( 'keydown'
                , function(e) {
                    if ($('.action-input-area:visible').length == 0) {
                      if (e.key == 'Escape') {
                        clearTrackTrSelection();
                        return false;
                      }
                      if (e.key == 'Delete' && !DELETE_BEHAVIOR_INHIBITED) {
                        deleteSelectedTrackTrs();
                        return false;
                      }
                      if (e.key == 'Control') {
                        is_ctrl_pressed = true;
                        return false;
                      }
                      if (e.key == 'a' && is_ctrl_pressed) {
                        selectAllTrackTrs();
                        return false;
                      }
                    }
                  }
                );
  $(document).on( 'keyup'
                , function(e) {
                    if (e.key == 'Control') {
                      is_ctrl_pressed = false;
                    }
                  }
                );
  $(window).resize(setPlaylistHeight);
  $(window).resize(renderTrackOverviews);
  $('.app-separator').each(
    function() {
      addAppResizeHandling($(this));
    }
  );
}

function inhibitDeleteBehavior() {
  DELETE_BEHAVIOR_INHIBITED = true;
}

function reenableDeleteBehavior() {
  DELETE_BEHAVIOR_INHIBITED = false;
}

function getTableOfTr(tr) {
  return tr.closest('table');
}

function abortLoadPlaylistContent(callback) {
  if (IS_LOADING_PLAYLIST_CONTENT) {
    ABORT_LOAD_PLAYLIST_CONTENT = true;
    ABORT_LOAD_PLAYLIST_CALLBACK = function() {
      ABORT_LOAD_PLAYLIST_CONTENT = false;
      ABORT_LOAD_PLAYLIST_CALLBACK = null;
      callback();
    };
  }
  else {
    callback();
  }
}

function loadPlaylistContent(playlist_info, success_f, fail_f) {
  PLAYLIST_INFO = playlist_info;
  let playlist_id = playlist_info.id;
  IS_LOADING_PLAYLIST_CONTENT = true;

  $('.playlist:not(.scratchpad) .playlist-title').text(playlist_info.name);

  let body = $(document.body);
  body.addClass('loading');
  setStatus('<?= LNG_DESC_LOADING ?>...');
  function success() {
    IS_LOADING_PLAYLIST_CONTENT = false;
    body.removeClass('loading');
    clearStatus();
    saveUndoState();
    success_f();
  }
  function fail() {
    IS_LOADING_PLAYLIST_CONTENT = false;
    let msg = '<?= LNG_ERR_FAILED_LOAD_PLAYLIST ?>';
    setStatus(msg, true);
    body.removeClass('loading');
    fail_f(msg);
  }
  function snapshot_success() {
    success();
    // TODO: fix bug that causes entire playlist to be erased
    //checkForChangesInSpotifyPlaylist(playlist_id);
  }
  function noSnapshot() {
    loadPlaylistFromSpotify(playlist_id, success, fail);
  }
  initTable(getPlaylistTable());
  initTable(getLocalScratchpadTable());
  resetPlaylistSettings();
  loadPlaylistFromSnapshot(playlist_id, snapshot_success, noSnapshot, fail);
}

function clearPlaylistContent() {
  LAST_SPOTIFY_PLAYLIST_HASH = '';
  for (let i = 0; i < UNDO_STACK.length; i++) {
    UNDO_STACK[i] = null;
  }
  UNDO_STACK_OFFSET = -1;
  LAST_SPOTIFY_PLAYLIST_HASH = '';
  LATEST_TRACK_CLICK_TR = null;
  LATEST_TRACK_CLICK_TIMESTAMP = 0;
  MARKED_AS_PLAYING_TRACK = null;
  MARKED_AS_PLAYING_INDEX = null;
  MARKED_AS_PLAYING_TABLE = null;

  [ getPlaylistTable(), getLocalScratchpadTable() ].forEach(
    table => {
      clearTable(table);
      renderTable(table);
    }
  );
  renderUndoRedoButtons();
}

function getCurrentPlaylistInfo() {
  return PLAYLIST_INFO;
}

function resetPlaylistSettings() {
  PLAYLIST_DANCE_DELIMITER = 0;
  setDelimiterAsHidden();

  PLAYLIST_DELIMITERS = [];
  clearPlaylistDelimiterElements();

  getTrackOverviews().forEach(([name, div]) => div.hide());
}

function loadPlaylistFromSpotify(playlist_id, success_f, fail_f) {
  function updatePlaylistHash() {
    let track_ids = getTrackData(getPlaylistTable()).map(t => t.trackId);
    LAST_SPOTIFY_PLAYLIST_HASH = computePlaylistHash(track_ids);
  }
  function abort() {
    if (ABORT_LOAD_PLAYLIST_CONTENT) {
      ABORT_LOAD_PLAYLIST_CALLBACK();
      return true;
    }
    return false;
  }
  function load(offset) {
    if (abort()) return;
    let data = { playlistId: playlist_id
               , offset: offset
               };
    callApi( '/api/get-playlist-tracks/'
           , data
           , function(d) {
             if (abort()) return;
               let track_ids = d.tracks.map((t) => t.track);
               let added_by = d.tracks.map((t) => t.addedBy);
               callApi( '/api/get-track-info/'
                      , { trackIds: track_ids }
                      , function(dd) {
                          let tracks = [];
                          for (let i = 0; i < dd.tracks.length; i++) {
                            if (abort()) return;
                            let t = dd.tracks[i];
                            let o = createPlaylistTrackObject(
                                      t.trackId
                                    , t.artists
                                    , t.name
                                    , t.length
                                    , t.bpm
                                    , t.acousticness
                                    , t.danceability
                                    , t.energy
                                    , t.instrumentalness
                                    , t.valence
                                    , t.genre.by_user
                                    , t.genre.by_others
                                    , t.comments
                                    , t.preview_url
                                    , added_by[i]
                                    );
                            tracks.push(o);
                          }
                          appendTracks(getPlaylistTable(), tracks);
                          let next_offset = offset + tracks.length;
                          if (next_offset < d.total) {
                            load(next_offset);
                          }
                          else {
                            renderTable(getPlaylistTable());
                            updatePlaylistHash();
                            success_f();
                          }
                          if (abort()) return;
                        }
                      , fail_f
                      );
             }
           , fail_f
           );
  }
  load(0);
}

function checkForChangesInSpotifyPlaylist(playlist_id) {
  function abort() {
    if (ABORT_LOAD_PLAYLIST_CONTENT) {
      ABORT_LOAD_PLAYLIST_CALLBACK();
      return true;
    }
    return false;
  }

  let body = $(document.body);
  function fail(msg) {
    setStatus('<?= LNG_ERR_FAILED_LOAD_PLAYLIST ?>', true);
    body.removeClass('loading');
  }
  function getActionArea() {
    return $('.action-input-area[name=playlist-inconsistencies]');
  }
  function checkForAdditions(snapshot_tracks, spotify_tracks, callback_f) {
    body.addClass('loading');
    setStatus('<?= LNG_DESC_LOADING ?>...');
    function cleanup() {
      body.removeClass('loading');
      clearStatus();
    }

    // Find tracks appearing Spotify but not in snapshot
    let new_tracks = [];
    for (let i = 0; i < spotify_tracks.length; i++) {
      let track = spotify_tracks[i].track;
      let tid = track.track;
      let t = getTrackWithMatchingId(snapshot_tracks, tid);
      if (t === null) {
        new_tracks.push(track);
      }
    }
    if (new_tracks.length == 0) {
      cleanup();
      callback_f();
      return;
    }

    let has_finalized = false;
    function finalize() {
      if (!has_finalized) {
        cleanup();
        clearActionInputs();
        callback_f();
      }
      has_finalized = true;
    }
    function loadTracks(offset, dest_table) {
      let tracks_to_load = [];
      let o = offset;
      for ( let o = offset
          ; o < new_tracks.length &&
            tracks_to_load.length < LOAD_TRACKS_LIMIT
          ; o++
          )
      {
        tracks_to_load.push(new_tracks[o]);
      }
      if (abort()) return;
      callApi( '/api/get-track-info/'
             , { trackIds: tracks_to_load.map((t) => t.track) }
             , function(d) {
                 if (abort()) return;
                 let tracks = [];
                 for (let i = 0; i < d.tracks.length; i++) {
                   if (abort()) return;
                   let t = d.tracks[i];
                   let o = createPlaylistTrackObject( t.trackId
                                                    , t.artists
                                                    , t.name
                                                    , t.length
                                                    , t.bpm
                                                    , t.acousticness
                                                    , t.danceability
                                                    , t.energy
                                                    , t.instrumentalness
                                                    , t.valence
                                                    , t.genre.by_user
                                                    , t.genre.by_others
                                                    , t.comments
                                                    , t.preview_url
                                                    , tracks_to_load[i].addedBy
                                                    );
                   tracks.push(o);
                 }
                 appendTracks(dest_table, tracks);
                 let next_offset = offset + tracks.length;
                 if (next_offset < d.total) {
                   loadTracks(next_offset, dest_table);
                 }
                 else {
                   renderTable(dest_table);
                   indicateStateUpdate();
                   finalize();
                 }
                 if (abort()) return;
               }
             , fail
             );
    }

    let a = getActionArea();
    a.find('p').text('<?= LNG_DESC_TRACK_ADDITIONS_DETECTED ?>');
    let btn1 = a.find('#inconPlaylistBtn1');
    let btn2 = a.find('#inconPlaylistBtn2');
    let cancel_btn = btn1.closest('div').find('button.cancel');
    btn1.text('<?= LNG_BTN_APPEND_TO_PLAYLIST ?>');
    btn2.text('<?= LNG_BTN_APPEND_TO_LOCAL_SCRATCHPAD ?>');
    btn1.click(
      function() {
        loadTracks(0, getPlaylistTable());
      }
    );
    btn2.click(
      function() {
        let table = getLocalScratchpadTable();
        loadTracks(0, table);
        showScratchpad(table);
      }
    );
    cancel_btn.click(finalize);
    a.show();

    function esc_f(e) {
      if (e.key == 'Escape') {
        finalize();
        $(document).unbind('keyup', esc_f);
      }
    }
    $(document).on('keyup', esc_f);
  }
  function checkForDeletions(snapshot_tracks, spotify_track_ids, callback_f) {
    if (abort()) return;

    // Find tracks not appearing Spotify but in snapshot
    let removed_track_ids = [];
    for (let i = 0; i < snapshot_tracks.length; i++) {
      let tid = snapshot_tracks[i].trackId;
      if (tid === undefined) {
        continue;
      }
      let found = false;
      for (let j = 0; j < spotify_track_ids.length; j++) {
        if (spotify_track_ids[j] == tid) {
          found = true;
          break;
        }
      }
      if (!found) {
        removed_track_ids.push(tid);
      }
    }
    if (removed_track_ids.length == 0) {
      callback_f();
      return;
    }

    let has_finalized = false;
    function finalize() {
      if (!has_finalized) {
        clearActionInputs();
        callback_f();
      }
      has_finalized = true;
    }
    function popTracks(tracks_to_remove) {
      let removed_tracks = [];

      // Pop from playlist
      let has_removed = false;
      let playlist_tracks = getTrackData(getPlaylistTable());
      for (let i = 0; i < tracks_to_remove.length; i++) {
        let res = popTrackWithMatchingId(playlist_tracks, tracks_to_remove[i]);
        playlist_tracks = res[0];
        let removed_t = res[1];
        if (removed_t !== null) {
          removed_tracks.push(removed_t);
          has_removed = true;
        }
      }
      if (has_removed) {
        replaceTracks(getPlaylistTable(), playlist_tracks);
      }

      // Pop from local scratchpad
      let s_table = getLocalScratchpadTable();
      has_removed = false;
      let scratchpad_tracks = getTrackData(s_table);
      for (let i = 0; i < tracks_to_remove.length; i++) {
        let res = popTrackWithMatchingId( scratchpad_tracks
                                        , tracks_to_remove[i]
                                        );
        scratchpad_tracks = res[0];
        let removed_t = res[1];
        if (removed_t !== null) {
          removed_tracks.push(removed_t);
          has_removed = true;
        }
      }
      if (has_removed) {
        replaceTracks(s_table, scratchpad_tracks);
      }

      return removed_tracks;
    }
    let a = getActionArea();
    a.find('p').text('<?= LNG_DESC_TRACK_DELETIONS_DETECTED ?>');
    let btn1 = a.find('#inconPlaylistBtn1');
    let btn2 = a.find('#inconPlaylistBtn2');
    btn1.text('<?= LNG_BTN_REMOVE ?>');
    btn2.text('<?= LNG_BTN_MOVE_TO_LOCAL_SCRATCHPAD ?>');
    let cancel_btn = btn1.closest('div').find('button.cancel');
    btn1.click(
      function() {
        popTracks(removed_track_ids);
        renderTable(getLocalScratchpadTable());
        indicateStateUpdate();
        finalize();
      }
    );
    btn2.click(
      function() {
        let table = getLocalScratchpadTable();
        let removed_tracks = popTracks(removed_track_ids);
        let scratchpad_data = getTrackData(table);
        let new_scratchpad_data = scratchpad_data.concat(removed_tracks);
        replaceTracks(table, new_scratchpad_data);
        renderTable(table);
        indicateStateUpdate();
        showScratchpad(table);
        finalize();
      }
    );
    cancel_btn.click(finalize);
    a.show();

    function esc_f(e) {
      if (e.key == 'Escape') {
        finalize();
        $(document).unbind('keyup', esc_f);
      }
    }
    $(document).on('keyup', esc_f);

    if (abort()) return;
  }
  let spotify_tracks = [];
  function load(offset) {
    if (abort()) return;
    let data = { playlistId: playlist_id
               , offset: offset
               };
    callApi( '/api/get-playlist-tracks/'
           , data
           , function(d) {
               if (abort()) return;
               spotify_tracks = spotify_tracks.concat(d.tracks);
               let next_offset = offset + d.tracks.length;
               if (next_offset < d.total) {
                 load(next_offset);
               }
               else {
                 let playlist_hash =
                   computePlaylistHash(spotify_tracks.map((t) => t.track));
                 if (playlist_hash == LAST_SPOTIFY_PLAYLIST_HASH) {
                   return;
                 }
                 LAST_SPOTIFY_PLAYLIST_HASH = playlist_hash;
                 let s_table = getLocalScratchpadTable();
                 let snapshot_tracks =
                   getTrackData(getPlaylistTable()).concat(getTrackData(s_table));
                 checkForAdditions( snapshot_tracks
                                  , spotify_tracks
                                  , function () {
                                      checkForDeletions( snapshot_tracks
                                                       , spotify_tracks
                                                       , function() {}
                                                       );
                                    }
                                  );
               }
               if (abort()) return;
             }
           , fail
           );
  }
  load(0);
}

function computePlaylistHash(track_ids) {
  track_ids =
    track_ids.map(
      (t) => (typeof t === 'string' && t.length > 0) ? t : 'placeholder'
    );

  // https://stackoverflow.com/a/52171480
  function cyrb53(str, seed = 0) {
    let h1 = 0xdeadbeef ^ seed, h2 = 0x41c6ce57 ^ seed;
    for (let i = 0, ch; i < str.length; i++) {
      ch = str.charCodeAt(i);
      h1 = Math.imul(h1 ^ ch, 2654435761);
      h2 = Math.imul(h2 ^ ch, 1597334677);
    }
    h1 = Math.imul(h1 ^ (h1>>>16), 2246822507) ^
         Math.imul(h2 ^ (h2>>>13), 3266489909);
    h2 = Math.imul(h2 ^ (h2>>>16), 2246822507) ^
         Math.imul(h1 ^ (h1>>>13), 3266489909);
    return 4294967296 * (2097151 & h2) + (h1>>>0);
  };

  return cyrb53(track_ids.join(''));
}

function playPreview(jlink, preview_url, playing_text, stop_text) {
  PREVIEW_AUDIO.attr('src', ''); // Stop playing
  let clicked_playing_preview = jlink.hasClass('playing');
  let preview_links = $.merge( getPlaylistTable().find('tr.track .title a')
                             , getLocalScratchpadTable().find('tr.track .title a')
                             , getGlobalScratchpadTable().find('tr.track .title a')
                             );
  preview_links.each(
    function() {
      $(this).removeClass('playing');
      $(this).html(stop_text);
    }
  );
  if (clicked_playing_preview) {
    jlink.html(stop_text);
    return;
  }

  jlink.html(playing_text);
  jlink.addClass('playing');
  PREVIEW_AUDIO.attr('src', preview_url);
  PREVIEW_AUDIO.get(0).play();
}

function updateBpmInDb(track_id, bpm, success_f, fail_f) {
  callApi( '/api/update-bpm/'
         , { trackId: track_id, bpm: bpm }
         , function(d) { success_f(d); }
         , function(msg) { fail_f(msg); }
         );
}

function addTrackBpmHandling(tr) {
  let input = tr.find('input[name=bpm]');

  input.click(
    function(e) {
      e.stopPropagation(); // Prevent row selection
    }
  );
  input.focus(
    function() {
      $(this).css('background-color', '#fff');
      $(this).css('color', '#000');
      $(this).data('old-value', $(this).val().trim());
      inhibitDeleteBehavior();
    }
  );
  input.blur(
    function() {
      renderTrackBpm($(this).closest('tr'));
      reenableDeleteBehavior();
    }
  );

  function fail(msg) {
    setStatus('<?= LNG_ERR_FAILED_UPDATE_BPM ?>', true);
  }

  function getBpmValue() {
    return input.val().trim();
  }

  function triggerBpmUpdate(bpm) {
    // Find corresponding track ID
    let tid_input = input.closest('tr').find('input[name=track_id]');
    if (tid_input.length == 0) {
      console.log('could not find track ID');
      return;
    }
    let tid = tid_input.val().trim();
    if (tid.length == 0) {
      return;
    }

    setStatus('<?= LNG_DESC_SAVING ?>...');
    updateBpmInDb( tid
                 , bpm
                 , clearStatus
                 , fail
                 );

    input.removeClass('fromSpotify');

    // Update BPM on all duplicate tracks (if any)
    function update(table, tid) {
      table.find('input[name=track_id][value=' + tid + ']').each(
        function() {
          let tr = $(this).closest('tr');
          let input = tr.find('input[name=bpm]');
          input.val(bpm);
          input.removeClass('fromSpotify');
          renderTrackBpm(tr);
        }
      );
    }
    update(getPlaylistTable(), tid);
    update(getLocalScratchpadTable(), tid);
    update(getGlobalScratchpadTable(), tid);

    let old_value = parseInt(input.data('old-value'));
    // .data() must be read here or else it will disappear upon undo/redo
    setCurrentUndoStateCallback(
      function() {
        updateBpmInDb( tid
                     , old_value
                     , function() {}
                     , fail
                     );
      }
    );
    indicateStateUpdate();
    setCurrentUndoStateCallback(
      function() {
        updateBpmInDb( tid
                     , bpm
                     , function() {}
                     , fail
                     );
      }
    );

    renderTrackOverviews();
  }

  let skip_confirm = false;
  function onKeyDown(e) {
    if (e.key === 'Enter') {
      skip_confirm = true;

      // Move focus to genre
      input.closest('tr').find('select[name=genre]').focus();
    }
  }

  let old_value = null;
  input.focus(
    function() {
      old_value = parseInt(getBpmValue());
      input.on('keydown', onKeyDown);
    }
  );
  input.blur(
    function() {
      input.off('keydown', onKeyDown);

      // Check BPM value
      let bpm = getBpmValue();
      if (!checkBpmInput(bpm)) {
        input.addClass('invalid');
        return;
      }
      bpm = parseInt(bpm);
      input.removeClass('invalid');

      if (old_value === bpm && input.hasClass('fromSpotify') && !skip_confirm) {
        skip_confirm = false;
        if (!window.confirm('<?= LNG_DESC_ASK_CONFIRM_BPM ?>')) {
          return;
        }
      }

      triggerBpmUpdate(bpm);
    }
  );
}

function renderTrackBpm(tr) {
  let input = tr.find('input[name=bpm]');
  if (input.length == 0) {
    return;
  }

  let bpm = input.val().trim();
  if (!checkBpmInput(bpm, false)) {
    return;
  }
  bpm = parseInt(bpm);
  let cs = getBpmRgbColor(bpm);
  input.css('background-color', 'rgb(' + cs.join(',') + ')');
  $text_color = !input.hasClass('fromSpotify') ?
                ((bpm <= 50 || bpm  > 190) ? '#fff' : '#000') : '#888';
  input.css('color', $text_color);
}

function getBpmRgbColor(bpm) {
  //               bpm    color (RGB)
  const colors = [ [   0, [  0,   0,   0] ] // Black
                 , [  40, [  0,   0, 255] ] // Blue
                 , [  65, [  0, 255, 255] ] // Turquoise
                 , [  80, [  0, 255,   0] ] // Green
                 , [  95, [255, 255,   0] ] // Yellow
                 , [ 140, [255,   0,   0] ] // Red
                 , [ 180, [255,   0, 255] ] // Purple
                 , [ 210, [  0,   0, 255] ] // Blue
                 , [ 255, [  0,   0,   0] ] // Black
                 ];
  for (let i = 0; i < colors.length; i++) {
    if (i == colors.length-2 || bpm < colors[i+1][0]) {
      let p = (bpm - colors[i][0]) / (colors[i+1][0] - colors[i][0]);
      let c = [...colors[i][1]];
      for (let j = 0; j < c.length; j++) {
        c[j] += Math.round((colors[i+1][1][j] - c[j]) * p);
      }
      return c;
    }
  }
  console.log('ERROR: getBpmColor() with arg ' + bpm);
  return null;
}

function checkBpmInput(str, report_on_fail = true) {
  bpm = parseInt(str);
  if (isNaN(bpm)) {
    if (report_on_fail) {
      alert('<?= LNG_ERR_BPM_NAN ?>');
    }
    return false;
  }
  if (bpm < BPM_MIN) {
    if (report_on_fail) {
      alert('<?= LNG_ERR_BPM_TOO_SMALL ?>');
    }
    return false;
  }
  if (bpm > BPM_MAX) {
    if (report_on_fail) {
      alert('<?= LNG_ERR_BPM_TOO_LARGE ?>');
    }
    return false;
  }
  return true;
}

function getEnergyRgbColor(e) {
  //               energy color (RGB)
  const colors = [ [ 0.0, [  0,   0, 255] ] // Blue
                 , [ 0.5, [255, 255,   0] ] // Yellow
                 , [ 1.0, [  0, 255,   0] ] // Green
                 ];
  for (let i = 0; i < colors.length; i++) {
    if (i == colors.length-2 || e < colors[i+1][0]) {
      let p = (e - colors[i][0]) / (colors[i+1][0] - colors[i][0]);
      let c = [...colors[i][1]];
      for (let j = 0; j < c.length; j++) {
        c[j] += Math.round((colors[i+1][1][j] - c[j]) * p);
      }
      return c;
    }
  }
  console.log('ERROR: getEnergyRgbColor() with arg ' + e);
  return null;
}

function updateGenreInDb(track_id, genre, success_f, fail_f) {
  callApi( '/api/update-genre/'
         , { trackId: track_id, genre: genre }
         , function(d) { success_f(d); }
         , function(msg) { fail_f(msg); }
         );
}

function addTrackGenreHandling(tr) {
  let select = tr.find('select[name=genre]');
  function update(s) {
    function fail(msg) {
      setStatus('<?= LNG_ERR_FAILED_UPDATE_GENRE ?>', true);
    }

    let genre = parseInt(s.find(':selected').val().trim());
    let old_value = parseInt(s.data('old-value'));
    if (genre == old_value && !s.hasClass('chosen-by-others')) {
      return;
    }
    s.removeClass('chosen-by-others');
    s.data('old-value', genre);

    // Find corresponding track ID
    let tid_input = s.closest('tr').find('input[name=track_id]');
    if (tid_input.length == 0) {
      console.log('could not find track ID');
      return;
    }
    let tid = tid_input.val().trim();
    if (tid.length == 0) {
      return;
    }

    setStatus('<?= LNG_DESC_SAVING ?>...');
    updateGenreInDb( tid
                   , genre
                   , clearStatus
                   , fail
                   );

    // Update genre on all duplicate tracks (if any)
    function update(table, tid) {
      table.find('input[name=track_id][value=' + tid + ']').each(
        function() {
          let tr = $(this).closest('tr');
          tr.find('select[name=genre] option').prop('selected', false);
          tr.find('select[name=genre] option[value=' + genre + ']')
            .prop('selected', true);
        }
      );
    }
    update(getPlaylistTable(), tid);
    update(getLocalScratchpadTable(), tid);
    update(getGlobalScratchpadTable(), tid);

    setCurrentUndoStateCallback(
      function() {
        updateGenreInDb( tid
                       , old_value
                       , function() {}
                       , fail
                       );
      }
    );
    indicateStateUpdate();
    setCurrentUndoStateCallback(
      function() {
        updateGenreInDb( tid
                       , genre
                       , function() {}
                       , fail
                       );
      }
    );
  }
  select.click(
    function(e) {
      e.stopPropagation(); // Prevent row selection
      update($(this));
    }
  );
  select.change(function() { update($(this)); });

  function onKeyDown(e) {
    if (e.key === 'Enter') {
      // Move focus to BPM of next track
      select.closest('tr').nextAll().find('input[name=bpm]').first().focus();
    }
  }
  select.focus(
    function() {
      select.on('keydown', onKeyDown);
    }
  );
  select.blur(
    function() {
      select.off('keydown', onKeyDown);
    }
  );
}

function updateCommentsInDb(track_id, comments, success_f, fail_f) {
  callApi( '/api/update-comments/'
         , { trackId: track_id, comments: comments }
         , function(d) { success_f(d); }
         , function(msg) { fail_f(msg); }
         );
}

function addTrackCommentsHandling(tr) {
  let textarea = tr.find('textarea[name=comments]');
  textarea.click(
    function(e) {
      e.stopPropagation(); // Prevent row selection
    }
  );
  textarea.focus(
    function() {
      $(this).data('old-value', $(this).val().trim());
      inhibitDeleteBehavior();
    }
  );
  textarea.blur(
    function() {
      reenableDeleteBehavior();
    }
  );
  function fail(msg) {
    setStatus('<?= LNG_ERR_FAILED_UPDATE_COMMENTS ?>', true);
  }
  textarea.change(
    function() {
      let textarea = $(this);
      renderTrackComments(textarea.closest('tr'));

      // Find corresponding track ID
      let tid_input = textarea.closest('tr').find('input[name=track_id]');
      if (tid_input.length == 0) {
        console.log('could not find track ID');
        return;
      }
      let tid = tid_input.val().trim();
      if (tid.length == 0) {
        return;
      }

      let comments = textarea.val().trim();
      setStatus('<?= LNG_DESC_SAVING ?>...');
      updateCommentsInDb( tid
                        , comments
                        , clearStatus
                        , fail
                        );

      // Update comments on all duplicate tracks (if any)
      function update(table, tid) {
        table.find('input[name=track_id][value=' + tid + ']').each(
          function() {
            let tr = $(this).closest('tr');
            tr.find('textarea[name=comments]').val(comments);
            renderTrackComments(tr);
          }
        );
      }
      update(getPlaylistTable(), tid);
      update(getLocalScratchpadTable(), tid);
      update(getGlobalScratchpadTable(), tid);

      let old_value = parseInt(textarea.data('old-value'));
      // .data() must be read here or else it will disappear upon undo/redo
      setCurrentUndoStateCallback(
        function() {
          updateCommentsInDb( tid
                            , old_value
                            , function() {}
                            , fail
                            );
        }
      );
      indicateStateUpdate();
      setCurrentUndoStateCallback(
        function() {
          updateCommentsInDb( tid
                            , comments
                            , function() {}
                            , fail
                            );
        }
      );
    }
  );
}

function renderTrackComments(tr) {
  let textarea = tr.find('textarea[name=comments]');
  if (textarea.length == 0) {
    return;
  }

  // Adjust height
  if (textarea.data('defaultHeight') === undefined) {
    textarea.data('defaultHeight', textarea.height());
  }
  textarea.css('height', textarea.data('defaultHeight'));
  textarea.css( 'height'
              , textarea.prop('scrollHeight')+2 + 'px'
                // +2 is to prevent scrollbars that appear otherwise
              );
}

function getTrTitleText(tr) {
  let nodes = tr.find('td.title').contents().filter(
                function() { return this.nodeType == 3; }
              );
  if (nodes.length > 0) {
    return nodes[0].nodeValue;
  }
  return '';
}

function getTrackObjectFromTr(tr) {
  if (tr.hasClass('track')) {
    let track_id = tr.find('input[name=track_id]').val().trim();
    let added_by = tr.find('input[name=added_by]').val().trim();
    let artists = tr.find('input[name=artists]').val().trim();
    artists = artists.length > 0 ? artists.split(',') : [];
    let name = tr.find('input[name=name]').val().trim();
    let preview_url = tr.find('input[name=preview_url]').val().trim();
    let bpm_input = tr.find('input[name=bpm]');
    let bpm = parseInt(bpm_input.val().trim());
    let is_bpm_custom = !bpm_input.hasClass('fromSpotify');
    let acousticness =
      parseFloat(tr.find('input[name=acousticness]').val().trim());
    let danceability =
      parseFloat(tr.find('input[name=danceability]').val().trim());
    let energy = parseFloat(tr.find('input[name=energy]').val().trim());
    let instrumentalness =
      parseFloat(tr.find('input[name=instrumentalness]').val().trim());
    let valence = parseFloat(tr.find('input[name=valence]').val().trim());
    let genre_by_user =
      parseInt(tr.find('select[name=genre] option:selected').val().trim());
    let genres_by_others_text =
      tr.find('input[name=genres_by_others]').val().trim();
    let genres_by_others =
      genres_by_others_text.length > 0
        ? genres_by_others_text.split(',').map(s => parseInt(s))
        : [];
    let title = getTrTitleText(tr);
    let len_ms = parseInt(tr.find('input[name=length_ms]').val().trim());
    let comments = tr.find('textarea[name=comments]').val().trim();
    return createPlaylistTrackObject( track_id
                                    , artists
                                    , name
                                    , len_ms
                                    , { 'custom': is_bpm_custom ? bpm : -1
                                      , 'spotify': bpm
                                      }
                                    , acousticness
                                    , danceability
                                    , energy
                                    , instrumentalness
                                    , valence
                                    , genre_by_user
                                    , genres_by_others
                                    , comments
                                    , preview_url
                                    , added_by
                                    );
  }
  else {
    let name = tr.find('td.title').text().trim();
    let length = tr.find('td.length').text().trim();
    let bpm = '';
    let genre = tr.find('td.genre').text().trim();
    return createPlaylistPlaceholderObject(name, length, bpm, genre);
  }
}

function getTrackData(table) {
  let playlist = [];
  table.find('tr.track, tr.empty-track').each(
    function() {
      let tr = $(this);
      playlist.push(getTrackObjectFromTr(tr));
    }
  );
  return playlist;
}

function removePlaceholdersFromTracks(tracks) {
  return tracks.filter( function(t) { return t.trackId !== undefined } );
}

function createPlaylistTrackObject( track_id
                                  , artists
                                  , name
                                  , length_ms
                                  , bpm
                                  , acousticness
                                  , danceability
                                  , energy
                                  , instrumentalness
                                  , valence
                                  , genre_by_user
                                  , genres_by_others
                                  , comments
                                  , preview_url
                                  , added_by
                                  )
{
  return { trackId: track_id
         , artists: artists
         , name: name
         , length: length_ms
         , bpm: bpm
         , acousticness: acousticness
         , danceability: danceability
         , energy: energy
         , instrumentalness: instrumentalness
         , valence: valence
         , genre: { by_user: genre_by_user
                  , by_others: genres_by_others
                  }
         , comments: comments
         , previewUrl: preview_url
         , addedBy: added_by
         }
}

function createPlaylistPlaceholderObject( name_text
                                        , length_text
                                        , bpm_text
                                        , genre_text
                                        )
{
  if (name_text === undefined) {
    return { name: '<?= LNG_DESC_PLACEHOLDER ?>'
           , length: ''
           , bpm: ''
           , genre: ''
           }
  }
  return { name: name_text
         , length: length_text
         , bpm: bpm_text
         , genre: genre_text
         }
}

function popTrackWithMatchingId(track_list, track_id) {
  let i = 0;
  for (; i < track_list.length && track_list[i].trackId != track_id; i++) {}
  if (i < track_list.length) {
    let t = track_list[i];
    track_list.splice(i, 1);
    return [track_list, t];
  }
  return [track_list, null];
}

function getTrackWithMatchingId(track_list, track_id) {
  let i = 0;
  for (; i < track_list.length && track_list[i].trackId != track_id; i++) {}
  return i < track_list.length ? track_list[i] : null;
}

function initTable(table) {
  table.find('thead').empty();
  table.find('tbody').empty();
  let head_tr =
    $( '<tr>' +
       '  <th class="index">#</th>' +
       '  <th class="bpm"><?= LNG_HEAD_BPM ?></th>' +
       '  <th class="genre"><?= LNG_HEAD_GENRE ?></th>' +
       '  <th><?= LNG_HEAD_TITLE ?></th>' +
       '  <th class="comments"><?= LNG_HEAD_COMMENTS ?></th>' +
       '  <th class="length"><?= LNG_HEAD_LENGTH ?></th>' +
       '  <th class="aggr-length"><?= LNG_HEAD_TOTAL ?></th>' +
       '</tr>'
     );
  table.find('thead').append(head_tr);
  table.append(buildNewTableSummaryRow());
}

function getGenreList() {
  return [ [  1, '<?= strtolower(LNG_GENRE_DANCEBAND) ?>']
         , [  2, '<?= strtolower(LNG_GENRE_COUNTRY) ?>']
         , [  3, '<?= strtolower(LNG_GENRE_ROCK) ?>']
         , [  4, '<?= strtolower(LNG_GENRE_POP) ?>']
         , [  5, '<?= strtolower(LNG_GENRE_SCHLAGER) ?>']
         , [  6, '<?= strtolower(LNG_GENRE_METAL) ?>']
         , [  7, '<?= strtolower(LNG_GENRE_PUNK) ?>']
         , [  8, '<?= strtolower(LNG_GENRE_DISCO) ?>']
         , [  9, '<?= strtolower(LNG_GENRE_RNB) ?>']
         , [ 10, '<?= strtolower(LNG_GENRE_BLUES) ?>']
         , [ 11, '<?= strtolower(LNG_GENRE_JAZZ) ?>']
         , [ 12, '<?= strtolower(LNG_GENRE_HIP_HOP) ?>']
         , [ 13, '<?= strtolower(LNG_GENRE_ELECTRONIC) ?>']
         , [ 14, '<?= strtolower(LNG_GENRE_HOUSE) ?>']
         , [ 15, '<?= strtolower(LNG_GENRE_CLASSICAL) ?>']
         , [ 16, '<?= strtolower(LNG_GENRE_SOUL) ?>']
         , [ 17, '<?= strtolower(LNG_GENRE_LATIN) ?>']
         , [ 18, '<?= strtolower(LNG_GENRE_REGGAE) ?>']
         , [ 19, '<?= strtolower(LNG_GENRE_TANGO) ?>']
         , [ 20, '<?= strtolower(LNG_GENRE_OPERA) ?>']
         , [ 21, '<?= strtolower(LNG_GENRE_SALSA) ?>']
         , [ 22, '<?= strtolower(LNG_GENRE_KIZOMBA) ?>']
         , [ 23, '<?= strtolower(LNG_GENRE_ROCKABILLY) ?>']
         , [ 24, '<?= strtolower(LNG_GENRE_ACOUSTIC) ?>']
         , [ 25, '<?= strtolower(LNG_GENRE_BALLAD) ?>']
         , [ 26, '<?= strtolower(LNG_GENRE_FUNK) ?>']
         , [ 27, '<?= strtolower(LNG_GENRE_VISPOP) ?>']
         , [ 28, '<?= strtolower(LNG_GENRE_FOLK_MUSIC) ?>']
         , [ 29, '<?= strtolower(LNG_GENRE_BIG_BAND) ?>']
         , [ 30, '<?= strtolower(LNG_GENRE_SOUND_EFFECT) ?>']
         , [ 31, '<?= strtolower(LNG_GENRE_GOSPEL) ?>']
         , [ 32, '<?= strtolower(LNG_GENRE_ACAPELLA) ?>']
         , [ 33, '<?= strtolower(LNG_GENRE_OUMPF) ?>']
         , [ 34, '<?= strtolower(LNG_GENRE_FOX) ?>']
         , [ 35, '<?= strtolower(LNG_GENRE_BUGG) ?>']
         , [ 36, '<?= strtolower(LNG_GENRE_WCS) ?>']
         ];
}

function genreToString(g) {
  const g_list = getGenreList();
  for (let i = 0; i < g_list.length; i++) {
    e = g_list[i];
    if (e[0] == g) {
      return e[1];
    }
  }
  return '';
}

function addOptionsToGenreSelect(s, ignore_empty = false) {
  let genres = [ [  0, ''] ].concat(getGenreList());
  if (ignore_empty) {
    genres.shift();
  }
  genres.sort( function(a, b) {
                 if (a[0] == 0) return -1;
                 if (b[0] == 0) return  1;
                 return strcmp(a[1], b[1]);
               }
             );
  genres.map(
    function(g) {
      let o = $('<option value="' + g[0] + '">' + g[1] + '</value>');
      s.append(o);
    }
  )
}

function formatGenre(g) {
  return [ [  0, ''] ].concat(getGenreList())[g][1];
}

function buildNewTableTrackTr() {
  let tr =
    $( '<tr class="track">' +
       '  <input type="hidden" name="track_id" value="" />' +
       '  <input type="hidden" name="added_by" value="" />' +
       '  <input type="hidden" name="artists" value="" />' +
       '  <input type="hidden" name="name" value="" />' +
       '  <input type="hidden" name="preview_url" value="" />' +
       '  <input type="hidden" name="length_ms" value="" />' +
       '  <input type="hidden" name="acousticness" value="" />' +
       '  <input type="hidden" name="danceability" value="" />' +
       '  <input type="hidden" name="energy" value="" />' +
       '  <input type="hidden" name="instrumentalness" value="" />' +
       '  <input type="hidden" name="valence" value="" />' +
       '  <input type="hidden" name="genres_by_others" value="" />' +
       '  <td class="index" />' +
       '  <td class="bpm">' +
       '    <input type="text" name="bpm" class="bpm" value="" />' +
       '  </td>' +
       '  <td class="genre">' +
       '    <select class="genre" name="genre"></select>' +
       '  </td>' +
       '  <td class="title" />' +
       '  <td class="comments">' +
       '    <textarea name="comments" class="comments" maxlength="255">' +
           '</textarea>' +
       '  </td>' +
       '  <td class="length" />' +
       '  <td class="aggr-length" />' +
       '</tr>'
     );
  addOptionsToGenreSelect(tr.find('select[name=genre]'));
  return tr;
}

function buildNewTableSummaryRow() {
  return $( '<tr class="summary">' +
             '  <td colspan="5" />' +
             '  <td class="length" />' +
             '  <td class="aggr-length" />' +
             '</tr>'
          );
}

function getTableSummaryTr(table) {
  let tr = table.find('tr.summary')[0];
  return $(tr);
}

function clearTable(table) {
  table.find('tbody tr').remove();
  table.append(buildNewTableSummaryRow());
}

function addTrackPreviewHandling(tr) {
  if (!tr.hasClass('track')) {
    return;
  }
  if (hasPlayback()) {
    return;
  }

  const static_text = '&#9835;';
  const playing_text = '&#9836;';
  const stop_text = static_text;
  let preview_url = tr.find('input[name=preview_url]').val().trim();
  if (preview_url.length == 0) {
    return;
  }

  let link = $('<a class="preview" href="#">' + static_text + '</a>');
  link.click(
    function(e) {
      playPreview($(this), preview_url, playing_text, stop_text);
      e.stopPropagation(); // Prevent row selection
    }
  );
  tr.find('td.title div.name').append(link);
}

function buildNewTableTrackTrFromTrackObject(track) {
  let tr = buildNewTableTrackTr();
  if ('trackId' in track) {
    tr.find('td.title').append( formatTrackTitleAsHtml( track.artists
                                                      , track.name
                                                      )
                              );
    tr.find('input[name=track_id]').prop('value', track.trackId);
    tr.find('input[name=added_by]').prop('value', track.addedBy);
    tr.find('input[name=artists]').prop('value', track.artists.join(','));
    tr.find('input[name=name]').prop('value', track.name);
    tr.find('input[name=preview_url]').prop('value', track.previewUrl);
    tr.find('input[name=acousticness]').prop('value', track.acousticness);
    tr.find('input[name=danceability]').prop('value', track.danceability);
    tr.find('input[name=energy]').prop('value', track.energy);
    tr.find('input[name=instrumentalness]')
      .prop('value', track.instrumentalness);
    tr.find('input[name=valence]').prop('value', track.valence);
    tr.find('input[name=length_ms]').prop('value', track.length);
    let bpm_input = tr.find('input[name=bpm]');
    if (track.bpm.custom >= 0) {
      bpm_input.prop('value', track.bpm.custom);
    }
    else {
      bpm_input.prop('value', track.bpm.spotify);
      bpm_input.addClass('fromSpotify');
    }
    tr.find('input[name=genres_by_others]')
      .prop('value', track.genre.by_others.join(','));
    tr.find('textarea[name=comments]').text(track.comments);
    tr.find('td.length').text(formatTrackLength(track.length));

    // Genre
    let genre_select = tr.find('select[name=genre]');
    let genres_by_others = uniq(track.genre.by_others);
    if (track.genre.by_user != 0) {
      genre_select.find('option[value=' + track.genre.by_user + ']')
        .prop('selected', true);
    }
    else if (genres_by_others.length > 0) {
      genre_select.find('option[value=' + genres_by_others[0] + ']')
        .prop('selected', true);
      if (track.genre.by_user == 0) {
        genre_select.addClass('chosen-by-others');
      }
    }

    addTrackPreviewHandling(tr);
    addTrackBpmHandling(tr);
    addTrackGenreHandling(tr);
    addTrackCommentsHandling(tr);
  }
  else {
    tr.removeClass('track').addClass('empty-track');
    tr.find('td.title').text(track.name);
    tr.find('input[name=track_id]').remove();
    tr.find('input[name=preview_url]').remove();
    tr.find('input[name=length_ms]').remove();
    tr.find('input[name=genres_by_others]').remove();
    tr.find('textarea[name=comments]').remove();
    bpm_td = tr.find('input[name=bpm]').closest('td');
    bpm_td.find('input').remove();
    bpm_td.text(track.bpm);
    genre_td = tr.find('select[name=genre]').closest('td');
    genre_td.find('select').remove();
    genre_td.text(track.genre);
    tr.find('td.length').text(track.length);
  }
  addTrackTrSelectHandling(tr);
  addTrackTrDragHandling(tr);
  addTrackTrRightClickMenu(tr);
  return tr;
}

function appendTracks(table, tracks) {
  for (let i = 0; i < tracks.length; i++) {
    let new_tr = buildNewTableTrackTrFromTrackObject(tracks[i]);
    table.append(new_tr);
    renderTrack(new_tr);
  }
  table.append(getTableSummaryTr(table)); // Move summary to last
}

function renderTrack(tr) {
  renderTrackBpm(tr);
  renderTrackComments(tr);
}

function replaceTracks(table, tracks) {
  clearTable(table);
  appendTracks(table, tracks);
}

function renderTableIndices(table) {
  let trs = table.find('tr.track, tr.empty-track');
  for (let i = 0; i < trs.length; i++) {
    let tr = $(trs[i]);
    tr.find('td.index').text(i+1);
  }
}

function renderTablePlayingTrack(table) {
  let trs = table.find('tr.track, tr.empty-track');

  // Find best index to mark as playing (and clear all other rows)
  let best_playing_index = -1;
  let best_playing_index_diff = Number.MAX_SAFE_INTEGER;
  for (let i = 0; i < trs.length; i++) {
    let tr = $(trs[i]);
    tr.removeClass('playing');
    let tid_input = tr.find('input[name=track_id]');
    if (tid_input.length > 0) {
      let tid = tid_input.val().trim();
      let diff = Math.abs(MARKED_AS_PLAYING_INDEX - i);
      if ( table.is(MARKED_AS_PLAYING_TABLE) &&
           tid === MARKED_AS_PLAYING_TRACK &&
           diff < best_playing_index_diff
         ) {
        best_playing_index = i;
        best_playing_index_diff = diff;
      }
    }
  }

  if (best_playing_index >= 0) {
    let tr = $(trs[best_playing_index]);
    tr.addClass('playing');
    tr.find('td.index').html(
      '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"' +
      '    viewBox="-2 -6 24 30">' +
      '  <path fill="currentColor" d="M2 24v-24l20 12-20 12z"/>' +
      '</svg>'
    );
  }
}

function computePlaylistDelimiterPositions(tracks) {
  const dance_delimiter = PLAYLIST_DANCE_DELIMITER;
  let desired_delimiters = PLAYLIST_DELIMITERS;
  desired_delimiters = desired_delimiters.map((v) => v*1000); // Convert to ms
  if (desired_delimiters.length == 0) {
    return [];
  }

  let placed_delimiters = [];

  let delimiter_index = 0;
  let delimiter_length = 0;
  tracks.forEach(
    function(track, i) {
      if (isNaN(parseInt(track.length))) return;

      delimiter_length += track.length;

      if (delimiter_index < desired_delimiters.length) {
        let desired_length = desired_delimiters[delimiter_index];
        if (desired_length < delimiter_length) {
          // Find most suitable position to insert the playlist delimiter
          let num_slots = dance_delimiter > 0 ? dance_delimiter : 1;
          let current_slot = (i+1) % num_slots;

          // Find length at start of this dance
          let length_dance_start = delimiter_length;
          let index_dance_start = i+1;
          for (let j = num_slots - current_slot; j > 0; j--) {
            length_dance_start -= track.length;
            index_dance_start--;
          }

          // Find length at end of this dance
          let length_dance_end = delimiter_length;
          let index_dance_end = i+1;
          for (let j = num_slots - current_slot; j < num_slots; j++) {
            index_dance_end++;
            if (index_dance_end < tracks.length) {
              length_dance_end += track.length;
            }
          }

          let delimiter_data =
             ( desired_length - length_dance_start <
               length_dance_end - desired_length
             )
             ? [index_dance_start, length_dance_start]
             : [index_dance_end, length_dance_end];
          placed_delimiters.push(delimiter_data);

          delimiter_index++;
          delimiter_length = 0;
        }
      }
    }
  );

  // Insert remaining delimiters at the end
  for (let i = delimiter_index; i < desired_delimiters.length; i++) {
    placed_delimiters.push([tracks.length, delimiter_length]);
  }

  return placed_delimiters;
}

function renderTable(table) {
  let dance_delimiter =
    table.is(getPlaylistTable()) ? PLAYLIST_DANCE_DELIMITER : 0;
  let playlist_delimiters =
    table.is(getPlaylistTable()) ? PLAYLIST_DELIMITERS : [];
  playlist_delimiters = playlist_delimiters.map((v) => v*1000); // Convert to ms

  const num_cols = buildNewTableTrackTr(table).find('td').length;

  renderTableIndices(table);
  renderTablePlayingTrack(table);

  function getTrackLength(tr) {
    return parseInt(tr.find('input[name=length_ms]').val());
  }

  // Insert dance delimiters
  table.find('tr.delimiter').remove();
  if (dance_delimiter > 0) {
    table
      .find('tr.track, tr.empty-track')
      .filter(':nth-child(' + dance_delimiter + 'n)')
      .after(
        $( '<tr class="delimiter">' +
             '<td colspan="' + (num_cols-2) + '"><div /></td>' +
             '<td class="length"></td>' +
             '<td><div /></td>' +
           '</tr>'
         )
      );
  }
  let i = 0;
  let dance_length = 0;
  table.find('tr.track, tr.delimiter').each(
    function() {
      let tr = $(this);
      if (tr.hasClass('track')) {
        dance_length += getTrackLength(tr);
      }
      else if (tr.hasClass('delimiter')) {
        tr.find('td.length').text(formatTrackLength(dance_length));
        dance_length = 0;
      }
    }
  );

  // Compute total length
  let total_length = 0;
  let delimiter_index = 0;
  let delimiter_length = 0;
  table.find('tr.track').each(
    function(i) {
      let tr = $(this);

      let track_length = getTrackLength(tr);
      total_length += track_length;
      delimiter_length += track_length;
      tr.find('td.aggr-length').text(formatTrackLength(total_length));
    }
  );
  getTableSummaryTr(table).find('td.length').text(
    formatTrackLength(total_length)
  );

  // Insert playlist delimiters
  if (table.is(getPlaylistTable())) {
    let tracks = getTrackData(table);
    let playlist_delimiters = computePlaylistDelimiterPositions(tracks);
    let track_trs = table.find('tr.track, tr.empty-track');
    playlist_delimiters.forEach(
      function([i, length]) {
        let tr =
          $( '<tr class="delimiter playlist">' +
               '<td colspan="' + (num_cols-2) + '"><div /></td>' +
               '<td class="length">' + formatTrackLength(length) + '</td>' +
               '<td><div /></td>' +
             '</tr>'
           );
        if (i < track_trs.length) {
          track_trs.eq(i).before(tr);
        }
        else {
          track_trs.last().after(tr);
        }
      }
    );
  }

  if (table.is(getPlaylistTable())) {
    renderTrackOverviews();
    setPlaylistHeight();
  }
}

function renderAllTables() {
  renderTable(getPlaylistTable());
  renderTable(getLocalScratchpadTable());
  renderTable(getGlobalScratchpadTable());
}

function formatTrackTitleAsText(artists, name) {
  return artists.join(', ') + ' - ' + name;
}

function formatTrackTitleAsHtml(artists, name) {
  return $( '<div class="title">' +
              '<div class="name">' + name + '</div>' +
              '<div class="artists">' + artists.join(', ') + '</div>' +
            '</div>'
          );
}

function formatTrackLength(ms) {
  let t = Math.trunc(ms / 1000);
  t = [0, 0, t];
  for (let i = t.length - 2; i >= 0; i--) {
    if (t[i+1] < 60) break;
    t[i] = Math.floor(t[i+1] / 60);
    t[i+1] = t[i+1] % 60;
  }

  if (t[0] == 0) t.shift();
  for (let i = 1; i < t.length; i++) {
    if (t[i] < 10) t[i] = '0' + t[i].toString();
  }

  return t.join(':');
}

function setDanceDelimiter(d) {
  PLAYLIST_DANCE_DELIMITER = d;
}

function isUsingDanceDelimiter() {
  return PLAYLIST_DANCE_DELIMITER > 0;
}

function clearTrackTrSelection() {
  $('.playlist tr.selected').removeClass('selected');
}

function selectAllTrackTrs() {
  let trs = getSelectedTrackTrs();
  let table = null;
  if (trs.length > 0) {
    table = getTableOfTr($(trs[0]));
  }
  else {
    table = getPlaylistTable();
  }
  table.find('.track, .empty-track').addClass('selected');
}

function addTrackTrSelection(table, track_index) {
  let tr = $(table.find('.track, .empty-track')[track_index]);
  tr.addClass('selected');
}

function removeTrackTrSelection(table, track_index) {
  let tr = $(table.find('.track, .empty-track')[track_index]);
  tr.removeClass('selected');
}

function getSelectedTrackTrs() {
  return $('.playlist tr.selected');
}

function getTrackIndexOfTr(tr) {
  let track_trs = tr.closest('table').find('.track, .empty-track');
  if (tr.hasClass('track') || tr.hasClass('empty-track')) {
    return track_trs.index(tr);
  }
  return track_trs.length;
}

function updateTrackTrSelection(tr, multi_select_mode, span_mode) {
  function isTrInPlaylistTable(tr) {
    return tr.closest('table').is(getPlaylistTable());
  }

  // Remove active selection in other playlist areas
  $('.playlist').each(
    function() {
      let p = $(this);
      if (p.is(tr.closest('.playlist'))) {
        return;
      }
      p.find('tr.selected').removeClass('selected');

      if (p.find('table').is(getPlaylistTable())) {
        clearTrackBarSelectionInAllOverviews();
      }
    }
  );

  if (multi_select_mode) {
    if (!tr.hasClass('selected')) {
      tr.addClass('selected');
      if (isTrInPlaylistTable(tr)) {
        addTrackBarSelectionInAllOverviews(getTrackIndexOfTr(tr));
      }
    }
    else {
      tr.removeClass('selected');
      if (isTrInPlaylistTable(tr)) {
        removeTrackBarSelectionInAllOverviews(getTrackIndexOfTr(tr));
      }
    }
    return;
  }

  if (span_mode) {
    let selected_sib_trs =
      tr.siblings().filter(function() { return $(this).hasClass('selected') });
    if (selected_sib_trs.length == 0) {
      tr.addClass('selected');
      if (isTrInPlaylistTable(tr)) {
        addTrackBarSelectionInAllOverviews(getTrackIndexOfTr(tr));
      }
      return;
    }
    let first = $(selected_sib_trs[0]);
    let last = $(selected_sib_trs[selected_sib_trs.length-1]);
    let trs = tr.siblings().add(tr);
    for ( let i = Math.min(tr.index(), first.index(), last.index())
        ; i <= Math.max(tr.index(), first.index(), last.index())
        ; i++
        )
    {
      let sib_tr = $(trs[i]);
      if (sib_tr.hasClass('track') || sib_tr.hasClass('empty-track')) {
        sib_tr.addClass('selected');
        if (isTrInPlaylistTable(sib_tr)) {
          addTrackBarSelectionInAllOverviews(getTrackIndexOfTr(sib_tr));
        }
      }
    }
    return;
  }

  let selected_sib_trs =
    tr.siblings().filter(function() { return $(this).hasClass('selected') });
  $.each( selected_sib_trs
        , function() {
            let tr = $(this);
            tr.removeClass('selected');
            if (isTrInPlaylistTable(tr)) {
              removeTrackBarSelectionInAllOverviews(getTrackIndexOfTr(tr));
            }
          }
        );
  if (selected_sib_trs.length > 0) {
    tr.addClass('selected');
    if (isTrInPlaylistTable(tr)) {
      addTrackBarSelectionInAllOverviews(getTrackIndexOfTr(tr));
    }
    return;
  }

  if (!tr.hasClass('selected')) {
    tr.addClass('selected');
    if (isTrInPlaylistTable(tr)) {
      addTrackBarSelectionInAllOverviews(getTrackIndexOfTr(tr));
    }
  }
  else {
    tr.removeClass('selected');
    if (isTrInPlaylistTable(tr)) {
      removeTrackBarSelectionInAllOverviews(getTrackIndexOfTr(tr));
    }
  }
}

function addTrackTrSelectHandling(tr) {
  tr.click(
    function(e) {
      let tr = $(this);

      // Check whether to play this track was double-clicked; if so, play track
      let is_doubleclick = false;
      let timestamp_ms = Date.now();
      if ( tr.is(LATEST_TRACK_CLICK_TR) &&
           timestamp_ms - LATEST_TRACK_CLICK_TIMESTAMP <= DOUBLECLICK_RANGE_MS
         )
      {
        if (tr.find('input[name=track_id]').length > 0 && hasPlayback()) {
          let track_id = tr.find('input[name=track_id]').val();
          playTrack(track_id, getTrackIndexOfTr(tr), getTableOfTr(tr), 0);
          is_doubleclick = true;
        }
      }
      LATEST_TRACK_CLICK_TR = tr;
      LATEST_TRACK_CLICK_TIMESTAMP = timestamp_ms;
      if (is_doubleclick) return;

      if (TRACK_DRAG_STATE == 0) {
        updateTrackTrSelection(tr, e.ctrlKey || e.metaKey, e.shiftKey);
      }
      else {
        TRACK_DRAG_STATE = 0;
      }
    }
  );
}

function addTrackTrDragHandling(tr) {
  tr.mousedown(
    function(e) {
      let mousedown_tr = $(e.target).closest('tr');
      if (!mousedown_tr.hasClass('selected')) {
        return;
      }
      let body = $(document.body);
      body.addClass('grabbed');

      let selected_trs = getSelectedTrackTrs();
      let src_table = getTableOfTr($(selected_trs[0]));
      let mb = $('.grabbed-info-block');
      function checkAndUpdateMbText(dst_table) {
        let pre = '';
        if ( getGlobalScratchpadTable().is(dst_table) &&
             !src_table.is(dst_table)
           )
        {
          pre = '+';
        }
        mb.find('span').text(pre + selected_trs.length);
      }
      checkAndUpdateMbText(null);

      $('.playlist').toggleClass('drag-mode');

      let ins_point = $('.tr-drag-insertion-point');

      function move(e) {
        function clearInsertionPoint() {
          $('.playlist tr.insert-before, .playlist tr.insert-after')
            .removeClass('insert-before insert-after');
        }

        // Move info block
        const of = 5; // To prevent grabbed-info-block to appear as target
        mb.css({ top: e.pageY+of + 'px', left: e.pageX+of + 'px' });
        mb.show();

        TRACK_DRAG_STATE = 1; // tr.click() and mouseup may both reset this.
                              // This is to prevent deselection if drag stops on
                              // selected tracks

        // Check if moving over insertion-point bar (to prevent flickering)
        if ($(e.target).hasClass('tr-drag-insertion-point')) {
          return;
        }

        // Hide insertion-point bar if we are not over a playlist
        if ($(e.target).closest('.playlist').length == 0) {
          ins_point.hide();
          clearInsertionPoint();
          checkAndUpdateMbText(null);
          return;
        }

        let dst_table = $(e.target).closest('.playlist').find('table');
        checkAndUpdateMbText(dst_table);

        let tr = $(e.target).closest('tr');
        let insert_before = false;
        if (tr.length == 1) {
          // We have moved over a table row
          // If moved over empty-track tr, mark entire tr as insertion point
          if (tr.hasClass('empty-track')) {
            clearInsertionPoint();
            tr.addClass('insert-before');
            ins_point.hide();
            return;
          }

          // If moving over a delimiter
          if (tr.hasClass('delimiter')) {
            // Leave everything as is
            return;
          }

          // If moving over table head, move insertion point to next visible
          // tbody tr
          if (tr.closest('thead').length > 0) {
            tr = tr.closest('table').find('tbody tr').first();
            while (!tr.is(':visible')) {
              tr = tr.next();
            }
          }

          let tr_y_half = e.pageY - tr.offset().top - (tr.height() / 2);
          insert_before = tr_y_half <= 0 || tr.hasClass('summary');
        }
        else {
          // We have moved over the table but outside of any rows
          // Find summary row
          tr = $(e.target).closest('.table-wrapper').find('tr.summary');
          if (tr.length == 0) {
            clearInsertionPoint();
            ins_point.hide();
            return;
          }

          // Check that we are underneath the summary row; otherwise do nothing
          if (e.pageY < tr.offset().top) {
            clearInsertionPoint();
            ins_point.hide();
            return;
          }

          insert_before = true;
        }

        // Mark insertion point and draw insertion-point bar
        clearInsertionPoint();
        tr.addClass(insert_before ? 'insert-before' : 'insert-after');
        ins_point.css( { width: tr.width() + 'px'
                       , left: tr.offset().left + 'px'
                       , top: ( tr.offset().top +
                                (insert_before ? 0 : tr.height()) -
                                ins_point.height()/2
                              ) + 'px'
                       }
                     );
        ins_point.show();
      }

      function up() {
        let tr_insert_point =
          $('.playlist tr.insert-before, .playlist tr.insert-after');
        if (tr_insert_point.length == 1) {
          // Forbid dropping adjacent to a selected track as that causes wierd
          // reordering
          let dropped_adjacent_to_selected =
            tr_insert_point.hasClass('selected') ||
            ( tr_insert_point.hasClass('insert-before') &&
              tr_insert_point.prevAll('.track, .empty-track')
                             .first()
                             .hasClass('selected')
            ) ||
            ( tr_insert_point.hasClass('insert-after') &&
              tr_insert_point.nextAll('.track, .empty-track')
                             .first()
                             .hasClass('selected')
            );
          if (!dropped_adjacent_to_selected) {
            let table = tr_insert_point.closest('table');
            let ins_track_index = getTrackIndexOfTr(tr_insert_point);
            if (tr_insert_point.hasClass('insert-after')) {
              ins_track_index++;
            }
            dragSelectedTracksTo(table, ins_track_index);
          }
          tr_insert_point.removeClass('insert-before insert-after');
        }

        // Remove info block and insertion-point bar
        mb.hide();
        body.removeClass('grabbed');
        ins_point.hide();

        $('.playlist').toggleClass('drag-mode');

        if (!tr_insert_point.is(tr)) {
          TRACK_DRAG_STATE = 0;
        }

        $(document).unbind('mousemove', move).unbind('mouseup', up);
      }

      $(document).mousemove(move).mouseup(up);
    }
  );
}

function dragSelectedTracksTo(dst_table, track_index) {
  let selected_trs = getSelectedTrackTrs();
  if (selected_trs.length == 0) {
    return;
  }

  // If dragging tracks to global scratchpad, make copies instead of moving
  let source_table = getTableOfTr($(selected_trs[0]));
  if ( dst_table.is(getGlobalScratchpadTable()) &&
       !source_table.is(dst_table)
     )
  {
    selected_trs =
      selected_trs.map( function() {
                          let o = getTrackObjectFromTr($(this));
                          let tr = buildNewTableTrackTrFromTrackObject(o);
                          renderTrack(tr);
                          return tr;
                        }
                      )
                  .get();
  }

  let trs = dst_table.find('.track, .empty-track');
  if (trs.length == 0) {
    dst_table.prepend(selected_trs);
  }
  else if (track_index < trs.length) {
    let tr_insert_point = $(trs[track_index]);
    insertPlaceholdersBeforeMovingTrackTrs(selected_trs);
    tr_insert_point.before(selected_trs);
    if (tr_insert_point.hasClass('empty-track')) {
      tr_insert_point.remove();
    }
  }
  else {
    let tr_insert_point = trs.last();
    insertPlaceholdersBeforeMovingTrackTrs(selected_trs);
    tr_insert_point.after(selected_trs);
  }

  renderAllTables();
  indicateStateUpdate();
}

function insertPlaceholdersBeforeMovingTrackTrs(selected_trs) {
  let source_table = getTableOfTr($(selected_trs[0]));
  if (isUsingDanceDelimiter() && source_table.is(getPlaylistTable())) {
    // Ignore tracks that covers an entire dance block
    rows_to_keep = [];
    function isTrackRow(tr) {
      return tr.hasClass('track') || tr.hasClass('empty-track');
    }
    for (let i = 0; i < selected_trs.length; i++) {
      let tr = $(selected_trs[i]);
      if (!isTrackRow(tr.prev())) {
        let skip = false;
        let j = i;
        do {
          let next_tr = tr.next();
          if (!isTrackRow(next_tr)) {
            skip = true;
            break;
          }
          j++;
          if (j == selected_trs.length) {
            break;
          }
          tr = $(selected_trs[j]);
          if (!tr.is(next_tr)) {
            break;
          }
        } while (true);
        if (skip) {
          i = j;
          continue;
        }
      }
      rows_to_keep.push(tr);
    }
    for (let i = 0; i < rows_to_keep.length; i++) {
      let old_tr = $(rows_to_keep[i]);
      if (!old_tr.hasClass('empty-track')) {
        let o = createPlaylistPlaceholderObject();
        let new_tr = buildNewTableTrackTrFromTrackObject(o);
        old_tr.before(new_tr);
      }
    }
  }
}

function deleteSelectedTrackTrs() {
  let trs = getSelectedTrackTrs();
  if (trs.length == 0) {
    return;
  }

  insertPlaceholdersBeforeMovingTrackTrs(trs);
  let table = getTableOfTr($(trs[0]));
  trs.remove();
  renderTable(table);
  indicateStateUpdate();
}

function addTrackTrRightClickMenu(tr) {
  function buildMenu(menu, clicked_tr, close_f) {
    function buildPlaceholderTr() {
      let o = createPlaylistPlaceholderObject();
      return buildNewTableTrackTrFromTrackObject(o);
    }
    const actions =
      [ [ '<?= LNG_MENU_SELECT_IDENTICAL_TRACKS ?>'
        , function() {
            clicked_tid_input = clicked_tr.find('input[name=track_id]');
            if (clicked_tid_input.length == 0) {
              return;
            }
            let clicked_tid = clicked_tid_input.val().trim();
            getTableOfTr(clicked_tr).find('tr').each(
              function() {
                let tr = $(this);
                let tr_tid_input = tr.find('input[name=track_id]');
                if (tr_tid_input.length == 0) {
                  return;
                }
                let tr_tid = tr_tid_input.val().trim();
                if (tr_tid == clicked_tid) {
                  tr.addClass('selected');
                }
              }
            );
            close_f();
          }
        , function(a) {
            clicked_tid_input = clicked_tr.find('input[name=track_id]');
            if (clicked_tid_input.length == 0) {
              a.addClass('disabled');
            }
          }
        ]
      , [ '<?= LNG_MENU_INSERT_PLACEHOLDER_BEFORE ?>'
        , function() {
            let new_tr = buildPlaceholderTr();
            clicked_tr.before(new_tr);
            renderTable(getTableOfTr(clicked_tr));
            indicateStateUpdate();
            close_f();
          }
        , function(a) {}
        ]
      , [ '<?= LNG_MENU_INSERT_PLACEHOLDER_AFTER ?>'
        , function() {
            let new_tr = buildPlaceholderTr();
            clicked_tr.after(new_tr);
            renderTable(getTableOfTr(clicked_tr));
            indicateStateUpdate();
            close_f();
          }
        , function(a) {}
        ]
      , [ '<?= LNG_MENU_SHOW_PLAYLISTS_WITH_TRACK ?>'
        , function() {
            clicked_tid_input = clicked_tr.find('input[name=track_id]');
            if (clicked_tid_input.length == 0) {
              return;
            }
            let clicked_tid = clicked_tid_input.val().trim();
            let clicked_title = getTrTitleText(clicked_tr);
            showPlaylistsWithTrack(clicked_tid, clicked_title);
            close_f();
          }
        , function(a) {
            clicked_tid_input = clicked_tr.find('input[name=track_id]');
            if (clicked_tid_input.length == 0) {
              a.addClass('disabled');
            }
          }
        ]
      , [ '<?= LNG_MENU_DELETE_SELECTED ?>'
        , function() {
            deleteSelectedTrackTrs();
            close_f();
          }
        , function(a) {
            let trs = getSelectedTrackTrs();
            if (trs.length == 0) {
              a.addClass('disabled');
            }
          }
        ]
      ];
    menu.empty();
    for (let i = 0; i < actions.length; i++) {
      let a = $('<a href="#" />');
      a.text(actions[i][0]);
      a.click(actions[i][1]);
      actions[i][2](a);
      menu.append(a);
    }
  }

  tr.bind(
    'contextmenu'
  , function(e) {
      function close() {
        tr.removeClass('right-clicked');
        menu.hide();
      }
      tr.addClass('right-clicked');
      let menu = $('.mouse-menu');
      buildMenu(menu, tr, close);
      menu.css({ top: e.pageY + 'px', left: e.pageX + 'px' });
      menu.show();

      function hide(e) {
        if ($(e.target).closest('.mouse-menu').length == 0) {
          close();
          $(document).unbind('mousedown', hide);
        }
      }
      $(document).mousedown(hide);

      // Prevent browser right-click menu from appearing
      e.preventDefault();
      return false;
    }
  );
}

function savePlaylistSnapshot( success_f
                             , fail_f
                             , show_status = true
                             , no_playlist = false
                             , playlist_id = null
                             ) {
  if (PLAYLIST_INFO === null) {
    success_f();
    return;
  }

  if (show_status) {
    setStatus('<?= LNG_DESC_SAVING ?>...');
  }

  function getTrackInfo(t) {
    if (t.trackId === undefined) {
      return '';
    }
    return { track: t.trackId, addedBy: t.addedBy };
  }

  let playlist_tracks = null;
  if (!no_playlist) {
    playlist_tracks = getTrackData(getPlaylistTable()).map(getTrackInfo);
    if ( LAST_SPOTIFY_PLAYLIST_HASH === '' ||
         LAST_SPOTIFY_PLAYLIST_HASH === computePlaylistHash(
           playlist_tracks.map((t) => t.track)
         )
       ) {
      playlist_tracks = null;
    }
  }
  let scratchpad_tracks =
    getTrackData(getLocalScratchpadTable()).map(getTrackInfo);
  let overviews = {};
  getTrackOverviews().forEach(
    ([name, div]) => {
      overviews[name] = isTrackOverviewShowing(div);
    }
  );

  if (playlist_id === null) {
    playlist_id = PLAYLIST_INFO.id;
  }

  let data = { playlistId: playlist_id
             , snapshot: { playlistData: playlist_tracks
                         , scratchpadData: scratchpad_tracks
                         , delimiter: PLAYLIST_DANCE_DELIMITER
                         , playlistDelimiter: PLAYLIST_DELIMITERS
                         , trackOverviews: overviews
                         , spotifyPlaylistHash: LAST_SPOTIFY_PLAYLIST_HASH
                         }
             };
  callApi( '/api/save-playlist-snapshot/'
         , data
         , function(d) {
             if (show_status) {
               clearStatus();
             }
             success_f();
           }
         , function() {
             let msg = '<?= LNG_ERR_FAILED_TO_SAVE ?>';
             if (show_status) {
               setStatus(msg, true);
             }
             fail_f(msg);
           }
         );
}

function loadGlobalScratchpad(success_f, fail_f) {
  let body = $(document.body);
  body.addClass('loading');
  setStatus('<?= LNG_DESC_LOADING ?>...');
  function success() {
    body.removeClass('loading');
    clearStatus();
    success_f();
  }
  function fail() {
    let msg = '<?= LNG_ERR_FAILED_LOAD_GLOBAL_SCRATCHPAD ?>';
    setStatus(msg, true);
    body.removeClass('loading');
    fail_f(msg);
  }
  function load(tracks, track_offset) {
    function hasTrackAt(o) {
      return !(typeof tracks[o] === 'string' && tracks[o].length == 0);
    }

    let table = getGlobalScratchpadTable();

    if (track_offset >= tracks.length) {
      LOADED_GLOBAL_SCRATCHPAD = true;
      renderTable(table);
      success();
      return;
    }
    if (hasTrackAt(track_offset)) {
      // Currently at a track entry; add tracks until next placeholder entry
      let tracks_to_load = [];
      let o = track_offset;
      for ( ; o < tracks.length &&
              hasTrackAt(o) &&
              tracks_to_load.length < LOAD_TRACKS_LIMIT
            ; o++
          )
      {
        tracks_to_load.push(tracks[o]);
      }
      callApi( '/api/get-track-info/'
             , { trackIds: tracks_to_load.map(
                             (t) => {
                               if (typeof t === 'string') {
                                 return t;
                               }
                               return t.track;
                             }
                           )
               }
             , function(d) {
                 let tracks = [];
                 for (let i = 0; i < d.tracks.length; i++) {
                   let t = d.tracks[i];
                   let added_by = typeof tracks_to_load[i] === 'string'
                                  ? '' : tracks_to_load[i].addedBy;
                   let obj = createPlaylistTrackObject( t.trackId
                                                      , t.artists
                                                      , t.name
                                                      , t.length
                                                      , t.bpm
                                                      , t.acousticness
                                                      , t.danceability
                                                      , t.energy
                                                      , t.instrumentalness
                                                      , t.valence
                                                      , t.genre.by_user
                                                      , t.genre.by_others
                                                      , t.comments
                                                      , t.preview_url
                                                      , added_by
                                                      );
                   tracks.push(obj);
                 }
                 appendTracks(table, tracks);
                 load(tracks, o);
               }
             , fail
             );
    }
    else {
      // Currently at a placeholder entry; add such until next track entry
      let placeholders = [];
      let o = track_offset;
      for (; o < tracks.length && !hasTrackAt(o); o++) {
        placeholders.push(createPlaylistPlaceholderObject());
      }
      appendTracks(table, placeholders);
      load(tracks, o);
    }
  }
  initTable(getGlobalScratchpadTable());
  callApi( '/api/get-global-scratchpad/'
         , {}
         , function(res) {
             if (res.status == 'OK') {
               load(res.scratchpad.data, 0);
             }
             else if (res.status == 'NOT-FOUND') {
               LOADED_GLOBAL_SCRATCHPAD = true;
               success();
             }
           }
         , fail
         );
}

function saveGlobalScratchpad(success_f, fail_f, show_status = true) {
  if (!LOADED_GLOBAL_SCRATCHPAD) {
    success_f();
    return;
  }

  if (show_status) {
    setStatus('<?= LNG_DESC_SAVING ?>...');
  }

  function getTrackInfo(t) {
    if (t.trackId === undefined) {
      return '';
    }
    return { track: t.trackId, addedBy: t.addedBy };
  }
  tracks = getTrackData(getGlobalScratchpadTable()).map(getTrackInfo);
  data = { scratchpad: { data: tracks } };
  callApi( '/api/save-global-scratchpad/'
         , data
         , function(d) {
             if (show_status) {
               clearStatus();
             }
             success_f();
           }
         , function(msg) {
             if (show_status) {
               setStatus('<?= LNG_ERR_FAILED_TO_SAVE ?>', true);
             }
             fail_f();
           }
         );
}

function loadPlaylistFromSnapshot(playlist_id, success_f, no_snap_f, fail_f) {
  let status = [false, false];
  function done(table, status_offset) {
    status[status_offset] = true;
    renderTable(table);
    if (status.every(x => x)) {
      success_f();
    }
  }
  function load(table, status_offset, tracks, track_offset) {
    function hasTrackAt(o) {
      return !(typeof tracks[o] === 'string' && tracks[o].length == 0);
    }

    if (track_offset >= tracks.length) {
      done(table, status_offset);
      return;
    }
    if (hasTrackAt(track_offset)) {
      // Currently at a track entry; add tracks until next placeholder entry
      let tracks_to_load = [];
      let o = track_offset;
      for ( ; o < tracks.length &&
              hasTrackAt(o) &&
              tracks_to_load.length < LOAD_TRACKS_LIMIT
            ; o++
          )
      {
        tracks_to_load.push(tracks[o]);
      }
      callApi( '/api/get-track-info/'
             , { trackIds: tracks_to_load.map(
                             (t) => {
                               if (typeof t === 'string') {
                                 return t;
                               }
                               return t.track;
                             }
                           )
               }
             , function(d) {
                 let playlist_tracks = [];
                 for (let i = 0; i < d.tracks.length; i++) {
                   let t = d.tracks[i];
                   let added_by = typeof tracks_to_load[i] === 'string'
                                  ? '' : tracks_to_load[i].addedBy;
                   let obj = createPlaylistTrackObject( t.trackId
                                                      , t.artists
                                                      , t.name
                                                      , t.length
                                                      , t.bpm
                                                      , t.acousticness
                                                      , t.danceability
                                                      , t.energy
                                                      , t.instrumentalness
                                                      , t.valence
                                                      , t.genre.by_user
                                                      , t.genre.by_others
                                                      , t.comments
                                                      , t.preview_url
                                                      , added_by
                                                      );
                   playlist_tracks.push(obj);
                 }
                 appendTracks(table, playlist_tracks);
                 load(table, status_offset, tracks, o);
               }
             , fail_f
             );
    }
    else {
      // Currently at a placeholder entry; add such until next track entry
      let placeholders = [];
      let o = track_offset;
      for (; o < tracks.length && !hasTrackAt(o); o++) {
        placeholders.push(createPlaylistPlaceholderObject());
      }
      appendTracks(table, placeholders);
      load(table, status_offset, tracks, o);
    }
  }
  callApi( '/api/get-playlist-snapshot/'
         , { playlistId: playlist_id }
         , function(res) {
             if (res.status == 'OK') {
               let data = res.snapshot;
               if ('delimiter' in data) {
                 PLAYLIST_DANCE_DELIMITER = data.delimiter;
                 if (PLAYLIST_DANCE_DELIMITER > 0) {
                   setDelimiterAsShowing();
                 }
               }

               if ('playlistDelimiter' in data) {
                 PLAYLIST_DELIMITERS = data.playlistDelimiter;
                 PLAYLIST_DELIMITERS.forEach(
                   (v) => addPlaylistDelimiterElement(v)
                 );
               }
               else {
                 addNewPlaylistDelimiterElement();
               }

               if ('trackOverviews' in data) {
                 getTrackOverviews().forEach(
                   ([name, div]) => {
                     if (data.trackOverviews[name]) {
                       div.show();
                     }
                   }
                 );
               }

               LAST_SPOTIFY_PLAYLIST_HASH = data.spotifyPlaylistHash;

               if (data.playlistData !== null) {
                 load(getPlaylistTable(), 0, data.playlistData, 0);
               }
               else {
                 no_snap_f();
               }

               let s_table = getLocalScratchpadTable();
               load(s_table, 1, data.scratchpadData, 0);
               if (data.scratchpadData.length > 0) {
                 showScratchpad(s_table);
               }
             }
             else if (res.status == 'NOT-FOUND') {
               no_snap_f();
             }
           }
         , fail_f
         );
}

function indicateStateUpdate() {
  saveUndoState();
  savePlaylistSnapshotAndGlobalScratchpad();
}

function savePlaylistSnapshotAndGlobalScratchpad() {
  setStatus('<?= LNG_DESC_SAVING ?>...');
  function success() {
    clearStatus();
  }
  function fail(msg) {
    setStatus('<?= LNG_ERR_FAILED_TO_SAVE ?>', true);
  }

  savePlaylistSnapshot(
    function() {
      saveGlobalScratchpad(success, fail, false);
    }
  , fail
  , false
  );
}

function saveUndoState() {
  const limit = UNDO_STACK_LIMIT;

  // Find slot to save state
  if (UNDO_STACK_OFFSET+1 == limit) {
    // Remove first and shift all states
    for (let i = 1; i < limit; i++) {
      UNDO_STACK[i-1] = UNDO_STACK[i];
    }
  }
  else {
    UNDO_STACK_OFFSET++;
  }
  const offset = UNDO_STACK_OFFSET;

  // Destroy obsolete redo states
  for (let o = offset; o < limit; o++) {
    if (UNDO_STACK[o] !== null) {
      UNDO_STACK[o] = null;
    }
  }

  let state = { playlistTracks: getTrackData(getPlaylistTable())
              , localScratchpadTracks: getTrackData(getLocalScratchpadTable())
              , globalScratchpadTracks: getTrackData(getGlobalScratchpadTable())
              , callback: function() {}
              };
  UNDO_STACK[offset] = state;

  renderUndoRedoButtons();
}

function setCurrentUndoStateCallback(callback_f) {
  if (UNDO_STACK_OFFSET < 0 || UNDO_STACK_OFFSET >= UNDO_STACK_LIMIT) {
    console.log('illegal undo-stack offset value: ' + UNDO_STACK_OFFSET);
    return;
  }

  UNDO_STACK[UNDO_STACK_OFFSET].callback = callback_f;
}

function performUndo() {
  if (UNDO_STACK_OFFSET <= 0) {
    return;
  }

  const offset = --UNDO_STACK_OFFSET;
  let state = UNDO_STACK[offset];
  restoreState(state);
  state.callback();
  renderUndoRedoButtons();
}

function performRedo() {
  if ( UNDO_STACK_OFFSET+1 == UNDO_STACK_LIMIT ||
       UNDO_STACK[UNDO_STACK_OFFSET+1] === null
     )
  {
    return;
  }

  const offset = ++UNDO_STACK_OFFSET;
  let state = UNDO_STACK[offset];
  restoreState(state);
  state.callback();
  renderUndoRedoButtons();
}

function restoreState(state) {
  replaceTracks(getPlaylistTable(), state.playlistTracks);
  replaceTracks(getLocalScratchpadTable(), state.localScratchpadTracks);
  replaceTracks(getGlobalScratchpadTable(), state.globalScratchpadTracks);
  renderAllTables();
  savePlaylistSnapshotAndGlobalScratchpad();
}

function renderUndoRedoButtons() {
  const offset = UNDO_STACK_OFFSET;
  let undo_b = $('#undoBtn');
  let redo_b = $('#redoBtn');
  if (offset > 0) {
    undo_b.removeClass('disabled');
  }
  else {
    undo_b.addClass('disabled');
  }
  if (offset+1 < UNDO_STACK_LIMIT && UNDO_STACK[offset+1] !== null) {
    redo_b.removeClass('disabled');
  }
  else {
    redo_b.addClass('disabled');
  }
}

function showPlaylistsWithTrack(tid, title) {
  let action_area = $('.action-input-area[name=show-playlists-with-track]');
  function setTableHeight() {
    let search_results_area = action_area.find('.search-results');
    let search_area_bottom =
      search_results_area.offset().top + search_results_area.height();
    let table = search_results_area.find('.table-wrapper');
    let table_top = table.offset().top;
    let table_height = search_area_bottom - table_top;
    table.css('height', table_height + 'px');
  }
  action_area.find('p').text(title);
  action_area.find('button.cancel').on('click', close);
  let search_results_area = action_area.find('table tbody');
  search_results_area.empty();
  clearProgress();
  action_area.show();
  setTableHeight();

  let body = $(document.body);
  body.addClass('loading');

  let cancel_loading = false;
  function finalize() {
    body.removeClass('loading');
  }
  function close() {
    cancel_loading = true;
    clearActionInputs();
    finalize();
  }
  function loadDone() {
    loads_finished++; // Protection not needed for browser JS
    renderProgress();
    if (loads_finished == total_loads) {
      finalize();
    }
  }
  function loadFail(msg) {
    cancel_loading = true;
    finalize();
  }

  let total_loads = 0;
  let loads_finished = 0;
  function clearProgress() {
    let bar = action_area.find('.progress-bar');
    bar.css('width', 0);
  }
  function initProgress(total) {
    total_loads = total;
    let bar = action_area.find('.progress-bar');
    bar.css('width', 0);
  }
  function hasInitProgress() {
    return total_loads > 0;
  }
  function renderProgress() {
    let bar = action_area.find('.progress-bar');
    bar.css('width', (loads_finished / total_loads)*100 + '%');
  }

  function loadPlaylists(offset) {
    if (cancel_loading) {
      return;
    }
    callApi( '/api/get-user-playlists/'
           , { userId: '<?= getThisUserId($api) ?>'
             , offset: offset
             }
           , function(d) {
               if (!hasInitProgress()) {
                 initProgress(d.total);
               }

               for (let i = 0; i < d.playlists.length; i++) {
                 if (cancel_loading) {
                   return;
                 }
                 let pid = d.playlists[i].id;
                 let pname = d.playlists[i].name;
                 if (pid != PLAYLIST_INFO.id) {
                   loadPlaylistTracks(pid, pname, 0);
                 }
                 else {
                   loadDone();
                 }
               }
               offset += d.playlists.length;
               if (offset == d.total) {
                 return;
               }
               loadPlaylists(offset);
             }
           , loadFail
           );
  }
  function loadPlaylistTracks(playlist_id, playlist_name, offset) {
    if (cancel_loading) {
      return;
    }
    callApi( '/api/get-playlist-tracks/'
           , { playlistId: playlist_id
             , offset: offset
             }
           , function(d) {
               for (let i = 0; i < d.tracks.length; i++) {
                 if (cancel_loading) {
                   return;
                 }
                 if (d.tracks[i] == tid) {
                   search_results_area.append(
                     '<tr>' +
                       '<td>' +
                         playlist_name +
                       '</td>' +
                     '</tr>'
                   );
                   loadDone();
                   return;
                 }
               }

               offset += d.tracks.length;
               if (offset == d.total) {
                 loadDone();
                 return;
               }
               loadPlaylistTracks(playlist_id, playlist_name, offset);
             }
           , loadFail
           );
  }
  loadPlaylists(0);
}

function setPlaylistHeight() {
  let screen_vh = window.innerHeight;
  let table_offset = $('div.playlists-wrapper div.table-wrapper').offset().top;
  let footer_vh = $('div.footer').outerHeight(true);

  let playback = $('div.playback');
  let playback_vh = playback.is(':visible') ? playback.outerHeight(true) : 0;

  let overviews_vh = 0;
  getTrackOverviews().forEach(
    ([name, overview]) => {
      let overview_vh =
        overview.is(':visible') ? overview.outerHeight(true) : 0;
      overviews_vh += overview_vh;
    }
  );

  let playlist_vh = screen_vh - table_offset - footer_vh - overviews_vh -
                    playback_vh;
  let playlist_px = playlist_vh + 'px';
  [ getPlaylistTable(), getLocalScratchpadTable(), getGlobalScratchpadTable() ]
    .forEach(
      table => table.closest('.table-wrapper').css('height', playlist_px)
    );
}

function renderTrackOverviews() {
  renderBpmOverview();
  renderEnergyOverview();
  renderDanceabilityOverview();
  renderAcousticnessOverview();
  renderInstrumentalnessOverview();
  renderValenceOverview();
}

function getTrackOverviews() {
  return [ [ 'bpm', $('div.bpm-overview') ]
         , [ 'energy', $('div.energy-overview') ]
         , [ 'danceability', $('div.danceability-overview') ]
         , [ 'acousticness', $('div.acousticness-overview') ]
         , [ 'instrumentalness', $('div.instrumentalness-overview') ]
         , [ 'valence', $('div.valence-overview') ]
         ];
}

function makeTrackOverviewStatsFunction(
  overview_div, heading_str, get_f, post_average_f
) {
  return function(stats_div, tracks) {
    tracks = tracks.filter(function(t) { return get_f(t) > 0 });
    tracks.sort(function(a, b) { return intcmp(get_f(a), get_f(b)); });
    let min = 0;
    let max = 0;
    let median = 0;
    let average = 0;
    if (tracks.length > 0) {
      min = get_f(tracks[0]);
      max = get_f(tracks[tracks.length-1]);
      let i = Math.floor(tracks.length / 2);
      median = tracks.length % 2 == 1 ? get_f(tracks[i+1]) : get_f(tracks[i]);
      average =
        tracks.reduce(function(a, t) { return a + get_f(t); }, 0) /
        tracks.length
      if (post_average_f) {
        average = post_average_f(average);
      }
    }
    let stats = overview_div.find('.stats');
    stats.text( heading_str + ' ' +
                '<?= LNG_DESC_MIN ?>: ' + min + ' ' +
                '<?= LNG_DESC_MAX ?>: ' + max + ' ' +
                '<?= LNG_DESC_MEDIAN ?>: ' + median + ' ' +
                '<?= LNG_DESC_AVERAGE ?>: ' + average
              );
  };
}

function renderBpmOverview() {
  function get_f(t) {
    return t.bpm.custom >= 0 ? t.bpm.custom : t.bpm.spotify;
  }

  let overview_div = $('div.bpm-overview');
  renderTrackOverview(
    overview_div
  , '<?= LNG_HEAD_BPM ?>'
  , get_f
  , BPM_MIN
  , BPM_MAX
  , getBpmRgbColor
  , makeTrackOverviewStatsFunction( overview_div
                                  , '<?= LNG_HEAD_BPM ?>'
                                  , get_f
                                  , Math.round
                                  )
  );
}

function renderEnergyOverview() {
  function get_f(t) {
    return t.energy;
  }

  let overview_div = $('div.energy-overview');
  renderTrackOverview(
    overview_div
  , '<?= LNG_HEAD_ENERGY ?>'
  , get_f
  , 0
  , 1
  , getEnergyRgbColor
  , makeTrackOverviewStatsFunction( overview_div
                                  , '<?= LNG_HEAD_ENERGY ?>'
                                  , get_f
                                  , function(v) {
                                      return Math.round(v*1000) / 1000;
                                    }
                                  )
  );
}

function renderDanceabilityOverview() {
  function get_f(t) {
    return t.danceability;
  }

  let overview_div = $('div.danceability-overview');
  renderTrackOverview(
    overview_div
  , '<?= LNG_HEAD_DANCEABILITY ?>'
  , get_f
  , 0
  , 1
  , getEnergyRgbColor
  , makeTrackOverviewStatsFunction( overview_div
                                  , '<?= LNG_HEAD_DANCEABILITY ?>'
                                  , get_f
                                  , function(v) {
                                      return Math.round(v*1000) / 1000;
                                    }
                                  )
  );
}

function renderAcousticnessOverview() {
  function get_f(t) {
    return t.acousticness;
  }

  let overview_div = $('div.acousticness-overview');
  renderTrackOverview(
    overview_div
  , '<?= LNG_HEAD_ACOUSTICNESS ?>'
  , get_f
  , 0
  , 1
  , getEnergyRgbColor
  , makeTrackOverviewStatsFunction( overview_div
                                  , '<?= LNG_HEAD_ACOUSTICNESS ?>'
                                  , get_f
                                  , function(v) {
                                      return Math.round(v*1000) / 1000;
                                    }
                                  )
  );
}

function renderInstrumentalnessOverview() {
  function get_f(t) {
    return t.instrumentalness;
  }

  let overview_div = $('div.instrumentalness-overview');
  renderTrackOverview(
    overview_div
  , '<?= LNG_HEAD_INSTRUMENTALNESS ?>'
  , get_f
  , 0
  , 1
  , getEnergyRgbColor
  , makeTrackOverviewStatsFunction( overview_div
                                  , '<?= LNG_HEAD_INSTRUMENTALNESS ?>'
                                  , get_f
                                  , function(v) {
                                      return Math.round(v*1000) / 1000;
                                    }
                                  )
  );
}

function renderValenceOverview() {
  function get_f(t) {
    return t.valence;
  }

  let overview_div = $('div.valence-overview');
  renderTrackOverview(
    overview_div
  , '<?= LNG_HEAD_VALENCE ?>'
  , get_f
  , 0
  , 1
  , getEnergyRgbColor
  , makeTrackOverviewStatsFunction( overview_div
                                  , '<?= LNG_HEAD_VALENCE ?>'
                                  , get_f
                                  , function(v) {
                                      return Math.round(v*1000) / 1000;
                                    }
                                  )
  );
}

function getTrackBarArea(overview_div) {
  return overview_div.find('.bar-area');
}

function isTrackOverviewShowing(overview_div) {
  return overview_div.is(':visible');
}

function renderTrackOverview(
  overview_div, desc, track_value_f, min_value, max_value, color_f, stats_f
) {
  if (!isTrackOverviewShowing(overview_div)) {
    return;
  }

  let num_areas_showing =
    getTrackOverviews().reduce(
      (a, [name, div]) => a + isTrackOverviewShowing(div)
    , 0
    );

  let area = getTrackBarArea(overview_div);
  area.empty();
  area.css( 'height'
          , ( TRACK_AREA_HEIGHT -
              TRACK_AREA_HEIGHT_REDUCTION * (num_areas_showing - 1)
            ) + 'rem'
          );

  let selected_track_indices = [];
  getSelectedTrackTrs().each(
    function() {
      let tr = $(this);
      if (!tr.closest('table').is(getPlaylistTable())) {
        return;
      }
      selected_track_indices.push(getTrackIndexOfTr(tr));
    }
  );

  // Draw bars
  let tracks = getTrackData(getPlaylistTable());
  let area_vw = area.innerWidth();
  let area_vh = area.innerHeight();
  const border_size = 1;
  let bar_vw = (area_vw - border_size) / tracks.length - border_size;
  let bar_voffset = 0;
  $.each(
    tracks
  , function(track_index) {
      let t = this;
      track_value = track_value_f(t);

      let bar_wrapper = $('<div class="bar-wrapper" />');
      bar_wrapper.css('left', bar_voffset + 'px');
      bar_wrapper.css('width', (bar_vw + 2*border_size) + 'px');
      let bar = $('<div class="bar" />');
      let bar_vh = (track_value - min_value) / max_value * area_vh;
      bar.css('height', bar_vh + 'px');
      bar.css('width', bar_vw + 'px');
      let cs = color_f(track_value);
      bar.css('background-color', 'rgb(' + cs.join(',') + ')');
      bar_voffset += bar_vw + border_size;
      bar_wrapper.append(bar);
      area.append(bar_wrapper);

      if ( isUsingDanceDelimiter() &&
           (track_index+1) % PLAYLIST_DANCE_DELIMITER == 0 &&
           track_index != 0 && track_index != tracks.length-1
         )
      {
        let delimiter = $('<div class="delimiter" />');
        delimiter.css('height', area_vh + 'px');
        delimiter.css('left', bar_voffset + 'px');
        area.append(delimiter);
      }

      let title = t.artists !== undefined ? formatTrackTitleAsText( t.artists
                                                                  , t.name
                                                                  )
                                          : t.name;
      let track_info = $( '<div class="track-overview-track-info">' +
                            '#' + (track_index+1) + ' ' + title +
                            '<br />' +
                            desc + ': ' + track_value +
                          '</div>'
                        );
      if (selected_track_indices.includes(track_index)) {
        bar_wrapper.addClass('selected');
      }
      area.append(track_info);
      bar.hover(
        function() {
          let bar = $(this);
          if (bar.closest('.drag-mode').length == 0) {
            let new_cs = rgbVisIncr(cs, 40);
            bar.css('background-color', 'rgb(' + new_cs.join(',') + ')');
          }

          let bar_wrapper = bar.closest('.bar-wrapper');
          let bar_wrapper_of = bar_wrapper.position();
          let bar_of = bar.position();
          let info_vh = track_info.outerHeight();
          let info_top_of = bar_of.top - info_vh;
          track_info.css('top', info_top_of + 'px');
          let info_vw = track_info.outerWidth();
          if (bar_wrapper_of.left + info_vw <= area_vw) {
            track_info.css('left', bar_wrapper_of.left + 'px');
          }
          else {
            track_info.css('left', (area_vw - info_vw) + 'px');
          }
          track_info.show();
        }
      , function() {
          bar.css('background-color', 'rgb(' + cs.join(',') + ')');
          track_info.hide();
        }
      );
      addTrackBarSelectHandling(overview_div, bar);
      addTrackBarDragHandling(overview_div, bar);
    }
  );

  let playlist_delimiters = computePlaylistDelimiterPositions(tracks);
  playlist_delimiters.forEach(
    function([i, length]) {
      let delimiter = $('<div class="delimiter playlist" />');
      delimiter.css('height', area_vh + 'px');
      delimiter.css('left', (i*(bar_vw + border_size)) + 'px');
      area.append(delimiter);
    }
  );

  stats_f(overview_div.find('.stats'), tracks);
}

function addTrackBarSelectHandling(overview_div, bar) {
  bar.click(
    function(e) {
      if (TRACK_DRAG_STATE == 0) {
        let bar_wr = $(this).closest('.bar-wrapper');
        updateTrackBarSelection( overview_div
                               , bar_wr
                               , e.ctrlKey || e.metaKey
                               , e.shiftKey
                               );
      }
      else {
        TRACK_DRAG_STATE = 0;
      }

      let track_index = getTrackIndexOfBarWrapper(bar.closest('.bar-wrapper'));
      scrollPlaylistToTrackIndex(track_index);
    }
  );
}

function addTrackBarSelectionInAllOverviews(track_index) {
  getTrackOverviews().forEach(
    ([name, div]) => addTrackBarSelection(div, track_index)
  );
}

function addTrackBarSelection(overview_div, track_index) {
  if (!isTrackOverviewShowing(overview_div)) {
    return;
  }

  let area = getTrackBarArea(overview_div);
  let bar_wrappers = area.find('.bar-wrapper');
  let bar_wr = $(bar_wrappers[track_index]);
  bar_wr.addClass('selected');
}

function removeTrackBarSelectionInAllOverviews(track_index) {
  getTrackOverviews().forEach(
    ([name, div]) => removeTrackBarSelection(div, track_index)
  );
}

function removeTrackBarSelection(overview_div, track_index) {
  if (!isTrackOverviewShowing(overview_div)) {
    return;
  }

  let area = getTrackBarArea(overview_div);
  let bar_wrappers = area.find('.bar-wrapper');
  let bar_wr = $(bar_wrappers[track_index]);
  bar_wr.removeClass('selected');
}

function clearTrackBarSelectionInAllOverviews() {
  getTrackOverviews().forEach(
    ([name, div]) => clearTrackBarSelection(div)
  );
}

function clearTrackBarSelection(overview_div) {
  if (!isTrackOverviewShowing(overview_div)) {
    return;
  }

  let area = getTrackBarArea(overview_div);
  area.find('.bar-wrapper').removeClass('selected');
}

function getTrackIndexOfBarWrapper(bar_wr) {
  return bar_wr.closest('.bar-area').find('.bar-wrapper').index(bar_wr);
}

function updateTrackBarSelection(
  overview_div, bar_wr, multi_select_mode, span_mode
) {
  function propagateBarSelectionToOtherOverviews() {
    // Clear selection in all other overviews
    getTrackOverviews().forEach(
      ([name, div]) => {
        if (div.is(overview_div)) return;
        if (!isTrackOverviewShowing(overview_div)) return;
        clearTrackBarSelection(div);
      }
    );

    // Find selected bars in this overview and set those in other views
    overview_div.find('.bar-wrapper').each(
      function(index) {
        let bar_wr = $(this);
        if (!bar_wr.hasClass('selected')) return;
        getTrackOverviews().forEach(
          ([name, div]) => {
            if (div.is(overview_div)) return;
            if (!isTrackOverviewShowing(overview_div)) return;
            addTrackBarSelection(div, index);
          }
        );
      }
    );
  }

  if (multi_select_mode) {
    if (!bar_wr.hasClass('selected')) {
        bar_wr.addClass('selected');
        addTrackTrSelection( getPlaylistTable()
                           , getTrackIndexOfBarWrapper(bar_wr)
                           );
    }
    else {
        bar_wr.removeClass('selected');
        removeTrackTrSelection( getPlaylistTable()
                              , getTrackIndexOfBarWrapper(bar_wr)
                              );
    }
    propagateBarSelectionToOtherOverviews();
    return;
  }

  if (span_mode) {
    let selected_sib_bar_wrappers =
      bar_wr.siblings().filter(
        function() { return $(this).hasClass('selected') }
      );
    if (selected_sib_bar_wrappers.length == 0) {
      bar_wr.addClass('selected');
      addTrackTrSelection( getPlaylistTable()
                         , getTrackIndexOfBarWrapper(bar_wr)
                         );
      propagateBarSelectionToOtherOverviews();
      return;
    }
    let first = $(selected_sib_bar_wrappers[0]);
    let last = $(selected_sib_bar_wrappers[selected_sib_bar_wrappers.length-1]);
    let bar_wrappers = bar_wr.siblings().add(bar_wr);
    for ( let i = Math.min(bar_wr.index(), first.index(), last.index())
        ; i <= Math.max(bar_wr.index(), first.index(), last.index())
        ; i++
        )
    {
      let sib_bar_wr = $(bar_wrappers[i]);
      if (sib_bar_wr.hasClass('bar-wrapper')) {
        sib_bar_wr.addClass('selected');
        addTrackTrSelection( getPlaylistTable()
                           , getTrackIndexOfBarWrapper(sib_bar_wr)
                           );
      }
    }
    propagateBarSelectionToOtherOverviews();
    return;
  }

  let selected_sib_bar_wrappers =
    bar_wr.siblings().filter(
      function() { return $(this).hasClass('selected') }
    );
  $.each( selected_sib_bar_wrappers
        , function() {
            let bar_wr = $(this);
            bar_wr.removeClass('selected');
            removeTrackTrSelection( getPlaylistTable()
                                  , getTrackIndexOfBarWrapper(bar_wr)
                                  );
          }
        );
  if (selected_sib_bar_wrappers.length > 0) {
    bar_wr.addClass('selected');
    addTrackTrSelection(getPlaylistTable(), getTrackIndexOfBarWrapper(bar_wr));
    propagateBarSelectionToOtherOverviews();
    return;
  }

  if (!bar_wr.hasClass('selected')) {
      bar_wr.addClass('selected');
      addTrackTrSelection( getPlaylistTable()
                         , getTrackIndexOfBarWrapper(bar_wr)
                         );
  }
  else {
      bar_wr.removeClass('selected');
      removeTrackTrSelection( getPlaylistTable()
                            , getTrackIndexOfBarWrapper(bar_wr)
                            );
  }
  propagateBarSelectionToOtherOverviews();
}

function addTrackBarDragHandling(overview_div, bar) {
  function disableTextSelection(e) {
    e.preventDefault();
  }

  bar.mousedown(
    function(e) {
      let mousedown_bar = $(e.target).closest('.bar');
      let mousedown_bar_wr = mousedown_bar.closest('.bar-wrapper');
      if (!mousedown_bar_wr.hasClass('selected')) {
        return false;
      }
      let body = $(document.body);
      body.addClass('grabbed');

      let selected_bars =
        mousedown_bar_wr.siblings().add(mousedown_bar_wr).filter(
          function() { return $(this).hasClass('selected') }
        );
      let mb = $('.grabbed-info-block');
      mb.find('span').text(selected_bars.length);

      let area = mousedown_bar.closest('.bar-area');
      area.toggleClass('drag-mode');

      let ins_point = $('.bar-drag-insertion-point');

      function move(e) {
        // Move info block
        const of = 5; // To prevent grabbed-info-block to appear as target
        mb.css({ top: e.pageY+of + 'px', left: e.pageX+of + 'px' });
        mb.show();

        TRACK_DRAG_STATE = 1; // bar.click() and mouseup may both reset this.
                              // This is to prevent deselection if drag stops on
                              // selected bars

        // Check if moving over insertion-point bar (to prevent flickering)
        if ($(e.target).hasClass('bar-drag-insertion-point')) {
          return false;
        }

        // Always clear previous insertion point; else we could in some cases
        // end up with multiple insertion points
        area.find('.insert-before, .insert-after')
          .removeClass('insert-before insert-after');

        // Hide insertion-point bar if we are not over area
        if ($(e.target).closest('.bar-area').length == 0) {
          ins_point.hide();
          return false;
        }

        let bar_wrapper = $(e.target).closest('.bar-wrapper');
        let insert_before = false;
        if (bar_wrapper.length == 1) {
          // We have moved over a bar wrapper
          let bar_x_half =
            e.pageX - bar_wrapper.offset().left - (bar_wrapper.width() / 2);
          insert_before = bar_x_half <= 0;
        }
        else {
          // We have moved over the bar area but not over a bar.
          // Don't do anything, as we assume that an insertion point has already
          // been established from previous move
          return false;
        }

        // Mark insertion point and draw insertion-point bar
        bar_wrapper.addClass(insert_before ? 'insert-before' : 'insert-after');
        ins_point.css( { height: bar_wrapper.height() + 'px'
                       , top: bar_wrapper.offset().top + 'px'
                       , left: ( bar_wrapper.offset().left +
                                 (insert_before ? 0 : bar_wrapper.width()) -
                                 ins_point.width()/2
                               ) + 'px'
                       }
                     );
        ins_point.show();
      }

      function up() {
        let area = getTrackBarArea(overview_div);
        let insert_point = area.find('.insert-before, .insert-after');
        if (insert_point.length == 1) {
          // Forbid dropping adjacent to a selected track as that causes wierd
          // reordering
          let dropped_adjacent_to_selected =
            insert_point.find('.selected').length > 0 ||
            ( insert_point.hasClass('insert-before') &&
              insert_point.prevAll('.bar-wrapper')
                          .first()
                          .find('.selected')
                          .length > 0
            ) ||
            ( insert_point.hasClass('insert-after') &&
              insert_point.nextAll('.bar-wrapper')
                          .first()
                          .find('.selected')
                          .length > 0
            );
          if (!dropped_adjacent_to_selected) {
            let ins_track_index = getTrackIndexOfBarWrapper(insert_point);
            if (insert_point.hasClass('insert-after')) {
              ins_track_index++;
            }
            dragSelectedTracksTo(getPlaylistTable(), ins_track_index);
          }
          insert_point.removeClass('insert-before insert-after');
        }

        // Remove info block and insertion-point bar
        mb.hide();
        body.removeClass('grabbed');
        ins_point.hide();

        area.toggleClass('drag-mode');

        if (!insert_point.is(mousedown_bar.closest('.bar-wrapper'))) {
          TRACK_DRAG_STATE = 0;
        }

        $(document).unbind('mousemove', move).unbind('mouseup', up);
      }

      $(document).mousemove(move).mouseup(up);

      return false; // Prevent text selection
    }
  );
}

function scrollPlaylistToTrackIndex(track_index) {
  let table = getPlaylistTable();
  let tr = $(table.find('.track, .empty-track')[track_index]);
  let scroll_area = table.closest('.table-wrapper');
  let scroll_area_vh = scroll_area.height();
  let scroll_pos = tr.position().top - scroll_area_vh/2;
  if (scroll_pos < 0) {
    scroll_pos = 0;
  }
  scroll_area.scrollTop(scroll_pos);
}

function markPlayingTrackInPlaylist(track_id, index, table) {
  MARKED_AS_PLAYING_TRACK = track_id;
  MARKED_AS_PLAYING_INDEX = index;
  MARKED_AS_PLAYING_TABLE = table;
  [ getPlaylistTable(), getLocalScratchpadTable(), getGlobalScratchpadTable() ]
    .forEach(
      table => {
        renderTableIndices(table);
        renderTablePlayingTrack(table);
      }
    );
}

function addAppResizeHandling(sep) {
  sep.mousedown(
    function(e) {
      let body = $(document.body);
      body.addClass('ew-resizing');

      let prev = sep.prev();
      let next = sep.next();
      let prev_w = prev.outerWidth();
      let next_w = next.outerWidth();
      let last_pagex = e.pageX;
      function move(e) {
        let xdiff = e.pageX - last_pagex;
        prev_w += xdiff;
        next_w -= xdiff;
        prev.css('width', prev_w);
        next.css('width', next_w);
        last_pagex = e.pageX;
        renderTrackOverviews();
        return false; // Prevent text selection
      }

      function up(e) {
        body.removeClass('ew-resizing');
        $(document).unbind('mousemove', move).unbind('mouseup', up);
      }

      $(document).mousemove(move).mouseup(up);
    }
  );
}
