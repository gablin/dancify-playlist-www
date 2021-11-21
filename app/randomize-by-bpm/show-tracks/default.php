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

<table id="playlist" class="tracks">
  <tr>
    <th></th>
    <th class="bpm"><?php echo(LNG_HEAD_BPM); ?></th>
    <th><?php echo(LNG_HEAD_TITLE); ?></th>
  </tr>
  <?php
  for ($i = 0; $i < count($tracks); $i++) {
    $t = $tracks[$i];
    $bpm = (int) $audio_feats[$i]->tempo;
    $tid = $t->id;
    $res = queryDb("SELECT bpm FROM bpm WHERE song = '$tid'");
    if ($res->num_rows == 1) {
      $bpm = $res->fetch_assoc()['bpm'];
    }
    $artists = formatArtists($t);
    $title = $artists . " - " . $t->name;
    $length = $t->duration_ms;
    ?>
    <tr>
      <input type="hidden" name="track_id" value="<?= $tid ?>" />
      <td class="index"><?php echo($i+1); ?></td>
      <td class="bpm">
        <input type="text" name="bpm" class="bpm" value="<?= $bpm ?>" />
      </td>
      <td class="title">
        <?php echo($title); ?>
      </td>
    </tr>
    <?php
  }
  ?>
</table>

</form>

<script type="text/javascript">
$(document).ready(initForm);

function initForm() {
  var form = $('form[name=playlist]');

  setupForm(form);
  setupBpmUpdate(form);
  setupButtons(form);
}

function setupForm(form) {
  // Disable submission
  form.submit(function() { return false; });
}

function setupBpmUpdate(form) {
  var bpm_inputs = form.find('input[name=bpm]');
  bpm_inputs.each(
    function() {
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
          var bpm = bpm_input.val();
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

function setupButtons(form) {
  // Add-requirement button
  var add_req_b = form.find('button[id=addReqBtn]');
  add_req_b.click(
    function() {
      // TODO: implement
      return false;
    }
  );

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
              window.location.href = '/app/randomize-by-bpm/show-tracks/?playlist_id=' + json.newPlaylistId;
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
      $.post('/api/randomize-by-bpm/', { data: JSON.stringify(data) })
        .done(
          function(res) {
            json = JSON.parse(res);
            if (json.status == 'OK') {
              updatePlaylist(form, json.trackOrder, data.rangeList);
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
}

function getPlaylistData( form
                        , include_unfilled_slots = false
                        , include_leftover = false
                        , report_errors = true
                        )
{
  var data = { trackIdList: []
             , leftoverTrackIdList: []
             , bpmList: []
               // TODO: get values below from form
             , rangeList: [[0, 255], [0, 255]]
             , minBpmDistanceList: [40]
             };
  var has_error = false;
  var in_leftover_section = false;

  form.find('tr').each(
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
        var bpm = bpm_input.val();
        if (!checkBpmInput(bpm, report_errors)) {
          bpm_input.addClass('invalid');
          has_error = true;
          return;
        }
      }
      else if (!include_unfilled_slots) {
        return;
      }
      if (!in_leftover_section) {
        data.trackIdList.push(tid);
        data.bpmList.push(parseInt(bpm));
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
  form.find('tr').each(
    function() {
      var tr = $(this);
      var tid_input = tr.find('input[name=track_id]');
      var title_e = tr.find('td[class=title]');
      var bpm_input = tr.find('input[name=bpm]');
      if (tid_input.length == 0 || title_e.length == 0 || bpm_input.length == 0) {
        return;
      }
      var tid = tid_input.val().trim();
      var title = title_e.text().trim();
      var bpm = bpm_input.val().trim();
      if (tid.length == 0 || title.length == 0 || !checkBpmInput(bpm, false)) {
        return;
      }
      track_ids.push(tid);
      track_titles.push(title);
      track_bpms.push(bpm);
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
  var createNewPlaylistRow = function(playlist_index, track_id, title, bpm) {
    var new_tr = tr_template.clone(true, true);
    new_tr.find('td[class=index]').text(playlist_index);
    new_tr.find('td[class=title]').text(title);
    new_tr.find('input[name=track_id]').prop('value', track_id);
    new_tr.find('input[name=bpm]').prop('value', bpm);
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
        createNewPlaylistRow(playlist_index, tid, track_titles[i], track_bpms[i]);
    }
    else {
      new_tr = createNewPlaylistRow( playlist_index
                                   , ''
                                   , '<?= LNG_DESC_NO_SUITABLE_TRACK_FOR_SLOT ?>'
                                   , ''
                                   );
      new_tr.addClass('unfilled-slot');
      new_tr.find('input[name=track_id]').prop('value', '');
      new_tr.find('input[name=bpm]').remove();
      var min_bpm = bpm_ranges[range_index][0];
      var max_bpm = bpm_ranges[range_index][1];
      new_tr.find('td[class=bpm]').text(min_bpm + '-' + max_bpm);
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
           '<?= LNG_DESC_TRACKS_NOT_INCLUDED ?>' +
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
