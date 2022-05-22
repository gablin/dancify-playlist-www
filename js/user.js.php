<?php
require '../autoload.php';
?>

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

  let table = $('#playlists');
  function load(offset) {
    let data = { userId: user_id
               , offset: offset
               };
    callApi( '/api/get-user-playlists/'
           , data
           , function(d) {
               for (let i = 0; i < d.playlists.length; i++) {
                 let p = d.playlists[i];
                 table.append( '<tr>' +
                                 '<td>' +
                                   '<a href="./playlist/?id=' + p.id + '">' +
                                     p.name +
                                   '</a>' +
                                 '</td>' +
                               '</tr>'
                             );
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
