<?php
require '../autoload.php';
?>

function getGenreCheckArea() {
  let form = getPlaylistForm();
  return form.find('div[name=genre-check]');
}

function getGenreCheckResultsArea() {
  return getGenreCheckArea().find('.check-results');
}

function setupGenreCheck() {
  $('#checkGenresBtn').click(doGenreCheck);
}

function onShowGenreCheck() {
  getGenreCheckResultsArea().hide();
}

function doGenreCheck(tables) {
  let body = $(document.body);
  body.addClass('loading');
  let action_area = getGenreCheckResultsArea();
  action_area.find('table tbody tr').remove();
  action_area.show();

  let tracks = getTrackData(getPlaylistTable());
  showGenreCheckResults(tracks, buildGenreCheckResults(tracks));
  body.removeClass('loading');
}

function buildGenreCheckResults(tracks) {
  tracks.forEach(
    (t) => {
      if (t.genre.by_user != 0) {
        t.genre = t.genre.by_user;
        return;
      }
      if (t.genre.by_others.length > 0) {
        t.genre = t.genre.by_others[0];
        return;
      }
      t.genre = 0;
    }
  );

  let genres = uniq(tracks.map((t) => t.genre));

  let playlist_delimiters = computePlaylistDelimiterPositions(tracks)
                            .map(([n, _ign]) => n);
  let dist_data = genres.map(
    (genre) => {
      let track_pairs = [];

      let track_pos = null;
      let has_matched = false;
      tracks.forEach(
        (track, i) => {
          if (playlist_delimiters.includes(i)) {
            track_pos = null;
            has_matched = false;
          }

          if (track.genre == genre) {
            if (track_pos === null) {
              track_pos = i;
            }
            else {
              let dist = i - track_pos - 1;
              track_pairs.push([track_pos, i, dist]);
              track_pos = i;
              has_matched = true;
            }
          }
        }
      );
      if (track_pos !== null && !has_matched) {
        track_pairs.push([track_pos, null, Number.MAX_SAFE_INTEGER]);
      }

      track_pairs.sort(
        (a, b) => {
          let a_pos = a[0];
          let b_pos = b[0];
          let a_dist = a[2];
          let b_dist = b[2];
          if (a_dist != b_dist) return intcmp(a_dist, b_dist);
          return intcmp(a_pos, b_pos);
        }
      );

      return [genre, track_pairs];
    }
  );
  return dist_data;
}

function showGenreCheckResults(tracks, data) {
  let table = getGenreCheckResultsArea().find('table tbody');

  // Sort so genre with shortest distance appears first
  data.sort(
    ([g1, data1], [g2, data2]) => intcmp(data1[0][2], data2[0][2])
  );

  data.forEach(
    ([genre, track_pairs]) => {
      let genre_name = genre > 0 ? genreToString(genre)
                                 : '<?= LNG_DESC_WO_GENRE ?>';
      let genre_tr = $( '<tr>' +
                          '<td colspan="5" class="genre">' +
                            genre_name +
                          '</td>' +
                        '</tr>'
                     ).appendTo(table);
      let track_trs =
        track_pairs.map(
          ([pos1, pos2, dist]) => {
            function buildTrackTd(track) {
              let td = $('<td />');
              if (track) {
                td.append(formatTrackTitleAsHtml(track.artists, track.name));
              }
              return td;
            }

            if (dist == Number.MAX_SAFE_INTEGER) {
              dist = '';
            }
            let track1 = tracks[pos1];
            let track2 = pos2 !== null ? tracks[pos2] : null;
            return $('<tr />')
                   .append('<td class="distance">' + dist + '</td>')
                   .append('<td class="index">' + (pos1 + 1) + '</td>')
                   .append(buildTrackTd(track1))
                   .append( '<td class="index">' +
                              (pos2 !== null ? (1 + pos2) : '') +
                            '</td>'
                          )
                   .append(buildTrackTd(track2))
                   .appendTo(table)
                   .hide();
          }
        );

      genre_tr.click(
        () => {
          track_trs.forEach((tr) => tr.toggle());
        }
      );
    }
  );
}
