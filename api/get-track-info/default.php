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

connectDb();
$genres = [];
$comments = [];
$user = getThisUserId($api);
$user_sql = escapeSqlValue($user);

// Load BPM data
$audio_feats = loadTrackAudioFeatures($api, $tracks);
$res = queryDb( "SELECT song, bpm FROM bpm " .
                "WHERE song IN (" .
                join( ','
                    , array_map(
                        function($t) { return "'" . escapeSqlValue($t) . "'"; }
                      , $track_ids
                      )
                    ) .
                ")"
              );
$bpms = [];
while ($row = $res->fetch_assoc()) {
  $bpms[] = [$row['song'], $row['bpm']];
}

// Load genre data
$res = queryDb( "SELECT song, genre, user FROM genre " .
                "WHERE song IN (" .
                join( ','
                    , array_map(
                        function($t) { return "'" . escapeSqlValue($t) . "'"; }
                      , $track_ids
                      )
                    ) .
                ")"
              );
while ($row = $res->fetch_assoc()) {
  $genres[] = [$row['song'], $row['genre'], $row['user']];
}

// Load comments data
$res = queryDb( "SELECT song, comments FROM comments " .
                "WHERE song IN (" .
                join( ','
                    , array_map(
                        function($t) { return "'" . escapeSqlValue($t) . "'"; }
                      , $track_ids
                      )
                    ) .
                ") AND user = '$user_sql'"
              );
while ($row = $res->fetch_assoc()) {
  $comments[] = [$row['song'], $row['comments']];
}

// Build result
$tracks_res = [];
for ($i = 0; $i < count($tracks); $i++) {
  $t = $tracks[$i];
  if (is_null($t)) {
    continue;
  }

  $bpm = array_values( // To reset indices
           array_filter(
             $bpms
           , function($b) use ($t) { return $b[0] === $t->id; }
           )
         );
  $bpm = count($bpm) > 0 ? $bpm[0][1] : (int) $audio_feats[$i]->tempo;
  $genres_by_user = array_values( // To reset indices
                      array_filter(
                        $genres
                      , function($g) use ($t, $user) {
                          return $g[0] === $t->id && $g[2] === $user;
                        }
                      )
                    );
  $genres_by_others = array_values( // To reset indices
                        array_filter(
                          $genres
                        , function($g) use ($t, $user) {
                            return $g[0] === $t->id && $g[2] !== $user;
                          }
                        )
                      );
  $genre_by_user = count($genres_by_user) > 0 ? $genres_by_user[0][1] : 0;
  $genres_by_others = array_map(function($t) { return $t[1]; }, $genres_by_others);
  $cmnt = array_values( // To reset indices
            array_filter( $comments
            , function($c) use ($t) { return $c[0] === $t->id; }
            )
          );
  $cmnt = count($cmnt) > 0 ? $cmnt[0][1] : '';
  $tracks_res[] = [ 'trackId' => $t->id
                  , 'name' => $t->name
                  , 'artists' => formatArtists($t)
                  , 'length' => $t->duration_ms
                  , 'bpm' => $bpm
                  , 'genre' => [ 'by_user' => $genre_by_user
                               , 'by_others' => $genres_by_others
                               ]
                  , 'comments' => $cmnt
                  , 'preview_url' => $t->preview_url
                  ];
}

$res = ['status' => 'OK'];
if (array_key_exists('trackIds', $json)) {
  $res['tracks'] = $tracks_res;
}
else {
  if (count($tracks_res) == 0) {
    fail("no track with ID: $track_ids[0]");
  }
  $res = array_merge($res, $tracks_res[0]);
}
echo(toJson($res));

} // End try
catch (NoSessionException $e) {
  echo(toJson(['status' => 'NOSESSION']));
}
catch (\Exception $e) {
  echo(toJson(['status' => 'FAILED', 'msg' => $e->getMessage()]));
}
?>
