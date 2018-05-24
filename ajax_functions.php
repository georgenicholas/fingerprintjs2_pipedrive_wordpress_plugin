<?php

function fingerprinting_ajax_request() {
  if ( isset($_REQUEST) ) {
    // set session variables (these will expire on each page load as not to destroy cacheing)
    // if(isset($_REQUEST['fingerprint'])) { $_SESSION["fingerprint_session"] = $_REQUEST['fingerprint']; } else { $_SESSION["fingerprint_session"] = null; }
    //
    // if(isset($_REQUEST['user_id'])) { $_SESSION["user_id"] = $_REQUEST['user_id']; $_COOKIE['user_id'] = $_REQUEST['user_id']; }
    // elseif(isset($_COOKIE['user_id'])) { $_SESSION["user_id"] = $_COOKIE['user_id']; }
    // else { $_SESSION["user_id"] = null; }
    //
    // if(isset($_REQUEST['company_id'])) { $company_id = $_REQUEST['company_id']; } else { $company_id = null; }

    if (isset($_REQUEST['user_id']) && isset($_REQUEST['fingerprint'])) {
      $user = new Person;
      if (isset($_REQUEST['user_id'])) { $user -> set_id($_SESSION["user_id"]); }
      if (isset($_REQUEST['fingerprint'])) { $user -> set_fingerprint($_REQUEST['fingerprint']); }
      $user -> get_fingerprints();
      Person::find_and_merge($user->id);
      // log website hit
      $activity = new Activity;
      $activity -> set_subject('Website Hit');
      $activity -> set_type('website_hit');
      $activity -> set_user_id($user->id);
      $activity -> create_activity();
      unset($activity);
      // log fingerprint on user

      // attempt to merge any duplicates.  finding users always calls a merge function.
      Persons::find_user_id_by_fingerprint($_SESSION["fingerprint_session"]);
    }
    elseif (isset($_SESSION["fingerprint_session"])) {
      write_log('no user id present, using fingerprint only');
      $user_id = Persons::find_user_id_by_fingerprint($_SESSION["fingerprint_session"]);
      if (isset($user_id)) {
        Activity::record_hit_by_user_id($user_id);
      }
      else {
        write_log('create new user in pipedrive with fingerprint');
        $data = '{"name": "Unknown", "visible_to": "3", "26dccb2d4b7b701a77f418266af26599c970a414":"' . $_SESSION["fingerprint_session"] . '"}';
        $user = Persons::create_pipedrive_user($data);
        Activity::record_hit_by_user_id($user["data"]["id"]);
      }
    }
  }
  die();
}


?>
