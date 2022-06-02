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
if (!array_key_exists('scratchpad', $json)) {
  fail('scratchpad missing');
}

connectDb();

// Check if entry exists
$user_sql = escapeSqlValue(getThisUserId($api));
$scratchpad_sql = escapeSqlValue(toJson($json['scratchpad']));
$res = queryDb( "SELECT scratchpad FROM global_scratchpads " .
                "WHERE user = '$user_sql'"
              );
if ($res->num_rows == 1) {
  queryDb( "UPDATE global_scratchpads SET scratchpad = '$scratchpad_sql' " .
           "WHERE user = '$user_sql'"
         );
}
else {
  queryDb( "INSERT INTO global_scratchpads (user, scratchpad) " .
           "VALUES ('$user_sql', '$scratchpad_sql')"
         );
}

echo(toJson(['status' => 'OK']));

} // End try
catch (NoSessionException $e) {
  echo(toJson(['status' => 'NOSESSION']));
}
catch (\Exception $e) {
  echo(toJson(['status' => 'FAILED', 'msg' => $e->getMessage()]));
}
?>
