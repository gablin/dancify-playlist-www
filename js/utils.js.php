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
        else if (json.status == 'NOSESSION') {
          window.location.href = '<?= getAuthUrl() ?>';
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
function strcmp(s1, s2) {
  return (s1 == s2) ? 0 : (s1 > s2) ? 1 : -1;
}

/**
 * Same as strcmp but for integers.
 *
 * @param i1 First integer.
 * @param i2 Second integer.
 * @return int
 */
function intcmp(i1, i2) {
  return (i1 == i2) ? 0 : (i1 > i2) ? 1 : -1;
}

/**
 * Returns a new array containing only unique values.
 *
 * @param a Array.
 * @return New array.
 */
function uniq(a) {
  return [...new Set(a)];
}

/**
 * Increments an RGB-specified color by a given amount. If one channel becomes
 * saturated, the leftover is distributed onto the other channels, thus attempting
 * to guarantee a visual difference.
 *
 * @param rgb Int array.
 * @param inc int.
 * @return Int array.
 */
function rgbVisIncr(cs, inc) {
  let cs_indices = cs.map((c, i) => { return { val: c, index: i } });
  cs_indices.sort((a, b) => { return -1*intcmp(a.val, b.val) } )
  let sorted_cs = cs_indices.map(o => o.val);
  for (let i = 0; i < sorted_cs.length; i++) {
    let c = sorted_cs[i] + inc;
    if (c > 255) {
      let residue = c - 255;
      inc += Math.round(residue / (sorted_cs.length - i - 1));
      c = 255;
    }
    sorted_cs[i] = c;
  }
  let new_cs = [];
  for (let i = 0; i < cs.length; i++) {
    for (let j = 0; j < cs.length; j++) {
      if (cs_indices[j].index == i) {
        new_cs.push(sorted_cs[j]);
        break;
      }
    }
  }
  return new_cs;
}

/**
 * Fix of jQuery's clone() function to also copy values of select and textarea
 * elements.
 *
 * See: https://stackoverflow.com/a/11804162
 */
(function (original) {
  jQuery.fn.clone = function () {
    var result           = original.apply(this, arguments),
        my_textareas     = this.find('textarea').add(this.filter('textarea')),
        result_textareas = result.find('textarea').add(result.filter('textarea')),
        my_selects       = this.find('select').add(this.filter('select')),
        result_selects   = result.find('select').add(result.filter('select'));

    for (var i = 0, l = my_textareas.length; i < l; ++i) {
      $(result_textareas[i]).val($(my_textareas[i]).val());
    }
    for (var i = 0, l = my_selects.length; i < l; ++i) {
      result_selects[i].selectedIndex = my_selects[i].selectedIndex;
    }
    return result;
  };
}) (jQuery.fn.clone);
