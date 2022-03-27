<?php
require '../autoload.php';
?>

const HEARTBEAT_INTERVAL_MS = 60*1000;

function setupHeartbeat() {
  setInterval(sendHeartbeat, HEARTBEAT_INTERVAL_MS);
}

function sendHeartbeat() {
  console.log('beating...');
  callApi('/api/heartbeat/', {}, function() {}, function() {});
}
