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
            savePlaylistSnapshot(function() {}, function() {});
            savePlaylistSnapshotAndGlobalScratchpad();
          }
          else {
            $(overview_id).hide();
            savePlaylistSnapshot(function() {}, function() {});
          }
          renderTrackOverviews();
          setPlaylistHeight();
        }
      );
    }
  );
}
