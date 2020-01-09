<?php
require '../autoload.php';

$session = createApiSession();
$options = [ 'scope' =>
             [ 'playlist-read-private'
             , 'playlist-read-collaborative'
             , 'playlist-modify-public'
             , 'playlist-modify-private'
             ]
           ];

header('Location: ' . $session->getAuthorizeUrl($options));
die();
?>
