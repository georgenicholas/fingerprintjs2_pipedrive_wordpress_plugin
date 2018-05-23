<?php
// https://premium.wpmudev.org/blog/set-get-delete-cookies/
  $testing= "test";
  setcookie($testing, "testing", 30 * DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN);
  write_log($_COOKIE[$testing]);




?>
