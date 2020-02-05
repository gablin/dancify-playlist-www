<?php
session_start();

/**
 * Outputs beginning of every HTML page.
 */
function beginPage() {
?>
<!DOCTYPE html>
<html>
  <head>
    <title><?php echo(LNG_SLOGAN); ?>!</title>
    <link href="https://fonts.googleapis.com/css?family=Lobster|Roboto|Roboto+Condensed:300&display=swap" rel="stylesheet"></link>
    <link rel="stylesheet" href="/css/main.css"></link>
  </head>
  <body>
    <div class="logo">
      <div class="text">
        <?php echo(LNG_SLOGAN); ?>!
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
  if (hasGET('accept_cookies')) {
    setcookie('accept_cookies', true);
  }
  else if (!hasCOOKIE('accept_cookies')) {
    ?>
    <div class="cookies">
      <h1><?php echo(LNG_DESC_USES_COOKIES); ?></h1>
      <table>
        <tr>
          <td><?php echo(LNG_TXT_COOKIES); ?></td>
          <td><a href="<?php echo(augmentThisLink(array('accept_cookies' => 'true'))); ?>" class="button"><?php echo(LNG_BTN_I_AGREE); ?></a></td>
        </tr>
      </table>
    </div>
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

  $callback = "http://{$_SERVER['SERVER_NAME']}/auth/callback/";
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
 * Checks whether a session is active. If not, the client is redirected to the
 * login page.
 */
function ensureSession() {
  if (!hasSession()) {
    header('Location: /auth/');
    die();
  }
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
 * Checks that the user of this API is authorized to use the application. If
 * not, the user is redirected to another page.
 *
 * @param SpotifyWebAPI\SpotifyWebAPI API object.
 */
function ensureAuthorizedUser($api) {
  global $AUTH_USERS;
  $user = $api->me();
  if (!in_array($user->id, $AUTH_USERS)) {
    header('Location: /auth/bad/');
    die();
  }
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
 * Converts a track length to "hh:mm:ss" format.
 *
 * @param int $ms Track length, in milliseconds.
 * @returns string Formatted string.
 */
function formatTrackLength($ms) {
  $t = (int) round($ms / 1000);
  $t = array(0, 0, $t);
  for ($i = count($t) - 2; $i >= 0; $i--) {
    if ($t[$i+1] < 60) break;
    $t[$i] = floor($t[$i+1] / 60);
    $t[$i+1] = $t[$i+1] % 60;
  }

  if ($t[0] == 0) unset($t[0]);
  $is = array_keys($t);
  for ($j = 1; $j < count($is); $j++) {
    $i = $is[$j];
    if ($t[$i] < 10) $t[$i] === '0' . $t[$i];
  }
  
  return join(":", $t);
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
  for ($i = 0; ; $i += $limit) {
    $options = [ 'limit' => $limit, 'offset' => $i ];
    $ts = $api->getPlaylistTracks($id, $options);
    foreach ($ts->items as $t) {
      array_push($tracks, $t);
    }
    if (count($ts->items) < $limit) break;
  }
  return $tracks;
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
 * Checks if user has set a language.
 *
 * @returns string Language setting.
 */
function isLangSet() {
  $k = 'lang';
  return hasCOOKIE($k) || hasGET($k);
}

/**
 * Gets language setting.
 *
 * @returns string Language setting.
 * @throws Exception If language is not set.
 */
function getLang() {
  $k = 'lang';
  if (hasGET($k)) {
    return fromGET($k);
  }
  else if (hasCOOKIE($k)) {
    return fromCOOKIE($k);
  }
  else {
    throw new Exception('no language set');
  }
}

/**
 * Saves current language setting.
 */
function saveLang() {
  if (isLangSet()) {
    setcookie('lang', getLang());
  }
}
?>
