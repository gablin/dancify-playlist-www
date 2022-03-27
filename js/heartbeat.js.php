<?php
require '../autoload.php';
?>

const HEARTBEAT_INTERVAL_MS = 60*1000;

function setupHeartbeat() {
  setInterval(sendHeartbeat, HEARTBEAT_INTERVAL_MS);
}

function sendHeartbeat() {
  callApi('/api/heartbeat/', {}, function() {}, function() {});
}
