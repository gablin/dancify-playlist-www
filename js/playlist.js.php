<?php
require '../autoload.php';
?>

var PREVIEW_AUDIO = $('<audio />');
var PLAYLIST_TRACK_DELIMITER = 0;
var TRACK_DRAG_STATE = 0;

function setupPlaylist() {
  $(document).on( 'keyup'
                , function(e) {
                    if (e.key == 'Escape') {
                      clearTrackSelection();
                    }
                  }
                );
}

function loadPlaylist(playlist_id) {
  function load(offset) {
    var data = { playlistId: playlist_id
               , offset: offset
               };
    function fail(msg) {
      console.log(msg);
      alert('ERROR: <?= LNG_ERR_FAILED_LOAD_PLAYLIST ?>');
    }
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
                                                             , t.category
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
                          }
                        }
                      , fail
                      )
             }
           , fail
           );
  }
  load(0);
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

function addTrackBpmHandling(tr) {
  var input = tr.find('input[name=bpm]');
  input.click(
    function(e) {
      e.stopPropagation(); // Prevent row selection
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
      input.removeClass('invalid');

      // Save new BPM to database
      var data = { trackId: tid, bpm: bpm };
      callApi( '/api/update-bpm/'
             , data
             , function(d) {}
             , function(msg) {
                 alert('<?= LNG_ERR_FAILED_UPDATE_BPM ?>');
                 console.log(msg);
               }
             );

      // Update BPM on all duplicate tracks (if any)
      input.closest('table').find('input[name=track_id][value=' + tid + ']').each(
        function() {
          $(this).parent().find('input[name=bpm]').val(bpm);
        }
      );
    }
  );
}

function addTrackCategoryHandling(tr) {
  var input = tr.find('input[name=category]');
  input.click(
    function(e) {
      e.stopPropagation(); // Prevent row selection
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

      // Save new category to database
      var data = { trackId: tid, category: category };
      callApi( '/api/update-category/'
             , data
             , function(d) {}
             , function(msg) {
                 alert('<?= LNG_ERR_FAILED_UPDATE_CATEGORY ?>');
                 console.log(msg);
               }
             );

      // Update category on all duplicate tracks (if any)
      input
        .closest('table').find('input[name=track_id][value=' + tid + ']').each(
          function() {
            $(this).parent().find('input[name=category]').val(category);
          }
        );
    }
  );
}

function checkBpmInput(str, report_on_fail = true) {
  bpm = parseInt(str);
  if (isNaN(bpm)) {
    if (report_on_fail) {
      alert('<?= LNG_ERR_BPM_NAN ?>');
    }
    return false;
  }
  if (bpm <= 0) {
    if (report_on_fail) {
      alert('<?= LNG_ERR_BPM_TOO_SMALL ?>');
    }
    return false;
  }
  if (bpm > 255) {
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

function verifyPlaylistData() {
  // TODO: implement
}

function getTrackData(table) {
  var playlist = [];
  table.find('tr').each(
    function() {
      var tr = $(this);
      if (tr.hasClass('track')) {
        var track_id = tr.find('input[name=track_id]').val().trim();
        var preview_url = tr.find('input[name=preview_url]').val().trim();
        var bpm = tr.find('input[name=bpm]').val().trim();
        var category = tr.find('input[name=category]').val().trim();
        var title = getTrTitleText(tr);
        var len = tr.find('input[name=length_ms]').val().trim();
        playlist.push( { trackId: track_id
                       , title: title
                       , length: len
                       , bpm: bpm
                       , category: category
                       , previewUrl: preview_url
                       }
                     );
      }
      else {
        // TODO: handle
      }
    }
  );

  return playlist;
}

function getPlaylistData()
{
  var table = getPlaylistTable();
  return getTrackData(table);
}

function getScratchpadData()
{
  var table = getScratchpadTable();
  return getTrackData(table);
}

function createPlaylistTrackObject( track_id
                                  , artists
                                  , name
                                  , length_ms
                                  , bpm
                                  , category
                                  , preview_url
                                  )
{
  return { trackId: track_id
         , title: formatTrackTitle(artists, name)
         , length: length_ms
         , bpm: bpm
         , category: category
         , previewUrl: preview_url
         }
}

function createPlaylistPlaceholderObject( title_text
                                        , length_text
                                        , bpm_text
                                        , category_text
                                        )
{
  return { title: title_text
         , length: length_text
         , bpm: bpm_text
         , category: category_text
         }
}

function getTrackWithMatchingId(track_list, track_id) {
  var i = 0;
  for (; i < track_list.length && track_list[i].trackId != track_id; i++) {}
  return i < track_list.length ? track_list[i] : null;
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
  table.find('tr > td').parent().remove();
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
    tr.find('input[name=category]').prop('value', track.category);
    tr.find('td.length').text(formatTrackLength(track.length));
    addTrackPreviewHandling(tr);
    addTrackSelectHandling(tr);
    addTrackDragHandling(tr);
    addTrackBpmHandling(tr);
    addTrackCategoryHandling(tr);
  }
  else {
    tr.removeClass('track');
    tr.addClass('unfilled-slot');
    tr.find('td.title').text(track.title);
    tr.find('input[name=track_id]').remove();
    tr.find('input[name=preview_url]').remove();
    tr.find('input[name=length_ms]').remove();
    bpm_td = tr.find('input[name=bpm]').parent();
    bpm_td.find('input').remove();
    bpm_td.text(track.bpm);
    category_td = tr.find('input[name=category]').parent();
    category_td.find('input').remove();
    category_td.text(track.category);
    tr.find('td.length').text(track.length);
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

function renderTable(table, delimiter) {
  // Assign indices
  var trs = table.find('tr.track');
  for (var i = 0; i < trs.length; i++) {
    var tr = $(trs[i]);
    tr.find('td.index').text(i+1);
  }

  // Insert delimiters
  table.find('tr.delimiter').remove();
  if (delimiter > 0) {
    var num_cols = getTableTrackTrTemplate(table).find('td').length;
    table.find('tr.track:nth-child(' + delimiter + 'n+1)').after(
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
  renderTable(getPlaylistTable(), PLAYLIST_TRACK_DELIMITER);
}

function renderScratchpad() {
  renderTable(getScratchpadTable(), 0);
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

function clearTrackSelection() {
  $('.playlist tr.selected').removeClass('selected');
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
      if ($(trs[i]).hasClass('track')) {
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

        if (tr.closest('thead').length > 0) {
          // Get first tbody tr
          tr = $(tr.closest('table').find('tbody tr.track, tbody tr.summary')[0]);
        }

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
            var selected_trs = $('tr.track.selected');
            if (tr_insert_point.hasClass('insert-above')) {
              tr_insert_point.before(selected_trs);
            }
            else {
              tr_insert_point.after(selected_trs);
            }
            renderPlaylist();
            renderScratchpad();
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
