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
if (!array_key_exists('playlistId', $json)) {
  fail('playlistId missing');
}
$pid = $json['playlistId'];
if (strlen($pid) == 0) {
  fail("illegal playlistId value: $pid");
}
$limit = array_key_exists('limit', $json) ? $json['limit'] : 50;
if (!is_int($limit) || $limit < 0) {
  fail("illegal limit value: $limit");
}
$offset = array_key_exists('offset', $json) ? $json['offset'] : 0;
if (!is_int($offset) || $offset < 0) {
  fail("illegal offset value: $offset");
}

$options = [ 'limit' => $limit
           , 'offset' => $offset
           , 'fields' => 'items(track(id)),total'
           ];
$res = $api->getPlaylistTracks($pid, $options);
$tracks = array_map(function($i) { return $i->track->id; }, $res->items);
// Spotify can sometimes return tracks with no ID
$tracks = array_values( // Reset keys if filtering happens
            array_filter($tracks, function($t) { return !is_null($t); })
          );
echo( toJson( [ 'status' => 'OK'
              , 'tracks' => $tracks
              , 'total' => count($tracks)
              ]
            )
    );

} // End try
catch (NoSessionException $e) {
  echo(toJson(['status' => 'NOSESSION']));
}
catch (\Exception $e) {
  echo(toJson(['status' => 'FAILED', 'msg' => $e->getMessage()]));
}
?>
