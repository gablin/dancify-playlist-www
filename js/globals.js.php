<?php
require '../autoload.php';
?>

var PLAYLIST_FORM = null;
var PLAYLIST_TABLE = null;
var SCRATCHPAD_TABLE = null;

function initPlaylistGlobals(form, p_table, s_table) {
  PLAYLIST_FORM = form;
  PLAYLIST_TABLE = p_table;
  SCRATCHPAD_TABLE = s_table;
}

function getPlaylistForm() {
  return PLAYLIST_FORM;
}

function getPlaylistTable() {
  return PLAYLIST_TABLE;
}

function getScratchpadTable() {
  return SCRATCHPAD_TABLE;
}
