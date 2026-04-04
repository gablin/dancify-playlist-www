<?php
require '../../autoload.php';

function fail($msg) {
  throw new \Exception($msg);
}

try {

if (!hasSession()) {
  throw new NoSessionException();
}

// Parse JSON data
if (!isset($_POST['data'])) {
  fail("missing required POST field: data");
}
$json_str = $_POST['data'];
$json = fromJson($json_str, true);
if (is_null($json)) {
  fail("POST field 'data' not in JSON format");
}

// Check data
$required_fields = [ 'numSlots'
                   , 'conflictGroups'
                   ];
foreach ($required_fields as $field) {
  if (!array_key_exists($field, $json)) {
    fail("$field missing");
  }
}

$num_slots = $json['numSlots'];
$conflict_groups = $json['conflictGroups'];
if ($num_slots <= 0) {
  fail('no slots');
}
if (count($conflict_groups) == 0) {
  fail('no conflict groups');
}
foreach ($conflict_groups as $group) {
  foreach ($group as $s) {
    if ($s >= $num_slots) {
      fail('slot in conflict group out of bound');
    }
  }
}

// Generate model input
$dzn_content = "";
$dzn_content .= "num_slots = $num_slots;\n";
$dzn_content .=
  "groups = [" .
   implode(
     ','
   , array_map(
       fn($vs) => '{' . implode(',', array_map(fn($v) => $v+1, $vs)) . '}'
     , $conflict_groups
     )
   ) .
  "];\n";
$dir = createTempDir();
$clean_up_fun = function () use ($dir) { shell_exec("rm -rf $dir"); };
if ($dir === false) {
  fail('failed to create temp dir');
}
$input_file = $dir . '/input.json';
$fh = fopen($input_file, 'w');
if ($fh === false) {
  $clean_up_fun();
  fail('failed to open solver input file');
}
if (fwrite($fh, $json_str) === false) {
  $clean_up_fun();
  fail('failed to write solver input file');
}
$time_limit_s = 60;
$num_workers = 6;
$res =
  shell_exec("./solve.py $input_file $time_limit_s $num_workers 2> /dev/null");
$json = fromJson($res);
if (is_null($json)) {
  $clean_up_fun();
  fail("no solution found: $dir");
}

$clean_up_fun();

echo(toJson(['status' => 'OK', 'slotOrder' => $json['order']]));

} // End try
catch (NoSessionException $e) {
  echo(toJson(['status' => 'NOSESSION']));
}
catch (\Exception $e) {
  echo(toJson(['status' => 'FAILED', 'msg' => $e->getMessage()]));
}
?>
