<?php
require '../../autoload.php';

function fail($msg) {
  throw new \Exception($msg);
}

try {

if (!hasSession()) {
  fail('no session');
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
if (!array_key_exists('leftoverTrackIdList', $json)) {
  fail('leftoverTrackIdList missing');
}
if (!array_key_exists('playlistName', $json)) {
  fail('playlistName missing');
}
if (!array_key_exists('publicPlaylist', $json)) {
  fail('publicPlaylist missing');
}
$track_ids = $json['trackIdList'];
$leftover_track_ids = $json['leftoverTrackIdList'];
if (count($track_ids) == 0) {
  fail('no track IDs');
}
$playlist_name = $json['playlistName'];
if (strlen($playlist_name) == 0) {
  fail('no playlist name');
}
$make_public = $json['publicPlaylist'];
if (!is_bool($make_public)) {
  fail('illegal publicPlaylist value');
}

// Build list of tracks
$filler_track_id = '2arG6nSmXmh7joBYxxqEdU'; // 5s silence
$leftover_sep_track_id = '7qLhC1Ib6rfiAJlcssbPDU'; // 60s silence
$tracks = [];
foreach ($track_ids as $tid) {
  if (strlen(trim($tid)) > 0) {
    $tracks[] = $tid;
  }
  else {
    // Add filler song
    $tracks[] = $filler_track_id;
  }
}
if (count($leftover_track_ids) > 0) {
  $tracks[] = $leftover_sep_track_id;
}
$tracks = array_merge($tracks, $leftover_track_ids);

// Create new playlist
$new_playlist = $api->createPlaylist([ 'name' => $playlist_name
                                     , 'public' => $make_public
                                     ]);

// Add tracks
//
// Due to API restrictions, we insert a limited number at a time. For more
// information, see: https://developer.spotify.com/documentation/web-api/reference/playlists/add-tracks-to-playlist/
$limit = 100;
for ($i = 0; $i < count($track_ids); $i += $limit) {
  $ts = array_slice($tracks, $i, $limit);
  $res = $api->addPlaylistTracks($new_playlist->id, $ts);
  if (!$res) {
    throw new Exception(LNG_ERR_FAILED_TO_ADD_SONGS_TO_NEW_PLAYLIST);
  }
}

echo(toJson(['status' => 'OK', 'newPlaylistId' => $new_playlist->id]));

} // End try
catch (\Exception $e) {
  echo(toJson(['status' => 'FAILED', 'msg' => $e->getMessage()]));
}
?>
