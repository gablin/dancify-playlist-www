<?php
require '../autoload.php';
?>

var PLAYLIST_ID = '';
var PREVIEW_AUDIO = $('<audio />');
var PLAYLIST_TRACK_DELIMITER = 0;
var TRACK_DRAG_STATE = 0;
var UNDO_STACK_LIMIT = 10;
var UNDO_STACK = Array(UNDO_STACK_LIMIT).fill(null);
var UNDO_STACK_OFFSET = -1;
var LAST_SPOTIFY_PLAYLIST_HASH = '';

const BPM_MIN = 0;
const BPM_MAX = 255;

function setupPlaylist(playlist_id) {
  PLAYLIST_ID = playlist_id;
  addTableHead(getPlaylistTable());
  addTableHead(getScratchpadTable());
  addTrTemplate(getPlaylistTable());
  addTrTemplate(getScratchpadTable());
  $(document).on( 'keyup'
                , function(e) {
                    if (e.key == 'Escape') {
                      clearTrackSelection();
                    }
                  }
                );
}

function getTableOfTr(tr) {
  return tr.closest('table');
}

function loadPlaylist(playlist_id) {
  var body = $(document.body);
  body.addClass('loading');
  setStatus('<?= LNG_DESC_LOADING ?>...');
  function success() {
    body.removeClass('loading');
    clearStatus();
    saveUndoState();
  }
  function fail(msg) {
    setStatus('<?= LNG_ERR_FAILED_LOAD_PLAYLIST ?>', true);
    body.removeClass('loading');
  }
  function snapshot_success() {
    success();
    checkForChangesInSpotifyPlaylist(playlist_id);
  }
  function noSnapshot() {
    loadPlaylistFromSpotify(playlist_id, success, fail);
  }
  loadPlaylistFromSnapshot(playlist_id, snapshot_success, noSnapshot, fail);
}

function loadPlaylistFromSpotify(playlist_id, success_f, fail_f) {
  function updatePlaylistHash() {
    var track_ids = getPlaylistTrackData().map(t => t.trackId);
    LAST_SPOTIFY_PLAYLIST_HASH = computePlaylistHash(track_ids);
  }
  function load(offset) {
    var data = { playlistId: playlist_id
               , offset: offset
               };
    callApi( '/api/get-playlist-tracks/'
           , data
           , function(d) {
               var data = { trackIds: d.tracks };
               callApi( '/api/get-track-info/'
                      , data
                      , function(dd) {
                          var tracks = [];
                          for (var i = 0; i < dd.tracks.length; i++) {
                            var t = dd.tracks[i];
                            var o = createPlaylistTrackObject( t.trackId
                                                             , t.artists
                                                             , t.name
                                                             , t.length
                                                             , t.bpm
                                                             , t.genre
                                                             , t.preview_url
                                                             );
                            tracks.push(o);
                          }
                          appendTracks(getPlaylistTable(), tracks);
                          var next_offset = offset + tracks.length;
                          if (next_offset < d.total) {
                            load(next_offset);
                          }
                          else {
                            renderPlaylist();
                            updatePlaylistHash();
                            success_f();
                          }
                        }
                      , fail_f
                      )
             }
           , fail_f
           );
  }
  load(0);
}

function checkForChangesInSpotifyPlaylist(playlist_id) {
  var body = $(document.body);
  function fail(msg) {
    setStatus('<?= LNG_ERR_FAILED_LOAD_PLAYLIST ?>', true);
    body.removeClass('loading');
  }
  function getActionArea() {
    return $('.action-input-area[name=playlist-inconsistencies]');
  }
  function checkForAdditions(snapshot_tracks, spotify_track_ids, callback_f) {
    body.addClass('loading');
    setStatus('<?= LNG_DESC_LOADING ?>...');
    function cleanup() {
      body.removeClass('loading');
      clearStatus();
    }

    // Find tracks appearing Spotify but not in snapshot
    var new_track_ids = [];
    for (var i = 0; i < spotify_track_ids.length; i++) {
      var tid = spotify_track_ids[i];
      var t = getTrackWithMatchingId(snapshot_tracks, tid);
      if (t === null) {
        new_track_ids.push(tid);
      }
    }
    if (new_track_ids.length == 0) {
      cleanup();
      callback_f();
      return;
    }

    var has_finalized = false;
    function finalize() {
      if (!has_finalized) {
        cleanup();
        clearActionInputs();
        callback_f();
      }
      has_finalized = true;
    }
    function loadTracks(offset, dest_table) {
      var tracks_to_load = [];
      var o = offset;
      for ( var o = offset
          ; o < new_track_ids.length && tracks_to_load.length < LOAD_TRACKS_LIMIT
          ; o++
          )
      {
        tracks_to_load.push(new_track_ids[o]);
      }
      callApi( '/api/get-track-info/'
             , { trackIds: tracks_to_load }
             , function(d) {
                 var tracks = [];
                 for (var i = 0; i < d.tracks.length; i++) {
                   var t = d.tracks[i];
                   var o = createPlaylistTrackObject( t.trackId
                                                    , t.artists
                                                    , t.name
                                                    , t.length
                                                    , t.bpm
                                                    , t.genre
                                                    , t.preview_url
                                                    );
                   tracks.push(o);
                 }
                 appendTracks(dest_table, tracks);
                 var next_offset = offset + tracks.length;
                 if (next_offset < d.total) {
                   loadTracks(next_offset, dest_table);
                 }
                 else {
                   renderTable(dest_table);
                   indicateStateUpdate();
                   finalize();
                 }
               }
             , fail
             );
    }

    var a = getActionArea();
    a.find('p').text('<?= LNG_DESC_TRACK_ADDITIONS_DETECTED ?>');
    var btn1 = a.find('#inconPlaylistBtn1');
    var btn2 = a.find('#inconPlaylistBtn2');
    var cancel_btn = btn1.closest('div').find('button.cancel');
    btn1.text('<?= LNG_BTN_APPEND_TO_PLAYLIST ?>');
    btn2.text('<?= LNG_BTN_APPEND_TO_SCRATCHPAD ?>');
    btn1.click(
      function() {
        loadTracks(0, getPlaylistTable());
      }
    );
    btn2.click(
      function() {
        loadTracks(0, getScratchpadTable());
        showScratchpad();
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
    // Find tracks not appearing Spotify but in snapshot
    var removed_track_ids = [];
    for (var i = 0; i < snapshot_tracks.length; i++) {
      var tid = snapshot_tracks[i].trackId;
      if (tid === undefined) {
        continue;
      }
      var found = false;
      for (var j = 0; j < spotify_track_ids.length; j++) {
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

    var has_finalized = false;
    function finalize() {
      if (!has_finalized) {
        clearActionInputs();
        callback_f();
      }
      has_finalized = true;
    }
    function popTracks(tracks_to_remove) {
      var removed_tracks = [];

      // Pop from playlist
      var has_removed = false;
      var playlist_tracks = getPlaylistTrackData();
      for (var i = 0; i < tracks_to_remove.length; i++) {
        var res = popTrackWithMatchingId(playlist_tracks, tracks_to_remove[i]);
        playlist_tracks = res[0];
        var removed_t = res[1];
        if (removed_t !== null) {
          removed_tracks.push(removed_t);
          has_removed = true;
        }
      }
      if (has_removed) {
        replaceTracks(getPlaylistTable(), playlist_tracks);
      }

      // Pop from scratchpad
      has_removed = false;
      var scratchpad_tracks = getScratchpadTrackData();
      for (var i = 0; i < tracks_to_remove.length; i++) {
        var res = popTrackWithMatchingId(scratchpad_tracks, tracks_to_remove[i]);
        scratchpad_tracks = res[0];
        var removed_t = res[1];
        if (removed_t !== null) {
          removed_tracks.push(removed_t);
          has_removed = true;
        }
      }
      if (has_removed) {
        replaceTracks(getScratchpadTable(), scratchpad_tracks);
      }

      return removed_tracks;
    }
    var a = getActionArea();
    a.find('p').text('<?= LNG_DESC_TRACK_DELETIONS_DETECTED ?>');
    var btn1 = a.find('#inconPlaylistBtn1');
    var btn2 = a.find('#inconPlaylistBtn2');
    btn1.text('<?= LNG_BTN_REMOVE ?>');
    btn2.text('<?= LNG_BTN_MOVE_TO_SCRATCHPAD ?>');
    var cancel_btn = btn1.closest('div').find('button.cancel');
    btn1.click(
      function() {
        popTracks(removed_track_ids);
        renderTable(getScratchpadTable());
        indicateStateUpdate();
        finalize();
      }
    );
    btn2.click(
      function() {
        var removed_tracks = popTracks(removed_track_ids);
        var scratchpad_data = getScratchpadTrackData();
        var new_scratchpad_data = scratchpad_data.concat(removed_tracks);
        replaceTracks(getScratchpadTable(), new_scratchpad_data);
        renderTable(getScratchpadTable());
        indicateStateUpdate();
        showScratchpad();
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
  }
  var spotify_track_ids = [];
  function load(offset) {
    var data = { playlistId: playlist_id
               , offset: offset
               };
    callApi( '/api/get-playlist-tracks/'
           , data
           , function(d) {
               spotify_track_ids = spotify_track_ids.concat(d.tracks);
               var next_offset = offset + d.tracks.length;
               if (next_offset < d.total) {
                 load(next_offset);
               }
               else {
                 var playlist_hash = computePlaylistHash(spotify_track_ids);
                 if (playlist_hash == LAST_SPOTIFY_PLAYLIST_HASH) {
                   return;
                 }
                 LAST_SPOTIFY_PLAYLIST_HASH = playlist_hash;
                 var snapshot_tracks =
                   getPlaylistTrackData().concat(getScratchpadTrackData());
                 checkForAdditions( snapshot_tracks
                                  , spotify_track_ids
                                  , function () {
                                      checkForDeletions( snapshot_tracks
                                                       , spotify_track_ids
                                                       , function() {}
                                                       );
                                    }
                                  );
               }
             }
           , fail
           );
  }
  load(0);
}

function computePlaylistHash(track_ids) {
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
  var clicked_playing_preview = jlink.hasClass('playing');
  var preview_links = $.merge( getPlaylistTable().find('tr.track .title a')
                             , getScratchpadTable().find('tr.track .title a')
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
  var input = tr.find('input[name=bpm]');
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
    }
  );
  input.blur(
    function() {
      renderTrackBpm($(this).closest('tr'));
    }
  );
  input.change(
    function() {
      var input = $(this);

      // Find corresponding track ID
      var tid_input = input.closest('tr').find('input[name=track_id]');
      if (tid_input.length == 0) {
        console.log('could not find track ID');
        return;
      }
      var tid = tid_input.val().trim();
      if (tid.length == 0) {
        return;
      }

      // Check BPM value
      var bpm = input.val().trim();
      if (!checkBpmInput(bpm)) {
        input.addClass('invalid');
        return;
      }
      bpm = parseInt(bpm);
      input.removeClass('invalid');

      setStatus('<?= LNG_DESC_SAVING ?>...');
      updateBpmInDb( tid
                   , bpm
                   , clearStatus
                   , function(msg) {
                       setStatus('<?= LNG_ERR_FAILED_UPDATE_BPM ?>', true);
                     }
                   );

      // Update BPM on all duplicate tracks (if any)
      input.closest('table').find('input[name=track_id][value=' + tid + ']').each(
        function() {
          $(this).closest('tr').find('input[name=bpm]').each(
            function() {
              $(this).val(bpm);
              renderTrackBpm($(this).closest('tr'));
            }
          );
        }
      );

      var old_value = parseInt(input.data('old-value'));
      // .data() must be read here or else it will disappear upon undo/redo
      setCurrentUndoStateCallback(
        function() {
          updateBpmInDb( tid
                       , old_value
                       , function() {}
                       , function(msg) {
                           setStatus('<?= LNG_ERR_FAILED_UPDATE_BPM ?>', true);
                         }
                       );
        }
      );
      indicateStateUpdate();
      setCurrentUndoStateCallback(
        function() {
          updateBpmInDb( tid
                       , bpm
                       , function() {}
                       , function(msg) {
                           setStatus('<?= LNG_ERR_FAILED_UPDATE_BPM ?>', true);
                         }
                       );
        }
      );
    }
  );
}

function renderTrackBpm(tr) {
  var input = tr.find('input[name=bpm]');
  var bpm = input.val().trim();
  if (!checkBpmInput(bpm, false)) {
    return;
  }
  bpm = parseInt(bpm);
  //               bpm    color (RGB)
  const colors = [ [   0, [  0,   0, 255] ] // Blue
                 , [  40, [  0, 255,   0] ] // Green
                 , [ 100, [255, 255,   0] ] // Yellow
                 , [ 160, [255,   0,   0] ] // Red
                 , [ 200, [255,   0, 255] ] // Purple
                 , [ 255, [  0,   0, 255] ] // Blue
                 ];
  for (var i = 0; i < colors.length; i++) {
    if (i == colors.length-2 || bpm < colors[i+1][0]) {
      var p = (bpm - colors[i][0]) / (colors[i+1][0] - colors[i][0]);
      var c = [...colors[i][1]];
      for (var j = 0; j < c.length; j++) {
        c[j] += Math.round((colors[i+1][1][j] - c[j]) * p);
      }
      input.css('background-color', 'rgb(' + c.join(',') + ')');
      $text_color = (bpm < 25 || bpm  > 210) ? '#fff' : '#000';
      input.css('color', $text_color);
      return;
    }
  }
}

function updateGenreInDb(track_id, genre, success_f, fail_f) {
  callApi( '/api/update-genre/'
         , { trackId: track_id, genre: genre }
         , function(d) { success_f(d); }
         , function(msg) { fail_f(msg); }
         );
}

function addTrackGenreHandling(tr) {
  var select = tr.find('select[name=genre]');
  select.click(
    function(e) {
      e.stopPropagation(); // Prevent row selection
    }
  );
  select.focus(
    function() {
      $(this).data('old-value', $(this).find(':selected').val().trim());
    }
  );
  select.change(
    function() {
      var s = $(this);

      // Find corresponding track ID
      var tid_input = s.closest('tr').find('input[name=track_id]');
      if (tid_input.length == 0) {
        console.log('could not find track ID');
        return;
      }
      var tid = tid_input.val().trim();
      if (tid.length == 0) {
        return;
      }

      var genre = parseInt(s.find(':selected').val().trim());
      setStatus('<?= LNG_DESC_SAVING ?>...');
      updateGenreInDb( tid
                     , genre
                     , clearStatus
                     , function(msg) {
                         setStatus('<?= LNG_ERR_FAILED_UPDATE_GENRE ?>', true);
                       }
                     );

      // Update genre on all duplicate tracks (if any)
      s.closest('table').find('input[name=track_id][value=' + tid + ']').each(
        function() {
          var tr = $(this).closest('tr');
          tr.find('select[name=genre] option').attr('selected', false);
          tr.find('select[name=genre] option[value=' + genre + ']')
            .attr('selected', true);
          renderTrackGenre(tr);
        }
      );

      var old_value = parseInt(s.data('old-value'));
      // .data() must be read here or else it will disappear upon undo/redo
      setCurrentUndoStateCallback(
        function() {
          updateGenreInDb( tid
                         , old_value
                         , function() {}
                         , function(msg) {
                             setStatus('<?= LNG_ERR_FAILED_UPDATE_GENRE ?>', true);
                           }
                         );
        }
      );
      indicateStateUpdate();
      setCurrentUndoStateCallback(
        function() {
          updateGenreInDb( tid
                         , genre
                         , function() {}
                         , function(msg) {
                             setStatus('<?= LNG_ERR_FAILED_UPDATE_GENRE ?>', true);
                           }
                         );
        }
      );
    }
  );
}

function renderTrackGenre(tr) {}

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

function getTrTitleText(tr) {
  var nodes = tr.find('td.title').contents().filter(
                function() { return this.nodeType == 3; }
              );
  if (nodes.length > 0) {
    return nodes[0].nodeValue;
  }
  return '';
}

function getTrackData(table) {
  var playlist = [];
  table.find('tr.track, tr.empty-track').each(
    function() {
      var tr = $(this);
      if (tr.hasClass('track')) {
        var track_id = tr.find('input[name=track_id]').val().trim();
        var preview_url = tr.find('input[name=preview_url]').val().trim();
        var bpm = parseInt(tr.find('input[name=bpm]').val().trim());
        var genre =
          parseInt(tr.find('select[name=genre] option:selected').val().trim());
        var title = getTrTitleText(tr);
        var len = parseInt(tr.find('input[name=length_ms]').val().trim());
        playlist.push( { trackId: track_id
                       , title: title
                       , length: len
                       , bpm: bpm
                       , genre: genre
                       , previewUrl: preview_url
                       }
                     );
      }
      else{
        var title = tr.find('td.title').text().trim();
        var length = tr.find('td.length').text().trim();
        var bpm = tr.find('td.bpm').text().trim();
        var genre = tr.find('td.genre').text().trim();
        playlist.push(createPlaylistPlaceholderObject(title, length, bpm, genre));
      }
    }
  );

  return playlist;
}

function removePlaceholdersFromTracks(tracks) {
  return tracks.filter( function(t) { return t.trackId !== undefined } );
}

function getPlaylistTrackData() {
  var table = getPlaylistTable();
  return getTrackData(table);
}

function getScratchpadTrackData() {
  var table = getScratchpadTable();
  return getTrackData(table);
}

function createPlaylistTrackObject( track_id
                                  , artists
                                  , name
                                  , length_ms
                                  , bpm
                                  , genre
                                  , preview_url
                                  )
{
  return { trackId: track_id
         , title: formatTrackTitle(artists, name)
         , length: length_ms
         , bpm: bpm
         , genre: genre
         , previewUrl: preview_url
         }
}

function createPlaylistPlaceholderObject( title_text
                                        , length_text
                                        , bpm_text
                                        , genre_text
                                        )
{
  if (title_text === undefined) {
    return { title: '<?= LNG_DESC_PLACEHOLDER ?>'
           , length: ''
           , bpm: ''
           , genre: ''
           }
  }
  return { title: title_text
         , length: length_text
         , bpm: bpm_text
         , genre: genre_text
         }
}

function popTrackWithMatchingId(track_list, track_id) {
  var i = 0;
  for (; i < track_list.length && track_list[i].trackId != track_id; i++) {}
  if (i < track_list.length) {
    var t = track_list[i];
    track_list.splice(i, 1);
    return [track_list, t];
  }
  return [track_list, null];
}

function getTrackWithMatchingId(track_list, track_id) {
  var i = 0;
  for (; i < track_list.length && track_list[i].trackId != track_id; i++) {}
  return i < track_list.length ? track_list[i] : null;
}

function addTableHead(table) {
  var tr =
    $( '<tr>' +
       '  <th class="index">#</th>' +
       '  <th class="bpm"><?= LNG_HEAD_BPM ?></th>' +
       '  <th class="genre"><?= LNG_HEAD_GENRE ?></th>' +
       '  <th><?= LNG_HEAD_TITLE ?></th>' +
       '  <th class="length"><?= LNG_HEAD_LENGTH ?></th>' +
       '</tr>'
     );
  table.find('thead').append(tr);
}

function addTrTemplate(table) {
  var genres = [ [  0, '']
               , [  1, '<?= strtolower(LNG_GENRE_DANCEBAND) ?>']
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
               ];
  genres.sort( function(a, b) {
                 if (a[0] == 0) return -1;
                 if (b[0] == 0) return  1;
                 return strcmp(a[1], b[1]);
               }
             );
  var tr =
    $( '<tr class="template">' +
       '  <input type="hidden" name="track_id" value="" />' +
       '  <input type="hidden" name="preview_url" value="" />' +
       '  <input type="hidden" name="length_ms" value="" />' +
       '  <td class="index" />' +
       '  <td class="bpm">' +
       '    <input type="text" name="bpm" class="bpm" value="" />' +
       '  </td>' +
       '  <td class="genre">' +
       '    <select class="genre" name="genre">' +
              genres.map(
                function(g) {
                  return '<option value="' + g[0] + '">' + g[1] + '</value>';
                }
              ).join('') +
       '    </select>' +
       '  </td>' +
       '  <td class="title" />' +
       '  <td class="length" />' +
       '</tr>' +
       '<tr class="summary">' +
       '  <td colspan="4" />' +
       '  <td class="length" />' +
       '</tr>'
     );
  table.find('tbody').append(tr);
}

function getTableTrackTrTemplate(table) {
  var tr = table.find('tr.template')[0];
  return $(tr);
}

function getTableSummaryTr(table) {
  var tr = table.find('tr.summary')[0];
  return $(tr);
}

function clearTable(table) {
  var track_tr_template = getTableTrackTrTemplate(table).clone(true, true);
  var summary_tr = getTableSummaryTr(table).clone(true, true);
  table.find('tbody tr').remove();
  table.append(track_tr_template);
  table.append(summary_tr);
}

function addTrackPreviewHandling(tr) {
  if (!tr.hasClass('track')) {
    return;
  }

  const static_text = '&#9835;';
  const playing_text = '&#9836;';
  const stop_text = static_text;
  var preview_url = tr.find('input[name=preview_url]').val().trim();
  if (preview_url.length == 0) {
    return;
  }

  var link = $('<a href="#">' + static_text + '</a>');
  link.click(
    function(e) {
      playPreview($(this), preview_url, playing_text, stop_text);
      e.stopPropagation(); // Prevent row selection
    }
  );
  tr.find('td.title').append(link);
}

function buildTrackRow(table, track) {
  var tr = getTableTrackTrTemplate(table).clone(true, true);
  tr.removeClass('template');
  tr.addClass('track');
  if ('trackId' in track) {
    tr.find('td.title').text(track.title);
    tr.find('input[name=track_id]').prop('value', track.trackId);
    tr.find('input[name=preview_url]').prop('value', track.previewUrl);
    tr.find('input[name=length_ms]').prop('value', track.length);
    tr.find('input[name=bpm]').prop('value', track.bpm);
    tr.find('select[name=genre] option[value=' + track.genre + ']')
      .prop('selected', true);
    tr.find('td.length').text(formatTrackLength(track.length));
    addTrackPreviewHandling(tr);
    addTrackSelectHandling(tr);
    addTrackDragHandling(tr);
    addTrackRightClickMenu(tr);
    renderTrackBpm(tr);
    addTrackBpmHandling(tr);
    addTrackGenreHandling(tr);
  }
  else {
    tr.removeClass('track').addClass('empty-track');
    tr.find('td.title').text(track.title);
    tr.find('input[name=track_id]').remove();
    tr.find('input[name=preview_url]').remove();
    tr.find('input[name=length_ms]').remove();
    bpm_td = tr.find('input[name=bpm]').closest('td');
    bpm_td.find('input').remove();
    bpm_td.text(track.bpm);
    genre_td = tr.find('select[name=genre]').closest('td');
    genre_td.find('select').remove();
    genre_td.text(track.genre);
    tr.find('td.length').text(track.length);
    addTrackSelectHandling(tr);
    addTrackDragHandling(tr);
    addTrackRightClickMenu(tr);
  }
  return tr;
}

function appendTracks(table, tracks) {
  for (var i = 0; i < tracks.length; i++) {
    var new_tr = buildTrackRow(table, tracks[i]);
    table.append(new_tr);
  }
  table.append(getTableSummaryTr(table)); // Move summary to last
}

function replaceTracks(table, tracks) {
  clearTable(table);
  appendTracks(table, tracks);
}

function renderTable(table) {
  const delimiter = (table.is(getPlaylistTable())) ? PLAYLIST_TRACK_DELIMITER : 0;

  // Assign indices
  var trs = table.find('tr.track, tr.empty-track');
  for (var i = 0; i < trs.length; i++) {
    var tr = $(trs[i]);
    tr.find('td.index').text(i+1);
  }

  // Insert delimiters
  table.find('tr.delimiter').remove();
  if (delimiter > 0) {
    var num_cols = getTableTrackTrTemplate(table).find('td').length;
    table
      .find('tr.track, tr.empty-track')
      .filter(':nth-child(' + delimiter + 'n+1)')
      .after(
        $( '<tr class="delimiter"><td colspan="' + num_cols + '"><div /></td>' +
           '</tr>'
         )
      );

    // Remove dangling delimiter, if any
    var trs = table.find('tr.track, tr.delimiter');
    if (trs.length > 0) {
      var last_tr = $(trs[trs.length-1]);
      if (last_tr.hasClass('delimiter')) {
        last_tr.remove();
      }
    }
  }

  // Compute total length
  var total_length = 0;
  table.find('tr.track').each(
    function() {
      total_length += parseInt($(this).find('input[name=length_ms]').val());
    }
  );
  getTableSummaryTr(table).find('td.length').text(formatTrackLength(total_length));
}

function renderPlaylist() {
  renderTable(getPlaylistTable());
}

function renderScratchpad() {
  renderTable(getScratchpadTable());
}

function formatTrackTitle(artists, name) {
  return artists + ' - ' + name;
}

function formatTrackLength(ms) {
  var t = Math.trunc(ms / 1000);
  t = [0, 0, t];
  for (var i = t.length - 2; i >= 0; i--) {
    if (t[i+1] < 60) break;
    t[i] = Math.floor(t[i+1] / 60);
    t[i+1] = t[i+1] % 60;
  }

  if (t[0] == 0) t.shift();
  for (var i = 1; i < t.length; i++) {
    if (t[i] < 10) t[i] = '0' + t[i].toString();
  }

  return t.join(':');
}

function setTrackDelimiter(d) {
  PLAYLIST_TRACK_DELIMITER = d;
}

function isUsingTrackDelimiter() {
  return PLAYLIST_TRACK_DELIMITER > 0;
}

function clearTrackSelection() {
  $('.playlist tr.selected').removeClass('selected');
}

function getSelectedTracks() {
  return $('.playlist tr.selected');
}

function updateTrackSelection(tr, multi_select_mode, span_mode) {
  // Remove active selection in other playlist areas
  $('.playlist').each(
    function() {
      var p = $(this);
      if (p[0] == tr.closest('.playlist')[0]) {
        return;
      }
      p.find('tr.selected').removeClass('selected');
    }
  );

  if (multi_select_mode) {
    tr.toggleClass('selected');
    return;
  }

  if (span_mode) {
    var selected_sib_trs =
      tr.siblings().filter(function() { return $(this).hasClass('selected') });
    if (selected_sib_trs.length == 0) {
      tr.addClass('selected');
      return;
    }
    var first = $(selected_sib_trs[0]);
    var last = $(selected_sib_trs[selected_sib_trs.length-1]);
    var trs = tr.siblings().add(tr);
    for ( var i = Math.min(tr.index(), first.index(), last.index())
        ; i <= Math.max(tr.index(), first.index(), last.index())
        ; i++
        )
    {
      if ($(trs[i]).hasClass('track') || $(trs[i]).hasClass('empty-track')) {
        $(trs[i]).addClass('selected');
      }
    }
    return;
  }

  var selected_sib_trs =
    tr.siblings().filter(function() { return $(this).hasClass('selected') });
  selected_sib_trs.removeClass('selected');
  if (selected_sib_trs.length > 0) {
    tr.addClass('selected');
    return
  }
  tr.toggleClass('selected');
}

function addTrackSelectHandling(tr) {
  tr.click(
    function(e) {
      if (TRACK_DRAG_STATE == 0) {
        updateTrackSelection($(this), e.ctrlKey || e.metaKey, e.shiftKey);
      }
      else {
        TRACK_DRAG_STATE = 0;
      }
    }
  );
}

function addTrackDragHandling(tr) {
  tr.mousedown(
    function(e) {
      var mousedown_tr = $(e.target).closest('tr');
      if (!mousedown_tr.hasClass('selected')) {
        return;
      }
      var body = $(document.body);
      body.addClass('grabbed');
      mousedown_tr.addClass('grabbed');

      var selected_trs =
        mousedown_tr.siblings().add(mousedown_tr).filter(
          function() { return $(this).hasClass('selected') }
        );
      var mb = $('.grabbed-info-block');
      mb.find('span').text(selected_trs.length);
      mb.css({ top: e.pageY + 'px', left: e.pageX + 'px' });
      mb.show();

      $('.playlist').toggleClass('drag-mode');

      var ins_point = $('.drag-insertion-point');

      function move(e) {
        // Move info block
        const of = 5; // To prevent grabbed-info-block to appear as target
        mb.css({ top: e.pageY+of + 'px', left: e.pageX+of + 'px' });

        TRACK_DRAG_STATE = 1; // tr.click() and mouseup may both reset this.
                              // This is to prevent deselection if drag stops on
                              // selected tracks

        // Check if moving over insertion-point bar (to prevent flickering)
        if ($(e.target).hasClass('drag-insertion-point')) {
          return;
        }

        // Always clear previous insertion point; else we could in some cases end
        // up with multiple insertion points
        $('.playlist tr.insert-above, .playlist tr.insert-below')
          .removeClass('insert-above insert-below');

        // Hide insertion-point bar if we are not over a playlist
        var tr = $(e.target.closest('tr'));
        if (tr.length == 0 || tr.closest('.playlist').length == 0) {
          ins_point.hide();
          return;
        }

        // We have moved over a playlist

        // If moved over empty-track tr, mark entire tr as insertion point
        if (tr.hasClass('empty-track')) {
          tr.addClass('insert-above');
          ins_point.hide();
          return;
        }

        // If moving over table head, move insertion point to next visible tbody tr
        if (tr.closest('thead').length > 0) {
          tr = $(tr.closest('table').find('tbody tr')[0]);
          while (!tr.is(':visible')) {
            tr = tr.next();
          }
        }

        // Mark insertion point and draw insertion-point bar
        var tr_y_half = e.pageY - tr.offset().top - (tr.height() / 2);
        var insert_above = tr_y_half <= 0 || tr.hasClass('summary');
        tr.addClass(insert_above ? 'insert-above' : 'insert-below');
        ins_point.css( { width: tr.width() + 'px'
                       , left: tr.offset().left + 'px'
                       , top: ( tr.offset().top +
                                (insert_above ? 0 : tr.height()) -
                                ins_point.height()/2
                              ) + 'px'
                       }
                     );
        ins_point.show();
      }

      function up(e) {
        var tr_insert_point =
          $('.playlist tr.insert-above, .playlist tr.insert-below');
        if (tr_insert_point.length == 1) {
          // Forbid dropping adjacent to a selected track as that causes wierd
          // reordering
          var dropped_adjacent_to_selected =
            tr_insert_point.hasClass('selected') ||
            ( tr_insert_point.hasClass('insert-above') &&
              tr_insert_point.prev().hasClass('selected')
            ) ||
            ( tr_insert_point.hasClass('insert-below') &&
              tr_insert_point.next().hasClass('selected')
            );
          if (!dropped_adjacent_to_selected) {
            var selected_trs = getSelectedTracks();

            // If appropriate, insert placeholders where selected tracks used to be
            var source_table = getTableOfTr($(selected_trs[0]));
            if (isUsingTrackDelimiter() && source_table.is(getPlaylistTable())) {
              // Ignore tracks that covers an entire dance block
              rows_to_keep = [];
              function isTrackRow(tr) {
                return tr.hasClass('track') || tr.hasClass('empty-track');
              }
              for (var i = 0; i < selected_trs.length; i++) {
                var tr = $(selected_trs[i]);
                if (!isTrackRow(tr.prev())) {
                  var skip = false;
                  var j = i;
                  do {
                    var next_tr = tr.next();
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
              for (var i = 0; i < rows_to_keep.length; i++) {
                var old_tr = $(rows_to_keep[i]);
                if (!old_tr.hasClass('empty-track')) {
                  var o = createPlaylistPlaceholderObject();
                  var new_tr = buildTrackRow(source_table, o);
                  old_tr.before(new_tr);
                }
              }
            }

            // Move selected
            if (tr_insert_point.hasClass('insert-above')) {
              tr_insert_point.before(selected_trs);
            }
            else {
              tr_insert_point.after(selected_trs);
            }
            if (tr_insert_point.hasClass('empty-track')) {
              tr_insert_point.remove();
            }
            renderPlaylist();
            renderScratchpad();
            indicateStateUpdate();
          }
          tr_insert_point.removeClass('insert-above insert-below');
        }

        // Remove info block and insertion-point bar
        mb.hide();
        mousedown_tr.removeClass('grabbed');
        body.removeClass('grabbed');
        ins_point.hide();

        $('.playlist').toggleClass('drag-mode');

        if (tr_insert_point[0] != mousedown_tr[0]) {
          TRACK_DRAG_STATE = 0;
        }

        $(document).unbind('mousemove', move).unbind('mouseup', up);
      }

      $(document).mousemove(move).mouseup(up);
    }
  );
}

function addTrackRightClickMenu(tr) {
  function buildMenu(menu, clicked_tr, close_f) {
    function buildPlaceholderTr() {
      var o = createPlaylistPlaceholderObject();
      return buildTrackRow(getTableOfTr(clicked_tr), o);
    }
    const actions =
      [ [ '<?= LNG_MENU_SELECT_IDENTICAL_TRACKS ?>'
        , function() {
            clicked_tid_input = clicked_tr.find('input[name=track_id]');
            if (clicked_tid_input.length == 0) {
              return;
            }
            var clicked_tid = clicked_tid_input.val().trim();
            getTableOfTr(clicked_tr).find('tr').each(
              function() {
                var tr = $(this);
                var tr_tid_input = tr.find('input[name=track_id]');
                if (tr_tid_input.length == 0) {
                  return;
                }
                var tr_tid = tr_tid_input.val().trim();
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
            var new_tr = buildPlaceholderTr();
            clicked_tr.before(new_tr);
            renderTable(getTableOfTr(clicked_tr));
            indicateStateUpdate();
            close_f();
          }
        , function(a) {}
        ]
      , [ '<?= LNG_MENU_INSERT_PLACEHOLDER_AFTER ?>'
        , function() {
            var new_tr = buildPlaceholderTr();
            clicked_tr.after(new_tr);
            renderTable(getTableOfTr(clicked_tr));
            indicateStateUpdate();
            close_f();
          }
        , function(a) {}
        ]
      , [ '<?= LNG_MENU_DELETE_SELECTED ?>'
        , function() {
            var trs = getSelectedTracks();
            if (trs.length == 0) {
              return;
            }

            var t = getTableOfTr($(trs[0]));
            var is_playlist = t.is(getPlaylistTable());
            trs.remove();
            if (is_playlist) {
              renderPlaylist();
            }
            else {
              renderScratchpad();
            }
            indicateStateUpdate();
            close_f();
          }
        , function(a) {
            var trs = getSelectedTracks();
            if (trs.length == 0) {
              a.addClass('disabled');
            }
          }
        ]
      ];
    menu.empty();
    for (var i = 0; i < actions.length; i++) {
      var a = $('<a href="#" />');
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
      var menu = $('.mouse-menu');
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

function savePlaylistSnapshot() {
  setStatus('<?= LNG_DESC_SAVING ?>...');

  function getTrackId(t) {
    if (t.trackId === undefined) {
      return '';
    }
    return t.trackId;
  }
  playlist_tracks = getPlaylistTrackData().map(getTrackId);
  scratchpad_tracks = getScratchpadTrackData().map(getTrackId);
  data = { playlistId: PLAYLIST_ID
         , snapshot: { playlistData: playlist_tracks
                     , scratchpadData: scratchpad_tracks
                     , delimiter: PLAYLIST_TRACK_DELIMITER
                     , spotifyPlaylistHash: LAST_SPOTIFY_PLAYLIST_HASH
                     }
         };
  callApi( '/api/save-playlist-snapshot/'
         , data
         , function(d) {
             clearStatus();
           }
         , function(msg) {
             setStatus('<?= LNG_ERR_FAILED_TO_SAVE ?>', true);
           }
         );
}

function loadPlaylistFromSnapshot(playlist_id, success_f, no_snap_f, fail_f) {
  var status = [false, false];
  function done(table, status_offset) {
    status[status_offset] = true;
    renderTable(table);
    if (status.every(x => x)) {
      success_f();
    }
  }
  function load(table, status_offset, track_ids, track_offset) {
    function hasTrackAt(o) {
      return track_ids[o].length > 0;
    }

    if (track_offset >= track_ids.length) {
      done(table, status_offset);
      return;
    }
    if (hasTrackAt(track_offset)) {
      // Currently at a track entry; add tracks until next placeholder entry
      var tracks_to_load = [];
      var o = track_offset;
      for ( ; o < track_ids.length &&
              hasTrackAt(o) &&
              tracks_to_load.length < LOAD_TRACKS_LIMIT
            ; o++
          )
      {
        tracks_to_load.push(track_ids[o]);
      }
      callApi( '/api/get-track-info/'
             , { trackIds: tracks_to_load }
             , function(d) {
                 var tracks = [];
                 for (var i = 0; i < d.tracks.length; i++) {
                   var t = d.tracks[i];
                   var obj = createPlaylistTrackObject( t.trackId
                                                      , t.artists
                                                      , t.name
                                                      , t.length
                                                      , t.bpm
                                                      , t.genre
                                                      , t.preview_url
                                                      );
                   tracks.push(obj);
                 }
                 appendTracks(table, tracks);
                 load(table, status_offset, track_ids, o);
               }
             , fail_f
             );
    }
    else {
      // Currently at a placeholder entry; add such until next track entry
      var placeholders = [];
      var o = track_offset;
      for (; o < track_ids.length && !hasTrackAt(o); o++) {
        placeholders.push(createPlaylistPlaceholderObject());
      }
      appendTracks(table, placeholders);
      load(table, status_offset, track_ids, o);
    }
  }
  callApi( '/api/get-playlist-snapshot/'
         , { playlistId: playlist_id }
         , function(res) {
             if (res.status == 'OK') {
               PLAYLIST_TRACK_DELIMITER = res.snapshot.delimiter;
               LAST_SPOTIFY_PLAYLIST_HASH = res.snapshot.spotifyPlaylistHash;
               if (PLAYLIST_TRACK_DELIMITER > 0) {
                 setDelimiterAsShowing();
               }
               load(getPlaylistTable(), 0, res.snapshot.playlistData, 0);
               load(getScratchpadTable(), 1, res.snapshot.scratchpadData, 0);
               if (res.snapshot.scratchpadData.length > 0) {
                 showScratchpad();
               }
             }
             else if (res.status == 'NOT-FOUND') {
               no_snap_f();
             }
           }
         , fail_f
         );
}

function setStatus(s, indicate_failure = false) {
  var status = $('.saving-status');
  status.text(s);
  status.removeClass('failed');
  if (indicate_failure) {
    status.addClass('failed');
  }
}

function clearStatus() {
  var status = $('.saving-status');
  status.empty();
  status.removeClass('failed');
}

function indicateStateUpdate() {
  saveUndoState();
  savePlaylistSnapshot();
}

function saveUndoState() {
  const limit = UNDO_STACK_LIMIT;

  // Find slot to save state
  if (UNDO_STACK_OFFSET+1 == limit) {
    // Remove first and shift all states
    for (var i = 1; i < limit; i++) {
      UNDO_STACK[i-1] = UNDO_STACK[i];
    }
  }
  else {
    UNDO_STACK_OFFSET++;
  }
  const offset = UNDO_STACK_OFFSET;

  // Destroy obsolete redo states
  for (var o = offset; o < limit; o++) {
    if (UNDO_STACK[o] !== null) {
      var state = UNDO_STACK[o];
      state.playlistTable.remove();
      state.scratchpadTable.remove();
      UNDO_STACK[o] = null;
    }
  }

  var playlist = getPlaylistTable().clone(true, true);
  playlist.find('tr.selected').removeClass('selected');
  playlist.find('tr.delimiter').remove();
  var scratchpad = getScratchpadTable().clone(true, true);
  scratchpad.find('tr.selected').removeClass('selected');
  scratchpad.find('tr.delimiter').remove();
  var state = { playlistTable: playlist
              , scratchpadTable: scratchpad
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
  var state = UNDO_STACK[offset];
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
  var state = UNDO_STACK[offset];
  restoreState(state);
  state.callback();
  renderUndoRedoButtons();
}

function restoreState(state) {
  getPlaylistTable().replaceWith(state.playlistTable.clone(true, true));
  getScratchpadTable().replaceWith(state.scratchpadTable.clone(true, true));
  renderPlaylist();
  renderScratchpad();
  savePlaylistSnapshot();
}

function renderUndoRedoButtons() {
  const offset = UNDO_STACK_OFFSET;
  var undo_b = $('#undoBtn');
  var redo_b = $('#redoBtn');
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
