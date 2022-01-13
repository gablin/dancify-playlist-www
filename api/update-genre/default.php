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
if (!array_key_exists('genre', $json)) {
  fail('genre missing');
}
if (!is_int($json['genre'])) {
  fail('not an integer: genre');
}

connectDb();

// Check if entry exists
$tid_sql = escapeSqlValue($json['trackId']);
$cid_sql = escapeSqlValue(getSession()->getClientId());
$genre_sql = escapeSqlValue($json['genre']);
$res = queryDb( "SELECT genre FROM genre " .
                "WHERE song = '$tid_sql' AND user = '$cid_sql'"
              );
if ($res->num_rows == 1) {
  if ($genre_sql != 0) {
    queryDb( "UPDATE genre SET genre = $genre_sql " .
             "WHERE song = '$tid_sql' AND user = '$cid_sql'"
           );
  }
  else {
    queryDb("DELETE FROM genre WHERE song = '$tid_sql' AND user = '$cid_sql'");
  }
}
else {
  if (strlen($genre_sql) > 0) {
    queryDb( "INSERT INTO genre (song, user, genre) " .
             "VALUES ('$tid_sql', '$cid_sql', $genre_sql)"
           );
  }
}

echo(toJson(['status' => 'OK']));

} // End try
catch (\Exception $e) {
  echo(toJson(['status' => 'FAILED', 'msg' => $e->getMessage()]));
}
?>
