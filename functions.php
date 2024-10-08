<?php
session_start();

/**
 * Outputs beginning of every HTML page.
 */
function beginPage() {
  $extra_logo_text_cls =
    count(explode('/', $_SERVER['REQUEST_URI'])) == 2 ? 'large' : '';
?>
<!DOCTYPE html>
<html>
  <head>
    <meta name="viewport" content="width=device-width, initial-scale=1"></meta>
    <title><?php echo(LNG_SLOGAN); ?></title>
    <link href="https://fonts.googleapis.com/css?family=Lobster|Roboto|Roboto+Condensed:300&display=swap" rel="stylesheet"></link>
    <link rel="stylesheet" href="/css/jquery-ui-1.13.0.css"></link>
    <link rel="stylesheet" href="/css/main.css"></link>
    <script src="/js/jquery-3.5.1.min.js"></script>
    <script src="/js/jquery-ui-1.13.0.js"></script>
    <script src="/js/js-cookie-3.0.1.min.js"></script>
  </head>
  <body>
    <div class="body-wrapper">
      <div class="logo">
        <div class="text <?= $extra_logo_text_cls ?>">
          <?php echo(LNG_SLOGAN); ?>
        </div>
        <div class="lang">
          <a href="<?php echo(augmentThisLink(array('lang' => 'en'))); ?>">EN</a>
          <a href="<?php echo(augmentThisLink(array('lang' => 'sv'))); ?>">SV</a>
        </div>
      </div>
<?php
}

/**
 * Creates HTML code for the menu.
 *
 * @param array|null $args List of number of menu items or nulls.
 */
function createMenu(...$args) {
  $items = array();
  foreach ($args as $a) {
    if (!is_null($a)) {
      array_push($items, $a);
    }
  }
  ?>
  <div class="menu">
    <?php
    if (hasSession()) {
      ?>
      <ul>
        <li><a href="/app/"><?php echo(LNG_MENU_HOME); ?></a></li>
        <?php
        foreach ($items as $i) {
          ?>
          <li><a href="<?php echo($i['lnk']); ?>"><?php echo($i['str']); ?></a></li>
          <?php
        }
        ?>
        <li class="logout"><a href="/app/logout"><?php echo(LNG_MENU_LOGOUT); ?></a></li>
      </ul>
      <?php
    }
    ?>
  </div>
  <?php
}

/**
 * Outputs end of every HTML page.
 */
function endPage() {
?>
      <div class="footer">
        <p>
          <?php
          $y_start = 2020;
          $y_now = date("Y");
          if ($y_now > $y_start) $year_str = "$y_start &ndash; $y_now";
          else                   $year_str = "$y_start";
          ?>
          <?php echo(LNG_DESC_COPYRIGHT); ?> &copy; <?php echo($year_str); ?>
          Gabriel Hjort Åkerlund
          &nbsp;&nbsp;&nbsp;&nbsp;
          <?php echo(sprintf(LNG_DESC_GIVE_FEEDBACK, 'gabriel [at] hjort.dev')); ?>
          &mdash; <?php echo(LNG_SOURCE_CODE); ?>:
          <a href="https://github.com/gablin/dancify-www">github</a>
        </p>
      </div>
    </div>
  </body>
</html>
<?php
}

/**
 * Outputs beginning of every content.
 */
function beginContent() {
  showCookieInfo();
?>
<div class="content">
<?php
}

/**
 * Outputs end of every content.
 */
function endContent() {
?>
</div>
<?php
}

/**
 * Output info that this website uses cookies, along with an 'I agree' button.
 * If user has already pressed the button, nothing is output.
 */
function showCookieInfo() {
  if (!hasCOOKIE('accept_cookies')) {
    ?>
    <div class="cookies">
      <h1><?php echo(LNG_DESC_USES_COOKIES); ?></h1>
      <table>
        <tr>
          <td><?php echo(LNG_TXT_COOKIES); ?></td>
          <td><button><?php echo(LNG_BTN_I_AGREE); ?></button></td>
        </tr>
      </table>
    </div>
    <script type="text/javascript">
      var div = $('div.cookies');
      div.find('button').click(
        function() {
          Cookies.set('accept_cookies'
                     , true
                     , { expires: 365
                       , path: '/'
                       }
                     );
          div.remove();
        }
      );
    </script>
    <?php
  }
}

/**
 * Creates an API session. It is assumed that a PHP session has already been
 * started.
 *
 * @returns SpotifyWebAPI\Session An active API session.
 */
function createApiSession() {
  global $SPOTIFY_CLIENT_ID, $SPOTIFY_CLIENT_SECRET;

  $callback = "{$_SERVER['REQUEST_SCHEME']}://{$_SERVER['SERVER_NAME']}" .
              getAuthCallbackUrl();
  $session = new SpotifyWebAPI\Session( $SPOTIFY_CLIENT_ID
                                      , $SPOTIFY_CLIENT_SECRET
                                      , $callback
                                      );
  return $session;
}

/**
 * Checks whether a session is active.
 * @returns bool true if session is active.
 */
function hasSession() {
  return isset($_SESSION['access-token']) &&
         isset($_SESSION['refresh-token']) &&
         strlen($_SESSION['access-token']) > 0 &&
         strlen($_SESSION['refresh-token']) > 0;
}

/**
 * Returns the URL to be used for acquiring authorization.
 */
function getAuthUrl() {
  return '/auth/';
}

/**
 * Returns the URL to be used for acquiring authorization.
 */
function getAuthCallbackUrl() {
  return '/auth/callback/';
}

/**
 * Checks whether a session is active. If not, the client is redirected to the
 * login page.
 */
function ensureSession() {
  if (!hasSession()) {
      header('Location: ' . getAuthUrl());
    die();
  }
}

/**
 * Gets the current Spotify access token.
 */
function getAccessToken() {
  return $_SESSION['access-token'];
}

/**
 * Gets the currently active API session. It is assumed that a PHP session has
 * already been started.
 *
 * @returns SpotifyWebAPI\Session Active API session.
 */
function getSession() {
  $session = createApiSession();
  $session->setAccessToken($_SESSION['access-token']);
  $session->setRefreshToken($_SESSION['refresh-token']);

  return $session;
}

/**
 * Gets the Spotify ID of this user.
 *
 * @param SpotifyWebAPI\SpotifyWebAPI api API object.
 * @returns string
 * @throws SpotifyWebAPI\SpotifyWebAPIException If something fails.
 */
function getThisUserId($api) {
  return $api->me()->id;
}

/**
 * Updates the tokens of an active session. It is assumed that a PHP session
 * has already been started.
 *
 * @param SpotifyWebAPI\Session $session Active API session.
 */
function updateTokens($session) {
  $_SESSION['access-token']  = $session->getAccessToken();
  $_SESSION['refresh-token'] = $session->getRefreshToken();
}

/**
 * Closes the currently active API session. It is assumed that a PHP session has
 * already been started.
 */
function closeSession() {
  unset($_SESSION['access-token'], $_SESSION['refresh-token']);
}

/**
 * Creates a SpotifyWebAPI from a given session.
 *
 * @param SpotifyWebAPI\Session $session Active API session.
 * @returns SpotifyWebAPI\SpotifyWebAPI API object.
 */
function createWebApi($session) {
  $api = new SpotifyWebAPI\SpotifyWebAPI();
  $api->setSession($session);
  $api->setOptions(['auto_refresh' => true]);

  return $api;
}

/**
 * Outputs an error message.
 *
 * @param string $msg Error message.
 */
function showError($msg) {
  ?>
  <div class="error">
    <span class="heading"><?php echo(LNG_DESC_ERROR); ?>:</span> <?php echo($msg); ?>
  </div>
  <?php
}

/**
 * Extracts and formats list of artists from a given track.
 *
 * @param object $t Track object.
 * @returns string Formatted list of artists.
 */
function formatArtists($t) {
  $artists = array();
  foreach ($t->artists as $a) {
    array_push($artists, $a->name);
  }
  return join(", ", $artists);
}

/**
 * Takes a Spotify link or URI and returns the track ID.
 *
 * @param string $s String to parse.
 * @returns string Track ID if found; otherwise empty string.
 */
function getTrackId($s) {
  // Check URI
  $pre = 'spotify:track:';
  if (substr($s, 0, strlen($pre)) === $pre) {
    return substr($s, strlen($pre));
  }

  // Check link
  $pre = 'https://open.spotify.com/track/';
  if (substr($s, 0, strlen($pre)) === $pre) {
    $s = substr($s, strlen($pre));
    $p = strpos($s, '?');
    if ($p > 0) $s = substr($s, 0, $p);
    return $s;
  }
}

/**
 * Checks if $_GET has a given key, and that the value given is a non-empty,
 * non-whitespace value (this check can be turned off).
 *
 * @param string k Key to check.
 * @param bool allow_empty Allow existing but empty or whitespace-only value.
 * @returns bool true if valid value is present.
 */
function hasGET($k, $allow_empty = false) {
  return isset($_GET[$k]) && ($allow_empty || strlen(trim($_GET[$k])) > 0);
}

/**
 * Gets a given key from $_GET. If the key does not exist, or if exists but with
 * a empty or whitespace-only value, an Exception is thrown.
 *
 * @param string k Key to use.
 * @param bool allow_empty Allow existing but empty or whitespace-only value.
 * @throws Exception If check fails.
 */
function fromGET($k, $allow_empty = false) {
  if (!hasGET($k, $allow_empty)) {
    throw new Exception("not in GET query: {$k}");
  }
  return $_GET[$k];
}

/**
 * Checks if $_POST has a given key, and that the value given is a non-empty,
 * non-whitespace value (this check can be turned off).
 *
 * @param string k Key to check.
 * @param bool allow_empty Allow existing but empty or whitespace-only value.
 * @returns bool true if valid value is present.
 */
function hasPOST($k, $allow_empty = false) {
  return isset($_POST[$k]) && ($allow_empty || strlen(trim($_POST[$k])) > 0);
}

/**
 * Gets a given key from $_POST. If the key does not exist, or if exists but with
 * a empty or whitespace-only value, an Exception is thrown.
 *
 * @param string k Key to use.
 * @param bool allow_empty Allow existing but empty or whitespace-only value.
 * @throws Exception If check fails.
 */
function fromPOST($k, $allow_empty = false) {
  if (!hasPOST($k, $allow_empty)) {
    throw new Exception("not in POST query: {$k}");
  }
  return $_POST[$k];
}

/**
 * Builds a link from a given URI and an array of GET fields.
 *
 * @param string $uri URI string.
 * @param array $gets Array where field names are the keys.
 * @returns string Corresponding link.
 */
function buildLink($uri, $gets) {
  // Add trailing '/' if necessary
  if (substr($uri, -1) !== '/' && count($gets) > 0) {
    $uri .= '/';
  }

  // Build GET query
  $get_query = '';
  if (count($gets) > 0) {
    $fields = array();
    foreach ($gets as $k => $v) {
      array_push($fields, "{$k}={$v}");
    }
    $get_query = '?' . join('&', $fields);
  }
  return $uri . $get_query;
}

/**
 * Builds a new link based on the current URI and GET query.
 *
 * @param array $gets Array with GET values to change. The field names are the
 *                    keys.
 * @returns string Corresponding link.
 */
function augmentThisLink($gets) {
  $uri = strtok($_SERVER['REQUEST_URI'], '?');
  foreach ($_GET as $k => $v) {
    if (!array_key_exists($k, $gets)) {
      $gets[$k] = $v;
    }
  }
  return buildLink($uri, $gets);
}

/**
 * Loads information for a given playlist.
 *
 * @param SpotifyWebAPI\SpotifyWebAPI api API object.
 * @param string id Playlist ID.
 * @returns Object Information.
 * @throws SpotifyWebAPI\SpotifyWebAPIException If something fails.
 */
function loadPlaylistInfo($api, $id) {
  return $api->getPlaylist($id);
}

/**
 * Loads all playlists from the current user.
 *
 * @param SpotifyWebAPI\SpotifyWebAPI api API object.
 * @returns array List of playlist objects.
 * @throws SpotifyWebAPI\SpotifyWebAPIException If something fails.
 */
function loadPlaylists($api) {
  // Due to API limitations, we can only receive a limited number at a time. For
  // more information, see:
  // https://developer.spotify.com/documentation/web-api/reference/playlists/
  //   get-list-users-playlists/
  $playlists = array();
  $limit = 50;
  $user_uri = $api->me()->uri;
  for ($i = 0; ; $i += $limit) {
    $options = [ 'limit' => $limit, 'offset' => $i ];
    $ps = $api->getUserPlaylists($user_uri, $options);
    foreach ($ps->items as $p) {
      array_push($playlists, $p);
    }
    if (count($ps->items) < $limit) break;
  }
  return $playlists;
}

/**
 * Deletes a playlist from the current user.
 *
 * @param SpotifyWebAPI\SpotifyWebAPI api API object.
 * @param string id Playlist ID.
 * @throws SpotifyWebAPI\SpotifyWebAPIException If something fails.
 */
function deletePlaylist($api, $id) {
  $api->unfollowPlaylistForCurrentUser($id);
}

/**
 * Adds tracks to a given playlist.
 *
 * @param SpotifyWebAPI\SpotifyWebAPI api API object.
 * @param string id Playlist ID.
 * @param array List of track IDs.
 * @throws SpotifyWebAPI\SpotifyWebAPIException If something fails.
 */
function addPlaylistTracks($api, $id, $tracks) {
  // Due to API restrictions, we insert a limited number at a time. For more
  // information, see: https://developer.spotify.com/documentation/web-api/reference/playlists/add-tracks-to-playlist/
  $limit = 100;
  for ($i = 0; $i < count($tracks); $i += $limit) {
    $ts = array_slice($tracks, $i, $limit);
    $res = $api->addPlaylistTracks($id, $ts);
    if (!$res) {
      throw new Exception("API addPlaylistsTracks call failed");
    }
  }
}

/**
 * Removes tracks from a given playlist.
 *
 * @param SpotifyWebAPI\SpotifyWebAPI api API object.
 * @param string id Playlist ID.
 * @param array List of track IDs.
 * @throws SpotifyWebAPI\SpotifyWebAPIException If something fails.
 */
function deletePlaylistTracks($api, $id, $tracks) {
  // Due to API limitations, we can only get a limited number of tracks at a
  // time. For more information, see:
  // https://developer.spotify.com/documentation/web-api/reference/playlists/
  //   delete-playlists-tracks/
  $limit = 100;
  for ($i = 0; $i < count($tracks); $i += $limit) {
    $ts = array_slice($tracks, $i, $limit);
    $tobjs = array_map(function($t) { return [ 'id' => $t ]; }, $ts);
    $api->deletePlaylistTracks($id, [ 'tracks' => $tobjs ]);
  }
}

/**
 * Loads all tracks from a given playlist.
 *
 * @param SpotifyWebAPI\SpotifyWebAPI api API object.
 * @param string id Playlist ID.
 * @returns array List of playlist track objects.
 * @throws SpotifyWebAPI\SpotifyWebAPIException If something fails.
 */
function loadPlaylistTracks($api, $id) {
  // Due to API limitations, we can only get a limited number of tracks at a
  // time. For more information, see:
  // https://developer.spotify.com/documentation/web-api/reference/playlists/
  //   get-playlists-tracks/
  $tracks = array();
  $limit = 100;
  for ($o = 0; ; $o += $limit) {
    $options = [ 'limit' => $limit, 'offset' => $o ];
    $ts = $api->getPlaylistTracks($id, $options);
    foreach ($ts->items as $t) {
      array_push($tracks, $t);
    }
    if (count($ts->items) < $limit) break;
  }
  return $tracks;
}

/**
 * Loads audio features from a given array of tracks.
 *
 * @param array $ts Track objects.
 * @returns array Audio features objects.
 */
function loadTrackAudioFeatures($api, $ts) {
  // Due to API limitations, we can only get a limited number of features at a
  // time. For more information, see:
  // https://developer.spotify.com/documentation/web-api/reference/#category-tracks
  $feats = [];
  $limit = 100;
  for ($o = 0; $o < count($ts); $o += $limit) {
    $ids = [];
    for ($i = $o; $i < $o + $limit && $i < count($ts); $i++) {
      if (is_null($ts[$i])) {
        continue;
      }
      $ids[] = $ts[$i]->id;
    }
    $res = $api->getAudioFeatures($ids);
    $feats = array_merge($feats, $res->audio_features);
  }
  return $feats;
}

/**
 * Checks if $_COOKIE has a given key, and that the value given is a non-empty,
 * non-whitespace value (this check can be turned off).
 *
 * @param string k Key to check.
 * @param bool allow_empty Allow existing but empty or whitespace-only value.
 * @returns bool true if valid value is present.
 */
function hasCOOKIE($k, $allow_empty = false) {
  return isset($_COOKIE[$k]) &&
         ($allow_empty || strlen(trim($_COOKIE[$k])) > 0);
}

/**
 * Gets a given key from $_COOKIE. If the key does not exist, or if exists but
 * with a empty or whitespace-only value, an Exception is thrown.
 *
 * @param string k Key to use.
 * @param bool allow_empty Allow existing but empty or whitespace-only value.
 * @throws Exception If check fails.
 */
function fromCOOKIE($k, $allow_empty = false) {
  if (!hasCOOKIE($k, $allow_empty)) {
    throw new Exception("not in COOKIE query: {$k}");
  }
  return $_COOKIE[$k];
}

/**
 * Gets language setting.
 *
 * @returns string Language setting.
 * @throws Exception If language is not set.
 */
function getLang() {
  $lang = '';
  $k = 'lang';
  if (hasGET($k)) {
    $lang = fromGET($k);
  }
  else if (hasCOOKIE($k)) {
    $lang = fromCOOKIE($k);
  }
  else {
    return 'en';
  }

  if (!in_array($lang, ['en', 'sv'])) {
    $lang = 'en';
  }
  return $lang;
}

/**
 * Saves current language setting.
 *
 * @param string $lang Language setting to save.
 */
function saveLang($lang) {
  $one_year = time() + 60*60*24*365;
  setcookie('lang', getLang(), $one_year, '/');
}

/**
 * Connection to the SQL database.
 */
$DH_DB_CONN = null;

function connectDb() {
  global $DH_DB_SERVER_IP;
  global $DH_DB_USERNAME;
  global $DH_DB_PASSWD;
  global $DH_DB_DATABASE;
  global $DH_DB_CONN;

  if (!$DH_DB_CONN) {
    $DH_DB_CONN = mysqli_connect( $DH_DB_SERVER_IP
                                , $DH_DB_USERNAME
                                , $DH_DB_PASSWD
                                );
    if (!$DH_DB_CONN) {
      throw new Exception(mysqli_connect_error());
    }

    // Check if there exists a database; if not, create it
    $res = queryDb("SHOW DATABASES LIKE '{$DH_DB_DATABASE}'");
    if ($res->num_rows == 0) {
      queryDb("CREATE DATABASE {$DH_DB_DATABASE}");
    }

    if (!$DH_DB_CONN->select_db($DH_DB_DATABASE)) {
      throw new Exception("failed to select database: {$DH_DB_DATABASE}");
    }
    checkDbTables();
  }
}

/**
 * Checks that all database tables exists, and creates them if not.
 * Set $DH_DB_NO_CHECKING=true to disable this check when not needed in order to
 * improve performance.
 */
function checkDbTables() {
  if (isset($DH_DB_NO_CHECKING) && $DH_DB_NO_CHECKING) {
    return;
  }

  $tables = [ 'bpm' =>
              'CREATE TABLE bpm' .
              ' ( song CHAR(22) NOT NULL' .
              ' , bpm TINYINT UNSIGNED NOT NULL' .
              ' , PRIMARY KEY (playlist)' .
              ' )'
            , 'genre' =>
              'CREATE TABLE genre' .
              ' ( song CHAR(22) NOT NULL' .
              ' , user TINYTEXT NOT NULL' .
              ' , genre TINYINT UNSIGNED NOT NULL' .
              ' )'
            , 'comments' =>
              'CREATE TABLE comments' .
              ' ( song CHAR(22) NOT NULL' .
              ' , user TINYTEXT NOT NULL' .
              ' , comments TINYTEXT NOT NULL' .
              ' )'
            , 'snapshots' =>
              'CREATE TABLE snapshots' .
              ' ( playlist CHAR(22) NOT NULL' .
              ' , user TINYTEXT NOT NULL' .
              ' , snapshot TEXT NOT NULL' .
              ' )'
            , 'playback' =>
              'CREATE TABLE playback' .
              ' ( user TINYTEXT NOT NULL' .
              ' , track_play_length_s MEDIUMINT UNSIGNED NOT NULL' .
              ' , fade_out_s TINYINT UNSIGNED NOT NULL' .
              ' )'
            , 'global_scratchpads' =>
              'CREATE TABLE global_scratchpads' .
              ' ( user TINYTEXT NOT NULL' .
              ' , scratchpad TEXT NOT NULL' .
              ' )'
            , 'logins' =>
              'CREATE TABLE logins' .
              ' ( user TINYTEXT NOT NULL' .
              ' , timestamp DATETIME NOT NULL' .
              ' )'
            ];

  // Check if there exists a database; if not, create it
  // https://stackoverflow.com/a/6432196
  foreach ($tables as $t => $sql) {
    if (queryDb("SELECT 1 FROM $t LIMIT 1", false) === false) {
      queryDb($sql);
    }
  }
}

/**
 * Send SQL query to database.
 *
 * @param string $sql The query string.
 * @param bool $fail_on_error Whether to throw exception on error.
 * @return mysqli_result object if successful.
 * @throws Exception when something went wrong.
 */
function queryDb($sql, $fail_on_error = true) {
  global $DH_DB_CONN;

  if (!$DH_DB_CONN) throw new Exception("no database connection");

  $res = $DH_DB_CONN->query($sql);
  if (!$res && $fail_on_error) {
    throw new Exception("Query failed ({$sql}): " . $DH_DB_CONN->error);
  }
  return $res;
}

/**
 * Escape a given value to make it safe to use in an SQL query.
 *
 * @param string $s Value to escape.
 * @return Escaped string.
 */
function escapeSqlValue($s) {
  global $DH_DB_CONN;
  if (!$DH_DB_CONN) throw new Exception("no database connection");
  return $DH_DB_CONN->real_escape_string($s);
}

/**
 * Converts JSON string into an associative array of JSON data.
 *
 * @param string $s JSON string.
 * @return mixed[]
 */
function fromJson($s) {
  return json_decode($s, true);
}

/**
 * Converts an array of JSON data into JSON string.
 *
 * @param string[] $j Array of JSON data.
 * @return string
 */
function toJson($j) {
  return json_encode($j, JSON_UNESCAPED_SLASHES);
}

/**
 * Creates a temporary directory with unique name and returns the path to it.
 *
 * @return string|false
 */
function createTempDir() {
  $path = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) .
          DIRECTORY_SEPARATOR .
          mt_rand() .
          microtime(true);
  if (mkdir($path)) {
      return $path;
  }
  return false;
}

/**
 * Make an array where each value is an array consists of each value from the
 * provided arrays. For example:
 *
 *   array_zip([1, 2, 3], [4, 5, 6]) = [[1, 4], [2, 5], [3, 6]];
 *
 * @param array $arrays Arrays.
 * @return array
 */
function array_zip(array ...$arrays) {
  foreach ($arrays as $a) {
    assert(is_array($a), 'not all arguments are arrays');
    assert(count($arrays[0]) == count($a), 'not same number of elements');
  }

  $num_arrays = count($arrays);
  if ($num_arrays == 0) {
    return [];
  }
  $num_elements = count($arrays[0]);
  $t = [];
  for ($i = 0; $i < $num_elements; $i++) {
    $v = [];
    foreach ($arrays as $a) {
      $v[] = $a[$i];
    }
    $t[] = $v;
  }
  return $t;
}

/**
 * Checks if a given string only consists of a certain set of characters.
 *
 * @param string $s String to check.
 * @param string $chars Set of characters.
 * @return true if check passes.
 */
function onlyHasChars($s, $chars) {
  assert(is_string($s), 'not a string');
  assert(is_string($chars), 'not a string');

  $chars = str_split($chars);
  foreach (str_split($s) as $c) {
    if (!in_array($c, $chars)) return false;
  }
  return true;
}

/**
 * Checks that a given value is a string consisting only of number characters.
 * A preceding '-' is allowed.
 *
 * @param mixed $s Value to check.
 * @return true if check passes.
 */
function isIntString($s) {
  if (!is_string($s) || strlen($s) == 0) {
    return false;
  }
  if ($s[0] == '-') {
    $s = substr($s, 1);
  }
  return onlyHasChars($s, '0123456789');
}

/**
 * Converts a string to corresponding integer.
 *
 * @param string $s String to convert.
 * @return int
 * @throws Exception when not an integer.
 */
function toIntString($s) {
  if (!isIntString($s)) {
    throw new Exception("not an int string: {$s}");
  }
  return (int) $s;
}

/**
 * Exception for representing failure due to having no active Spotify session.
 */
class NoSessionException extends \Exception {}

?>
