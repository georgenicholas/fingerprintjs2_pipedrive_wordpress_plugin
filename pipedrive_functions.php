<?php
 class Pipedrive {

   protected static function get_user($user_id) {
     $url = 'https://api.pipedrive.com/v1/persons/' . $user_id . '?api_token=' . DB_PIPEDRIVE_API_KEY;
     $data = null;
     return Pipedrive::pipedrive_api("GET", $url, $data);
   }

   protected static function find_users($field_key, $field_term) {
     $url = 'https://api.pipedrive.com/v1/searchResults/field?term=' . $field_term . '&exact_match=0&field_type=personField&field_key=' . $field_key . '&return_item_ids=1&start=0&api_token=' . DB_PIPEDRIVE_API_KEY;
     $data = null;
     return Pipedrive::pipedrive_api("GET", $url, $data);
   }

   protected static function create_person($data) {
     $url = 'https://api.pipedrive.com/v1/persons?api_token=' . DB_PIPEDRIVE_API_KEY;
     return Pipedrive::pipedrive_api("POST", $url, $data);
   }

   protected static function update_user($user_id, $data) {
     $url = 'https://api.pipedrive.com/v1/persons/' . $user_id . '?api_token=' . DB_PIPEDRIVE_API_KEY;
     return Pipedrive::pipedrive_api("PUT", $url, $data);
   }

   protected static function merge_users($user_to_be_merged, $user_id) {
     $url = 'https://api.pipedrive.com/v1/persons/' . $user_to_be_merged . '/merge?api_token=' . DB_PIPEDRIVE_API_KEY;
     $data = '{"merge_with_id":"' . $user_id . '"}';
     $response = Pipedrive::pipedrive_api("PUT", $url, $data);
     write_log('merged users success status: '.$response['success']);
   }

   protected static function create_activity($subject, $done, $type, $deal_id, $user_id, $message) {
     $url = 'https://api.pipedrive.com/v1/activities?api_token=' . DB_PIPEDRIVE_API_KEY;
     $data = '{
       "subject": "' . $subject . '",
       "done": "' . $done . '",
       "type": "' . $type . '",
       "deal_id": "' . $deal_id . '",
       "person_id": "' . $user_id . '",
       "note": "' . $message . '"
     }';
     return Pipedrive::pipedrive_api("POST", $url, $data);
   }

   protected static function create_deal($data) {
     $url = 'https://api.pipedrive.com/v1/deals?api_token=' . DB_PIPEDRIVE_API_KEY;
     return Pipedrive::pipedrive_api("POST", $url, $data);
   }

   private static function pipedrive_api($request_type, $url, $data) {
     write_log('calling pipedrive');
     $ch = curl_init($url);
     curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
     curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $request_type);
     curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json;', 'Content-Type: application/json'));
     curl_setopt($ch, CURLOPT_POST,           1 );
     curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
     return json_decode(curl_exec($ch), true);
   }
 }

class Persons extends Pipedrive {
  public static function find_user_ids_by_fingerprint($fingerprint) {
    write_log('get users by fingerprint');
    $response = Pipedrive::find_users('26dccb2d4b7b701a77f418266af26599c970a414', $fingerprint);
    if (isset($response['data'][0]['id'])) {
      $user_id_array = [];
      foreach($response['data'] as $user_id) {
        array_push($user_id_array,$user_id['id']);
      }
      return $user_id_array;
    }
    else {
      write_log('cant find fingerprint in pipedrive');
      return null;
    }
  }

  public static function get_user_fingerprints_by_id($user_id) {
    write_log('get users fingerprints');
    $user = Pipedrive::get_user($user_id);
    if (isset($user['data']['26dccb2d4b7b701a77f418266af26599c970a414'])) {
      write_log('found a fingerprint!');
      $user_fingerprints_string = $user['data']['26dccb2d4b7b701a77f418266af26599c970a414'];
      return explode(',', $user_fingerprints_string);
    }
    else {
      write_log('user has no fingerprints');
      return null;
    }
  }

  public static function get_pipedrive_user($user_id) {
    return Pipedrive::get_user($user_id);
  }

  public static function merge_pipedrive_users($user_array) {
    write_log('merge users');
    $ordered_users = [];
    foreach($user_array as $user) {
      if ($user['data']['name'] == 'Unknown') {
        array_push($ordered_users, $user);
      }
      else {
        array_unshift($ordered_users, $user);
      }
    }
    $master_record = array_shift($ordered_users);
    foreach($ordered_users as $user) {
      Pipedrive::merge_users($user['data']['id'], $master_record['data']['id']);
    }
    write_log('get fingerprints from merge, run them through the cleaner, reattach to the user');
    $user_fingerprints = Persons::get_user_fingerprints_by_id($master_record['data']['id']);
    Persons::set_user_fingerprints_by_id($user_fingerprints, $master_record['data']['id']);
    write_log('master record ID: '.$master_record['data']['id']);
    $_SESSION["user_id"] = $master_record['data']['id'];
    $_SESSION["fingerprint_session"] = $user_fingerprints;
    return $master_record['data']['id'];
  }

  public static function set_user_fingerprints_by_id($fingerprints, $user_id) {
    write_log('set user fingerprints');
    $fingerprints = Processing::clean_user_fingerprints($fingerprints);
    $data = '{"26dccb2d4b7b701a77f418266af26599c970a414":"' . $fingerprints . '"}';
    $response = Pipedrive::update_user($user_id, $data);
    write_log('added fingerprints to user was a success:'.$response['success']);
  }

  public static function create_pipedrive_user($data) {
    write_log('creating user');
    $response = Pipedrive::create_person($data);
    write_log('added new user was a success:'.$response['success']);
    return $response;
  }

  public static function find_user_id_by_email($email) {
    write_log('find user by email');
    $response = find_user('email', $email);
    if (isset($response['data'][0]['id'])) {
      write_log('found user by email!');
      return $response['data'][0]['id'];
    }

    else{ write_log('cant find user by email '.$email); return null; }
    return null;
  }




}

class Activity extends Pipedrive {
  function __construct () {
    $subject = '';
    $done = '1';
    $type = '';
    $deal_id = '';
    $user_id = '';
    $message = '';
  }

  public static function record_hit_by_user_id($user_id) {
    write_log('record hit');
    $deal_id = '';
    $message = '';
    $subject = 'Website Hit';
    $done = 1;
    $type = 'website_hit';
    $response = Pipedrive::create_activity($subject, $done, $type, $deal_id, $user_id, $message);
    write_log('recorded hit successfully:' . $response['success']);
  }

  public static function record_form_submission($user_id, $deal_id, $message) {
    write_log('record form submission');
    $deal_id = '';
    $user_id = '';
    $message = '';
    $type = 'website_form_submission';
    $subject = 'Website Form Submission';
    $response = Pipedrive::create_activity($subject, $done, $type, $deal_id, $user_id, $message);
    write_log('recorded web form submission details successfully: ' . $response["success"]);
    return $response;
  }
}

class Deal extends Pipedrive {
  public static function create_pipedrive_deal($data) {
    $response = Pipedrive::create_deal($data);
    write_log('creating a new deal was a success:'.$response['success']);
    return $response;
  }
}


















?>
