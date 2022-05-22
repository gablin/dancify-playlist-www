<?php
require '../autoload.php';
?>

const MS_FAILURE_LIMIT = 1000;
var STATUS_FAILURE_REPORT_TIMESTAMP = 0;

function getMsTimestamp() {
  return Date.now();
}

function msElapsedSinceLastTimestamp() {
  return getMsTimestamp() - STATUS_FAILURE_REPORT_TIMESTAMP;
}

function setStatus(s, indicate_failure = false) {
  if (msElapsedSinceLastTimestamp() < MS_FAILURE_LIMIT && !indicate_failure) {
    return;
  }

  let status = $('.saving-status');
  status.text(s);
  status.removeClass('failed');
  if (indicate_failure) {
    status.addClass('failed');
    STATUS_FAILURE_REPORT_TIMESTAMP = getMsTimestamp();
  }
}

function clearStatus() {
  if (msElapsedSinceLastTimestamp() < MS_FAILURE_LIMIT) {
    return;
  }

  let status = $('.saving-status');
  status.empty();
  status.removeClass('failed');
}
