<?php
session_start();

function createApiSession() {
  global $SPOTIFY_CLIENT_ID, $SPOTIFY_CLIENT_SECRET;

  $callback = 'http://' . $_SERVER['SERVER_NAME'] . '/auth/callback/';
  $session = new SpotifyWebAPI\Session( $SPOTIFY_CLIENT_ID
                                      , $SPOTIFY_CLIENT_SECRET
                                      , $callback
                                      );
  return $session;    
}

function checkTokens() {
  if (!isset($_SESSION['access-token']) || !isset($_SESSION['refresh-token'])) {
    header('Location: /auth/');
    die();
  }
}

function getSession() {
  checkTokens();
  $session = createApiSession();
  $session->setAccessToken($_SESSION['access-token']);
  $session->setRefreshToken($_SESSION['refresh-token']);

  return $session;
}

function updateTokens($session) {
  $_SESSION['access-token']  = $session->getAccessToken();
  $_SESSION['refresh-token'] = $session->getRefreshToken();
}

function createWebApi($session) {
  $api = new SpotifyWebAPI\SpotifyWebAPI();
  $api->setSession($session);
  $api->setOptions(['auto_refresh' => true]);

  return $api;
}

?>