<?php
require '../autoload.php';
?>

const DEFAULT_FADE_OUT_LENGTH = 5;

function setupSetTrackFadeOut(playlist_id) {
  let form = getPlaylistForm();
  $('#saveTrackFadeOut').click(
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
      saveTrackFadeOut(playlist_id, success, fail);
    }
  );
  $('#removeTrackFadeOut').click(
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
      removeTrackFadeOut(playlist_id, success, fail);
    }
  );

  let fade_out_length = getFadeOutSliderDiv();
  fade_out_length.slider(
    { min: 1
    , max: 30
    , value: DEFAULT_FADE_OUT_LENGTH
    , slide: function(event, ui) {
        printFadeOutValue(ui.value);
      }
    }
  );
  printFadeOutValue(fade_out_length.slider('value'));
}

function getSetTrackFadeOutArea() {
  return $('div[name=set-track-fade-out]');
}

function printFadeOutValue(len_s) {
  let tr = getSetTrackFadeOutArea().find('table.track-fade-out-area tr');
  tr.find('td.label > span').text(formatTrackLength(len_s*1000));
}

function getFadeOutSliderDiv() {
  let tr = getSetTrackFadeOutArea().find('table.track-fade-out-area tr');
  return tr.find('td.track-fade-out-controller > div');
}

function onShowSetTrackFadeOut() {
  let len_s = Math.ceil(getFadeOutLength() / 1000);
  if (len_s == 0) {
    len_s = DEFAULT_FADE_OUT_LENGTH;
  }
  getFadeOutSliderDiv().slider('value', len_s);
  printFadeOutValue(len_s);
}

function saveTrackFadeOut(playlist_id, success_f, fail_f) {
  let len_s = getFadeOutSliderDiv().slider('value');
  callApi( '/api/update-playback/'
         , { playlistId: playlist_id, fadeOutLength: len_s }
         , function() {
             setFadeOutLength(len_s * 1000);
             success_f();
           }
         , fail_f
         );
}

function removeTrackFadeOut(playlist_id, success_f, fail_f) {
  callApi( '/api/update-playback/'
         , { playlistId: playlist_id, fadeOutLength: 0 }
         , function() {
             setFadeOutLength(0);
             success_f();
           }
         , fail_f
         );
}
