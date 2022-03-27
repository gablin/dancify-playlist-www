<?php
require '../../autoload.php';

function fail($msg) {
  throw new \Exception($msg);
}

try {

if (!hasSession()) {
  throw new NoSessionException();
}
$session = getSession();
$api = createWebApi($session);

// Parse JSON data
if (!isset($_POST['data'])) {
  fail("missing required POST field: data");
}
$json = fromJson($_POST['data'], true);
if (is_null($json)) {
  fail("POST field 'data' not in JSON format");
}

// Check data
if (!array_key_exists('trackIdList', $json)) {
  fail('trackIdList missing');
}
$track_ids = $json['trackIdList'];
if (count($track_ids) == 0) {
  fail('no track IDs');
}
$overwrite_playlist =
  array_key_exists('overwritePlaylist', $json) ? $json['overwritePlaylist']
                                               : false;
if (!is_bool($overwrite_playlist)) {
  fail('illegal overwritePlaylist value');
}
if ($overwrite_playlist) {
  if (!array_key_exists('playlistId', $json)) {
    fail('playlistId missing');
  }
  $playlist_id = $json['playlistId'];
  if (strlen($playlist_id) == 0) {
    fail('no playlist ID');
  }
}
else {
  if (!array_key_exists('playlistName', $json)) {
    fail('playlistName missing');
  }
  $playlist_name = $json['playlistName'];
  if (strlen($playlist_name) == 0) {
    fail('no playlist name');
  }
  if (!array_key_exists('publicPlaylist', $json)) {
    fail('publicPlaylist missing');
  }
  $make_public = $json['publicPlaylist'];
  if (!is_bool($make_public)) {
    fail('illegal publicPlaylist value');
  }
}

if ($overwrite_playlist) {
  // Load name of existing playlist
  $p = loadPlaylistInfo($api, $playlist_id);
  $old_playlist_name = $p->name;

  // Load tracks from existing playlist
  $old_tracks = loadPlaylistTracks($api, $playlist_id);
  $old_track_ids = array_map(function($t) { return $t->track->id; }, $old_tracks);

  // Create backup playlist
  $new_playlist = $api->createPlaylist( [ 'name' => $old_playlist_name .
                                                    ' - BACKUP'
                                        , 'public' => true
                                        ]
                                      );
  $new_playlist_id = $new_playlist->id;
  addPlaylistTracks($api, $new_playlist_id, $old_track_ids);

  deletePlaylistTracks($api, $playlist_id, $old_track_ids);
  addPlaylistTracks($api, $playlist_id, $track_ids);

  deletePlaylist($api, $new_playlist_id);

  echo(toJson(['status' => 'OK']));
}
else {
  // Create new playlist
  $new_playlist = $api->createPlaylist( [ 'name' => $playlist_name
                                        , 'public' => $make_public
                                        ]
                                      );
  addPlaylistTracks($api, $new_playlist->id, $track_ids);
  echo(toJson(['status' => 'OK', 'newPlaylistId' => $new_playlist->id]));
}

} // End try
catch (NoSessionException $e) {
  echo(toJson(['status' => 'NOSESSION']));
}
catch (\Exception $e) {
  echo(toJson(['status' => 'FAILED', 'msg' => $e->getMessage()]));
}
?>
