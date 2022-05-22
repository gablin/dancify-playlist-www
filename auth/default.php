<?php
require '../autoload.php';

$session = createApiSession();
$options = [ 'scope' =>
             [ 'playlist-read-private'
             , 'playlist-read-collaborative'
             , 'playlist-modify-public'
             , 'playlist-modify-private'
             , 'streaming'
             , 'user-read-email'
             , 'user-read-private'
             ]
           , 'show_dialog' => true
           ];

header("Location: {$session->getAuthorizeUrl($options)}");
die();
?>
