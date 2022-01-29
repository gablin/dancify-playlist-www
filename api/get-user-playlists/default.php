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
$uid = $json['userId'];
if (strlen($uid) == 0) {
  fail("illegal userId value: $uid");
}
$limit = array_key_exists('limit', $json) ? $json['limit'] : 50;
if (!is_int($limit) || $limit < 0) {
  fail("illegal limit value: $limit");
}
$offset = array_key_exists('offset', $json) ? $json['offset'] : 0;
if (!is_int($offset) || $offset < 0) {
  fail("illegal offset value: $offset");
}

$options = [ 'limit' => $limit
           , 'offset' => $offset
           , 'fields' => 'items(name,id),total'
           ];
$res = $api->getUserPlaylists($uid, $options);
$playlists = array_map( function($i) {
                          return [ 'name' => $i->name, 'id' => $i->id ];
                        }
                      , $res->items
                      );
echo( toJson( [ 'status' => 'OK'
              , 'playlists' => $playlists
              , 'total' => $res->total
              ]
            )
    );

} // End try
catch (\Exception $e) {
  echo(toJson(['status' => 'FAILED', 'msg' => $e->getMessage()]));
}
?>
