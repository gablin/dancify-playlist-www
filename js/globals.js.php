<?php
require '../autoload.php';
?>

var PLAYLIST_FORM = null;
var PLAYLIST_TABLE = null;

function initPlaylistGlobals(form, table) {
  PLAYLIST_FORM = form;
  PLAYLIST_TABLE = table;
}

function getPlaylistForm() {
  return PLAYLIST_FORM;
}

function getPlaylistTable() {
  return PLAYLIST_TABLE;
}
