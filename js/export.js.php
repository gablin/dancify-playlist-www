<?php
require '../autoload.php';
?>

function setupExport() {
  let form = getPlaylistForm();
  $('#exportPlaylistBtn').click(
    function() {
      let content = generateCsvContent();
      let info = getCurrentPlaylistInfo();
      triggerCsvDownload(info.name, content);
    }
  );
}

function generateCsvContent() {
  function toCsv(d) {
    if (d === undefined) {
      return '';
    }
    return '"' + d.toString().replaceAll('"', '""') + '"';
  }

  let headers = [ '#'
                , '<?= LNG_HEAD_ADDED_BY ?>'
                , '<?= LNG_HEAD_NAME ?>'
                , '<?= LNG_HEAD_ARTIST ?>'
                , '<?= LNG_HEAD_BPM ?>'
                , '<?= LNG_HEAD_GENRE ?>'
                , '<?= LNG_HEAD_COMMENTS ?>'
                , '<?= LNG_HEAD_LENGTH ?>'
                , '<?= LNG_HEAD_TOTAL ?>'
                ];
  let track_data = getTrackData(getPlaylistTable());
  let csv_data = headers.map(toCsv).join(',') + '\n';
  let i = 0;
  let total_length = 0;
  csv_data += track_data.map( t => { i++;
                                     total_length += t.length;
                                     return [ i
                                            , t.addedBy
                                            , t.name
                                            , t.artists !== undefined
                                              ? t.artists.join(', ') : ''
                                            , t.bpm
                                            , t.genre !== undefined &&
                                              t.genre.by_user !== undefined
                                              ? formatGenre(t.genre.by_user) : ''
                                            , t.comments
                                            , t.length !== undefined
                                              ? formatTrackLength(t.length) : ''
                                            , formatTrackLength(total_length)
                                            ].map(toCsv);
                                   }
                            )
                        .join('\n');
  return csv_data;
}

function triggerCsvDownload(playlist_name, content) {
  let a = $('<a></a>');
  a.attr('href', 'data:text/plain;charset=utf-8,' + encodeURIComponent(content));
  a.attr('download', playlist_name + '.csv');
  a.hide();

  $(document.body).append(a);
  a[0].click();
  a.remove();
}
