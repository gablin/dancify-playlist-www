<?php
require '../autoload.php';
?>

function setupScratchpad() {
  setupFormElementsForScratchpad();
  renderTable(getLocalScratchpadTable());
  renderTable(getGlobalScratchpadTable());

  if (Cookies.get('show-global-scratchpad') === 'true') {
    showScratchpad(getGlobalScratchpadTable());
  }
}

function getLocalScratchpadButton() {
  return getPlaylistForm().find('button[id=localScratchpadBtn]');
}

function getGlobalScratchpadButton() {
  return getPlaylistForm().find('button[id=globalScratchpadBtn]');
}

function setupFormElementsForScratchpad() {
  let local_btn = getLocalScratchpadButton();
  let global_btn = getGlobalScratchpadButton();
  local_btn.click(
    function() {
      let table = getLocalScratchpadTable();
      if (isScratchpadTableShowing(table)) {
        hideScratchpad(table);
      }
      else {
        showScratchpad(table);
      }
    }
  );
  global_btn.click(
    function() {
      let table = getGlobalScratchpadTable();
      if (isScratchpadTableShowing(table)) {
        hideScratchpad(table);
        Cookies.set('show-global-scratchpad', 'false', { expires: 365*5 });
      }
      else {
        showScratchpad(table);
        Cookies.set('show-global-scratchpad', 'true', { expires: 365*5 });
      }
    }
  );
}

function isScratchpadTableShowing(table) {
  return table.closest('.playlist').is(':visible');
}

function showScratchpad(table) {
  table.closest('.playlist').show();
  if (table.is(getLocalScratchpadTable())) {
    getLocalScratchpadButton().text('<?= LNG_BTN_HIDE_LOCAL_SCRATCHPAD ?>');
  }
  else {
    getGlobalScratchpadButton().text('<?= LNG_BTN_HIDE_GLOBAL_SCRATCHPAD ?>');
  }
}

function hideScratchpad(table) {
  table.closest('.playlist').hide();
  if (table.is(getLocalScratchpadTable())) {
    getLocalScratchpadButton().text('<?= LNG_BTN_SHOW_LOCAL_SCRATCHPAD ?>');
  }
  else {
    getGlobalScratchpadButton().text('<?= LNG_BTN_SHOW_GLOBAL_SCRATCHPAD ?>');
  }
}
