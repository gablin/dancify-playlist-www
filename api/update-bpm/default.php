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
if (!array_key_exists('trackId', $json)) {
  fail('trackId missing');
}
if (!array_key_exists('bpm', $json)) {
  fail('bpm missing');
}

connectDb();

// Check if track exists
$tid = $json['trackId'];
$bpm = $json['bpm'];
$res = queryDb("SELECT bpm FROM bpm WHERE song = '$tid'");
if ($res->num_rows == 1) {
  queryDb("UPDATE bpm SET bpm = $bpm WHERE song = '$tid'");
}
else {
  queryDb("INSERT INTO bpm (song, bpm) VALUES ('$tid', $bpm)");
}

echo(toJson(['status' => 'OK']));

} // End try
catch (\Exception $e) {
  echo(toJson(['status' => 'FAILED', 'msg' => $e->getMessage()]));
}
?>
