<?php
require '../autoload.php';
?>

function getDuplicateCheckArea() {
  let form = getPlaylistForm();
  return form.find('div[name=duplicate-check]');
}

function getDuplicateCheckResultsArea() {
  return getDuplicateCheckArea().find('.search-results');
}

function setupDuplicateCheck() {
  $('#checkDuplicatesLocallyBtn').click(
    function() {
      doDuplicateCheck([getPlaylistTable(), getLocalScratchpadTable()]);
    }
  );
  $('#checkDuplicatesGloballyBtn').click(
    function() {
      doDuplicateCheck([getGlobalScratchpadTable()]);
    }
  );
}

function onShowDuplicateCheck() {
  let action_area = getDuplicateCheckResultsArea();
  action_area.find('.none-found').hide();
  action_area.find('.duplicates-found').hide();
  action_area.hide();
}

function doDuplicateCheck(tables) {
  let body = $(document.body);
  body.addClass('loading');
  let action_area = getDuplicateCheckResultsArea();
  action_area.find('table tbody tr').remove();
  action_area.show();

  listTrackDuplicates(getTrackDuplicates(tables));
  body.removeClass('loading');
}

function getTrackDuplicates(tables) {
  let playlist_tracks = getTrackData(getPlaylistTable());
  let tracks = [];
  tables.forEach(
    table => tracks = tracks.concat(getTrackData(table))
  );
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
  let none_found_area = action_area.find('.none-found');
  let duplicates_area = action_area.find('.duplicates-found');
  if (idx_track_pairs.length == 0) {
    none_found_area.show();
    none_found_area.text('<?= LNG_DESC_NO_DUPLICATES_FOUND ?>');
    duplicates_area.hide();
    return;
  }
  none_found_area.hide();
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
