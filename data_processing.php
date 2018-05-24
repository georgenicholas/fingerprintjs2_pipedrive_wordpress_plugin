<?php

class Processing {
  // accepts an array of fingerprints
  // returns a string
  public static function clean_user_fingerprints($user_fingerprints) {
    write_log('clean users fingerprints');
    //remove excess spaces and duplicates
    $user_fingerprints = array_unique(array_filter(str_replace(' ', '', $user_fingerprints)));
    return implode(",",$user_fingerprints);
  }

  // take an array of users, and order the array from the master record to those to be merged.
  public static function order_those_to_be_merged($users) {
    write_log('order the users array by those who are to be merged');
    $ordered_users = [];
    foreach($users as $user) {

      if ($user['data']['name'] != 'Unknown' && isset($user['data']['email']['0']['value']) && $user['data']['email']['0']['value'] != "") {
        write_log('found that is not named unknown and has an email: ' . $user['data']['id'] . ' email: ' . $user['data']['email']['0']['value']);
        array_push($ordered_users, $user);
      }
      elseif ($user['data']['name'] != 'Unknown') {
        write_log('found user that doesnt have name of unknown but has no email: ' . $user['data']['id'] . ' email: ' . $user['data']['email']['0']['value']);
        array_push($ordered_users, $user);
      }
      else {
        write_log('found user with name of unknown: ' . $user['data']['id']);
        array_push($ordered_users, $user);
      }

    }
    // write_log($ordered_users);
    foreach($ordered_users as $user) {write_log($user['data']['id']);}
    return $ordered_users;
  }

  // accpets a string

}

?>
