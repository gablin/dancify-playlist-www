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
if (!array_key_exists('playlistId', $json)) {
  fail('playlistId missing');
}
$playlist_id = $json['playlistId'];

connectDb();
$res = queryDb("SELECT snapshot FROM snapshots WHERE playlist = '$playlist_id'");
if ($res->num_rows == 0) {
  echo(toJson(['status' => 'NOT-FOUND']));
  die();
}
$snapshot = fromJson($res->fetch_assoc()['snapshot']);
echo(toJson(['status' => 'OK', 'snapshot' => $snapshot]));

} // End try
catch (\Exception $e) {
  echo(toJson(['status' => 'FAILED', 'msg' => $e->getMessage()]));
}
?>
