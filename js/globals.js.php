<?php
require '../autoload.php';
?>

var PREVIEW_AUDIO = $('<audio />');
var PLAYLIST_TRACK_DELIMITER = 0;
var PLAYLIST_FORM = null;
var PLAYLIST_TABLE = null;

function initPlaylistGlobals(form, table) {
  PLAYLIST_FORM = form;
  PLAYLIST_TABLE = table;
}
