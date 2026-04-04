<?php
require '../autoload.php';
?>


function setupSeparateDanceTracks() {
  $('#separateBtn').click(
    function() {
      let params = getSeparateDanceTracksParams();
      if (!params) {
        return;
      }
      let data = generateSeparateApiData(params);

      let cancel_button = $('#cancelSeparateBtn');

      let b = $(this);
      let b_old_text = b.text();
      b.prop('disabled', true);
      cancel_button.prop('disabled', true);
      b.addClass('loading');
      cancel_button.addClass('loading');
      let body = $(document.body);
      body.addClass('loading');

      let count_handle = null;

      function restoreButtons() {
        clearInterval(count_handle);
        b.text(b_old_text);
        b.prop('disabled', false);
        b.removeClass('loading');
        cancel_button.prop('disabled', false);
        cancel_button.removeClass('loading');
        body.removeClass('loading');
      };

      let countdown = 60;
      count_handle = setInterval(
                       function() {
                         countdown -= 1;
                         b.text(countdown);
                       }
                     , 1000
                     );

      callApi( '/api/separate-dance-tracks/'
             , data
             , function(d) {
                 updatePlaylistAfterSeparation(d.slotOrder);
                 restoreButtons();
                 clearActionInputs();
               }
             , function fail(msg) {
                 alert('ERROR: <?= LNG_ERR_FAILED_TO_SEPARATE ?>');
                 restoreButtons();
                 clearActionInputs();
               }
             );
      return false;
    }
  );
}

function getSeparateDanceTracksParams() {
  let error_div = $('#separateDanceTrackErrors');
  error_div.empty();

  let select_area = $('#separateGenreSelectArea');
  let selected_genres = [];
  select_area.find('input').each(
    function() {
      let input = $(this);
      if (input.is(':checked')) {
        selected_genres.push(input.val());
      }
    }
  );

  let bpm_input = $('input[name=above-bpm-to-separate]');
  let bpm_value = bpm_input.val().trim();
  bpm_value = bpm_value.length > 0 ? parseInt(bpm_value) : 0;
  if (isNaN(bpm_value)) {
    error_div.text('<?= LNG_ERR_BPM_NAN ?>');
    return null;
  }

  if (selected_genres.length == 0 && bpm_value == 0) {
    error_div.text('<?= LNG_MUST_SELECT_A_GENRE_OR_BPM ?>');
    return null;
  }

  return { genres: selected_genres, bpm: bpm_value };
}

function getSeparateBpmFromTrack(t) {
  if (!t.bpm) return 0;
  if (t.bpm.custom > -1) return t.bpm.custom;
  return t.bpm.spotify;
}

function getSeparateGenreFromTrack(t) {
  if (!t.genre) return 0;
  if (t.genre.by_user > 0) return t.genre.by_user;
  if (t.genre.by_others.length > 0) return t.genre.by_others[0];
  return 0;
}

function generateSeparateApiData(params) {
  let tracks = getTrackData(getPlaylistTable());
  let delimiter = getDanceDelimiter();

  let genres_in_use =
    uniq(tracks.map(getSeparateGenreFromTrack).filter((v) => v > 0));
  let genres = getGenreList().map((t) => t[0])
                             .filter((g) => genres_in_use.includes(g));

  // First group is BPM, then follows all genres
  let groups = [];
  for (let i = 0; i < genres.length + 1; i++) {
    groups.push([]);
  }

  function addToGroup(s, i) {
    if (groups[i].includes(s)) return;
    groups[i].push(s);
  }

  let num_slots = Math.ceil(tracks.length / delimiter);
  for (let s = 0; s < num_slots; s++) {
    let dance_tracks = [];
    let i = s * delimiter;
    for (let j = 0; j < delimiter; j++) {
      if (i + j >= tracks.length) break;
      dance_tracks.push(tracks[i + j]);
    }

    dance_tracks.forEach(
      (t) => {
        if (getSeparateBpmFromTrack(t) >= params.bpm && params.bpm > 0) {
          addToGroup(s, 0);
        }
        genres.forEach(
          (g, i) => {
            if (g == getSeparateGenreFromTrack(t)) {
              addToGroup(s, i + 1);
            }
          }
        );
      }
    );
  }

  groups = groups.filter((g) => g.length > 1);

  return { numSlots: num_slots, conflictGroups: groups }
}

function updatePlaylistAfterSeparation(order) {
  let tracks = getTrackData(getPlaylistTable());
  let delimiter = getDanceDelimiter();
  let num_groups = Math.ceil(tracks.length / delimiter);

  let grouped_tracks = [];
  for (let g = 0; g < num_groups; g++) {
    let group = [];
    let i = g * delimiter;
    for (let j = 0; j < delimiter; j++) {
      if (i + j < tracks.length) {
        group.push(tracks[i + j]);
      }
      else {
        group.push(createPlaylistPlaceholderObject());
      }
    }
    grouped_tracks.push(group);
  }

  let new_playlist = [];
  for (let g = 0; g < num_groups; g++) {
    let i = order.indexOf(g);
    new_playlist.push(...grouped_tracks[i]);
  }

  replaceTracks(getPlaylistTable(), new_playlist);
  renderTable(getPlaylistTable());
  indicateStateUpdate();
}

function onShowSeparateDanceTracks() {
  let separate_button = $('#separateBtn');
  let warning = $('#danceLimiterNotSetWarning');
  separate_button.attr('disabled', !isUsingDanceDelimiter());
  if (isUsingDanceDelimiter()) warning.hide();
  else                         warning.show();

  // Populate genre list
  let tracks = getTrackData(getPlaylistTable());
  let genres_in_use =
    uniq(tracks.map(getSeparateGenreFromTrack).filter((v) => v > 0));
  let genres = getGenreList().filter((t) => genres_in_use.includes(t[0]));

  let select_area = $('#separateGenreSelectArea');
  select_area.empty();
  $('<div class="wrapper" />')
  .appendTo(select_area)
  .append(
    $('<button />')
    .text('<?= LNG_SELECT_ALL ?>')
    .click(
      function() {
        select_area.find('input').prop('checked', true);
      }
    )
  );
  genres.forEach(
    ([genre_id, genre_txt]) => {
      let num_tracks =
        tracks.filter((t) => getSeparateGenreFromTrack(t) == genre_id).length;

      $('<div class="wrapper" />')
      .appendTo(select_area)
      .append($('<div />').text(genre_txt + ' (' + num_tracks + ')'))
      .append($('<input type="checkbox" />').attr('value', genre_id));
    }
  );
}
