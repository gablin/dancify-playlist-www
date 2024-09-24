<?php
require '../autoload.php';
?>

PLAYLIST_DELIMITER_BASE_ELEMENT = null;

function setupPlaylistDelimiter() {
  setupFormElementsForPlaylistDelimiter();
}

function playlistDelimiterWrapper() {
  return getPlaylistForm().find('.playlist-delimiters');
}

function setupFormElementsForPlaylistDelimiter() {
  let form = getPlaylistForm();

  let delimiters = playlistDelimiterWrapper().children();
  PLAYLIST_DELIMITER_BASE_ELEMENT = delimiters.eq(0).clone();

  delimiters.each(
    function() {
      setupPlaylistDelimiterElement($(this));
    }
  );

  form.find('.playlist-delimiter-heading button.add').click(
    function() {
      addNewPlaylistDelimiterElement();
    }
  );

  form.find('button[id=applyPlaylistDelimitersBtn]').click(
    function() {
      let values = [];
      let has_errors = false;
      playlistDelimiterWrapper().find('input').each(
        function() {
          let value = parsePlaylistDelimiterInput($(this));
          if (value === null) {
            has_errors = true;
            return;
          }
          values.push(value);
        }
      );
      if (has_errors) return;

      PLAYLIST_DELIMITERS = values;
      renderTable(getPlaylistTable());
      renderTrackOverviews();
      clearActionInputs();
      savePlaylistSnapshot(function() {}, function() {});
    }
  );
}

function setupPlaylistDelimiterElement(div) {
  div.find('button.remove').click(
    function() {
      div.remove();
    }
  );
}

function parsePlaylistDelimiterInput(input) {
  let value = input.val().trim();

  function reportInvalidFormat() {
    alert('<?= LNG_ERR_INVALID_DELIMITER_FORMAT ?>: ' + value);
  }

  let parts = value.split(':');
  if (parts.length != 3) {
    reportInvalidFormat();
    return null;
  }

  parts = parts.map((p) => parseInt(p.trim()));
  if (!parts.reduce((b, p) => b && p >= 0 && p <= 59, true)) {
    reportInvalidFormat();
    return null;
  }

  return parts[0]*3600 + parts[1]*60 + parts[2];
}

function setPlaylistDelimiterValue(elem, v) {
  function pad(i) {
    if (i < 10) return '0' + i;
    return i;
  }

  let s = v % 60;
  let m = (Math.trunc(v / 60)) % 60;
  let h = Math.trunc(v / 3600);
  let str = pad(h) + ':' + pad(m) + ':' + pad(s);
  elem.find('input').val(str);
}

function addNewPlaylistDelimiterElement() {
  let new_delimiter = PLAYLIST_DELIMITER_BASE_ELEMENT.clone();
  setupPlaylistDelimiterElement(new_delimiter);
  playlistDelimiterWrapper().append(new_delimiter);
  return new_delimiter;
}

function clearPlaylistDelimiterElements() {
  playlistDelimiterWrapper().children().remove();
}

function addPlaylistDelimiterElement(v) {
  let delimiter = addNewPlaylistDelimiterElement();
  setPlaylistDelimiterValue(delimiter, v);
}
