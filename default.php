<?php
require 'autoload.php';

beginPage();
beginContent();
?>

<p>
  Have you ever had a playlist where you would need to insert a sound effect
  of some sort &ndash; like silence or a <em>dinnnnng!</em> &ndash; every second
  or third song? Then you would know how tedious, boring, and time-consuming
  that task is. And then you would be especially happy to know that you no
  longer need to do that manually, because this website can do that for you!
  Just log in, select the playlist you want to <em>dingify</em>, enter song and
  frequency, and then save as new playlist. Done!
</p>

<div class="login">
  <a href="/auth/" class="button">Login with Spotify</a>
</div>

<?php
endContent();
endPage();
?>
