<?php
require '../autoload.php';
?>

function setupPlaylistDelimiter() {
  setupFormElementsForPlaylistDelimiter();
}

function setupFormElementsForPlaylistDelimiter() {
  let form = getPlaylistForm();

  let delimiters_wrapper = form.find('.playlist-delimiters');
  let delimiters = delimiters_wrapper.children();
  let delimiter_base = delimiters.eq(0).clone();

  delimiters.each(
    function() {
      setupPlaylistDelimiterElement($(this));
    }
  );

  form.find('.playlist-delimiter-heading button.add').click(
    function() {
      let new_delimiter = delimiter_base.clone();
      setupPlaylistDelimiterElement(new_delimiter);
      delimiters_wrapper.append(new_delimiter);
    }
  );

  form.find('button[id=applyPlaylistDelimitersBtn]').click(
    function() {
      let values = [];
      let has_errors = false;
      delimiters_wrapper.find('input').each(
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
