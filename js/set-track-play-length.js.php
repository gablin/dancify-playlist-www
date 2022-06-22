<?php
require '../autoload.php';
?>

const DEFAULT_PLAY_TRACK_LENGTH = 60;

function setupSetTrackPlayLength() {
  let form = getPlaylistForm();
  $('#saveTrackPlayLength').click(
    function() {
      let b = $(this);
      b.prop('disabled', true);
      b.addClass('loading');
      let body = $(document.body);
      body.addClass('loading');
      function restoreButton() {
        b.prop('disabled', false);
        b.removeClass('loading');
        body.removeClass('loading');
      };
      function success() {
        restoreButton();
        clearActionInputs();
      }
      function fail(msg) {
        alert('ERROR: <?= LNG_ERR_FAILED_TO_SAVE ?>');
        restoreButton();
      }
      saveTrackPlayLength(success, fail);
    }
  );
  $('#removeTrackPlayLength').click(
    function() {
      let b = $(this);
      b.prop('disabled', true);
      b.addClass('loading');
      let body = $(document.body);
      body.addClass('loading');
      function restoreButton() {
        b.prop('disabled', false);
        b.removeClass('loading');
        body.removeClass('loading');
      };
      function success() {
        restoreButton();
        clearActionInputs();
      }
      function fail(msg) {
        alert('ERROR: <?= LNG_ERR_FAILED_TO_SAVE ?>');
        restoreButton();
      }
      removeTrackPlayLength(success, fail);
    }
  );

  let play_length = getPlayLengthSliderDiv();
  play_length.slider(
    { min: 1
    , max: 10*60
    , value: DEFAULT_PLAY_TRACK_LENGTH
    , slide: function(event, ui) {
        printPlayLengthValue(ui.value);
      }
    }
  );
  printPlayLengthValue(play_length.slider('value'));
}

function getSetTrackPlayLengthArea() {
  return $('div[name=set-track-play-length]');
}

function printPlayLengthValue(len_s) {
  let tr = getSetTrackPlayLengthArea().find('table.track-play-length-area tr');
  tr.find('td.label > span').text(formatTrackLength(len_s*1000));
}

function getPlayLengthSliderDiv() {
  let tr = getSetTrackPlayLengthArea().find('table.track-play-length-area tr');
  return tr.find('td.track-play-length-controller > div');
}

function onShowSetTrackPlayLength() {
  let len_s = Math.ceil(getMaxPlayLength() / 1000);
  if (len_s == 0) {
    len_s = DEFAULT_PLAY_TRACK_LENGTH;
  }
  getPlayLengthSliderDiv().slider('value', len_s);
  printPlayLengthValue(len_s);
}

function saveTrackPlayLength(success_f, fail_f) {
  let len_s = getPlayLengthSliderDiv().slider('value');
  callApi( '/api/update-playback/'
         , { trackPlayLength: len_s }
         , function() {
             setMaxPlayLength(len_s * 1000);
             success_f();
           }
         , fail_f
         );
}

function removeTrackPlayLength(success_f, fail_f) {
  callApi( '/api/update-playback/'
         , { trackPlayLength: 0 }
         , function() {
             setMaxPlayLength(0);
             success_f();
           }
         , fail_f
         );
}
