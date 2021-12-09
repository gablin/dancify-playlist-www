<?php
require '../autoload.php';
?>

var audio = $('<audio />');

function setupPlaylistElementsForPreview(form, table) {
  // Play preview when clicking on row corresponding to track
  table.find('tbody tr.track').each(
    function() {
      $(this).click(
        function() {
          audio.attr('src', ''); // Stop playing
          $(this).siblings().removeClass('playing');
          $(this).siblings().removeClass('cannot-play');
          if ($(this).hasClass('playing')) {
            $(this).removeClass('playing');
            return;
          }

          var url = $(this).find('input[name=preview_url]').val();
          if (url == null) {
            return;
          }
          if (url.length > 0) {
            $(this).addClass('playing');
            audio.attr('src', url);
            audio.get(0).play();
          }
          else {
            $(this).addClass('cannot-play');
          }
        }
      );
    }
  );
}
