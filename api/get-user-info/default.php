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
$uid = $json['userId'];
if (strlen($uid) == 0) {
  fail("illegal userId value: $uid");
}

$res = $api->getUser($uid);
$name = isset($res->display_name) ? $res->display_name : $uid;
echo( toJson( [ 'status' => 'OK'
              , 'id' => $uid
              , 'name' => $name
              ]
            )
    );

} // End try
catch (NoSessionException $e) {
  echo(toJson(['status' => 'NOSESSION']));
}
catch (\Exception $e) {
  echo(toJson(['status' => 'FAILED', 'msg' => $e->getMessage()]));
}
?>
