<?php
require '../../../autoload.php';
require '../functions.php';

ensureSession();
$session = getSession();
$api = createWebApi($session);

connectDb();

beginPage();
createMenu( mkMenuItemShowPlaylists($api)
          , mkMenuItemShowPlaylistTracks($api)
          );
beginContent();
try {
$playlist_id = fromGET('playlist_id');
$playlist_info = loadPlaylistInfo($api, $playlist_id);
$tracks = [];
foreach (loadPlaylistTracks($api, $playlist_id) as $t) {
  $tracks[] = $t->track;
}
$audio_feats = loadTrackAudioFeatures($api, $tracks);
?>

<form name="playlist">

<div>
  <button id="randomizeBtn"><?php echo(LNG_BTN_RANDOMIZE); ?></button>
</div>
<div id="new-playlist-area" style="display: none;">
  <div class="input" style="margin-top: 2em;">
    <?php echo(LNG_INSTR_ENTER_NAME_OF_NEW_PLAYLIST); ?>:
    <input type="text" name="new_playlist_name"></input>
  </div>
  <div>
    <button id="saveBtn">
      <?php echo(LNG_BTN_SAVE_AS_NEW_PLAYLIST); ?>
    </button>
  </div>
</div>

<table class="bpm-range-area">
  <tbody>
    <tr class="range">
      <td class="track">
        <?php echo(LNG_DESC_BPM_RANGE_TRACK) ?> <span>1</span>
      </td>
      <td class="label">
        <?php echo(LNG_DESC_BPM) ?>: <span></span>
      </td>
      <td class="range-controller">
        <div></div>
      </td>
      <td>
        <button class="add lowlight">+</button>
        <button class="remove lowlight">-</button>
      </td>
    </tr>
    <tr class="difference">
      <td></td>
      <td class="label">
        <?php echo(LNG_DESC_BPM_DIFFERENCE) ?>: <span></span>
      </td>
      <td class="difference-controller">
        <div></div>
      </td>
      <td></td>
    </tr>
    <tr class="range">
      <td class="track">
        <?php echo(LNG_DESC_BPM_RANGE_TRACK) ?> <span>2</span>
      </td>
      <td class="label">
        <?php echo(LNG_DESC_BPM) ?>: <span></span>
      </td>
      <td class="range-controller">
        <div></div>
      </td>
      <td>
        <button class="add lowlight">+</button>
        <button class="remove lowlight">-</button>
      </td>
    </tr>
  <tbody>
</table>
<label>
  <input type="checkbox" id="chkboxDanceSlotSameCategory"
    name="dance-slot-has-same-category" value="true" />
  <span class="checkmark"></span>
  <?php echo(LNG_DESC_DANCE_SLOT_SAME_CATEGORY) ?>
</label>

<table id="playlist" class="tracks">
  <thead>
    <tr>
      <th></th>
      <th class="bpm"><?php echo(LNG_HEAD_BPM); ?></th>
      <th class="category"><?php echo(LNG_HEAD_CATEGORY_SHORT); ?></th>
      <th><?php echo(LNG_HEAD_TITLE); ?></th>
    </tr>
  </thead>
  <tbody>
    <?php
    for ($i = 0; $i < count($tracks); $i++) {
      $t = $tracks[$i];

      // Get BPM
      $bpm = (int) $audio_feats[$i]->tempo;
      $tid = $t->id;
      $res = queryDb("SELECT bpm FROM bpm WHERE song = '$tid'");
      if ($res->num_rows == 1) {
        $bpm = $res->fetch_assoc()['bpm'];
      }

      // Get category
      $category = '';
      $cid = $session->getClientId();
      $res = queryDb( "SELECT category FROM category " .
                      "WHERE song = '$tid' AND user = '$cid'"
                    );
      if ($res->num_rows == 1) {
        $category = $res->fetch_assoc()['category'];
      }

      $artists = formatArtists($t);
      $title = $artists . " - " . $t->name;
      $length = $t->duration_ms;
      $preview_url = $t->preview_url;
      ?>
      <tr class="track">
        <input type="hidden" name="track_id" value="<?= $tid ?>" />
        <input type="hidden" name="preview_url" value="<?= $preview_url ?>" />
        <td class="index"><?php echo($i+1); ?></td>
        <td class="bpm">
          <input type="text" name="bpm" class="bpm" value="<?= $bpm ?>" />
        </td>
        <td class="category">
          <input type="text" name="category" class="category"
                 value="<?= $category ?>" />
        </td>
        <td class="title">
          <?php echo($title); ?>
        </td>
      </tr>
      <?php
    }
    ?>
  </tbody>
</table>

</form>

<script src="/js/playlist.js.php"></script>
<script src="/js/play-preview.js.php"></script>
<script src="/js/randomize-by-bpm.js.php"></script>
<script type="text/javascript">
$(document).ready(
  function() {
    var form = $('form[name=playlist]');
    var table = $('table.tracks');

    // Disable submission
    form.submit(function() { return false; });

    setupPlaylistElementsForPreview(form, table);

    setupSaveButton(form, <?= $playlist_info->public ? 'true' : 'false' ?>);
    setupRandomizeByBpm(form, table);
  }
);
</script>

<?php
}
catch (Exception $e) {
  showError($e->getMessage());
}
endContent();
endPage();
updateTokens($session);
?>
