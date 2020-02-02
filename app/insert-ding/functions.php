<?php
/**
 * Builds the menu item for showing all playlists.
 *
 * @param SpotifyWebAPI\SpotifyWebAPI $api API object.
 * @returns array|null Menu item.
 */
function mkMenuItemShowPlaylists($api) {
  return array('str' => 'Select playlist', 'lnk' => '/app/insert-ding/');
}

/**
 * Builds the menu item for showing the selected playlist.
 *
 * @param SpotifyWebAPI\SpotifyWebAPI $api API object.
 * @returns array|null Menu item.
 */
function mkMenuItemShowPlaylistTracks($api) {
  $name = '';
  $uri = '/app/insert-ding/show-tracks/';
  $gets = array();
  try {
    $playlist_id = fromGET('playlist_id');
    $info = loadPlaylistInfo($api, $playlist_id);

    // Get name
    $name = $info->name;
    $max_length = 32;
    if (strlen($name) > $max_length) {
      $name = substr($name, 0, $max_length) . '&hellip;';
    }

    // Make list of GET values
    foreach (['playlist_id', 'track', 'freq'] as $k) {
      if (hasGET($k)) {
        $gets[$k] = fromGET($k);
      }
    }
  }
  catch (Exception $e) {
    $name = 'ERROR';
  }
  return array('str' => $name, 'lnk' => buildLink($uri, $gets));
}

/**
 * Builds the menu item for showing the selected playlist.
 *
 * @param SpotifyWebAPI\SpotifyWebAPI $api API object.
 * @returns array|null Menu item.
 */
function mkMenuItemNewPlaylist($api) {
  $name = 'Save as new playlist';
  $uri = '/app/insert-ding/show-tracks/new-playlist/';
  $gets = array();
  foreach (['playlist_id', 'track', 'track_id', 'freq'] as $k) {
    if (hasGET($k)) {
      $gets[$k] = fromGET($k);
    }
  }
  return array('str' => $name, 'lnk' => buildLink($uri, $gets));
}

/**
 * Builds the menu item for the newly created playlist.
 *
 * @param SpotifyWebAPI\SpotifyWebAPI $api API object.
 * @returns array|null Menu item.
 */
function mkMenuItemNewPlaylistCreated($api) {
  $name = 'Success';
  $uri = '/app/insert-ding/show-tracks/new-playlist/commit/ok';
  $gets = array();
  foreach ( ['playlist_id', 'track', 'track_id', 'freq', 'new_playlist_id']
            as $k
          ) {
    if (hasGET($k)) {
      $gets[$k] = fromGET($k);
    }
  }
  return array('str' => $name, 'lnk' => buildLink($uri, $gets));
}
?>