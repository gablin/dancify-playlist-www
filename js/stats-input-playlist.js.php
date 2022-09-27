<?php
require '../autoload.php';

ensureSession();
$session = getSession();
$api = createWebApi($session);
?>

function setupStatsInputPlaylist() {
  let form = $('div[name=stats-input-playlist]');
  prepareStatsInputForm(form);

  $('#statsInputPlaylistBtn').click(
    function() {
      let input_playlist =
        form.find('select[name=input-playlist] option:selected').val().trim();
      let against_playlists = [];
      form.find('select[name=against-playlists] option:selected').each(
        function() {
          let o = $(this);
          against_playlists.push(o.val().trim());
        }
      );
      if (input_playlist.length == 0) {
        alert('<?= LNG_ERROR_MUST_SELECT_INPUT_PLAYLIST ?>');
        return;
      }
      if (against_playlists.length == 0) {
        alert('<?= LNG_ERROR_MUST_SELECT_COMPARE_AGAINST_PLAYLISTS ?>');
        return;
      }

      let body = $(document.body);
      body.addClass('loading');
      function done(content) {
        body.removeClass('loading');
        triggerStatsDownload('input-stats', content);
      }
      function fail(msg) {
        body.removeClass('loading');
        alert(msg);
      }
      generateStatsContent(input_playlist, against_playlists, done, fail);
    }
  );
}

function prepareStatsInputForm(form) {
  function fail(msg) {
    setStatus('<?= LNG_ERR_FAILED_LOAD_PLAYLIST ?>', true);
  }

  let input_select = form.find('select[name=input-playlist]');
  let against_select = form.find('select[name=against-playlists]');
  function load(offset) {
    let data = { userId: '<?= getThisUserId($api) ?>'
               , offset: offset
               };
    callApi( '/api/get-user-playlists/'
           , data
           , function(d) {
               for (let i = 0; i < d.playlists.length; i++) {
                 let p = d.playlists[i];
                 let option = $('<option />').text(p.name).attr('value', p.id);
                 input_select.append(option);
                 against_select.append(option.clone());
               }
               offset += d.playlists.length;
               if (offset == d.total) {
                 return;
               }
               load(offset);
             }
           , fail
           );
  }
  load(0);
}

function generateStatsContent(input_playlist, against_playlists, done_f, fail_f) {
  function toCsv(d) {
    if (d === undefined) {
      return '';
    }
    return '"' + d.toString().replaceAll('"', '""') + '"';
  }

  function loadPlaylists(ps, loaded_ps) {
    if (ps.length == 0) {
      input_playlist = loaded_ps[0];
      getTrackInfo(
        input_playlist.tracks.map((t) => t.track)
      , function(tracks) {
          input_playlist.tracks = tracks.map(
                                    (t, i) => {
                                      t.addedBy = input_playlist.tracks[i].addedBy;
                                      return t;
                                    }
                                  );
          computeStats(input_playlist, loaded_ps.slice(1));
        }
      , fail_f
      );
    }
    else {
      getTracksAndInfoFromPlaylist(
        ps[0]
      , function(d) {
          loaded_ps.push(d);
          loadPlaylists(ps.slice(1), loaded_ps);
        }
      , fail_f
      );
    }
  }
  let playlists = [input_playlist].concat(against_playlists);
  loadPlaylists(playlists, []);

  function computeStats(input_playlist, against_playlists) {
    let users = uniq(input_playlist.tracks.map((t) => t.addedBy));
    getUserInfo(
      users
    , (users) => {
        let stats = computeStatsSub(users, input_playlist, against_playlists);
        let csv = stats.map((r) => r.map(toCsv).join(',')).join('\n');
        done_f(csv);
      }
      , fail_f
    );
  }

  function computeStatsSub(users, input_playlist, against_playlists) {
    let headers = [ '#'
                  , '<?= LNG_HEAD_SELECTED_IN ?>'
                  , '<?= LNG_HEAD_ADDED_BY ?>'
                  , '<?= LNG_HEAD_NAME ?>'
                  , '<?= LNG_HEAD_ARTIST ?>'
                  , '<?= LNG_HEAD_BPM ?>'
                  , '<?= LNG_HEAD_GENRE ?>'
                  ];

    let against_all_track_ids =
      against_playlists.reduce(
        (a, p) => a.concat(p.tracks.map((t) => t.track))
      , []
      );
    let user_stats = users.map(
      (u) => {
        function entry(t) {
          return [ null
                 , t.inPlaylist ? t.inPlaylist : ''
                 , u.name
                 , t.name
                 , t.artists.join(', ')
                 , t.bpm
                 , formatGenre(t.genre.by_user)
                 ];
        }

        let tracks = input_playlist.tracks.filter((t) => t.addedBy === u.id);
        let granted = [];
        tracks.forEach(
          (t) => {
            for (let i = 0; i < against_playlists.length; i++) {
              let p = against_playlists[i];
              if (p.tracks.map((w) => w.track).includes(t.trackId)) {
                t.inPlaylist = p.name;
                granted.push(t);
                break;
              }
            }
          }
        );
        let left =
          tracks.filter((t) => !against_all_track_ids.includes(t.trackId));

        let granted_entries = granted.map((t) => entry(t));
        let left_entries = left.map((t) => entry(t));

        return { user: u, entries: granted_entries.concat(left_entries) };
      }
    );

    user_stats.sort(
      (u1, u2) => u1.entries.length < u2.entries.length
                  ? -1 : (u1.entries.length > u2.entries.length ? 1 : 0)
    );

    let stats = user_stats.reduce((a, d) => a.concat(d.entries), []);
    stats = stats.map((e, i) => { e[0] = i+1; return e; });

    return [headers].concat(stats);
  }
}

function getTracksAndInfoFromPlaylist(playlist_id, done_f, fail_f) {
  let tracks = [];
  function load(offset) {
    let data = { playlistId: playlist_id
               , offset: offset
               };
    callApi( '/api/get-playlist-tracks/'
           , data
           , function(d) {
               tracks = tracks.concat(d.tracks);
               let next_offset = offset + d.tracks.length;
               if (next_offset < d.total) {
                 load(next_offset);
               }
               else {
                 callApi( '/api/get-playlist-info/'
                        , { playlistId: playlist_id }
                        , function(d) {
                            done_f({ name: d.info.name, tracks: tracks });
                          }
                        , fail_f
                        );
               }
             }
           , fail_f
           );
  }
  load(0);
}

function getTrackInfo(track_ids, done_f, fail_f) {
  let LIMIT = 50;
  let tracks = [];
  function load(offset) {
    callApi( '/api/get-track-info/'
           , { trackIds: track_ids.filter(
                           (t, i) => i >= offset && i < offset + LIMIT
                         )
             }
           , function(d) {
               tracks = tracks.concat(d.tracks);
               let next_offset = offset + d.tracks.length;
               if (next_offset < track_ids.length) {
                 load(next_offset);
               }
               else {
                 done_f(tracks);
               }
             }
           , fail_f
           );
  }
  load(0);
}

function getUserInfo(users, done_f, fail_f) {
  let user_info = [];
  function load(i) {
    callApi( '/api/get-user-info/'
           , { userId: users[i] }
           , function(d) {
               user_info.push(d)
               i++;
               if (i < users.length) {
                 load(i);
               }
               else {
                 done_f(user_info);
               }
             }
           , fail_f
           );
  }
  load(0);
}

function triggerStatsDownload(title, content) {
  let a = $('<a></a>');
  a.attr('href', 'data:text/plain;charset=utf-8,' + encodeURIComponent(content));
  a.attr('download', title + '.csv');
  a.hide();

  $(document.body).append(a);
  a[0].click();
  a.remove();
}
