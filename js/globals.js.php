<?php
require '../autoload.php';
?>

var PLAYLIST_FORM = null;
var PLAYLIST_TABLE = null;
var LOCAL_SCRATCHPAD_TABLE = null;
var GLOBAL_SCRATCHPAD_TABLE = null;
var LOAD_TRACKS_LIMIT = 50;

function initPlaylistGlobals(form, p_table, local_s_table, global_s_table) {
  PLAYLIST_FORM = form;
  PLAYLIST_TABLE = p_table;
  LOCAL_SCRATCHPAD_TABLE = local_s_table;
  GLOBAL_SCRATCHPAD_TABLE = global_s_table;
}

function getPlaylistForm() {
  return $(PLAYLIST_FORM);
}

function getPlaylistTable() {
  return $(PLAYLIST_TABLE);
}

function getLocalScratchpadTable() {
  return $(LOCAL_SCRATCHPAD_TABLE);
}

function getGlobalScratchpadTable() {
  return $(GLOBAL_SCRATCHPAD_TABLE);
}
