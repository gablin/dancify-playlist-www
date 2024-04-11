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
  let tracks = [];
  tables.forEach(
    table => tracks = tracks.concat(getTrackData(table))
  );

  let marked_duplicates = new Array(tracks.length).fill(-1);
  for (let i = 0; i < tracks.length; i++) {
    for (let j = i+1; j < tracks.length; j++) {
      if ( marked_duplicates[j] < 0 &&
           ( tracks[i].trackId == tracks[j].trackId ||
             formatTrackTitleAsText(tracks[i].artists, tracks[i].name) ==
             formatTrackTitleAsText(tracks[j].artists, tracks[j].name)
           )
         )
      {
        marked_duplicates[i] = i;
        marked_duplicates[j] = i;
      }
    }
  }

  let duplicates = [];
  for (let i = 0; i < tracks.length; i++) {
    if (marked_duplicates[i] < 0 || marked_duplicates[i] != i) continue;
    let count = 1;
    for (let j = i+1; j < tracks.length; j++) {
      if (marked_duplicates[j] == marked_duplicates[i]) {
        count++;
      }
    }
    duplicates.push([tracks[i], count]);
  }
  return duplicates;
}

function listTrackDuplicates(dup_tracks) {
  let action_area = getDuplicateCheckResultsArea();
  let none_found_area = action_area.find('.none-found');
  let duplicates_area = action_area.find('.duplicates-found');
  if (dup_tracks.length == 0) {
    none_found_area.show();
    none_found_area.text('<?= LNG_DESC_NO_DUPLICATES_FOUND ?>');
    duplicates_area.hide();
    return;
  }
  none_found_area.hide();
  duplicates_area.show();
  let table = duplicates_area.find('table tbody');
  dup_tracks.forEach(
    ([t, count]) => {
      let tr = $( '<tr class="track">' +
                    '<td>' + formatTrackTitleAsText(t.artists, t.name) + '</td>' +
                    '<td class="count">' + count + '</td>' +
                  '</tr>'
                );
      table.append(tr);
    }
  );
}
