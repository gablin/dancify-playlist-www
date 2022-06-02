<?php
require '../../autoload.php';
require '../functions.php';

ensureSession();
$session = getSession();
$api = createWebApi($session);

beginPage();
mkHtmlNavMenu(
  [ [ LNG_MENU_DANCE_DELIMITER, '#', 'dance-delimiter' ]
  , [ LNG_MENU_SCRATCHPAD, '#', 'scratchpad' ]
  , [ LNG_MENU_BPM_OVERVIEW, '#', 'bpm-overview' ]
  , [ LNG_MENU_DUPLICATE_CHECK, '#', 'duplicate-check', 'onShowDuplicateCheck()' ]
  , []
  , [ LNG_MENU_INSERT_TRACK_AT_INTERVAL, '#', 'insert-track-at-interval' ]
  , [ LNG_MENU_INSERT_SILENCE_AT_INTERVAL, '#', 'insert-silence-at-interval' ]
  , [ LNG_MENU_SEARCH_FOR_TRACKS, '#', 'search-for-tracks' ]
  , [ LNG_MENU_SORT, '#', 'sort' ]
  , [ LNG_MENU_RANDOMIZE, '#', 'randomize' ]
  , [ LNG_MENU_RANDOMIZE_BY_BPM, '#', 'randomize-by-bpm' ]
  , []
  , [ LNG_MENU_SET_TRACK_PLAY_LENGTH
    , '#'
    , 'set-track-play-length'
    , 'onShowSetTrackPlayLength()'
    ]
  , [ LNG_MENU_SET_TRACK_FADE_OUT
    , '#'
    , 'set-track-fade-out'
    , 'onShowSetTrackFadeOut()'
    ]
  , []
  , [ LNG_MENU_CHANGE_PLAYLIST, '../' ]
  , [ LNG_MENU_SAVE_CHANGES_TO_SPOTIFY, '#', 'save-changes-to-spotify' ]
  , [ LNG_MENU_EXPORT_PLAYLIST, '#', 'export-playlist' ]
  , [ LNG_MENU_RESTORE_PLAYLIST, '#', 'restore-playlist' ]
  , []
  , [ LNG_MENU_DONATE, '#', 'donate' ]
  ]
, true
);
beginContent();
try {
$playlist_id = fromGET('id');
$playlist_info = loadPlaylistInfo($api, $playlist_id);
$playlist_name = $playlist_info->name;
?>

<form id="playlistForm">

<div class="action-input-area" name="first-time"
     <?php if (!hasCOOKIE('first-time')) { echo('style="display: block"'); } ?>>
  <div class="background"></div>
  <div class="input">
    <div class="title"><?= LNG_MENU_FIRST_TIME_INFORMATION ?></div>
    <p><?= LNG_DESC_FIRST_TIME_PART1 ?></p>
    <p><?= LNG_DESC_FIRST_TIME_PART2 ?></p>
    <div class="buttons">
      <button class="cancel" onclick="markFirstTimeShown()">
        <?= LNG_BTN_CLOSE ?>
      </button>
    </div>
  </div>
</div>

<div class="action-input-area" name="save-changes-to-spotify">
  <div class="background"></div>
  <div class="input">
    <div class="title"><?= LNG_MENU_SAVE_CHANGES_TO_SPOTIFY ?></div>
    <div>
      <label>
        <?= LNG_DESC_PLAYLIST_NAME ?>:
        <input type="text" name="new-playlist-name" />
      </label>
    </div>
    <p>
      <label class="checkbox">
        <input type="checkbox" name="overwrite-existing-playlist" value="true" />
        <span class="checkmark"></span>
        <?= LNG_DESC_OVERWRITE_EXISTING_PLAYLIST ?>
      </label>
    </p>
    <p><?= LNG_DESC_INFO_ON_SAVING ?></p>
    <div class="buttons">
      <button class="cancel" onclick="clearActionInputs();">
        <?= LNG_BTN_CANCEL ?>
      </button>
      <button id="saveChangesToSpotifyBtn"><?= LNG_BTN_SAVE ?></button>
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
          <td class="bpm-range-controller">
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
            <select name="direction">
              <option value="+1"><?= LNG_DESC_FASTER ?></option>
              <option value="-1"><?= LNG_DESC_SLOWER ?></option>
            </select>
          </td>
          <td class="bpm-difference-controller">
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
          <td class="bpm-range-controller">
            <div></div>
          </td>
          <td>
            <button class="add lowlight">+</button>
            <button class="remove lowlight">-</button>
          </td>
        </tr>
      <tbody>
    </table>
    <table class="dance-length-range-area">
      <tbody>
        <tr class="range">
          <td class="desc"><?= LNG_DESC_DANCE_LENGTH ?></td>
          <td class="label">
            <span></span>
          </td>
          <td class="dance-length-range-controller">
            <div></div>
          </td>
        </tr>
      <tbody>
    </table>
    <label class="checkbox">
      <input type="checkbox" name="dance-slot-has-same-genre" value="true" />
      <span class="checkmark"></span>
      <?= LNG_DESC_DANCE_SLOT_SAME_GENRE ?>
    </label>
    <p class="warning">
      <span><?= LNG_DESC_WARNING ?>:</span>
      <?= LNG_DESC_THIS_WILL_REMOVE_ALL_WORK ?>
    </p>

    <div class="buttons">
      <button class="cancel" onclick="clearActionInputs();">
        <?= LNG_BTN_CANCEL ?>
      </button>
      <button id="randomizeBtn"><?= LNG_BTN_RANDOMIZE ?></button>
    </div>
  </div>
</div>

<div class="action-input-area" name="sort">
  <div class="background"></div>
  <div class="input">
    <div class="title"><?= LNG_MENU_SORT ?></div>
    <p>
      <?= sprintf( LNG_INSTR_SORT_TRACKS
                 , "<select name=\"order_direction\" />" .
                     "<option value=\"+1\">" . LNG_DESC_RISING . "</option>" .
                     "<option value=\"-1\">" . LNG_DESC_FALLING . "</option>" .
                   "</select>"
                 , "<select name=\"order_field\" />" .
                     "<option value=\"bpm\">" . LNG_DESC_BPM . "</option>" .
                     "<option value=\"genre\">" . LNG_DESC_GENRE . "</option>" .
                   "</select>"
                 ) ?>
    </p>
    <p class="warning">
      <span><?= LNG_DESC_WARNING ?>:</span>
      <?= LNG_DESC_THIS_WILL_REMOVE_PLAYLIST_WORK ?>
    </p>
    <div class="buttons">
      <button class="cancel" onclick="clearActionInputs();">
        <?= LNG_BTN_CANCEL ?>
      </button>
      <button id="sortLocalScratchpadBtn">
        <?= LNG_BTN_SORT_LOCAL_SCRATCHPAD ?>
      </button>
      <button id="sortPlaylistBtn"><?= LNG_BTN_SORT_PLAYLIST ?></button>
    </div>
  </div>
</div>

<div class="action-input-area" name="randomize">
  <div class="background"></div>
  <div class="input">
    <div class="title"><?= LNG_MENU_RANDOMIZE ?></div>
    <p><?= LNG_INSTR_RANDOMIZE_ORDER ?></p>
    <p class="warning">
      <span><?= LNG_DESC_WARNING ?>:</span>
      <?= LNG_DESC_THIS_WILL_REMOVE_PLAYLIST_WORK ?>
    </p>
    <div class="buttons">
      <button class="cancel" onclick="clearActionInputs();">
        <?= LNG_BTN_CANCEL ?>
      </button>
      <button id="randomizeLocalScratchpadBtn">
        <?= LNG_BTN_RANDOMIZE_LOCAL_SCRATCHPAD ?>
      </button>
      <button id="randomizePlaylistBtn">
        <?= LNG_BTN_RANDOMIZE_PLAYLIST ?>
      </button>
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
                   , "<input type=\"text\" name=\"track-insertion-freq\" class=\"number centered\" />"
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

<div class="action-input-area" name="insert-silence-at-interval">
  <div class="background"></div>
  <div class="input">
    <div class="title"><?= LNG_MENU_INSERT_SILENCE_AT_INTERVAL ?></div>

    <div>
      <div>
        <?= LNG_INSTR_CHOOSE_SILENCE_LENGTH ?>:
        <select name="silence-to-insert">
          <?php
          $silence_tracks = [ [  '5s', '37y25dG9sw4I5zoL17RhcV' ]
                            , [ '10s', '2kLGdOul5dz2mKtTwvXf2r' ]
                            , [ '15s', '6HCeE4rWf4AIEskHZeLkOz' ]
                            , [ '20s', '5fKCBiLEHnnOFFUxQhogGO' ]
                            , [ '30s', '0smMqKMlcdRwNBMVR91EWQ' ]
                            , [ '40s', '7kj6uEhhZTfanELdgVFk36' ]
                            , [ '50s', '6A6dK5HzbAcxq7iVsjvqJs' ]
                            , [  '1m', '6ZfRjAcExubAvJFOkZfGtI' ]
                            , [  '2m', '2EeLn1LGhXQO9uFERlb2gE' ]
                            , [  '3m', '5nbMQjQKOwHuBsP2mGvvvn' ]
                            , [  '4m', '0MOoNgDUyEgVPi1fbvJFLZ' ]
                            , [  '5m', '4zJFlydxSMdcQfXMzCiLbq' ]
                            ];
          foreach($silence_tracks as $t) {
            $len = $t[0];
            $track_id = $t[1];
            ?>
            <option value="<?= $track_id ?>"><?= $len ?></option>
            <?php
          }
          ?>
        </select>
      </div>
      <div class="more-input">
        <?= sprintf( LNG_INSTR_INSERT_SILENCE_ENTER_FREQ
                   , "<input type=\"text\" name=\"silence-insertion-freq\" class=\"number centered\" />"
                   ) ?>
      </div>
    </div>

    <div class="buttons">
      <button class="cancel" onclick="clearActionInputs();">
        <?= LNG_BTN_CANCEL ?>
      </button>
      <button id="insertSilenceBtn"><?= LNG_BTN_INSERT ?></button>
    </div>
  </div>
</div>

<div class="action-input-area" name="search-for-tracks">
  <div class="background"></div>
  <div class="input">
    <div class="title"><?= LNG_MENU_SEARCH_FOR_TRACKS ?></div>

    <div>
      <label>
        <?= LNG_DESC_SEARCH_BY_GENRE ?>:
        <select name="search-by-genre"></select>
      </label>
    </div>
    <table class="bpm-range-area">
      <tbody>
        <tr class="range">
          <td class="label">
            <?= LNG_DESC_BPM ?>: <span></span>
          </td>
          <td class="bpm-range-controller">
            <div></div>
          </td>
        </tr>
      </tbody>
    </table>
    <div class="more-input">
      <label class="checkbox">
        <input type="checkbox" name="search-my-playlists-only" value="true" />
        <span class="checkmark"></span>
        <?= LNG_DESC_SEARCH_MY_PLAYLISTS_ONLY ?>
      </label>
    </div>

    <div class="buttons">
      <button class="cancel" onclick="clearActionInputs();">
        <?= LNG_BTN_CANCEL ?>
      </button>
      <button id="searchTracksBtn"><?= LNG_BTN_SEARCH ?></button>
    </div>

    <div class="search-results">
      <div class="error"></div>
      <div class="none-found"></div>
      <div class="tracks-found">
        <div class="title"><?= LNG_DESC_SEARCH_RESULTS ?></div>
        <p class="info">
          <?= LNG_DESC_SEARCH_FOR_TRACKS_RESULTS_HELP ?>
        </p>
        <div class="playlist">
          <div class="table-wrapper">
            <table>
              <thead>
                <tr>
                  <th><?= LNG_HEAD_TITLE ?></th>
                  <th class="bpm"><?= LNG_HEAD_BPM ?></th>
                  <th class="length"><?= LNG_HEAD_LENGTH ?></th>
                </tr>
              </thead>
              <tbody>
              </tbody>
            </table>
          </div>
          <div class="buttons">
            <button id="addSearchToPlaylistBtn">
              <?= LNG_BTN_ADD_SEARCH_TO_PLAYLIST ?>
            </button>
            <button id="addSearchToLocalScratchpadBtn">
              <?= LNG_BTN_ADD_SEARCH_TO_LOCAL_SCRATCHPAD ?>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="action-input-area" name="dance-delimiter">
  <div class="background"></div>
  <div class="input">
    <div class="title"><?= LNG_MENU_DANCE_DELIMITER ?></div>

    <p>
      <?= LNG_DESC_DELIMITER ?>
    </p>

    <div>
      <div>
        <?= sprintf( LNG_INSTR_DANCE_DELIMITER_ENTER_FREQ
                   , "<input type=\"text\" name=\"delimiter-freq\" class=\"number centered\" />"
                   ) ?>
      </div>
    </div>

    <div class="buttons">
      <button class="cancel" onclick="clearActionInputs();">
        <?= LNG_BTN_CANCEL ?>
      </button>
      <button id="hideDanceDelimiterBtn"><?= LNG_BTN_HIDE ?></button>
      <button id="showDanceDelimiterBtn"><?= LNG_BTN_SHOW ?></button>
    </div>
  </div>
</div>

<div class="action-input-area" name="scratchpad">
  <div class="background"></div>
  <div class="input">
    <div class="title"><?= LNG_MENU_SCRATCHPAD ?></div>

    <p>
      <?= LNG_DESC_SCRATCHPAD_1 ?>
    </p>
    <p>
      <?= LNG_DESC_SCRATCHPAD_2 ?>
    </p>

    <div class="buttons">
      <button class="cancel" onclick="clearActionInputs();">
        <?= LNG_BTN_CLOSE ?>
      </button>
      <button id="globalScratchpadBtn">
        <?= LNG_BTN_SHOW_GLOBAL_SCRATCHPAD ?>
      </button>
      <button id="localScratchpadBtn">
        <?= LNG_BTN_SHOW_LOCAL_SCRATCHPAD ?>
      </button>
    </div>
  </div>
</div>

<div class="action-input-area" name="bpm-overview">
  <div class="background"></div>
  <div class="input">
    <div class="title"><?= LNG_MENU_BPM_OVERVIEW ?></div>

    <p>
      <?= LNG_DESC_BPM_OVERVIEW ?>
    </p>

    <div class="buttons">
      <button class="cancel" onclick="clearActionInputs();">
        <?= LNG_BTN_CANCEL ?>
      </button>
      <button id="hideBpmOverviewBtn"><?= LNG_BTN_HIDE ?></button>
      <button id="showBpmOverviewBtn"><?= LNG_BTN_SHOW ?></button>
    </div>
  </div>
</div>

<div class="action-input-area" name="duplicate-check">
  <div class="background"></div>
  <div class="input">
    <div class="title"><?= LNG_MENU_DUPLICATE_CHECK ?></div>

    <p>
      <?= LNG_DESC_DUPLICATE_CHECK ?>
    </p>

    <div class="buttons">
      <button class="cancel" onclick="clearActionInputs();">
        <?= LNG_BTN_CANCEL ?>
      </button>
      <button id="checkDuplicatesGloballyBtn">
        <?= LNG_BTN_CHECK_GLOBALLY ?>
      </button>
      <button id="checkDuplicatesLocallyBtn">
        <?= LNG_BTN_CHECK_LOCALLY ?>
      </button>
    </div>

    <div class="search-results">
      <div class="title"><?= LNG_DESC_SEARCH_RESULTS ?></div>
      <div class="none-found"></div>
      <div class="duplicates-found">
        <div class="playlist">
          <div class="table-wrapper">
            <table class="playlist">
              <thead>
                <tr>
                  <th class="index">#</th>
                  <th><?= LNG_HEAD_TITLE ?></th>
                </tr>
              </thead>
              <tbody>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="action-input-area" name="restore-playlist">
  <div class="background"></div>
  <div class="input">
    <div class="title"><?= LNG_MENU_RESTORE_PLAYLIST ?></div>

    <p>
      <?= LNG_DESC_RESTORE_PLAYLIST ?>
    </p>
    <p class="warning">
      <span><?= LNG_DESC_WARNING ?>:</span>
      <?= LNG_DESC_THIS_WILL_REMOVE_ALL_WORK ?>
      <br />
      <?= strtoupper(LNG_DESC_NO_WAY_TO_UNDO) ?>
    </p>

    <div class="buttons">
      <button class="cancel" onclick="clearActionInputs();">
        <?= LNG_BTN_CANCEL ?>
      </button>
      <button id="restorePlaylistBtn"><?= LNG_BTN_RESTORE ?></button>
    </div>
  </div>
</div>

<div class="action-input-area" name="donate">
  <div class="background"></div>
  <div class="input">
    <div class="title"><?= LNG_MENU_DONATE ?></div>
    <p><?= LNG_DESC_DONATE_TEXT ?></p>
    <p class="donate center-text">
      <a href="#" onclick="triggerPaypalDonation()">
        <?php
        if (getLang() == 'en') {
          ?>
          <img src="https://www.paypalobjects.com/en_US/SE/i/btn/btn_donateCC_LG.gif" title="Donate with Paypal" />
          <?php
        }
        else if (getLang() == 'sv') {
          ?>
          <img src="https://www.paypalobjects.com/sv_SE/SE/i/btn/btn_donateCC_LG.gif" title="Donera med Paypal" />
          <?php
        }
        else {
          showError('ERROR: unknown language');
        }
        ?>
      </a>
      <a href="#" onclick="triggerSwishDonation()">
        <?php
        if (getLang() == 'en') {
          ?>
          <img class="swish-logo" src="/images/swish-logo.svg" title="Donate with Swish" />
          <?php
        }
        else if (getLang() == 'sv') {
          ?>
          <img class="swish-logo" src="/images/swish-logo.svg" title="Donera med Swish" />
          <?php
        }
        else {
          showError('ERROR: unknown language');
        }
        ?>
      </a>
    </p>
    <div class="buttons">
      <button class="cancel" onclick="clearActionInputs()">
        <?= LNG_BTN_CLOSE ?>
      </button>
    </div>
  </div>
</div>

<div class="action-input-area" name="swish-qr">
  <div class="background"></div>
  <div class="input">
    <img class="swish-qr" src="/images/swish-qr.svg" />
  </div>
</div>

<div class="action-input-area" name="playlist-inconsistencies">
  <div class="background"></div>
  <div class="input">
    <div class="title"><?= LNG_DESC_PLAYLIST_UPDATES_DETECTED ?></div>
    <p><!-- ENTER APPROPRIATE TEXT --></p>
    <div class="buttons">
      <button class="cancel"><?= LNG_BTN_DO_NOTHING ?></button>
      <button id="inconPlaylistBtn2"><!-- ENTER APPROPRIATE TITLE --></button>
      <button id="inconPlaylistBtn1"><!-- ENTER APPROPRIATE TITLE --></button>
    </div>
  </div>
</div>

<div class="action-input-area" name="show-playlists-with-track">
  <div class="background"></div>
  <div class="input">
    <div class="title"><?= LNG_MENU_SHOW_PLAYLISTS_WITH_TRACK ?></div>
    <p></p>

    <div class="buttons">
      <button class="cancel"><?= LNG_BTN_CLOSE ?></button>
    </div>

    <div class="search-results">
      <div class="title"><?= LNG_DESC_SEARCH_RESULTS ?></div>
      <div class="progress-bar"></div>
      <div class="error"></div>
      <div class="none-found"></div>
      <div class="playlists-found">
        <div class="table-wrapper">
          <table>
            <tbody>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="action-input-area" name="export-playlist">
  <div class="background"></div>
  <div class="input">
    <div class="title"><?= LNG_MENU_EXPORT_PLAYLIST ?></div>

    <p>
      <?= LNG_DESC_EXPORT_PLAYLIST ?>
    </p>

    <div class="buttons">
      <button class="cancel" onclick="clearActionInputs();">
        <?= LNG_BTN_CANCEL ?>
      </button>
      <button id="exportPlaylistBtn"><?= LNG_BTN_EXPORT ?></button>
    </div>
  </div>
</div>

<div class="action-input-area" name="set-track-play-length">
  <div class="background"></div>
  <div class="input">
    <div class="title"><?= LNG_MENU_SET_TRACK_PLAY_LENGTH ?></div>
    <p><?= LNG_INSTR_SET_TRACK_PLAY_LENGTH ?></p>
    <table class="track-play-length-area centered">
      <tbody>
        <tr class="range">
          <td class="label">
            <span></span>
          </td>
          <td class="track-play-length-controller">
            <div></div>
          </td>
        </tr>
      <tbody>
    </table>

    <div class="buttons">
      <button class="cancel" onclick="clearActionInputs();">
        <?= LNG_BTN_CANCEL ?>
      </button>
      <button id="removeTrackPlayLength">
        <?= LNG_BTN_REMOVE ?>
      </button>
      <button id="saveTrackPlayLength">
        <?= LNG_BTN_SAVE ?>
      </button>
    </div>
  </div>
</div>

<div class="action-input-area" name="set-track-fade-out">
  <div class="background"></div>
  <div class="input">
    <div class="title"><?= LNG_MENU_SET_TRACK_FADE_OUT ?></div>
    <p><?= LNG_INSTR_SET_TRACK_FADE_OUT ?></p>
    <table class="track-fade-out-area centered">
      <tbody>
        <tr class="range">
          <td class="label">
            <span></span>
          </td>
          <td class="track-fade-out-controller">
            <div></div>
          </td>
        </tr>
      <tbody>
    </table>

    <div class="buttons">
      <button class="cancel" onclick="clearActionInputs();">
        <?= LNG_BTN_CANCEL ?>
      </button>
      <button id="removeTrackFadeOut">
        <?= LNG_BTN_REMOVE ?>
      </button>
      <button id="saveTrackFadeOut">
        <?= LNG_BTN_SAVE ?>
      </button>
    </div>
  </div>
</div>

<div class="playlists-wrapper">

<div class="playlist">
<div class="playlist-title"><?= $playlist_name ?></div>
<div class="table-wrapper">
<table id="playlist">
  <thead></thead>
  <tbody></tbody>
</table>
<p class="sub-info"><?= LNG_DESC_BPM_INFO ?></p>
<p class="sub-info"><?= LNG_DESC_GENRE_INFO ?></p>
</div>
</div>

<div class="playlist scratchpad">
<div class="playlist-title"><?= LNG_HEAD_LOCAL_SCRATCHPAD ?></div>
<div class="table-wrapper">
<table id="local-scratchpad">
  <thead></thead>
  <tbody></tbody>
</table>
</div>
</div>

<div class="playlist scratchpad">
<div class="playlist-title"><?= LNG_HEAD_GLOBAL_SCRATCHPAD ?></div>
<div class="table-wrapper">
<table id="global-scratchpad">
  <thead></thead>
  <tbody></tbody>
</table>
</div>
</div>

</div>

<div class="bpm-overview">
  <div class="bar-area"></div>
  <div class="stats">THIS MUST NOT BE EMPTY</div>
</div>

</form>

<script src="/js/utils.js.php"></script>
<script src="/js/globals.js.php"></script>
<script src="/js/status.js.php"></script>
<script src="/js/actions.js.php"></script>
<script src="/js/playlist.js.php"></script>
<script src="/js/save-changes-to-spotify.js.php"></script>
<script src="/js/insert-track.js.php"></script>
<script src="/js/insert-silence.js.php"></script>
<script src="/js/randomize-by-bpm.js.php"></script>
<script src="/js/dance-delimiter.js.php"></script>
<script src="/js/scratchpad.js.php"></script>
<script src="/js/restore-playlist.js.php"></script>
<script src="/js/donations.js.php"></script>
<script src="/js/sort.js.php"></script>
<script src="/js/search-for-tracks.js.php"></script>
<script src="/js/heartbeat.js.php"></script>
<script src="/js/bpm-overview.js.php"></script>
<script src="/js/duplicate-check.js.php"></script>
<script src="/js/randomize.js.php"></script>
<script src="/js/playback.js.php"></script>
<script src="/js/export.js.php"></script>
<script src="/js/set-track-play-length.js.php"></script>
<script src="/js/set-track-fade-out.js.php"></script>
<script type="text/javascript">
function markFirstTimeShown() {
  clearActionInputs();
  Cookies.set('first-time', 'true', { expires: 365*5 });
}

$(document).ready(
  function() {
    var form = 'form[id=playlistForm]';
    var p_table = 'table[id=playlist]';
    var local_s_table = 'table[id=local-scratchpad]';
    var global_s_table = 'table[id=global-scratchpad]';
    initPlaylistGlobals(form, p_table, local_s_table, global_s_table);

    // Disable default form submission when pressing Enter
    $(form).submit(function() { return false; });

    setupPlaylist('<?= $playlist_id ?>');
    setupSaveChangesToSpotify( '<?= $playlist_id ?>'
                             , <?= $playlist_info->public ? 'true' : 'false' ?>
                             );
    setupRandomizeByBpm();
    setupInsertTrack();
    setupInsertSilence();
    setupDanceDelimiter();
    setupScratchpad();
    setupRestorePlaylist('<?= $playlist_id ?>');
    setupSort();
    setupSearchForTracks();
    setupHeartbeat();
    setupBpmOverview();
    setupDuplicateCheck();
    setupRandomize();
    setupPlayback('<?= $playlist_id ?>');
    setupExport('<?= str_replace('\'', '\\\'', $playlist_name) ?>');
    setupSetTrackPlayLength('<?= $playlist_id ?>');
    setupSetTrackFadeOut('<?= $playlist_id ?>');
  }
);
</script>

<div class="grabbed-info-block">
  <span></span>
</div>

<div class="tr-drag-insertion-point"></div>
<div class="bar-drag-insertion-point"></div>

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
