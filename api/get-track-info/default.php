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
if ( !array_key_exists('trackUrl', $json) &&
     !array_key_exists('trackId', $json) &&
     !array_key_exists('trackIds', $json)
   )
{
  fail('trackUrl/trackId/trackIds missing');
}
if ( ( array_key_exists('trackUrl', $json) +
       array_key_exists('trackId', $json) +
       array_key_exists('trackIds', $json)
     ) > 1
   )
{
  fail('only one of trackUrl/trackId/trackIds can be specified');
}
$track_ids = [];
if (array_key_exists('trackUrl', $json)) {
  $id = getTrackId($json['trackUrl']);
  if (strlen($id) == 0) {
    fail('illegal URL format');
  }
  $track_ids[] = $id;
}
else if (array_key_exists('trackId', $json)) {
  $track_ids[] = $json['trackId'];
}
else {
  $track_ids = $json['trackIds'];
}

$tracks = $api->getTracks($track_ids)->tracks;
$audio_feats = loadTrackAudioFeatures($api, $tracks);

connectDb();
$genres = [];
$client_id_sql = escapeSqlValue($session->getClientId());
$res = queryDb( "SELECT song, genre FROM genre " .
                "WHERE song IN (" .
                join( ','
                    , array_map(
                        function($t) { return "'" . escapeSqlValue($t) . "'"; }
                      , $track_ids
                      )
                    ) .
                ") AND user = '$client_id_sql'"
              );
while ($row = $res->fetch_assoc()) {
  $genres[] = [$row['song'], $row['genre']];
}
$tracks_res = [];
for ($i = 0; $i < count($tracks); $i++) {
  $t = $tracks[$i];
  $bpm = (int) $audio_feats[$i]->tempo;
  $genre = array_values( // To reset indices
             array_filter( $genres
             , function($g) use ($t) { return $g[0] === $t->id; }
             )
           );
  $genre = count($genre) > 0 ? $genre[0][1] : 0;
  $tracks_res[] = [ 'trackId' => $t->id
                  , 'name' => $t->name
                  , 'artists' => formatArtists($t)
                  , 'length' => $t->duration_ms
                  , 'bpm' => $bpm
                  , 'genre' => $genre
                  , 'preview_url' => $t->preview_url
                  ];
}

$res = ['status' => 'OK'];
if (array_key_exists('trackIds', $json)) {
  $res['tracks'] = $tracks_res;
}
else {
  $res = array_merge($res, $tracks_res[0]);
}
echo(toJson($res));

} // End try
catch (\Exception $e) {
  echo(toJson(['status' => 'FAILED', 'msg' => $e->getMessage()]));
}
?>
