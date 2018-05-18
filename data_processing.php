<?php

function clean_user_fingerprints($user_fingerprints) {
  write_log('clean users fingerprints');
  //remove excess spaces and duplicates
  return array_unique(array_filter(str_replace(' ', '', $user_fingerprints)));
}

?>
