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
if (!array_key_exists('trackIdList', $json)) {
  fail('trackIdList missing');
}
if (!array_key_exists('trackBpmList', $json)) {
  fail('trackBpmList missing');
}
if (!array_key_exists('trackCategoryList', $json)) {
  fail('trackCategoryList missing');
}
if (!array_key_exists('bpmRangeList', $json)) {
  fail('bpmRangeList missing');
}
if (!array_key_exists('minBpmDistanceList', $json)) {
  fail('minBpmDistanceList missing');
}
if (!array_key_exists('danceSlotSameCategory', $json)) {
  fail('danceSlotSameCategory missing');
}
$track_ids = $json['trackIdList'];
$bpms = $json['trackBpmList'];
$categories = $json['trackCategoryList'];
if (count($track_ids) == 0) {
  fail('no track IDs');
}
if (count($bpms) == 0) {
  fail('no BPMs');
}
if (count($categories) == 0) {
  fail('no categories');
}
if (count($track_ids) != count($bpms) || count($track_ids) != count($categories)) {
  fail('inconsistent number of track IDs, BPMs and/or categories');
}
$ranges = $json['bpmRangeList'];
$min_bpm_dists = $json['minBpmDistanceList'];
if (count($ranges) == 0) {
  fail('no ranges');
}
if (count($min_bpm_dists) == 0) {
  fail('no min-BPM distances');
}
if (count($ranges) != count($min_bpm_dists)+1) {
  fail('inconsistent number of ranges and min-BPM distances');
}

// Randomize track order
$a = array_zip($track_ids, $bpms, $categories);
shuffle($a);
$track_ids  = array_map(function ($l) { return $l[0]; }, $a);
$bpms       = array_map(function ($l) { return $l[1]; }, $a);
$categories = array_map(function ($l) { return $l[2]; }, $a);

$uniq_categories = array_values(array_unique($categories));
$int_categories = array_map( function ($g) use ($uniq_categories) {
                               return array_search($g, $uniq_categories);
                             }
                           , $categories
                           );

// Generate model input
$dzn_content = "";
$dzn_content .= "bpm = [" . implode(',', $bpms) . "];\n";
$dzn_content .= "categories = [" . implode(',', $int_categories) . "];\n";
$dzn_content .= "ranges = [|" .
                 implode( '|'
                        , array_map( function ($l) { return implode(',', $l); }
                                   , $ranges
                                   )
                        ) .
                "|];\n";
$dzn_content .= "min_bpm_distance = [" . implode(',', $min_bpm_dists) . "];\n";
$dzn_content .= "dance_slot_same_category = " .
                ($json['danceSlotSameCategory'] ? "true" : "false") .
                "\n;";
$dir = createTempDir();
$clean_up_fun = function () use ($dir) { shell_exec("rm -rf $dir"); };
if ($dir === false) {
  fail('failed to create temp dir');
}
$dzn_file = $dir . '/input.dzn';
$fh = fopen($dzn_file, 'w');
if ($fh === false) {
  $clean_up_fun();
  fail('failed to open model input file');
}
if (fwrite($fh, $dzn_content) === false) {
  $clean_up_fun();
  fail('failed to write model input file');
}

// Solve model and get output
$time_limit_s = 10;
$time_limit_ms = $time_limit_s*1000;
$res = shell_exec( "minizinc model.mzn $dzn_file " .
                   "--time-limit $time_limit_ms " .
                   "--unbounded-msg '' " .
                   "--unknown-msg '' " .
                   "--search-complete-msg '' " .
                   "--soln-sep '' " .
                   "2> /dev/null"
                 );
$json = fromJson($res);
if (is_null($json)) {
  $clean_up_fun();
  fail("no solution found");
}

// Build new track list
$new_track_list = [];
foreach ($json['trackOrder'] as $i) {
  $new_track_list[] = $i > 0 ? $track_ids[$i-1] : '';
}

$clean_up_fun();

echo(toJson(['status' => 'OK', 'trackOrder' => $new_track_list]));

} // End try
catch (\Exception $e) {
  echo(toJson(['status' => 'FAILED', 'msg' => $e->getMessage()]));
}
?>
