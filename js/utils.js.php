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
        if (json.status == 'FAILED') {
          console.log(json.msg);
          fail_f(json.msg);
          return;
        }
        success_f(json);
      }
    )
    .fail(
      function(xhr, status, error) {
        console.log(error);
        fail_f(error);
      }
    );
}

/**
 * Do string comparison that can be used for sorting. Returns:
 *   <0 if s1 is lexicographically less than s2,
 *   >0 if s1 is lexicographically greater than s2,
 *    0 if s1 is equal to s2.
 *
 * @param s1 First string.
 * @param s2 Second string.
 * @return int
 */
function strcmp(str1, str2) {
  return (str1 == str2) ? 0 : (str1 > str2) ? 1 : -1;
}
