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
if (!array_key_exists('bpmRange', $json)) {
  fail('bpm range missing');
}
$bpm_range = $json['bpmRange'];
if (!is_array($bpm_range)) {
  fail('bpm range not an array');
}
if (count($bpm_range) != 2) {
  fail('bpm range list != 2');
}
if (!is_int($bpm_range[0]) || !is_int($bpm_range[1])) {
  fail('bpm range has illegal values');
}
$genre = null;
if (array_key_exists('genre', $json)) {
  $genre = $json['genre'];
}
if (!is_null($genre) && strlen($genre) == 0) {
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
$only_in_my_playlist =
  array_key_exists('onlyInMyPlaylists', $json) ? $json['onlyInMyPlaylists']
                                                 : false;
if (!is_bool($only_in_my_playlist)) {
  fail("illegal onlyInMyPlaylists value: $only_in_my_playlist");
}

connectDb();
$limit_sql = escapeSqlValue($limit);
$offset_sql = escapeSqlValue($offset);

// Find tracks
$sql = "SELECT genre.song FROM genre INNER JOIN bpm ON genre.song = bpm.song " .
       "WHERE bpm >= $bpm_range[0] AND bpm <= $bpm_range[1]";
if (!is_null($genre)) {
  $sql .= " AND genre = " . escapeSqlValue($genre);
}
if ($only_in_my_playlist) {
  $sql .= " AND user = '" . escapeSqlValue(getThisUserId($api)) . "'";
}
$sql .= " LIMIT $limit_sql OFFSET $offset_sql";
$res = queryDb($sql);
$tracks = [];
while ($row = $res->fetch_assoc()) {
  $tracks[] = $row['song'];
}

echo(toJson(['status' => 'OK', 'trackIds' => $tracks]));

} // End try
catch (NoSessionException $e) {
  echo(toJson(['status' => 'NOSESSION']));
}
catch (\Exception $e) {
  echo(toJson(['status' => 'FAILED', 'msg' => $e->getMessage()]));
}
?>
