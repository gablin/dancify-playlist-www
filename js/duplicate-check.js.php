<?php
require '../autoload.php';
?>

function getDuplicateCheckArea() {
  var form = getPlaylistForm();
  return form.find('div[name=duplicate-check]');
}

function getDuplicateCheckResultsArea() {
  return getDuplicateCheckArea().find('.search-results');
}

function setupDuplicateCheck() {
  $('#checkDuplicatesBtn').click(doDuplicateCheck);
}

function onShowDuplicateCheck() {
  let action_area = getDuplicateCheckResultsArea();
  action_area.find('.none-found').hide();
  action_area.find('.duplicates-found').hide();
  action_area.hide();
}

function doDuplicateCheck() {
  var body = $(document.body);
  body.addClass('loading');
  let action_area = getDuplicateCheckResultsArea();
  action_area.find('table tbody tr').remove();
  action_area.show();

  listTrackDuplicates(getTrackDuplicates());
  body.removeClass('loading');
}

function getTrackDuplicates() {
  let playlist_tracks = getPlaylistTrackData();
  let scratchpad_tracks = getScratchpadTrackData();
  let tracks = playlist_tracks.concat(scratchpad_tracks);
  let duplicates = [];
  function getIdx(i) {
    if (i < playlist_tracks.length) {
      return i+1;
    }
    return i+1 - playlist_tracks.length;
  }
  for (let i = 0; i < tracks.length; i++) {
    let found_duplicate = false;
    for (let j = i+1; j < tracks.length; j++) {
      if ( tracks[i].trackId == tracks[j].trackId ||
           formatTrackTitleAsText(tracks[i].artists, tracks[i].name) ==
           formatTrackTitleAsText(tracks[j].artists, tracks[j].name)
         )
      {
        if (!found_duplicate) {
          duplicates.push([getIdx(i), tracks[i]]);
          found_duplicate = true;
        }
        duplicates.push([getIdx(j), tracks[j]]);
      }
    }
  }
  return duplicates;
}

function listTrackDuplicates(idx_track_pairs) {
  let action_area = getDuplicateCheckResultsArea();
  if (idx_track_pairs.length == 0) {
    let none_found_area = action_area.find('.none-found');
    none_found_area.show();
    none_found_area.text('<?= LNG_DESC_NO_DUPLICATES_FOUND ?>');
    return;
  }

  let duplicates_area = action_area.find('.duplicates-found');
  duplicates_area.show();
  let table = duplicates_area.find('table tbody');
  for (let i = 0; i < idx_track_pairs.length; i++) {
    let idx = idx_track_pairs[i][0];
    let t = idx_track_pairs[i][1];
    let tr = $( '<tr class="track">' +
                  '<td class="index">' + idx + '</td>' +
                  '<td>' + formatTrackTitleAsText(t.artists, t.name) + '</td>' +
                '</tr>'
              );
    table.append(tr);
  }
}
