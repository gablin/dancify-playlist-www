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

connectDb();
$user_sql = escapeSqlValue(getThisUserId($api));
$res = queryDb( "SELECT scratchpad FROM global_scratchpads " .
                "WHERE user = '$user_sql'"
              );
if ($res->num_rows == 0) {
  echo(toJson(['status' => 'NOT-FOUND']));
  die();
}
$scratchpad = fromJson($res->fetch_assoc()['scratchpad']);
echo(toJson(['status' => 'OK', 'scratchpad' => $scratchpad]));

} // End try
catch (NoSessionException $e) {
  echo(toJson(['status' => 'NOSESSION']));
}
catch (\Exception $e) {
  echo(toJson(['status' => 'FAILED', 'msg' => $e->getMessage()]));
}
?>
