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
$json = fromJson($_POST['data'], true);
if (is_null($json)) {
  fail("POST field 'data' not in JSON format");
}

// Check data
$required_fields = [ 'trackIdList'
                   , 'trackLengthList'
                   , 'trackBpmList'
                   , 'trackEnergyList'
                   , 'trackGenreList'
                   , 'bpmRangeList'
                   , 'bpmDifferenceList'
                   , 'danceSlotSameGenre'
                   , 'danceLengthRange'
                   ];
foreach ($required_fields as $field) {
  if (!array_key_exists($field, $json)) {
    fail("$field missing");
  }
}

$track_ids = $json['trackIdList'];
$track_lengths = $json['trackLengthList'];
$bpms = $json['trackBpmList'];
$energies = $json['trackEnergyList'];
$genres = $json['trackGenreList'];
$dance_length_range = $json['danceLengthRange'];
if (count($track_ids) == 0) {
  fail('no tracks');
}
if ( count($track_ids) != count($track_lengths) ||
     count($track_ids) != count($bpms) ||
     count($track_ids) != count($energies) ||
     count($track_ids) != count($genres)
   )
{
  fail(
    'inconsistent number of track IDs, lengths, BPMs, energies, and/or genres'
  );
}
if (count($dance_length_range) != 2) {
  fail('unexpected number of values in danceLengthRange');
}
$ranges = $json['bpmRangeList'];
$bpm_diffs = $json['bpmDifferenceList'];
$energy_diffs = $json['energyDifferenceList'];
if ( count($ranges) != count($bpm_diffs)+1 ||
     count($ranges) != count($energy_diffs)+1
   ) {
  fail('inconsistent number of BPM ranges, energy, ranges and differences');
}

// Randomize track order
$a = array_zip($track_ids, $track_lengths, $bpms, $energies, $genres);
shuffle($a);
$track_ids     = array_map(function ($l) { return $l[0]; }, $a);
$track_lengths = array_map(function ($l) { return $l[1]; }, $a);
$bpms          = array_map(function ($l) { return $l[2]; }, $a);
$energies      = array_map(function ($l) { return $l[3]; }, $a);
$genres        = array_map(function ($l) { return $l[4]; }, $a);

// Generate model input
$dzn_content = "";
$dzn_content .= "bpm = [" . implode(',', $bpms) . "];\n";
$dzn_content .= "energy = [" . implode(',', $energies) . "];\n";
$dzn_content .= "genres = [" . implode(',', $genres) . "];\n";
$dzn_content .= "lengths = [" . implode(',', $track_lengths) . "];\n";
$dzn_content .= "min_length = $dance_length_range[0];\n";
$dzn_content .= "max_length = $dance_length_range[1];\n";
$dzn_content .= "ranges = [|" .
                 implode( '|'
                        , array_map( function ($l) { return implode(',', $l); }
                                   , $ranges
                                   )
                        ) .
                "|];\n";
$dzn_content .= "bpm_diffs = [|" .
                 implode( '|'
                        , array_map( function ($l) { return implode(',', $l); }
                                   , $bpm_diffs
                                   )
                        ) .
                "|];\n";
$dzn_content .= "energy_diffs = [|" .
                 implode( '|'
                        , array_map( function ($l) { return implode(',', $l); }
                                   , $energy_diffs
                                   )
                        ) .
                "|];\n";
$dzn_content .= "dance_slot_same_genre = " .
                ($json['danceSlotSameGenre'] ? "true" : "false") . ";\n";
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
$time_limit_s = 60;
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
catch (NoSessionException $e) {
  echo(toJson(['status' => 'NOSESSION']));
}
catch (\Exception $e) {
  echo(toJson(['status' => 'FAILED', 'msg' => $e->getMessage()]));
}
?>
