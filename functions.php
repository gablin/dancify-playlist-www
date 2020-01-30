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
    <link rel="stylesheet" href="/css/main.css"></link>
  </head>
  <body>
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
 * Outputs an error message.
 *
 * @param Exception $e
 */
function showError($e) {
  ?>
  <div class="error">
    ERROR: <?php echo($e->getMessage()); ?>
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
 * non-whitespace value.
 *
 * @param string k Key to check.
 * @returns bool true if valid value is present.
 */
function hasGET($k) {
  return isset($_GET[$k]) && strlen(trim($_GET[$k])) > 0;
}

/**
 * Checks if $_GET has a given key with a non-empty, non-whitespace value. If
 * not, an Exception is thrown.
 *
 * @param string k Key to check.
 * @throws Exception If check fails.
 */
function ensureGET($k) {
  if (!hasGET($k)) {
    throw new Exception(sprintf('\'%s\' not in GET query', $k));
  }
}

/**
 * Loads all playlists from the current user.
 *
 * @param SpotifyWebAPI\SpotifyWebAPI api API object.
 * @returns List of playlist objects.
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
 * @returns List of playlist track objects.
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