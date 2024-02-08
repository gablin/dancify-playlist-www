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
if ( !array_key_exists('trackPlayLength', $json) &&
     !array_key_exists('fadeOutLength', $json)
   ) {
  fail('trackPlayLength/fadeOutLength missing');
}

connectDb();

// Check if entry exists
$user_sql = escapeSqlValue(getThisUserId($api));
$res = queryDb( "SELECT track_play_length_s, fade_out_s FROM playback " .
                "WHERE user = '$user_sql'"
              );
$row = $res->fetch_assoc();
$play_len_sql = array_key_exists('trackPlayLength', $json)
                  ? escapeSqlValue($json['trackPlayLength'])
                  : ($res->num_rows == 1 ? $row ['track_play_length_s'] : 0);
$fade_out_len_sql = array_key_exists('fadeOutLength', $json)
                      ? escapeSqlValue($json['fadeOutLength'])
                      : ($res->num_rows == 1 ? $row['fade_out_s'] : 0);
if ($res->num_rows == 1) {
  if ($play_len_sql > 0 || $fade_out_len_sql > 0) {
    queryDb( "UPDATE playback SET track_play_length_s = $play_len_sql " .
             "                  , fade_out_s = $fade_out_len_sql " .
             "WHERE user = '$user_sql'"
           );
  }
  else {
    queryDb("DELETE FROM playback WHERE user = '$user_sql'");
  }
}
else {
  if ($play_len_sql > 0 || $fade_out_len_sql > 0) {
    queryDb( "INSERT INTO playback " .
             "  (user, track_play_length_s, fade_out_s) " .
             "VALUES ('$user_sql', $play_len_sql, $fade_out_len_sql)"
           );
  }
}

echo(toJson(['status' => 'OK']));

} // End try
catch (NoSessionException $e) {
  echo(toJson(['status' => 'NOSESSION']));
}
catch (\Exception $e) {
  echo(toJson(['status' => 'FAILED', 'msg' => $e->getMessage()]));
}
?>
