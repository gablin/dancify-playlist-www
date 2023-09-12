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
      let b_old_text = b.text();
      b.prop('disabled', true);
      b.addClass('loading');
      let body = $(document.body);
      body.addClass('loading');
      let count_handle = null;
      function restoreButton() {
        clearInterval(count_handle);
        b.text(b_old_text);
        b.prop('disabled', false);
        b.removeClass('loading');
        body.removeClass('loading');
      };

      let countdown = 60;
      count_handle = setInterval(
                       function() {
                         countdown -= 1;
                         b.text(countdown);
                       }
                     , 1000
                     );

      let playlist_data = getTrackData(getPlaylistTable())
                            .concat(getTrackData(getLocalScratchpadTable()));
      playlist_data = removePlaceholdersFromTracks(playlist_data);
      let track_ids = [];
      let track_lengths = [];
      let bpms = [];
      let genres = [];
      let energies = [];
      for (let i = 0; i < playlist_data.length; i++) {
        let track = playlist_data[i];
        track_ids.push(track.trackId);
        track_lengths.push(Math.round(track.length / 1000));
        bpms.push(track.bpm.custom >= 0 ? track.bpm.custom : track.bpm.spotify);
        let genre = 0;
        if (track.genre.by_user != 0) {
          genre = track.genre.by_user;
        }
        else if (track.genre.by_others.length > 0) {
          genre = track.genre.by_others[0];
        }
        genres.push(genre);
        energies.push(Math.round(track.energy * 100));
      }
      let settings = getRandomizeByBpmSettings();
      let data = { trackIdList: track_ids
                 , trackLengthList: track_lengths
                 , trackBpmList: bpms
                 , trackGenreList: genres
                 , trackEnergyList: energies
                 , bpmRangeList: settings.bpmRangeList
                 , bpmDifferenceList: settings.bpmDifferenceList
                 , energyDifferenceList: settings.energyDifferenceList
                 , danceLengthRange: settings.danceLengthRange
                 , danceSlotSameGenre:
                     form.find('input[name=dance-slot-has-same-genre]')
                     .is(':checked')
                 };
      callApi( '/api/randomize-by-bpm/'
             , data
             , function(d) {
                 updatePlaylistAfterRandomize(d.trackOrder);
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

  function setSliderActivation(tr, enabled) {
    tr.find('td.range > div').each(
      function() {
        $(this).slider('option', { disabled: !enabled });
      }
    );
  }

  // BPM differences
  function buildBpmDiffSlider(tr) {
    function printValues(v1, v2) {
      tr.find('td.label span').text(v1 + ' - ' + v2);
    }

    tr.find('td.range.bpm-diff > div').each(
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
  function setupBpmDiffHandling(tr) {
    buildBpmDiffSlider(tr);

    tr.find('input[name=bpm-constraint]').each(
      function() {
        let input = $(this);
        let tr = input.closest('tr');

        function update() {
          let is_checked = input.prop('checked');
          setSliderActivation(tr, is_checked);
          tr.find('select').prop('disabled', !is_checked);
          if (is_checked) {
            tr.removeClass('disabled');
          }
          else {
            tr.addClass('disabled');
          }
        }

        $(this).click(update);
        update();
      }
    );
  }
  action_area.find('table.bpm-range-area tr.bpm-difference').each(
    function () { setupBpmDiffHandling($(this)); }
  )

  // Energy differences
  function buildEnergyDiffSlider(tr) {
    function printValues(v1, v2) {
      tr.find('td.label span').text(v1 + '% - ' + v2 + '%');
    }

    tr.find('td.range.energy-diff > div').each(
      function() {
        if ($(this).children().length > 0) {
          $(this).empty();
        }
        $(this).slider(
          { range: true
          , min: 0
          , max: 100
          , values: [20, 50]
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
  function setupEnergyDiffHandling(tr) {
    buildEnergyDiffSlider(tr);

    tr.find('input[name=energy-constraint]').each(
      function() {
        let input = $(this);
        let tr = input.closest('tr');

        function update() {
          let is_checked = input.prop('checked');
          setSliderActivation(tr, is_checked);
          tr.find('select').prop('disabled', !is_checked);
          if (is_checked) {
            tr.removeClass('disabled');
          }
          else {
            tr.addClass('disabled');
          }
        }

        $(this).click(update);
        update();
      }
    );
  }
  action_area.find('table.bpm-range-area tr.energy-difference').each(
    function() { setupEnergyDiffHandling($(this)); }
  );

  // BPM ranges and buttons
  function buildBpmRangeSlider(tr) {
    function printValues(v1, v2) {
      tr.find('td.label span').text(v1 + ' - ' + v2);
    }

    tr.find('td.range.bpm > div').each(
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
    let base_range_tr = range_tr.clone();
    let bpm_diff_tr = range_tr.next().length > 0 ? range_tr.next()
                                                 : range_tr.prev().prev();
    let energy_diff_tr = range_tr.next().length > 0 ? range_tr.next().next()
                                                    : range_tr.prev();
    let base_bpm_diff_tr = bpm_diff_tr.clone();
    let base_energy_diff_tr = energy_diff_tr.clone();

    // Add button
    let btn = range_tr.find('button.add');
    btn.click(
      function() {
        let new_range_tr = base_range_tr.clone();
        let new_bpm_diff_tr = base_bpm_diff_tr.clone();
        let new_energy_diff_tr = base_energy_diff_tr.clone();
        buildBpmRangeSlider(new_range_tr);
        setupBpmDiffHandling(new_bpm_diff_tr);
        setupEnergyDiffHandling(new_energy_diff_tr);
        range_tr.after(new_bpm_diff_tr);
        new_bpm_diff_tr.after(new_energy_diff_tr);
        new_energy_diff_tr.after(new_range_tr);
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
            let range_tr = $(this).parent().parent();
            let bpm_diff_tr = range_tr.next().length > 0
                              ? range_tr.next() : range_tr.prev().prev();
            let energy_diff_tr = range_tr.next().length > 0
                                 ? range_tr.next().next() : range_tr.prev();
            range_tr.remove();
            bpm_diff_tr.remove();
            energy_diff_tr.remove();
            disableRemoveButtonsIfNeeded();
            updateBpmRangeTrackCounters();
          }
        );
      }
    );
  }

  function enableRemoveButtons() {
    action_area.find('table.bpm-range-area button.remove').each(
      function() {
        $(this).prop('disabled', false);
      }
    );
  }

  function disableRemoveButtonsIfNeeded() {
    let table = action_area.find('table.bpm-range-area');
    let num_ranges = table.find('tr.range').length;
    if (num_ranges <= 2) {
      table.find('button.remove').each(
        function() {
          $(this).prop('disabled', true);
        }
      );
    }
  }

  function updateBpmRangeTrackCounters() {
    action_area.find('table.bpm-range-area tr > td.track > span').each(
      function(i) {
        $(this).text(i+1);
      }
    );
  }

  action_area.find('table.bpm-range-area tr.range').each(
    function() {
      let tr = $(this);
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
    tr.find('td.range.dance-length > div').each(
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
             , energyDifferenceList: []
             };
  let action_area = $('div[name=randomize-by-bpm]');

  function getDiffValues(tr) {
    let range = [];
    tr.find('.range > div').each(
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
        range = [v1, v2];
      }
    );

    if (tr.find('input[type=checkbox]').prop('checked')) {
      return range;
    }
    return [0, 0];
  }

  action_area.find('table.bpm-range-area tr.range').each(
    function() {
      let tr = $(this);
      tr.find('td.range.bpm > div').each(
        function() {
          v1 = $(this).slider('values', 0);
          v2 = $(this).slider('values', 1);
          data.bpmRangeList.push([v1, v2]);
        }
      );
    }
  );
  action_area.find('table.bpm-range-area tr.bpm-difference').each(
    function() {
      let tr = $(this);
      let diff = getDiffValues(tr);
      data.bpmDifferenceList.push(diff);
    }
  );
  action_area.find('table.bpm-range-area tr.energy-difference').each(
    function() {
      let tr = $(this);
      let diff = getDiffValues(tr);
      data.energyDifferenceList.push(diff);
    }
  );

  let len_slider =
    action_area.find(
      'table.dance-length-range-area .range.dance-length > div'
    ).first();
  data.danceLengthRange = [ len_slider.slider('values', 0)
                          , len_slider.slider('values', 1)
                          ];

  return data;
}

function updatePlaylistAfterRandomize(track_order) {
  let p_table = getPlaylistTable();
  let s_table = getLocalScratchpadTable();

  let playlist = getTrackData(p_table).concat(getTrackData(s_table));
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

  let new_local_scratchpad = [];
  for (let i = 0; i < playlist.length; i++) {
    let track = playlist[i]
    let tid = track.trackId;
    if (getTrackWithMatchingId(new_playlist, tid) === null) {
      new_local_scratchpad.push(track);
    }
  }

  replaceTracks(p_table, new_playlist);
  renderTable(p_table);
  if (new_local_scratchpad.length > 0) {
    replaceTracks(s_table, new_local_scratchpad);
    renderTable(s_table);
    showScratchpad(s_table);
  }
  else {
    clearTable(s_table);
    renderTable(s_table);
  }
  indicateStateUpdate();
}
