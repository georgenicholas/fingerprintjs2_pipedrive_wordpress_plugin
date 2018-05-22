<?php

class Processing {
  public function clean_user_fingerprints($user_fingerprints) {
    write_log('clean users fingerprints');
    //remove excess spaces and duplicates
    $user_fingerprints = array_unique(array_filter(str_replace(' ', '', $user_fingerprints)));
    return implode(",",$user_fingerprints);
  }
}

?>
