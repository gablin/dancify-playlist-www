<?php
require '../autoload.php';
?>

function setupRandomizeByBpm() {
  setupFormElementsForRandomizeByBpm();
}

function setupFormElementsForRandomizeByBpm() {
  let form = getPlaylistForm();
  let table = getPlaylistTable();
  let action_area = $('div[name=randomize-by-bpm]');

  // Randomize button
  let rnd_b = form.find('button[id=randomizeBtn]');
  rnd_b.click(
    function() {
      let b = $(this);
      b.prop('disabled', true);
      b.addClass('loading');
      let body = $(document.body);
      body.addClass('loading');
      function restoreButton() {
        b.prop('disabled', false);
        b.removeClass('loading');
        body.removeClass('loading');
      };

      let playlist_data = getPlaylistTrackData().concat(getScratchpadTrackData());
      playlist_data = removePlaceholdersFromTracks(playlist_data);
      let track_ids = [];
      let track_lengths = [];
      let bpms = [];
      let genres = [];
      for (let i = 0; i < playlist_data.length; i++) {
        let track = playlist_data[i];
        track_ids.push(track.trackId);
        track_lengths.push(Math.round(track.length / 1000));
        bpms.push(track.bpm);
        let genre = 0;
        if (track.genre.by_user != 0) {
          genre = track.genre.by_user;
        }
        else if (track.genre.by_others.length > 0) {
          genre = track.genre.by_others[0];
        }
        genres.push(genre);
      }
      let settings = getRandomizeByBpmSettings();
      let data = { trackIdList: track_ids
                 , trackLengthList: track_lengths
                 , trackBpmList: bpms
                 , trackGenreList: genres
                 , bpmRangeList: settings.bpmRangeList
                 , bpmDifferenceList: settings.bpmDifferenceList
                 , danceLengthRange: settings.danceLengthRange
                 , danceSlotSameGenre:
                     form.find('input[name=dance-slot-has-same-genre]')
                     .prop('checked')
                 };
      callApi( '/api/randomize-by-bpm/'
             , data
             , function(d) {
                 updatePlaylistAfterRandomize( d.trackOrder
                                             , data.bpmRangeList
                                             );
                 restoreButton();
                 clearActionInputs();
               }
             , function fail(msg) {
                 alert('ERROR: <?= LNG_ERR_FAILED_TO_RANDOMIZE ?>');
                 restoreButton();
                 clearActionInputs();
               }
             );
      return false;
    }
  );

  // BPM differences
  function buildBpmDiffSlider(tr) {
    var printValues =
      function(v1, v2) { tr.find('td.label > span').text(v1 + ' - ' + v2); };
    tr.find('td.bpm-difference-controller > div').each(
      function() {
        if ($(this).children().length > 0) {
          $(this).empty();
        }
        $(this).slider(
          { range: true
          , min: 0
          , max: 255
          , values: [25, 50]
          , slide: function(event, ui) {
              printValues(ui.values[0], ui.values[1]);
            }
          }
        );
        printValues( $(this).slider('values', 0)
                   , $(this).slider('values', 1)
                   );
      }
    );
  }
  action_area.find('table.bpm-range-area tr.difference').each(
    function() { buildBpmDiffSlider($(this)); }
  );

  // BPM ranges and buttons
  function buildBpmRangeSlider(tr) {
    var printValues =
      function(v1, v2) { tr.find('td.label > span').text(v1 + ' - ' + v2); };
    tr.find('td.bpm-range-controller > div').each(
      function() {
        if ($(this).children().length > 0) {
          $(this).empty();
        }
        $(this).slider(
          { range: true
          , min: 0
          , max: 255
          , values: [0, 255]
          , slide: function(event, ui) {
              printValues(ui.values[0], ui.values[1]);
            }
          }
        );
        printValues( $(this).slider('values', 0)
                   , $(this).slider('values', 1)
                   );
      }
    );
  }

  function setupBpmRangeButtons(range_tr) {
    var base_range_tr = range_tr.clone();
    var diff_tr = range_tr.next().length > 0 ? range_tr.next() : range_tr.prev();
    var base_diff_tr = diff_tr.clone();

    // Add button
    var btn = range_tr.find('button.add');
    btn.click(
      function() {
        var new_range_tr = base_range_tr.clone();
        var new_diff_tr = base_diff_tr.clone();
        buildBpmRangeSlider(new_range_tr);
        buildBpmDiffSlider(new_diff_tr);
        range_tr.after(new_diff_tr);
        new_diff_tr.after(new_range_tr);
        setupBpmRangeButtons(new_range_tr);
        updateBpmRangeTrackCounters();
        enableRemoveButtons();
      }
    );

    // Remove button
    range_tr.find('button.remove').each(
      function() {
        $(this).click(
          function() {
            var range_tr = $(this).parent().parent();
            var diff_tr = range_tr.next().length > 0
                            ? range_tr.next() : range_tr.prev();
            range_tr.remove();
            diff_tr.remove();
            disableRemoveButtonsIfNeeded();
            updateBpmRangeTrackCounters();
          }
        );
      }
    );
  };
  var enableRemoveButtons = function() {
    action_area.find('table.bpm-range-area button.remove').each(
      function() {
        $(this).prop('disabled', false);
      }
    );
  };
  var disableRemoveButtonsIfNeeded = function() {
    var table = action_area.find('table.bpm-range-area');
    var num_ranges = table.find('tr.range').length;
    if (num_ranges <= 2) {
      table.find('button.remove').each(
        function() {
          $(this).prop('disabled', true);
        }
      );
    }
  };
  function updateBpmRangeTrackCounters() {
    action_area.find('table.bpm-range-area tr > td.track > span').each(
      function(i) {
        $(this).text(i+1);
      }
    );
  };
  action_area.find('table.bpm-range-area tr.range').each(
    function() {
      var tr = $(this);
      buildBpmRangeSlider(tr);
      setupBpmRangeButtons(tr);
      updateBpmRangeTrackCounters();
    }
  );

  function buildDanceLengthRangeSlider(tr) {
    function printValues(v1, v2) {
      let t1 = formatTrackLength(v1*1000);
      let t2 = formatTrackLength(v2*1000);
      tr.find('td.label > span').text(t1 + ' - ' + t2);
    }
    tr.find('td.dance-length-range-controller > div').each(
      function() {
        if ($(this).children().length > 0) {
          $(this).empty();
        }
        $(this).slider(
          { range: true
          , min: 0
          , max: 30*60
          , values: [4*60, 8*60]
          , slide: function(event, ui) {
              printValues(ui.values[0], ui.values[1]);
            }
          }
        );
        printValues( $(this).slider('values', 0)
                   , $(this).slider('values', 1)
                   );
      }
    );
  };
  action_area.find('table.dance-length-range-area tr.range').each(
    function() {
      let tr = $(this);
      buildDanceLengthRangeSlider(tr);
    }
  );
  disableRemoveButtonsIfNeeded();
}

function getRandomizeByBpmSettings() {
  let form = getPlaylistForm();
  let data = { bpmRangeList: []
             , bpmDifferenceList: []
             };
  let action_area = $('div[name=randomize-by-bpm]');

  action_area.find('table.bpm-range-area tr').each(
    function() {
      let tr = $(this);
      tr.find('td.bpm-range-controller > div').each(
        function() {
          v1 = $(this).slider('values', 0);
          v2 = $(this).slider('values', 1);
          data.bpmRangeList.push([v1, v2]);
        }
      );
      tr.find('.bpm-difference-controller > div').each(
        function() {
          v1 = $(this).slider('values', 0);
          v2 = $(this).slider('values', 1);
          let tr = $(this).closest('tr');
          let direction =
            parseInt(tr.find('select[name=direction] :selected').val());
          if (direction < 0) {
            let tmp = v1;
            v1 = -v2;
            v2 = -tmp;
          }
          data.bpmDifferenceList.push([v1, v2]);
        }
      );
    }
  );

  let len_slider =
    action_area.find(
      'table.dance-length-range-area .dance-length-range-controller > div'
    ).first();
  data.danceLengthRange = [ len_slider.slider('values', 0)
                          , len_slider.slider('values', 1)
                          ];

  return data;
}

function updatePlaylistAfterRandomize(track_order, bpm_ranges, unused_tracks) {
  let playlist = getPlaylistTrackData().concat(getScratchpadTrackData());
  playlist = removePlaceholdersFromTracks(playlist);
  let new_playlist = [];
  for (let i = 0; i < track_order.length; i++) {
    let tid = track_order[i];
    if (tid.length > 0) {
      let track = getTrackWithMatchingId(playlist, tid);
      if (track == null) {
        console.log('failed to find track with ID: ' + tid);
        continue;
      }
      new_playlist.push(track);
    }
  }

  let new_scratchpad = [];
  for (let i = 0; i < playlist.length; i++) {
    let track = playlist[i]
    let tid = track.trackId;
    if (getTrackWithMatchingId(new_playlist, tid) === null) {
      new_scratchpad.push(track);
    }
  }

  replaceTracks(getPlaylistTable(), new_playlist);
  renderPlaylist();
  if (new_scratchpad.length > 0) {
    replaceTracks(getScratchpadTable(), new_scratchpad);
    renderScratchpad();
    showScratchpad();
  }
  else {
    clearTable(getScratchpadTable());
    renderScratchpad();
  }
  indicateStateUpdate();
}
