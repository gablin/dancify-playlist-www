<?php
require '../autoload.php';
?>

function setupTrackOverview() {
  setupFormElementsForTrackOverview();
}

function getTrackOverviewShowButton() {
  let form = getPlaylistForm();
  return form.find('button[id=showTrackOverviewBtn]');
}

function getTrackOverviewHideButton() {
  let form = getPlaylistForm();
  return form.find('button[id=hideTrackOverviewBtn]');
}

function setupFormElementsForTrackOverview() {
  let overviews = [ [ 'show-bpm-overview', 'div.bpm-overview' ]
                  , [ 'show-energy-overview', 'div.energy-overview' ]
                  , [ 'show-danceability-overview'
                    , 'div.danceability-overview'
                    ]
                  , [ 'show-acousticness-overview'
                    , 'div.acousticness-overview'
                    ]
                  , [ 'show-instrumentalness-overview'
                    , 'div.instrumentalness-overview'
                    ]
                  , [ 'show-valence-overview', 'div.valence-overview' ]
                  , [ 'show-genres-overview', 'div.genres-overview' ]
                  ];
  overviews.forEach(
    function(a) {
      let checkmark_id = a[0];
      let overview_id = a[1];
      let checkmark =
        getPlaylistForm().find('input[name=' + checkmark_id + ']');
      checkmark.click(
        function() {
          if (checkmark.is(':checked')) {
            $(overview_id).show();
          }
          else {
            $(overview_id).hide();
          }
          savePlaylistSnapshot(function() {}, function() {});
          renderTrackOverviews();
          updatePlaylistHeights();
        }
      );
    }
  );

  $('select[name=track-overview-genres]')
  .on(
    'change'
  , function() {
      let selected_options = $(this).find(':selected');
      let selected_genres = [];
      selected_options.each(
        function() {
          let opt = $(this);
          selected_genres.push(parseInt(opt.val()));
        }
      );
      if (selected_genres.length > 10) {
        alert('<?= LNG_TOO_MANY_GENRES_SELECTED ?>');
      }

      let sorted_genres = getGenreList()
                          .map((t) => t[0])
                          .filter((g) => selected_genres.includes(g));
      TRACK_OVERVIEW_GENRES = sorted_genres;

      savePlaylistSnapshot(function() {}, function() {});
      renderTrackOverviews();
    }
  );
}

function onShowTrackOverview() {
  [ 'bpm'
  , 'energy'
  , 'danceability'
  , 'acousticness'
  , 'instrumentalness'
  , 'valence'
  , 'genres'
  ].forEach(
    (name) => {
      let input = $('input[name=show-' + name + '-overview]');
      let div = $('div.' + name + '-overview');
      input.prop('checked', div.is(':visible'));
    }
  );

  // Populate genre list
  let tracks = getTrackData(getPlaylistTable());
  let genres_in_use =
    uniq(tracks.map(getGenreFromTrack).filter((v) => v > 0));
  let genres = getGenreList().filter((t) => genres_in_use.includes(t[0]));

  let genres_count =
    genres.map(
      (g) => [ g[0]
             , g[1]
             , tracks.filter((t) => getGenreFromTrack(t) == g[0]).length
             ]
    );

  genres_count.sort((g1, g2) => g2[2] - g1[2]);

  let genre_select = $('select[name=track-overview-genres]');
  genre_select.empty();
  genres_count.forEach(
    ([g, txt, num]) => {
      $('<option />')
      .appendTo(genre_select)
      .attr('value', g)
      .prop('selected', TRACK_OVERVIEW_GENRES.includes(g))
      .text(txt + ' (' + num + ')');
    }
  );
}
