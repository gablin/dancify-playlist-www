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
if (!is_int($json['bpm'])) {
  fail('not an integer: bpm');
}

connectDb();

// Check if entry exists
$tid_sql = escapeSqlValue($json['trackId']);
$bpm_sql = escapeSqlValue($json['bpm']);
$res = queryDb("SELECT bpm FROM bpm WHERE song = '$tid_sql'");
if ($res->num_rows == 1) {
  queryDb("UPDATE bpm SET bpm = $bpm_sql WHERE song = '$tid_sql'");
}
else {
  queryDb("INSERT INTO bpm (song, bpm) VALUES ('$tid_sql', $bpm_sql)");
}

echo(toJson(['status' => 'OK']));

} // End try
catch (\Exception $e) {
  echo(toJson(['status' => 'FAILED', 'msg' => $e->getMessage()]));
}
?>
