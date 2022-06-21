<?php
require '../autoload.php';
?>

function loadPlaylist(playlist_id) {
  function fail(msg) {
    alert(msg);
  }
  callApi( '/api/get-playlist-info/'
         , { playlistId: playlist_id }
         , function(d) {
             abortLoadPlaylistContent(
               function() {
                 clearPlaylistContent();
                 loadPlaylistContent(
                   d.info
                 , function() {
                     loadPlaybackSettings(
                       playlist_id
                     , noop
                     , fail
                     );
                   }
                 , fail
                 );
               }
             );
           }
         , fail
         );
}
