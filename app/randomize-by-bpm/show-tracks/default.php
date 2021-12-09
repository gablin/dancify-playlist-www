<?php
require '../../../autoload.php';
require '../functions.php';

ensureSession();
$session = getSession();
$api = createWebApi($session);

connectDb();

beginPage();
createMenu( mkMenuItemShowPlaylists($api)
          , mkMenuItemShowPlaylistTracks($api)
          );
beginContent();
try {
$playlist_id = fromGET('playlist_id');
$playlist_info = loadPlaylistInfo($api, $playlist_id);
$tracks = [];
foreach (loadPlaylistTracks($api, $playlist_id) as $t) {
  $tracks[] = $t->track;
}
$audio_feats = loadTrackAudioFeatures($api, $tracks);
?>

<form name="playlist">

<div>
  <button id="randomizeBtn"><?php echo(LNG_BTN_RANDOMIZE); ?></button>
</div>
<div id="new-playlist-area" style="display: none;">
  <div class="input" style="margin-top: 2em;">
    <?php echo(LNG_INSTR_ENTER_NAME_OF_NEW_PLAYLIST); ?>:
    <input type="text" name="new_playlist_name"></input>
  </div>
  <div>
    <button id="saveBtn">
      <?php echo(LNG_BTN_SAVE_AS_NEW_PLAYLIST); ?>
    </button>
  </div>
</div>

<table class="bpm-range-area">
  <tbody>
    <tr class="range">
      <td class="track">
        <?php echo(LNG_DESC_BPM_RANGE_TRACK) ?> <span>1</span>
      </td>
      <td class="label">
        <?php echo(LNG_DESC_BPM) ?>: <span></span>
      </td>
      <td class="range-controller">
        <div></div>
      </td>
      <td>
        <button class="add lowlight">+</button>
        <button class="remove lowlight">-</button>
      </td>
    </tr>
    <tr class="distance">
      <td></td>
      <td class="label">
        <?php echo(LNG_DESC_MIN_BPM_DISTANCE) ?>: <span></span>
      </td>
      <td class="dist-controller">
        <div></div>
      </td>
      <td></td>
    </tr>
    <tr class="range">
      <td class="track">
        <?php echo(LNG_DESC_BPM_RANGE_TRACK) ?> <span>2</span>
      </td>
      <td class="label">
        <?php echo(LNG_DESC_BPM) ?>: <span></span>
      </td>
      <td class="range-controller">
        <div></div>
      </td>
      <td>
        <button class="add lowlight">+</button>
        <button class="remove lowlight">-</button>
      </td>
    </tr>
  <tbody>
</table>
<label>
  <input type="checkbox" id="chkboxDanceSlotSameCategory"
    name="dance-slot-has-same-category" value="true" />
  <span class="checkmark"></span>
  <?php echo(LNG_DESC_DANCE_SLOT_SAME_CATEGORY) ?>
</label>

<table id="playlist" class="tracks">
  <thead>
    <tr>
      <th></th>
      <th class="bpm"><?php echo(LNG_HEAD_BPM); ?></th>
      <th class="category"><?php echo(LNG_HEAD_CATEGORY_SHORT); ?></th>
      <th><?php echo(LNG_HEAD_TITLE); ?></th>
    </tr>
  </thead>
  <tbody>
    <?php
    for ($i = 0; $i < count($tracks); $i++) {
      $t = $tracks[$i];

      // Get BPM
      $bpm = (int) $audio_feats[$i]->tempo;
      $tid = $t->id;
      $res = queryDb("SELECT bpm FROM bpm WHERE song = '$tid'");
      if ($res->num_rows == 1) {
        $bpm = $res->fetch_assoc()['bpm'];
      }

      // Get category
      $category = '';
      $cid = $session->getClientId();
      $res = queryDb( "SELECT category FROM category " .
                      "WHERE song = '$tid' AND user = '$cid'"
                    );
      if ($res->num_rows == 1) {
        $category = $res->fetch_assoc()['category'];
      }

      $artists = formatArtists($t);
      $title = $artists . " - " . $t->name;
      $length = $t->duration_ms;
      $preview_url = $t->preview_url;
      ?>
      <tr class="track">
        <input type="hidden" name="track_id" value="<?= $tid ?>" />
        <input type="hidden" name="preview_url" value="<?= $preview_url ?>" />
        <td class="index"><?php echo($i+1); ?></td>
        <td class="bpm">
          <input type="text" name="bpm" class="bpm" value="<?= $bpm ?>" />
        </td>
        <td class="category">
          <input type="text" name="category" class="category"
                 value="<?= $category ?>" />
        </td>
        <td class="title">
          <?php echo($title); ?>
        </td>
      </tr>
      <?php
    }
    ?>
  </tbody>
</table>

</form>

<script type="text/javascript">
$(document).ready(initForm);
var audio = $('<audio />');

function initForm() {
  var form = $('form[name=playlist]');
  var table = $('table.tracks');

  setupForm(form);
  setupBpmUpdate(form);
  setupCategoryUpdate(form);
  setupFormElements(form);
  setupTableElements(table);
}

function setupForm(form) {
  // Disable submission
  form.submit(function() { return false; });
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

function setupFormElements(form) {
  // Save-playlist button
  var save_b = form.find('button[id=saveBtn]');
  save_b.click(
    function() {
      var b = $(this);
      b.prop('disabled', true);
      b.addClass('loading');
      var restoreButton = function() {
        b.prop('disabled', false);
        b.removeClass('loading');
      };

      // Check new playlist name
      var name = form.find('input[name=new_playlist_name]').val().trim();
      if (name.length == 0) {
        alert('<?= LNG_INSTR_PLEASE_ENTER_NAME ?>');
        restoreButton();
        return false;
      }

      // Save new playlist
      var playlist_data = getPlaylistData(form, true, true, false);
      var data = { trackIdList: playlist_data.trackIdList
                 , leftoverTrackIdList: playlist_data.leftoverTrackIdList
                 , playlistName: name
                 , publicPlaylist: <?= $playlist_info->public ? 'true' : 'false' ?>
                 };
      $.post('/api/save-randomized-playlist/', { data: JSON.stringify(data) })
        .done(
          function(res) {
            json = JSON.parse(res);
            if (json.status == 'OK') {
              alert('<?= LNG_DESC_NEW_PLAYLIST_ADDED ?>');
              window.location.href =
                '/app/randomize-by-bpm/show-tracks/?playlist_id=' +
                json.newPlaylistId;
            }
            else if (json.status == 'FAILED') {
              alert('ERROR: ' + json.msg);
            }
            restoreButton();
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
              updatePlaylist(form, json.trackOrder, data.bpmRangeList);
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

function setupTableElements(table) {
  // Play preview when clicking on row corresponding to track
  table.find('tbody tr.track').each(
    function() {
      $(this).click(
        function() {
          audio.attr('src', ''); // Stop playing
          $(this).siblings().removeClass('playing');
          $(this).siblings().removeClass('cannot-play');
          if ($(this).hasClass('playing')) {
            $(this).removeClass('playing');
            return;
          }

          var url = $(this).find('input[name=preview_url]').val();
          if (url.length > 0) {
            $(this).addClass('playing');
            audio.attr('src', url);
            audio.get(0).play();
          }
          else {
            $(this).addClass('cannot-play');
          }
        }
      );
    }
  );
}

function getPlaylistData( form
                        , include_unfilled_slots = false
                        , include_leftover = false
                        , report_errors = true
                        )
{
  var data = { trackIdList: []
             , leftoverTrackIdList: []
             , trackBpmList: []
             , trackCategoryList: []
             , bpmRangeList: []
             , minBpmDistanceList: []
             };
  var has_error = false;
  var in_leftover_section = false;

  // Get BPM range and distance info
  form.find('table.bpm-range-area tr').each(
    function() {
      var tr = $(this);
      tr.find('td.range-controller > div').each(
        function() {
          v1 = $(this).slider('values', 0);
          v2 = $(this).slider('values', 1);
          data.bpmRangeList.push([v1, v2]);
        }
      );
      tr.find('td.dist-controller > div').each(
        function() {
          v = $(this).slider('values', 0);
          data.minBpmDistanceList.push(v);
        }
      );
    }
  );

  // Get track info
  form.find('table.tracks tr').each(
    function() {
      var tr = $(this);
      if ($(this).find('td[class=leftover]').length > 0 && include_leftover) {
        in_leftover_section = true;
        return;
      }
      else if ($(this).find('input[name=track_id]').length == 0) {
        return;
      }

      var tid = tr.find('input[name=track_id]').val();
      if (tid.length > 0) {
        var bpm_input = tr.find('input[name=bpm]');
        var bpm = bpm_input.val().trim();
        if (!checkBpmInput(bpm, report_errors)) {
          bpm_input.addClass('invalid');
          has_error = true;
          return;
        }
        var category_input = tr.find('input[name=category]');
        var category = category_input.val().trim();
      }
      else if (!include_unfilled_slots) {
        return;
      }
      if (!in_leftover_section) {
        data.trackIdList.push(tid);
        data.trackBpmList.push(parseInt(bpm));
        data.trackCategoryList.push(category);
      }
      else {
        data.leftoverTrackIdList.push(tid);
      }
    }
  );

  return !has_error ? data : null;
}

function updatePlaylist(form, track_order, bpm_ranges) {
  // Save existing track IDs, track names, and BPMs
  var track_ids = [];
  var track_titles = [];
  var track_bpms = [];
  var track_categories = [];
  form.find('tr').each(
    function() {
      var tr = $(this);
      var tid_input = tr.find('input[name=track_id]');
      var title_e = tr.find('td[class=title]');
      var bpm_input = tr.find('input[name=bpm]');
      var category_input = tr.find('input[name=category]');
      if (tid_input.length == 0 || title_e.length == 0 || bpm_input.length == 0) {
        return;
      }
      var tid = tid_input.val().trim();
      var title = title_e.text().trim();
      var bpm = bpm_input.val().trim();
      var category = category_input.val().trim();
      if (tid.length == 0 || title.length == 0 || !checkBpmInput(bpm, false)) {
        return;
      }
      track_ids.push(tid);
      track_titles.push(title);
      track_bpms.push(bpm);
      track_categories.push(category);
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
    function(playlist_index, track_id, title, bpm, category) {
      var new_tr = tr_template.clone(true, true);
      new_tr.find('td[class=index]').text(playlist_index);
      new_tr.find('td[class=title]').text(title);
      new_tr.find('input[name=track_id]').prop('value', track_id);
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
                                   );
      new_tr.addClass('unfilled-slot');
      new_tr.find('input[name=track_id]').prop('value', '');
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
</script>

<?php
}
catch (Exception $e) {
  showError($e->getMessage());
}
endContent();
endPage();
updateTokens($session);
?>
