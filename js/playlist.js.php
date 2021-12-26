<?php
require '../autoload.php';
?>

function setupPlaylist() {
  setupTrackPreview();
  setupBpmUpdate();
  setupCategoryUpdate();
}

function setupTrackPreview() {
  var form = PLAYLIST_FORM;
  var table = PLAYLIST_TABLE;

  // Play preview when clicking on row corresponding to track
  table.find('tbody tr.track').each(
    function() {
      $(this).click(
        function() {
          PREVIEW_AUDIO.attr('src', ''); // Stop playing
          $(this).siblings().removeClass('playing');
          $(this).siblings().removeClass('cannot-play');
          if ($(this).hasClass('playing')) {
            $(this).removeClass('playing');
            return;
          }

          var url = $(this).find('input[name=preview_url]').val();
          if (url == null) {
            return;
          }
          if (url.length > 0) {
            $(this).addClass('playing');
            PREVIEW_AUDIO.attr('src', url);
            PREVIEW_AUDIO.get(0).play();
          }
          else {
            $(this).addClass('cannot-play');
          }
        }
      );
    }
  );
}

function setupBpmUpdate() {
  var form = PLAYLIST_FORM;
  var table = PLAYLIST_TABLE;
  var bpm_inputs = table.find('input[name=bpm]');
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

          // Update BPM on all duplicate tracks (if any)
          table.find('input[name=track_id][value=' + tid + ']').each(
            function() {
              $(this).parent().find('input[name=bpm]').val(bpm);
            }
          );
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

function setupCategoryUpdate() {
  var form = PLAYLIST_FORM;
  var table = PLAYLIST_TABLE;
  var category_inputs = table.find('input[name=category]');
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

          // Update category on all duplicate tracks (if any)
          table.find('input[name=track_id][value=' + tid + ']').each(
            function() {
              $(this).parent().find('input[name=category]').val(category);
            }
          );
        }
      );
    }
  );
}

function verifyPlaylistData() {
  // TODO: implement
}

function getPlaylistData()
{
  var form = PLAYLIST_FORM;
  var table = PLAYLIST_TABLE;
  var playlist = [];
  table.find('tr').each(
    function() {
      var tr = $(this);
      if (tr.hasClass('track')) {
        var track_id = tr.find('input[name=track_id]').val().trim();
        var preview_url = tr.find('input[name=preview_url]').val().trim();
        var bpm = tr.find('input[name=bpm]').val().trim();
        var category = tr.find('input[name=category]').val().trim();
        var title = tr.find('td.title').text().trim();
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

function updatePlaylist(new_playlist) {
  var form = PLAYLIST_FORM;
  var table = PLAYLIST_TABLE;
  if (new_playlist === undefined) {
    new_playlist = getPlaylistData();
  }

  // Find <tr> template to use when constructing new playlist
  var tr_templates = table.find('tr.track');
  if (tr_templates.length == 0) {
    console.log('failed to find track <tr> template');
    return;
  }
  tr_template = $(tr_templates[0]).clone(true, true);
  var summary_tr = table.find('tr.summary');

  // Clear playlist
  table.find('tr > td').parent().remove();

  // Construct new playlist
  var total_length = 0;
  var delimiter_i = 0;
  for (var i = 0; i < new_playlist.length; i++) {
    if ( PLAYLIST_TRACK_DELIMITER > 0 &&
         delimiter_i == PLAYLIST_TRACK_DELIMITER
       )
    {
      var cols = tr_template.find('td').length;
      var delimiter_tr = $('<tr class="delimiter"><td colspan="' + cols + '" /><div /></tr>');
      table.append(delimiter_tr);
      delimiter_i = 1;
    }
    else {
      delimiter_i++;
    }

    var track = new_playlist[i];
    var new_tr = tr_template.clone(true, true);
    if ('trackId' in track) {
      new_tr.find('td.index').text(i+1);
      new_tr.find('td.title').text(track.title);
      new_tr.find('input[name=track_id]').prop('value', track.trackId);
      new_tr.find('input[name=preview_url]').prop('value', track.previewUrl);
      new_tr.find('input[name=length_ms]').prop('value', track.length);
      new_tr.find('input[name=bpm]').prop('value', track.bpm);
      new_tr.find('input[name=category]').prop('value', track.category);
      new_tr.find('td.length').text(formatTrackLength(track.length));
      total_length += parseInt(track.length);
    }
    else {
      new_tr.removeClass('track');
      new_tr.addClass('unfilled-slot');
      new_tr.find('td.index').text(i+1);
      new_tr.find('td.title').text(track.title);
      new_tr.find('input[name=track_id]').remove();
      new_tr.find('input[name=preview_url]').remove();
      new_tr.find('input[name=length_ms]').remove();
      bpm_td = new_tr.find('input[name=bpm]').parent();
      bpm_td.find('input').remove();
      bpm_td.text(track.bpm);
      category_td = new_tr.find('input[name=category]').parent();
      category_td.find('input').remove();
      category_td.text(track.category);
      new_tr.find('td.length').text(track.length);
    }
    table.append(new_tr);
  }
  var cols = tr_template.find('td').length;
  summary_tr.find('td.length').text(formatTrackLength(total_length));
  table.append(summary_tr);
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
