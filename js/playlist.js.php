<?php
require '../autoload.php';
?>

function setupSaveButton(form, table, is_playlist_public) {
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
      var playlist_data = getPlaylistData(form, table, true, true, false);
      var data = { trackIdList: playlist_data.trackIdList
                 , leftoverTrackIdList: playlist_data.leftoverTrackIdList
                 , playlistName: name
                 , publicPlaylist: is_playlist_public
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
}

function getPlaylistData( form
                        , table
                        , include_unfilled_slots = false
                        , include_leftover = false
                        , report_errors = true
                        )
{
  var data = { trackIdList: []
             , leftoverTrackIdList: []
             , trackBpmList: []
             , trackCategoryList: []
             };
  var has_error = false;
  var in_leftover_section = false;

  // Get track info
  table.find('tr').each(
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
