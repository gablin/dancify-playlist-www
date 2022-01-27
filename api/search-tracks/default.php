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
if (!array_key_exists('genre', $json)) {
  fail('genre missing');
}
$genre = $json['genre'];
if (strlen($genre) == 0) {
  fail("illegal genre value: $genre");
}
$limit = array_key_exists('limit', $json) ? $json['limit'] : 50;
if (!is_int($limit) || $limit < 0) {
  fail("illegal limit value: $limit");
}
$offset = array_key_exists('offset', $json) ? $json['offset'] : 0;
if (!is_int($offset) || $offset < 0) {
  fail("illegal offset value: $offset");
}
$only_in_client_playlist =
  array_key_exists('onlyInClientPlaylists', $json) ? $json['onlyInClientPlaylists']
                                                 : false;
if (!is_bool($only_in_client_playlist)) {
  fail("illegal onlyInClientPlaylists value: $only_client_playlist");
}

connectDb();
$genre_sql = escapeSqlValue($genre);
$limit_sql = escapeSqlValue($limit);
$offset_sql = escapeSqlValue($offset);
$client_id_sql = escapeSqlValue($session->getClientId());

// Find tracks
$sql = "SELECT song FROM genre WHERE genre = $genre_sql";
if ($only_in_client_playlist) {
  $sql .= " AND client = '$client_id_sql'";
}
$sql .= " LIMIT $limit_sql OFFSET $offset_sql";
$res = queryDb($sql);
$tracks = [];
while ($row = $res->fetch_assoc()) {
  $tracks[] = $row['song'];
}

echo(toJson(['status' => 'OK', 'trackIds' => $tracks]));

} // End try
catch (\Exception $e) {
  echo(toJson(['status' => 'FAILED', 'msg' => $e->getMessage()]));
}
?>
