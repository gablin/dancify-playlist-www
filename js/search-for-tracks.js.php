<?php
require '../autoload.php';

ensureSession();
$session = getSession();
$api = createWebApi($session);
?>

const SEARCH_LIMIT = 50;
var CANCEL_SEARCH_FOR_TRACK = false;

function getSearchForTracksActionArea() {
  let form = getPlaylistForm();
  return form.find('div[name=search-for-tracks]');
}

function getSearchForTracksResultsArea() {
  return getSearchForTracksActionArea().find('.search-results');
}

function setupSearchForTracks() {
  let action_area = getSearchForTracksActionArea();
  addOptionsToGenreSelect(action_area.find('select[name=search-by-genre]'));
  setupSearchForTracksBpmController();
  setupSearchForTracksButtons();
  setupSearchForTracksAddSearchResultsButtons();
}

function setupSearchForTracksBpmController() {
  let action_area = getSearchForTracksActionArea();
  let bpm_slider = action_area.find('td.bpm-range-controller > div');
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
  let action_area = getSearchForTracksActionArea();
  let bpm_slider = action_area.find('td.bpm-range-controller > div');
  let v1 = bpm_slider.slider('values', 0);
  let v2 = bpm_slider.slider('values', 1);
  return [v1, v2];
}

function setupSearchForTracksButtons() {
  let action_area = getSearchForTracksActionArea();
  let search_btn = action_area.find('button[id=searchTracksBtn]');
  function search() {
    let genre = action_area.find('select[name=search-by-genre] :selected').val();
    let bpm_range = getSearchForTracksBpmValues();
    let in_my_playlists_only =
      action_area.find('input[name=search-my-playlists-only]').is(':checked');

    let body = $(document.body);
    body.addClass('loading');
    action_area.find('.error').hide();
    action_area.find('.none-found').hide();
    action_area.find('.tracks-found').hide();
    CANCEL_SEARCH_FOR_TRACK = false;

    let close_btn = $(this).closest('.buttons').find('button.cancel');
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
        let none_found_area = action_area.find('.none-found');
        none_found_area.show();
        none_found_area.text('<?= LNG_DESC_NO_TRACKS_FOUND ?>');
      }
    }
    function stop() {
      CANCEL_SEARCH_FOR_TRACK = true;
      finalize();
    }
    function fail(msg) {
      let error_space = action_area.find('.error');
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
  let search_results_area = getSearchForTracksResultsArea();
  search_results_area.show();
  $('#addSearchToPlaylistBtn').prop('disabled', true);
  $('#addSearchToLocalScratchpadBtn').prop('disabled', true);
  $('#addSearchToGlobalScratchpadBtn').prop('disabled', true);

  function setTableHeight() {
    let search_area_bottom =
      search_results_area.offset().top + search_results_area.height();
    let table = search_results_area.find('.table-wrapper');
    let table_top = table.offset().top;
    let buttons_height = search_results_area.find('.buttons').outerHeight();
    let table_height = search_area_bottom - table_top - buttons_height;
    table.css('height', table_height + 'px');
  }

  function load(offset) {
    if (CANCEL_SEARCH_FOR_TRACK) {
      done_f();
      return;
    }

    let data = { onlyInMyPlaylists: in_my_playlists_only
               , limit: SEARCH_LIMIT
               , offset: offset
               , bpmRange: [bpm_range[0], bpm_range[1]]
               };
    if (genre > 0) {
      data.genre = genre;
    }
    callApi( '/api/search-tracks/'
           , data
           , function(d) {
               if (d.trackIds.length == 0) {
                 done_f();
                 return;
               }

               callApi( '/api/get-track-info/'
                      , { trackIds: d.trackIds }
                      , function(dd) {
                          let tracks = [];
                          for (let i = 0; i < dd.tracks.length; i++) {
                            let t = dd.tracks[i];
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
    let tbody = getSearchForTracksResultsArea().find('.playlist table tbody');
    tbody.empty();
  }

  function appendResults(tracks) {
    let table = getSearchForTracksResultsArea().find('.playlist table');
    for (let i = 0; i < tracks.length; i++) {
      let t = tracks[i];
      let t_bpm = t.bpm.custom >= 0 ? t.bpm.custom : t.bpm.spotify;
      let t_genre = t.genre.by_user != 0
                    ? t.genre.by_user
                    : (t.genre.by_others.length > 0 ? t.genre.by_others[0] : 0);
      console.log(t.genre);
      let tr = $( '<tr class="track">' +
                    '<td>' + formatTrackTitleAsText(t.artists, t.name) + '</td>' +
                    '<td class="bpm">' + t_bpm + '</td>' +
                    '<td class="genre">' + genreToString(t_genre) + '</td>' +
                    '<td class="length">' +
                      formatTrackLength(t.length) +
                    '</td>' +
                  '</tr>'
                );
      tr.data('trackData', t);
      addSearchTrackSelectHandling(tr);
      table.append(tr);
    }
  }
}

function addSearchTrackSelectHandling(tr) {
  tr.click(
    function(e) {
      if (TRACK_DRAG_STATE == 0) {
        updateSearchTrackSelection($(this), e.ctrlKey || e.metaKey, e.shiftKey);
      }
      else {
        TRACK_DRAG_STATE = 0;
      }
    }
  );
}

function updateSearchTrackSelection(tr, multi_select_mode, span_mode) {
  function renderButtons() {
    let table = getSearchForTracksResultsArea().find('table');
    let playlist_btn = $('#addSearchToPlaylistBtn');
    let local_scratchpad_btn = $('#addSearchToLocalScratchpadBtn');
    let global_scratchpad_btn = $('#addSearchToGlobalScratchpadBtn');
    if (table.find('tbody tr.selected').length == 0) {
      playlist_btn.prop('disabled', true);
      local_scratchpad_btn.prop('disabled', true);
      global_scratchpad_btn.prop('disabled', true);
      return;
    }
    playlist_btn.prop('disabled', false);
    local_scratchpad_btn.prop('disabled', false);
    global_scratchpad_btn.prop('disabled', false);
  }

  if (multi_select_mode) {
    tr.toggleClass('selected');
    renderButtons();
    return;
  }

  if (span_mode) {
    let selected_sib_trs =
      tr.siblings().filter(function() { return $(this).hasClass('selected') });
    if (selected_sib_trs.length == 0) {
      tr.addClass('selected');
      renderButtons();
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
      if ($(trs[i]).hasClass('track')) {
        $(trs[i]).addClass('selected');
      }
    }
    renderButtons();
    return;
  }

  let selected_sib_trs =
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
    let tracks = [];
    getSearchForTracksResultsArea().find('table tbody tr.selected').each(
      function() {
        let tr = $(this);
        let t = tr.data('trackData');
        let o = createPlaylistTrackObject( t.trackId
                                         , t.artists
                                         , t.name
                                         , t.length
                                         , t.bpm
                                         , t.genre.by_user
                                         , t.genre.by_others
                                         , t.comments
                                         , t.preview_url
                                         , '<?= getThisUserId($api) ?>'
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
  $('#addSearchToLocalScratchpadBtn').click(
    function() {
      let table = getLocalScratchpadTable();
      addToTable(table);
      showScratchpad(table);
    }
  );
  $('#addSearchToGlobalScratchpadBtn').click(
    function() {
      let table = getGlobalScratchpadTable();
      addToTable(table);
      showScratchpad(table);
    }
  );
}
