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
if (!array_key_exists('snapshot', $json)) {
  fail('snapshot missing');
}

connectDb();

// Check if entry exists
$pid_sql = escapeSqlValue($json['playlistId']);
$user_sql = escapeSqlValue(getThisUserId($api));
$snapshot_sql = escapeSqlValue(toJson($json['snapshot']));
$res = queryDb( "SELECT playlist FROM snapshots " .
                "WHERE playlist = '$pid_sql' AND user = '$user_sql'"
              );
if ($res->num_rows == 1) {
  queryDb( "UPDATE snapshots SET snapshot = '$snapshot_sql' " .
           "WHERE playlist = '$pid_sql' AND user = '$user_sql'"
         );
}
else {
  queryDb( "INSERT INTO snapshots (playlist, user, snapshot) " .
           "VALUES ('$pid_sql', '$user_sql', '$snapshot_sql')"
         );
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
