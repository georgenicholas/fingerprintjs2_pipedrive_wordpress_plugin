<?php

function fingerprinting_ajax_request() {
  if ( isset($_REQUEST) ) {
    if(isset($_REQUEST['fingerprint'])) { $fingerprint = $_REQUEST['fingerprint']; } else { $fingerprint = null; }
    if(isset($_REQUEST['user_id'])) { $user_id = $_REQUEST['user_id']; } else { $user_id = null; }
    if(isset($_REQUEST['company_id'])) { $company_id = $_REQUEST['company_id']; } else { $company_id = null; }
    if(isset($fingerprint)) { $_SESSION["fingerprint_session"] = $fingerprint; } else { $_SESSION["fingerprint_session"] = null; }
    if(isset($user_id)) { $_SESSION["user_id"] = $user_id; } else { $_SESSION["user_id"] = null; }
    // write_log('fingerprint: '.$fingerprint.' user id: '.$user_id.' company id: '.$company_id);
    if (isset($user_id) && isset($fingerprint)) {
      write_log('found finerprint and user id');
      $user_fingerprints = Persons::get_user_fingerprints_by_id($user_id);
      if(!isset($user_fingerprints)) { $user_fingerprints = []; }
      array_push($user_fingerprints,$_SESSION["fingerprint_session"]);
      Persons::set_user_fingerprints_by_id($user_fingerprints, $user_id);
      Activity::record_hit_by_user_id($user_id);
      write_log('check for duplicates');
      $user_id_array = Persons::find_user_ids_by_fingerprint($fingerprint); //returns array of user ids.
      if (count($user_id_array) >= 2) {
        $user_array = [];
        foreach($user_id_array as $this) { array_push($user_array, Persons::get_pipedrive_user($this)); };
        Persons::merge_pipedrive_users($user_array);
      }
      else {
        write_log('didnt find any duplicates');
      }
    }
    elseif (isset($fingerprint)) {
      write_log('no user id present, using fingerprint only');
      $user_id_array = Persons::find_user_ids_by_fingerprint($fingerprint);
      if (is_array($user_id_array)) {
        foreach($user_id_array as $user_id) {
          Activity::record_hit_by_user_id($user_id);
        }
      }
      else {
        write_log('create new user in pipedrive with fingerprint');
        $data = '{"name": "Unknown", "visible_to": "3", "26dccb2d4b7b701a77f418266af26599c970a414":"' . $fingerprint . '"}';
        $user = Persons::create_pipedrive_user($data);
        Activity::record_hit_by_user_id($user["data"]["id"]);
      }
    }
  }
  die();
}

?>
