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
if (!array_key_exists('action', $json)) {
  fail('action missing');
}
if (!array_key_exists('device', $json)) {
  fail('device missing');
}

$action = $json['action'];
$device = $json['device'];
switch ($action) {
  case 'play': {
    if (!array_key_exists('track', $json)) {
      fail('track missing');
    }
    if (!array_key_exists('positionMs', $json)) {
      fail('positionMs missing');
    }
    $res = $api->play( $device
                     , [ 'uris' => ['spotify:track:' . $json['track']]
                       , 'position_ms' => $json['positionMs']
                       ]
                     );
    if (!$res) {
      fail('failed to play track');
    }
    echo(toJson(['status' => 'OK']));
    break;
  }

  default: {
    fail("unknown action: $action");
  }
}

} // End try
catch (NoSessionException $e) {
  echo(toJson(['status' => 'NOSESSION']));
}
catch (\Exception $e) {
  echo(toJson(['status' => 'FAILED', 'msg' => $e->getMessage()]));
}
?>
