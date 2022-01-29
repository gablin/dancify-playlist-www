<?php
require '../autoload.php';
?>

function setStatus(s, indicate_failure = false) {
  var status = $('.saving-status');
  status.text(s);
  status.removeClass('failed');
  if (indicate_failure) {
    status.addClass('failed');
  }
}

function clearStatus() {
  var status = $('.saving-status');
  status.empty();
  status.removeClass('failed');
}
