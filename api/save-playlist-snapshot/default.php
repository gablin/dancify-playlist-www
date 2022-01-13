<?php
require '../../autoload.php';

function fail($msg) {
  throw new \Exception($msg);
}

try {

if (!hasSession()) {
  fail('no session');
}

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
$pid = $json['playlistId'];
$snapshot = toJson($json['snapshot']);
$res = queryDb("SELECT playlist FROM snapshots WHERE playlist = '$pid'");
if ($res->num_rows == 1) {
  queryDb("UPDATE snapshots SET snapshot = '$snapshot' WHERE playlist = '$pid'");
}
else {
  queryDb( "INSERT INTO snapshots (playlist, snapshot) " .
           "VALUES ('$pid', '$snapshot')"
         );
}

echo(toJson(['status' => 'OK']));

} // End try
catch (\Exception $e) {
  echo(toJson(['status' => 'FAILED', 'msg' => $e->getMessage()]));
}
?>
