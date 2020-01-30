<?php
require '../../../../autoload.php';

ensureSession();
$session = getSession();
$api = createWebApi($session);
ensureAuthorizedUser($api);

try {
  // Check and sanitize input
  foreach (['playlist_id', 'track_id', 'freq', 'new_name'] as $k) {
    ensureGET($k);
  }
  $freq = $_GET['freq'];
  if (!is_numeric($freq)) {
    throw new Exception(sprintf('freq is not a number: %d', $freq));
  }
  $freq = intval($freq);
  
  // Load necessary data
  $old_track_list = loadPlaylistTracks($api, $_GET['playlist_id']);
  $ins_track = $api->getTrack($_GET['track_id']);
  
  // Build new list of tracks
  $new_track_list = array();
  $ins_i = 0;
  foreach ($old_track_list as $t) {
    $t = $t->track;
    if ($ins_i >= $freq) {
      array_push($new_track_list, $ins_track->id);
      $ins_i = 1;
    }
    else {
      $ins_i += 1;
    }
    array_push($new_track_list, $t->id);
  }

  // Create new playlist
  $new_playlist = $api->createPlaylist([ 'name' => $_GET['new_name']
                                       , 'public' => $old_playlist->public
                                       ]);

  // Add songs to playlist
  //
  // Due to API restrictions, we insert a limited number at a time. For more
  // information, see: https://developer.spotify.com/documentation/web-api/reference/playlists/add-tracks-to-playlist/
  $slice_size = 50;
  for ($i = 0; $i < count($new_track_list); $i += $slice_size) {
    $slice = array_slice($new_track_list, $i, $slice_size);
    $res = $api->addPlaylistTracks($new_playlist->id, $slice);
    if (!$res) {
      throw new Exception('failed to add tracks to new playlist');
    }
  }
  
  // Forward to next page
  header('Location: ./ok/?new_playlist_id=' . $new_playlist->id);
}
catch (Exception $e) {
  beginPage();
  showError($e);
  endPage();
}
updateTokens($session);
?>
