<?php
require '../autoload.php';
?>

function setupRandomizeByBpm(form, table) {
  setupBpmUpdate(form);
  setupCategoryUpdate(form);
  setupFormElementsForRandomizeByBpm(form);
}

function setupBpmUpdate(form) {
  var bpm_inputs = form.find('input[name=bpm]');
  bpm_inputs.each(
    function() {
      $(this).click(
        function(e) {
          // Prevent playing of track preview
          e.stopPropagation();
          return false;
        }
      );
      $(this).change(
        function() {
          var bpm_input = $(this);

          // Find corresponding track ID
          var tid_input = bpm_input.parent().parent().find('input[name=track_id]');
          if (tid_input.length == 0) {
            console.log('could not find track ID');
            return;
          }
          var tid = tid_input.val().trim();
          if (tid.length == 0) {
            return;
          }

          // Check BPM value
          var bpm = bpm_input.val().trim();
          if (!checkBpmInput(bpm)) {
            bpm_input.addClass('invalid');
            return;
          }
          bpm_input.removeClass('invalid');

          // Save new BPM to database
          var data = { trackId: tid, bpm: bpm };
          $.post('/api/update-bpm/', { data: JSON.stringify(data) })
            .done(
              function(res) {
                json = JSON.parse(res);
                if (json.status == 'OK') {
                  // Do nothing
                }
                else if (json.status == 'FAILED') {
                  alert('ERROR: ' + json.msg);
                }
              }
            )
            .fail(
              function(xhr, status, error) {
                alert('ERROR: ' + error);
              }
            );
        }
      );
    }
  );
}

function setupCategoryUpdate(form) {
  var category_inputs = form.find('input[name=category]');
  category_inputs.each(
    function() {
      $(this).click(
        function(e) {
          // Prevent playing of track preview
          e.stopPropagation();
          return false;
        }
      );
      $(this).change(
        function() {
          var category_input = $(this);

          // Find corresponding track ID
          var tid_input =
            category_input.parent().parent().find('input[name=track_id]');
          if (tid_input.length == 0) {
            console.log('could not find track ID');
            return;
          }
          var tid = tid_input.val().trim();
          if (tid.length == 0) {
            return;
          }

          var category = category_input.val().trim();

          // Save new category to database
          var data = { trackId: tid, category: category };
          $.post('/api/update-category/', { data: JSON.stringify(data) })
            .done(
              function(res) {
                json = JSON.parse(res);
                if (json.status == 'OK') {
                  // Do nothing
                }
                else if (json.status == 'FAILED') {
                  alert('ERROR: ' + json.msg);
                }
              }
            )
            .fail(
              function(xhr, status, error) {
                alert('ERROR: ' + error);
              }
            );
        }
      );
    }
  );
}

function setupFormElementsForRandomizeByBpm(form) {
  // Randomize button
  var rnd_b = form.find('button[id=randomizeBtn]');
  rnd_b.click(
    function() {
      var b = $(this);
      b.prop('disabled', true);
      b.addClass('loading');
      var restoreButton = function() {
        b.prop('disabled', false);
        b.removeClass('loading');
      };

      // Randomize playlist
      var data = getPlaylistData(form);
      if (data == null) {
        return;
      }

      delete data.leftoverTrackIdList;
      data.danceSlotSameCategory =
        form.find('input[id=chkboxDanceSlotSameCategory]').prop('checked');
      $.post('/api/randomize-by-bpm/', { data: JSON.stringify(data) })
        .done(
          function(res) {
            json = JSON.parse(res);
            if (json.status == 'OK') {
              updatePlaylistAfterRandomize( form
                                          , json.trackOrder
                                          , data.bpmRangeList
                                          );
            }
            else if (json.status == 'FAILED') {
              alert('ERROR: ' + json.msg);
            }
            restoreButton();
            b.addClass('lowlight');
            form.find('div[id=new-playlist-area]').css('display', 'block');
          }
        )
        .fail(
          function(xhr, status, error) {
            alert('ERROR: ' + error);
            restoreButton();
          }
        );

      return false;
    }
  );

  // BPM distance
  var buildBpmDistSlider = function(tr) {
    var printValue =
      function(v1) { tr.find('td.label > span').text(v1); };
    tr.find('td.dist-controller > div').each(
      function() {
        if ($(this).children().length > 0) {
          $(this).empty();
        }
        $(this).slider(
          { min: -128
          , max: 128
          , values: [0]
          , slide: function(event, ui) {
              printValue(ui.values[0]);
            }
          }
        );
        printValue($(this).slider('values', 0));
      }
    );
  };
  $('table.bpm-range-area tr.distance').each(
    function() { buildBpmDistSlider($(this)); }
  );

  // BPM ranges and buttons
  var buildBpmRangeSlider = function(tr) {
    var printValues =
      function(v1, v2) { tr.find('td.label > span').text(v1 + ' - ' + v2); };
    tr.find('td.range-controller > div').each(
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
  };
  var setupBpmRangeButtons = function(range_tr) {
    var base_range_tr = range_tr.clone();
    var dist_tr = range_tr.next().length > 0 ? range_tr.next() : range_tr.prev();
    var base_dist_tr = dist_tr.clone();

    // Add button
    var btn = range_tr.find('button.add');
    btn.click(
      function() {
        var new_range_tr = base_range_tr.clone();
        var new_dist_tr = base_dist_tr.clone();
        buildBpmRangeSlider(new_range_tr);
        buildBpmDistSlider(new_dist_tr);
        range_tr.after(new_dist_tr);
        new_dist_tr.after(new_range_tr);
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
            var dist_tr = range_tr.next().length > 0
                            ? range_tr.next() : range_tr.prev();
            range_tr.remove();
            dist_tr.remove();
            disableRemoveButtonsIfNeeded();
            updateBpmRangeTrackCounters();
          }
        );
      }
    );
  };
  var enableRemoveButtons = function() {
    $('table.bpm-range-area button.remove').each(
      function() {
        $(this).prop('disabled', false);
      }
    );
  };
  var disableRemoveButtonsIfNeeded = function() {
    var table = $('table.bpm-range-area');
    var num_ranges = table.find('tr.range').length;
    if (num_ranges <= 2) {
      table.find('button.remove').each(
        function() {
          $(this).prop('disabled', true);
        }
      );
    }
  };
  var updateBpmRangeTrackCounters = function() {
    $('table.bpm-range-area tr > td.track > span').each(
      function(i) {
        $(this).text(i+1);
      }
    );
  };
  $('table.bpm-range-area tr.range').each(
    function() {
      var tr = $(this);
      buildBpmRangeSlider(tr);
      setupBpmRangeButtons(tr);
      updateBpmRangeTrackCounters();
    }
  );
  disableRemoveButtonsIfNeeded();

  // Checkbox for same category in dance slot
  var chk_b = form.find('input[id=chkboxDanceSlotSameCategory]');
  chk_b.click(
    function() {
      $('table.tracks .category')
      .css('display', $(this).prop('checked') ? 'block' : 'none');
    }
  );
}

function updatePlaylistAfterRandomize(form, track_order, bpm_ranges) {
  // Save existing track IDs, track names, and BPMs
  var track_ids = [];
  var track_titles = [];
  var track_preview_urls = [];
  var track_bpms = [];
  var track_categories = [];
  form.find('tr.track').each(
    function() {
      var tr = $(this);
      var tid = tr.find('input[name=track_id]').val().trim();
      var title = tr.find('td[class=title]').text().trim();
      var preview_url = tr.find('input[name=preview_url]').val().trim();
      var bpm = tr.find('input[name=bpm]').val().trim();
      var category = tr.find('input[name=category]').val().trim();
      track_ids.push(tid);
      track_titles.push(title);
      track_bpms.push(bpm);
      track_categories.push(category);
      track_preview_urls.push(preview_url);
    }
  );

  // Find <tr> template to use when constructing new playlist
  var table = form.find('table[id=playlist]');
  if (table.length == 0) {
    console.log('failed to find table');
    return;
  }
  var tr_template = table.find('tr').filter(
    function (index) {
      return $(this).find('input[name=bpm]').length > 0;
    }
  );
  if (tr_template.length == 0) {
    console.log('failed to find <tr> template');
    return;
  }
  tr_template = $(tr_template[0]).clone(true, true);
  var createNewPlaylistRow =
    function(playlist_index, track_id, title, preview_url, bpm, category) {
      var new_tr = tr_template.clone(true, true);
      new_tr.find('td[class=index]').text(playlist_index);
      new_tr.find('td[class=title]').text(title);
      new_tr.find('input[name=track_id]').prop('value', track_id);
      new_tr.find('input[name=preview_url]').prop('value', preview_url);
      new_tr.find('input[name=bpm]').prop('value', bpm);
      new_tr.find('input[name=category]').prop('value', category);
      return new_tr;
    };

  // Construct new playlist using given track order
  table.find('tr > td').parent().remove();
  var order_index = 0;
  var playlist_index = 1;
  var range_index = 0;
  var num_used_tracks = 0;
  var num_cols = table.find('tr > th').length;
  while (order_index < track_order.length) {
    var tid = track_order[order_index];

    var new_tr = null;
    if (tid.length > 0) {
      // Find track with matching ID
      var i = 0;
      for (; track_ids[i] != tid && i < track_ids.length; i++) {}
      if (i == track_ids.length) {
        console.log('failed to find track with ID: ' + tid);
        continue;
      }
      num_used_tracks++;

      new_tr =
        createNewPlaylistRow( playlist_index
                            , tid
                            , track_titles[i]
                            , track_preview_urls[i]
                            , track_bpms[i]
                            , track_categories[i]
                            );
    }
    else {
      new_tr = createNewPlaylistRow( playlist_index
                                   , ''
                                   , '<?= LNG_DESC_NO_SUITABLE_TRACK_FOR_SLOT ?>'
                                   , ''
                                   , ''
                                   , ''
                                   );
      new_tr.removeClass('track');
      new_tr.addClass('unfilled-slot');
      new_tr.find('input[name=track_id]').remove();
      new_tr.find('input[name=preview_url]').remove();
      new_tr.find('input[name=bpm]').remove();
      var min_bpm = bpm_ranges[range_index][0];
      var max_bpm = bpm_ranges[range_index][1];
      new_tr.find('td[class=bpm]').text(min_bpm + '-' + max_bpm);
      new_tr.find('input[name=category]').remove();
    }
    table.append(new_tr);

    // Add dance slot separator
    if ( playlist_index % bpm_ranges.length == 0 &&
         order_index < track_order.length-1
       )
    {
      table.append(
        $( '<tr class="dance-slot-sep">' +
             '<td colspan="' + num_cols + '"><div /></td>' +
           '</tr>'
         )
      );
    }

    playlist_index++;
    order_index++;
    range_index++;
    if (range_index >= bpm_ranges.length) {
      range_index = 0;
    }
  }

  // Append left-over tracks and mark as such
  if (num_used_tracks < track_ids.length) {
    table.append(
      $( '<tr><td class="leftover" colspan="' + num_cols + '">' +
           '<?= LNG_DESC_TRACKS_NOT_PLACED ?>' +
         '</td></tr>'
       )
    );

    for (var i = 0; i < track_ids.length; i++) {
      var included = false;
      for (var j = 0; j < track_order.length; j++) {
        if (track_ids[i] == track_order[j]) {
          included = true;
          break;
        }
      }
      if (!included) {
        var new_tr =
          createNewPlaylistRow('', track_ids[i], track_titles[i], track_bpms[i]);
        table.append(new_tr);
      }
    }
  }
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
