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
if (!array_key_exists('category', $json)) {
  fail('category missing');
}

connectDb();

// Check if entry exists
$tid = $json['trackId'];
$cid = getSession()->getClientId();
$category = trim($json['category']);
$res = queryDb( "SELECT category FROM category " .
                "WHERE song = '$tid' AND user = '$cid'"
              );
if ($res->num_rows == 1) {
  if (strlen($category) > 0) {
    queryDb( "UPDATE category SET category = '$category' " .
             "WHERE song = '$tid' AND user = '$cid'"
           );
  }
  else {
    queryDb("DELETE FROM category WHERE song = '$tid' AND user = '$cid'");
  }
}
else {
  if (strlen($category) > 0) {
    queryDb( "INSERT INTO category (song, user, category) " .
             "VALUES ('$tid', '$cid', '$category')"
           );
  }
}

echo(toJson(['status' => 'OK']));

} // End try
catch (\Exception $e) {
  echo(toJson(['status' => 'FAILED', 'msg' => $e->getMessage()]));
}
?>
