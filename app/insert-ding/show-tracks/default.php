<?php
require '../../../autoload.php';
require '../functions.php';

ensureSession();
$session = getSession();
$api = createWebApi($session);
ensureAuthorizedUser($api);

// Check if 'save' button was pushed. If so, forward GET to next page
if (hasGET('save')) {
  header("Location: ./new-playlist/?{$_SERVER['QUERY_STRING']}");
  die();
}

beginPage();
createMenu( mkMenuItemShowPlaylists($api)
          , mkMenuItemShowPlaylistTracks($api)
          );
beginContent();
try {
$playlist_id = fromGET('playlist_id');
$tracks = loadPlaylistTracks($api, $playlist_id);

// Get track to insert (if specified)
$ins_track = null;
if (hasGET('track')) {
  $error = '';
  $track_id = getTrackId($_GET['track']);
  if (strlen($track_id) > 0) {
    try {
      $ins_track = $api->getTrack($track_id);
    }
    catch (Exception $e) {
      $error = sprintf('%s: %s', LNG_ERR_FAILED_LOAD_TRACK, $e->getMessage());
    }
  }
  else {
    $error = LNG_ERR_INVALID_TRACK_FORMAT;
  }

  if (strlen($error) > 0) {
    showError($error);
  }
}
else {
  $_GET['track'] = '';
}

// Get insertion frequency (if specified)
$ins_freq = null;
if (hasGET('freq')) {
  $error = '';
  $i = $_GET['freq'];
  if (is_numeric($i)) {
    $ins_freq = intval($i);
    if ($ins_freq > 0) {
      $_GET['freq'] = $ins_freq;
    }
    else {
      $error = sprintf(LNG_ERR_FREQ_MUST_BE_GT, 0);
    }
  }
  else {
    $error = LNG_ERR_FREQ_NOT_INT;
  }

  if (strlen($error) > 0) {
    showError($error);
  }
}
else {
  $_GET['freq'] = '';
}

$has_ins = !(is_null($ins_track) || is_null($ins_freq));
?>

<form action="." method="GET">
  <input type="hidden" name="playlist_id" value="<?php echo($playlist_id); ?>"></input>
  <?php
  if ($has_ins) {
    ?>
    <input type="hidden" name="track_id" value="<?php echo($track_id); ?>"></input>
    <?php
  }
  ?>
  <div class="input">
  <?php echo(sprintf(LNG_INSTR_ENTER_SONG, 'Song Link', 'Spotify URI')); ?>:
    <input type="text" name="track" value="<?php echo($_GET['track']); ?>"></input>
    <a class="small_button" href="#" onclick="$('div.song_link_help').show();">?</a>
  </div>
  <div class="song_link_help" onclick="$(this).hide();">
    <div class="background"></div>
    <div class="info">
      <h1><?php echo(LNG_DESC_INSTRUCTIONS); ?></h1>
      <p><?php echo(LNG_TXT_SONG_LINK_HELP); ?></p>
      <img src="/images/song-link.png"></img>
    </div>
  </div>
  <div class="input">
    <?php echo(sprintf(LNG_INSTR_ENTER_FREQ, "<input type=\"text\" name=\"freq\" value=\"{$_GET['freq']}\" class=\"number centered\"></input>")); ?>
  </div>
  <div>
    <input class="button" type="submit" name="preview" value="<?php echo(LNG_BTN_PREVIEW_RESULT); ?>"></input>
    <?php
    if ($has_ins) {
      ?>
      <input class="button" style="margin-left: 1em;" type="submit" name="save" value="<?php echo(LNG_BTN_SAVE_AS_NEW_PLAYLIST); ?>"></input>
      <?php
    }
    ?>
  </div>
</form>

<table class="tracks">
  <tr>
    <th></th>
    <th><?php echo(LNG_HEAD_TITLE); ?></th>
    <th class="length"><?php echo(LNG_HEAD_LENGTH); ?></th>
  </tr>
  <?php
  $index = 1;
  if ($has_ins) {
    $ins_artists = formatArtists($ins_track);
    $ins_title = $ins_artists . " - " . $ins_track->name;
    $ins_length = $ins_track->duration_ms;
    $ins_i = 0;
  }
  $playlist_length = 0;
  $playlist_length_wo_ins = 0;
  foreach ($tracks as $t) {
    $t = $t->track;

    // Insert track (if specified)
    if ($has_ins) {
      if ($ins_i >= $ins_freq) {
        ?>
        <tr>
          <td class="insert index"></td>
          <td class="insert">
            <?php echo($ins_title); ?>
          </td>
          <td class="insert length">
            <?php echo(formatTrackLength($ins_length)); ?>
          </td>
        </tr>
        <?php
        $playlist_length += $ins_length;
        $ins_i = 1;
      }
      else {
        $ins_i += 1;
      }
    }

    $artists = formatArtists($t);
    $title = $artists . " - " . $t->name;
    $length = $t->duration_ms;
    $playlist_length += $length;
    $playlist_length_wo_ins += $length;
    ?>
    <tr>
      <td class="index"><?php echo($index); ?></td>
      <td>
        <?php echo($title); ?>
      </td>
      <td class="length">
        <?php echo(formatTrackLength($length)); ?>
      </td>
    </tr>
    <?php
    $index += 1;
  }
  ?>
  <tr>
    <td colspan="3" class="sep"></td>
  </tr>
  <?php
  if ($has_ins) {
    ?>
    <tr>
      <td class="index"></td>
      <td class="summary">
        <?php echo(sprintf( '%s (%s):'
                          , LNG_DESC_TOTAL_PLAYLIST_LENGTH
                          , LNG_DESC_BEFORE)
                          ); ?>
      </td>
      <td class="summary length">
        <?php echo(formatTrackLength($playlist_length_wo_ins)); ?>
      </td>
    </tr>
    <?php
    }
  ?>
  <tr>
    <?php
    if ($has_ins) {
      $class_str = ' after';
      $desc_str = sprintf(' (%s)', LNG_DESC_AFTER);
    }
    else {
      $class_str = '';
      $desc_str = '';
    }
    ?>
    <td class="index<?php echo($class_str); ?>"></td>
    <td class="summary<?php echo($class_str); ?>">
      Total playlist length<?php echo($desc_str); ?>:
    </td>
    <td class="summary length<?php echo($class_str); ?>">
      <?php echo(formatTrackLength($playlist_length)); ?>
    </td>
  </tr>
  <?php
  if ($has_ins) {
    ?>
    <tr>
      <td class="index after"></td>
      <td class="summary after">
        <?php echo(LNG_DESC_DIFFERENCE); ?>:
      </td>
      <td class="summary length after">
        +<?php echo(formatTrackLength($playlist_length - $playlist_length_wo_ins)); ?>
      </td>
    </tr>
    <?php
    }
  ?>
</table>

<?php
}
catch (Exception $e) {
  showError($e->getMessage());
}
endContent();
endPage();
updateTokens($session);
?>