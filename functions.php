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
    <title>Dingify Your Playlist!</title>
    <link href="https://fonts.googleapis.com/css?family=Lobster|Roboto|Roboto+Condensed:300&display=swap" rel="stylesheet"></link>
    <link rel="stylesheet" href="/css/main.css"></link>
  </head>
  <body>
    <div class="logo">
      <div class="text">
        Dingify Your Playlist!
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
        <li class="logout"><a href="/app/logout">Logout</a></li>
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
 * Creates an API session. It is assumed that a PHP session has already been
 * started.
 *
 * @returns SpotifyWebAPI\Session An active API session.
 */
function createApiSession() {
  global $SPOTIFY_CLIENT_ID, $SPOTIFY_CLIENT_SECRET;

  $callback = 'http://' . $_SERVER['SERVER_NAME'] . '/auth/callback/';
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
    <span class="heading">ERROR:</span> <?php echo($msg); ?>
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
    if ($t[$i] < 10) $t[$i] = '0' . $t[$i];
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
?>
