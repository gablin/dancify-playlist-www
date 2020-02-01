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
 * @param string $plst_id Playlist ID.
 * @returns array|null Menu item.
 */
function mkMenuItemShowPlaylistTracks($api, $plst_id) {
  $name = '';
  $lnk = '/app/insert-ding/show-tracks/?playlist_id=' . $plst_id;
  try {
    $info = loadPlaylistInfo($api, $plst_id);
  
    // Get name
    $name = $info->name;
    $max_length = 32;
    if (strlen($name) > $max_length) {
      $name = substr($name, 0, $max_length) . '&hellip;';
    }
  
    // Append additional GET to link
    if (hasGET('track')) {
      $lnk .= '&track=' . $_GET['track'];
    }
    if (hasGET('freq')) {
      $lnk .= '&freq=' . $_GET['freq'];
    }
  }
  catch (SpotifyWebAPI\SpotifyWebAPIException $e) {
    $name = 'ERROR';
  }
  return array('str' => $name, 'lnk' => $lnk);
}
?>