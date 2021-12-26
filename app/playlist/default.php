<?php
require '../../autoload.php';
require '../functions.php';

ensureSession();
$session = getSession();
$api = createWebApi($session);

connectDb();
beginPage();
mkHtmlNavMenu(
  [ [ LNG_MENU_CHANGE_PLAYLIST, '../' ]
  , [ LNG_MENU_TRACK_DELIMITER, '#', 'track-delimiter' ]
  , [ LNG_MENU_INSERT_TRACK_AT_INTERVAL, '#', 'insert-track-at-interval' ]
  , [ LNG_MENU_RANDOMIZE_BY_BPM, '#', 'randomize-by-bpm' ]
  , [ LNG_MENU_SAVE_AS_NEW_PLAYLIST, '#', 'save-as-new-playlist' ]
  ]
);
beginContent();
try {
$playlist_id = fromGET('id');
$playlist_info = loadPlaylistInfo($api, $playlist_id);
$playlist_name = $playlist_info->name;
$tracks = [];
foreach (loadPlaylistTracks($api, $playlist_id) as $t) {
  $tracks[] = $t->track;
}
$audio_feats = loadTrackAudioFeatures($api, $tracks);
?>

<form id="playlistForm">

<div class="action-input-area" name="save-as-new-playlist">
  <div class="background"></div>
  <div class="input">
    <div class="title"><?= LNG_MENU_SAVE_AS_NEW_PLAYLIST ?></div>
    <label>
      <?= LNG_DESC_PLAYLIST_NAME ?>:
      <input type="text" name="new-playlist-name" />
    </label>
    <div class="buttons">
      <button class="cancel" onclick="clearActionInputs();">
        <?= LNG_BTN_CANCEL ?>
      </button>
      <button id="saveAsNewPlaylistBtn"><?= LNG_BTN_SAVE ?></button>
    </div>
  </div>
</div>

<div class="action-input-area" name="randomize-by-bpm">
  <div class="background"></div>
  <div class="input">
    <div class="title"><?= LNG_MENU_RANDOMIZE_BY_BPM ?></div>
    <table class="bpm-range-area">
      <tbody>
        <tr class="range">
          <td class="track">
            <?= LNG_DESC_BPM_RANGE_TRACK ?> <span>1</span>
          </td>
          <td class="label">
            <?= LNG_DESC_BPM ?>: <span></span>
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
            <?= LNG_DESC_BPM_DIFFERENCE ?>: <span></span>
          </td>
          <td class="difference-controller">
            <div></div>
          </td>
          <td></td>
        </tr>
        <tr class="range">
          <td class="track">
            <?= LNG_DESC_BPM_RANGE_TRACK ?> <span>2</span>
          </td>
          <td class="label">
            <?= LNG_DESC_BPM ?>: <span></span>
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
    <label class="checkbox">
      <input type="checkbox" name="dance-slot-has-same-category" value="true" />
      <span class="checkmark"></span>
      <?= LNG_DESC_DANCE_SLOT_SAME_CATEGORY ?>
    </label>
    <div class="buttons">
      <button class="cancel" onclick="clearActionInputs();">
        <?= LNG_BTN_CANCEL ?>
      </button>
      <button id="randomizeBtn"><?= LNG_BTN_RANDOMIZE ?></button>
    </div>
  </div>
</div>

<div class="action-input-area" name="insert-track-at-interval">
  <div class="background"></div>
  <div class="input">
    <div class="title"><?= LNG_MENU_INSERT_TRACK_AT_INTERVAL ?></div>

    <div class="song_link_help" onclick="$(this).hide();">
      <div class="background"></div>
      <div class="info">
        <h1><?= LNG_DESC_INSTRUCTIONS ?></h1>
        <p><?= LNG_TXT_SONG_LINK_HELP ?></p>
        <img src="/images/song-link.png"></img>
      </div>
    </div>

    <div>
      <div>
        <?= sprintf(LNG_INSTR_ENTER_SONG, 'Song Link', 'Spotify URI') ?>:
        <input type="text" name="track-to-insert" />
        <button class="small" onclick="$('div.song_link_help').show();">?</button>
      </div>
      <div>
        <?= sprintf( LNG_INSTR_INSERT_TRACK_ENTER_FREQ
                   , "<input type=\"text\" name=\"insertion-freq\" class=\"number centered\" />"
                   ) ?>
      </div>
    </div>

    <div class="buttons">
      <button class="cancel" onclick="clearActionInputs();">
        <?= LNG_BTN_CANCEL ?>
      </button>
      <button id="insertTrackBtn"><?= LNG_BTN_INSERT ?></button>
    </div>
  </div>
</div>

<div class="action-input-area" name="track-delimiter">
  <div class="background"></div>
  <div class="input">
    <div class="title"><?= LNG_MENU_TRACK_DELIMITER ?></div>

    <div>
      <div>
        <?= sprintf( LNG_INSTR_TRACK_DELIMITER_ENTER_FREQ
                   , "<input type=\"text\" name=\"delimiter-freq\" class=\"number centered\" />"
                   ) ?>
      </div>
    </div>

    <div class="buttons">
      <button class="cancel" onclick="clearActionInputs();">
        <?= LNG_BTN_CANCEL ?>
      </button>
      <button id="hideTrackDelimiterBtn"><?= LNG_BTN_HIDE ?></button>
      <button id="showTrackDelimiterBtn"><?= LNG_BTN_SHOW ?></button>
    </div>
  </div>
</div>

<div class="playlist">
<div class="playlist-title"><?= $playlist_name ?></div>

<table id="playlist">
  <thead>
    <tr>
      <th></th>
      <th class="bpm"><?= LNG_HEAD_BPM ?></th>
      <th class="category"><?= LNG_HEAD_CATEGORY_SHORT ?></th>
      <th><?= LNG_HEAD_TITLE ?></th>
      <th class="length"><?= LNG_HEAD_LENGTH ?></th>
    </tr>
  </thead>
  <tbody>
    <?php
    $total_length = 0;
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
      $title = formatTrackTitle($artists, $t->name);
      $length = $t->duration_ms;
      $preview_url = $t->preview_url;
      ?>
      <tr class="track">
        <input type="hidden" name="track_id" value="<?= $tid ?>" />
        <input type="hidden" name="preview_url" value="<?= $preview_url ?>" />
        <input type="hidden" name="length_ms" value="<?= $length ?>" />
        <td class="index"><?= $i+1 ?></td>
        <td class="bpm">
          <input type="text" name="bpm" class="bpm" value="<?= $bpm ?>" />
        </td>
        <td class="category">
          <input type="text" name="category" class="category"
                 value="<?= $category ?>" />
        </td>
        <td class="title"><?= $title ?></td>
        <td class="length"><?= formatTrackLength($length) ?></td>
      </tr>
      <?php
      $total_length += $length;
    }
    ?>
    <tr class="summary">
      <td colspan="4"></td>
      <td class="length"><?= formatTrackLength($total_length) ?></td>
    </tr>
  </tbody>
</table>

</div>

</form>

<script src="/js/actions.js.php"></script>
<script src="/js/playlist.js.php"></script>
<script src="/js/save-playlist.js.php"></script>
<script src="/js/insert-track.js.php"></script>
<script src="/js/randomize-by-bpm.js.php"></script>
<script src="/js/track-delimiter.js.php"></script>
<script type="text/javascript">
$(document).ready(
  function() {
    var form = $('form[id=playlistForm]');
    var table = $('table[id=playlist]');

    // Disable submission
    form.submit(function() { return false; });

    setupPlaylist(form, table);

    setupSaveNewPlaylistButton( form
                              , table
                              , <?= $playlist_info->public ? 'true' : 'false' ?>
                              );
    setupRandomizeByBpm(form, table);
    setupInsertTrack(form, table);
    setupTrackDelimiter(form, table);
    setupUnloadWarning(form, table);
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
