<?php
/**
 * Builds the menu item for showing all playlists.
 *
 * @param SpotifyWebAPI\SpotifyWebAPI $api API object.
 * @returns array|null Menu item.
 */
function mkMenuItemShowPlaylists($api) {
  return array('str' => LNG_MENU_SELECT_PLAYLIST, 'lnk' => '/app/randomize-by-bpm/');
}

/**
 * Builds the menu item for showing the selected playlist.
 *
 * @param SpotifyWebAPI\SpotifyWebAPI $api API object.
 * @returns array|null Menu item.
 */
function mkMenuItemShowPlaylistTracks($api) {
  $name = '';
  $uri = '/app/randomize-by-bpm/show-tracks/';
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
    foreach (['playlist_id', 'track_order'] as $k) {
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
?>
