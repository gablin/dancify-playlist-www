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
if (!array_key_exists('trackUrl', $json)) {
  fail('trackUrl missing');
}

$track_id = getTrackId($json['trackUrl']);
if (strlen($track_id) > 0) {
  try {
    $track = $api->getTrack($track_id);
  }
  catch (Exception $e) {
    fail(sprintf('%s: %s', LNG_ERR_FAILED_LOAD_TRACK, $e->getMessage()));
  }
}
else {
  fail(LNG_ERR_INVALID_TRACK_FORMAT);
}

$audio_feats = loadTrackAudioFeatures($api, [$track]);

$category = '';
$client_id = $session->getClientId();
connectDb();
$res = queryDb( "SELECT category FROM category " .
                "WHERE song = '$track_id' AND user = '$client_id'"
              );
if ($res->num_rows == 1) {
  $category = $res->fetch_assoc()['category'];
}

echo( toJson( [ 'status' => 'OK'
              , 'trackId' => $track->id
              , 'name' => $track->name
              , 'artists' => formatArtists($track)
              , 'length' => $track->duration_ms
              , 'bpm' => (int) $audio_feats[0]->tempo
              , 'category' => $category
              , 'preview_url' => $track->preview_url
              ]
            )
    );

} // End try
catch (\Exception $e) {
  echo(toJson(['status' => 'FAILED', 'msg' => $e->getMessage()]));
}
?>
