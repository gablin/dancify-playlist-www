<?php
require '../autoload.php';
?>

function buildUserPlaylistsTableTr(playlist_id, name) {
  let tr = $( '<tr>' +
                '<td>' +
                  '<a href="#">' + name + '</a>' +
                '</td>' +
              '</tr>'
            );
  tr.find('a').click(
    function() {
      let a = $(this);
      a.closest('table').find('a').removeClass('selected');
      a.addClass('selected');
      loadPlaylist(playlist_id);
    }
  );
  return tr;
}

function getUserPlaylistsTable() {
  return $('#playlists');
}

function loadUserPlaylists(user_id) {
  let body = $(document.body);
  body.addClass('loading');
  setStatus('<?= LNG_DESC_LOADING ?>...');
  function success() {
    body.removeClass('loading');
    clearStatus();
  }
  function fail(msg) {
    setStatus('<?= LNG_ERR_FAILED_LOAD_PLAYLIST ?>', true);
    body.removeClass('loading');
  }

  let table = getUserPlaylistsTable();
  function load(offset) {
    let data = { userId: user_id
               , offset: offset
               };
    callApi( '/api/get-user-playlists/'
           , data
           , function(d) {
               for (let i = 0; i < d.playlists.length; i++) {
                 let p = d.playlists[i];
                 let tr = buildUserPlaylistsTableTr(p.id, p.name);
                 table.append(tr);
               }
               offset += d.playlists.length;
               if (offset == d.total) {
                 success();
                 return;
               }
               load(offset);
             }
           , fail
           );
  }
  load(0);
}

function addToUserPlaylists(playlist_id, name) {
  let tr = buildUserPlaylistsTableTr(playlist_id, name);
  let table = getUserPlaylistsTable();
  table.prepend(tr);
  return tr;
}
