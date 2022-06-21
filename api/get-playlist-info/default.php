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
if (!array_key_exists('playlistId', $json)) {
  fail('playlistId missing');
}
$pid = $json['playlistId'];
if (strlen($pid) == 0) {
  fail("illegal playlistId value: $pid");
}

$playlist_info = loadPlaylistInfo($api, $pid);
echo( toJson( [ 'status' => 'OK'
              , 'info' => [ 'id' => $pid
                          , 'name' => $playlist_info->name
                          , 'isPublic' => $playlist_info->public
                          ]
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
