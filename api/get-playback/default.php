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

connectDb();
$pid_sql = escapeSqlValue($json['playlistId']);
$user_sql = escapeSqlValue(getThisUserId($api));
$res = queryDb( "SELECT track_play_length_s, fade_out_s FROM playback " .
                "WHERE playlist = '$pid_sql' AND user = '$user_sql'"
              );
if ($res->num_rows == 0) {
  echo(toJson(['status' => 'NOT-FOUND']));
  die();
}
$play_length_s = $res->fetch_assoc()['track_play_length_s'];
$fade_out_s = $res->fetch_assoc()['fade_out_s'];
echo( toJson( [ 'status' => 'OK'
              , 'trackPlayLength' => $play_length_s
              , 'fadeOutLength' => $fade_out_s
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
