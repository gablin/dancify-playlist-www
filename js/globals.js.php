<?php
require '../autoload.php';
?>

var PLAYLIST_FORM_S = null;
var PLAYLIST_TABLE_S = null;
var SCRATCHPAD_TABLE_S = null;
var LOAD_TRACKS_LIMIT = 50;

function initPlaylistGlobals(form_s, p_table_s, s_table_s) {
  PLAYLIST_FORM_S = form_s;
  PLAYLIST_TABLE_S = p_table_s;
  SCRATCHPAD_TABLE_S = s_table_s;
}

function getPlaylistForm() {
  return $(PLAYLIST_FORM_S);
}

function getPlaylistTable() {
  return $(PLAYLIST_TABLE_S);
}

function getScratchpadTable() {
  return $(SCRATCHPAD_TABLE_S);
}
