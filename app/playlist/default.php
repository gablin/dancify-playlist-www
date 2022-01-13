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
  , [ LNG_MENU_SCRATCHPAD, '#', 'scratchpad' ]
  , [ LNG_MENU_INSERT_TRACK_AT_INTERVAL, '#', 'insert-track-at-interval' ]
  , [ LNG_MENU_RANDOMIZE_BY_BPM, '#', 'randomize-by-bpm' ]
  , [ LNG_MENU_SAVE_AS_NEW_PLAYLIST, '#', 'save-as-new-playlist' ]
  ]
);
beginContent();
try {
$playlist_id = fromGET('id');
$playlist_info = loadPlaylistInfo($api, $playlist_id);
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
      <input type="checkbox" name="dance-slot-has-same-genre" value="true" />
      <span class="checkmark"></span>
      <?= LNG_DESC_DANCE_SLOT_SAME_GENRE ?>
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

<div class="action-input-area" name="scratchpad">
  <div class="background"></div>
  <div class="input">
    <div class="title"><?= LNG_MENU_SCRATCHPAD ?></div>

    <p>
      <?= LNG_DESC_SCRATCHPAD ?>
    </p>

    <div class="buttons">
      <button class="cancel" onclick="clearActionInputs();">
        <?= LNG_BTN_CANCEL ?>
      </button>
      <button id="hideScratchpadBtn"><?= LNG_BTN_HIDE ?></button>
      <button id="showScratchpadBtn"><?= LNG_BTN_SHOW ?></button>
    </div>
  </div>
</div>

<div class="playlists-wrapper">

<div class="playlist">
<div class="playlist-title"><?= $playlist_info->name ?></div>
<div class="table-wrapper">
<table id="playlist">
  <thead></thead>
  <tbody></tbody>
</table>
</div>
</div>

<div class="playlist scratchpad">
<div class="playlist-title"><?= LNG_HEAD_SCRATCHPAD ?></div>
<div class="table-wrapper">
<table id="scratchpad">
  <thead></thead>
  <tbody></tbody>
</table>
</div>
</div>

</div>

</form>

<script src="/js/utils.js.php"></script>
<script src="/js/globals.js.php"></script>
<script src="/js/actions.js.php"></script>
<script src="/js/playlist.js.php"></script>
<script src="/js/save-playlist.js.php"></script>
<script src="/js/insert-track.js.php"></script>
<script src="/js/randomize-by-bpm.js.php"></script>
<script src="/js/track-delimiter.js.php"></script>
<script src="/js/scratchpad.js.php"></script>
<script type="text/javascript">
$(document).ready(
  function() {
    var form = $('form[id=playlistForm]');
    var p_table = $('table[id=playlist]');
    var s_table = $('table[id=scratchpad]');
    initPlaylistGlobals(form, p_table, s_table);

    // Disable default form submission when pressing Enter
    form.submit(function() { return false; });

    setupPlaylist('<?= $playlist_id ?>');
    loadPlaylist('<?= $playlist_id ?>');
    setupSaveNewPlaylist(<?= $playlist_info->public ? 'true' : 'false' ?>);
    setupRandomizeByBpm();
    setupInsertTrack();
    setupTrackDelimiter();
    setupScratchpad();

    function limitPlaylistHeight() {
      var screen_vh = window.innerHeight;
      var table_offset = $('div.playlists-wrapper div.table-wrapper').offset().top;
      var footer_vh = $('div.footer').outerHeight();
      var playlist_vh = screen_vh - table_offset - footer_vh;
      $('div.playlist .table-wrapper').css('height', playlist_vh + 'px');
    };
    $(window).resize(limitPlaylistHeight);
    limitPlaylistHeight();
  }
);
</script>

<div class="grabbed-info-block">
  <span>X</span>
</div>

<div class="drag-insertion-point"></div>

<div class="mouse-menu"></div>

<?php
}
catch (Exception $e) {
  showError($e->getMessage());
}
endContent();
endPage();
updateTokens($session);
?>
