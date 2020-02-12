<?php
require '../../../../../autoload.php';
require '../../../functions.php';

ensureSession();
$session = getSession();
$api = createWebApi($session);

try {
  // Check and sanitize input
  $playlist_id = fromGET('playlist_id');
  $track_id = fromGET('track_id');
  $freq = fromGET('freq');
  $new_name = fromGET('new_name');

  if (!is_numeric($freq)) {
    throw new Exception(sprintf('%s: %s', LNG_ERR_FREQ_NOT_INT, $freq));
  }
  $freq = intval($freq);

  // Load necessary data
  $old_playlist = loadPlaylistInfo($api, $playlist_id);
  $old_track_list = loadPlaylistTracks($api, $playlist_id);

  $ins_track = $api->getTrack($track_id);

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
  $new_playlist = $api->createPlaylist([ 'name' => $new_name
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
      throw new Exception(LNG_ERR_FAILED_TO_ADD_SONGS_TO_NEW_PLAYLIST);
    }
  }

  // Forward to next page
  $gets = array();
  $gets['new_playlist_id'] = $new_playlist->id;
  foreach (['playlist_id', 'track', 'track_id', 'freq'] as $k) {
    if (hasGET($k)) {
      $gets[$k] = fromGET($k);
    }
  }
  $lnk = buildLink('./ok/', $gets);
  header("Location: {$lnk}");
}
catch (Exception $e) {
  beginPage();
  createMenu( mkMenuItemShowPlaylists($api)
            , mkMenuItemShowPlaylistTracks($api)
            , mkMenuItemNewPlaylist($api)
            );
  beginContent();
  showError($e->getMessage());
  endContent();
  endPage();
}
updateTokens($session);
?>
