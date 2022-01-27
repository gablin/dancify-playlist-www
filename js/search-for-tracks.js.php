<?php
require '../autoload.php';
?>

const SEARCH_LIMIT = 50;
var CANCEL_SEARCH_FOR_TRACK = false;

function getSearchForTracksActionArea() {
  var form = getPlaylistForm();
  return form.find('div[name=search-for-tracks]');
}

function getSearchForTracksResultsArea() {
  return getSearchForTracksActionArea().find('.search-results');
}

function setupSearchForTracks() {
  var action_area = getSearchForTracksActionArea();
  addOptionsToGenreSelect(action_area.find('select[name=search-by-genre]'), true);
  setupSearchForTracksBpmController();
  setupSearchForTracksButtons();
  setupSearchForTracksAddSearchResultsButtons();
}

function setupSearchForTracksBpmController() {
  var action_area = getSearchForTracksActionArea();
  var bpm_slider = action_area.find('td.range-controller > div');
  function printValues(v1, v2) {
    bpm_slider.closest('tr').find('td.label > span').text(v1 + ' - ' + v2);
  }
  bpm_slider.slider(
    { range: true
    , min: 0
    , max: 255
    , values: [0, 255]
    , slide: function(event, ui) {
        printValues(ui.values[0], ui.values[1]);
      }
    }
  );
  printValues( bpm_slider.slider('values', 0)
             , bpm_slider.slider('values', 1)
             );
}

function getSearchForTracksBpmValues() {
  var action_area = getSearchForTracksActionArea();
  var bpm_slider = action_area.find('td.range-controller > div');
  var v1 = bpm_slider.slider('values', 0);
  var v2 = bpm_slider.slider('values', 1);
  return [v1, v2];
}

function setupSearchForTracksButtons() {
  var action_area = getSearchForTracksActionArea();
  var search_btn = action_area.find('button[id=searchTracksBtn]');
  function search() {
    var genre = action_area.find('select[name=search-by-genre] :selected').val();
    var bpm_range = getSearchForTracksBpmValues();
    var in_my_playlists_only =
      action_area.find('input[name=search-my-playlists-only]').prop('checked');

    var body = $(document.body);
    body.addClass('loading');
    action_area.find('.error').hide();
    action_area.find('.none-found').hide();
    action_area.find('.tracks-found').hide();
    CANCEL_SEARCH_FOR_TRACK = false;

    var close_btn = $(this).closest('.buttons').find('button.cancel');
    close_btn.text('<?= LNG_BTN_CLOSE ?>');
    close_btn.one('click', stop);

    search_btn.text('<?= LNG_BTN_STOP ?>');
    search_btn.off('click');
    search_btn.one('click', stop);

    function finalize() {
      body.removeClass('loading');
      search_btn.text('<?= LNG_BTN_SEARCH ?>');
      search_btn.on('click', search);
      close_btn.off('click', stop);
      if (getSearchForTracksResultsArea().find('table tbody tr').length == 0) {
        var none_found_area = action_area.find('.none-found');
        none_found_area.show();
        none_found_area.text('<?= LNG_DESC_SEARCH_RESULTS_NONE ?>');
      }
    }
    function stop() {
      CANCEL_SEARCH_FOR_TRACK = true;
      finalize();
    }
    function fail(msg) {
      var error_space = action_area.find('.error');
      error_space.text('<?= LNG_ERR_FAILED_TO_SEARCH ?>');
      error_space.show();
      finalize();
    }
    function done() {
      finalize();
    }

    searchForTracks(genre, bpm_range, in_my_playlists_only, done, fail);
  }
  search_btn.on('click', search);
}

function searchForTracks( genre
                        , bpm_range
                        , in_my_playlists_only
                        , done_f
                        , fail_f
                        )
{
  clearResults();
  var search_results_area = getSearchForTracksResultsArea();
  search_results_area.show();
  $('#addSearchToPlaylistBtn').prop('disabled', true);
  $('#addSearchToScratchpadBtn').prop('disabled', true);

  function setTableHeight() {
    var search_area_bottom =
      search_results_area.offset().top + search_results_area.height();
    var table = search_results_area.find('.table-wrapper');
    var table_top = table.offset().top;
    var buttons_height = search_results_area.find('.buttons').outerHeight();
    var table_height = search_area_bottom - table_top - buttons_height;
    table.css('height', table_height + 'px');
  }

  function load(offset) {
    if (CANCEL_SEARCH_FOR_TRACK) {
      done_f();
      return;
    }

    callApi( '/api/search-tracks/'
           , { genre: genre
             , onlyInMyPlaylists: in_my_playlists_only
             , limit: SEARCH_LIMIT
             , offset: offset
             }
           , function(d) {
               if (d.trackIds.length == 0) {
                 done_f();
                 return;
               }

               callApi( '/api/get-track-info/'
                      , { trackIds: d.trackIds }
                      , function(dd) {
                          var tracks = [];
                          for (var i = 0; i < dd.tracks.length; i++) {
                            var t = dd.tracks[i];
                            if (t.bpm < bpm_range[0] || t.bpm > bpm_range[1]) {
                              continue;
                            }
                            tracks.push(t);
                          }
                          appendResults(tracks);
                          search_results_area.find('.tracks-found').show();
                          setTableHeight();
                          if (d.trackIds.length == SEARCH_LIMIT) {
                            load(offset + SEARCH_LIMIT);
                          }
                          else {
                            done_f();
                          }
                        }
                      , fail_f
                      );
             }
           , fail_f
           );
  }
  load(0);

  function clearResults() {
    var tbody = getSearchForTracksResultsArea().find('.playlist table tbody');
    tbody.empty();
  }

  function appendResults(tracks) {
    var table = getSearchForTracksResultsArea().find('.playlist table');
    for (var i = 0; i < tracks.length; i++) {
      var t = tracks[i];
      var tr = $( '<tr class="track">' +
                    '<td>' +
                      formatTrackTitle(t.artists, t.name) +
                    '</td>' +
                    '<td class="bpm">' + t.bpm + '</td>' +
                    '<td class="length">' +
                      formatTrackLength(t.length) +
                    '</td>' +
                  '</tr>'
                );
      tr.data('trackData', t);
      addTrackSelectHandling(tr);
      table.append(tr);
    }
  }
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

function updateTrackSelection(tr, multi_select_mode, span_mode) {
  function renderButtons() {
    var table = getSearchForTracksResultsArea().find('table');
    var playlist_btn = $('#addSearchToPlaylistBtn');
    var scratchpad_btn = $('#addSearchToScratchpadBtn');
    if (table.find('tbody tr.selected').length == 0) {
      playlist_btn.prop('disabled', true);
      scratchpad_btn.prop('disabled', true);
      return;
    }
    playlist_btn.prop('disabled', false);
    scratchpad_btn.prop('disabled', false);
  }

  if (multi_select_mode) {
    tr.toggleClass('selected');
    renderButtons();
    return;
  }

  if (span_mode) {
    var selected_sib_trs =
      tr.siblings().filter(function() { return $(this).hasClass('selected') });
    if (selected_sib_trs.length == 0) {
      tr.addClass('selected');
      renderButtons();
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
    renderButtons();
    return;
  }

  var selected_sib_trs =
    tr.siblings().filter(function() { return $(this).hasClass('selected') });
  selected_sib_trs.removeClass('selected');
  if (selected_sib_trs.length > 0) {
    tr.addClass('selected');
    renderButtons();
    return
  }
  tr.toggleClass('selected');
  renderButtons();
}

function setupSearchForTracksAddSearchResultsButtons() {
  function addToTable(table) {
    var tracks = [];
    getSearchForTracksResultsArea().find('table tbody tr.selected').each(
      function() {
        var tr = $(this);
        var t = tr.data('trackData');
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
    );
    appendTracks(table, tracks);
    renderTable(table);
    indicateStateUpdate();
  }

  $('#addSearchToPlaylistBtn').click(
    function() {
      addToTable(getPlaylistTable());
    }
  );
  $('#addSearchToScratchpadBtn').click(
    function() {
      addToTable(getScratchpadTable());
      showScratchpad();
    }
  );
}
