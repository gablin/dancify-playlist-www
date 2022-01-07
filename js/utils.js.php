<?php
require '../autoload.php';
?>

/**
 * Runs an API call and invokes a callback function depending on the outcome.
 * Note that the call is executed asynchronously, meaning this function will return
 * immediately even if the API call has not finished executing.
 *
 * @param url HTTP URL of API to access.
 * @param data Struct object to pass to API.
 * @param success_f Function to run on success, takes struct object as arg.
 * @param fail_f Function to run on failure, takes message string as arg.
 */
function callApi(url, data, success_f, fail_f) {
  $.post(url, { data: JSON.stringify(data) })
    .done(
      function(res) {
        json = JSON.parse(res);
        if (json.status == 'OK') {
          success_f(json);
        }
        else if (json.status == 'FAILED') {
          console.log(json.msg);
          fail_f(json.msg);
        }
      }
    )
    .fail(
      function(xhr, status, error) {
        console.log(error);
        fail_f(error);
      }
    );
}
