<?php
 class Pipedrive {

   protected function get_user() {

   }

   protected function find_users($field_key, $field_term) {
     $url = 'https://api.pipedrive.com/v1/searchResults/field?term=' . $fingerprint . '&exact_match=0&field_type=personField&field_key=&return_item_ids=1&start=0&api_token=' . DB_PIPEDRIVE_API_KEY;
     $data = null;
     $response = pipedrive_api("GET", $url, $data)
   }

   protected function create_person($data) {
     $url = 'https://api.pipedrive.com/v1/persons?api_token=' . DB_PIPEDRIVE_API_KEY;
     return pipedrive_api("POST", $url, $data);
   }

   protected function update_user($user_id, $data) {
     $url = 'https://api.pipedrive.com/v1/persons/' . $user_id . '?api_token=' . DB_PIPEDRIVE_API_KEY;
     return pipedrive_api("PUT", $url, $data);
   }

   protected function merge_users($user_to_be_merged, $user_id) {
     $url = 'https://api.pipedrive.com/v1/persons/' . $user_to_be_merged . '/merge?api_token=' . DB_PIPEDRIVE_API_KEY;
     $data = '{"merge_with_id":"' . $user_id . '"}';
     $response = pipedrive_api("PUT", $url, $data);
     write_log('merged users success status: '.$response['success']);
   }

   protected function create_activity($subject, $done, $type, $deal_id, $user_id, $message) {
     $url = 'https://api.pipedrive.com/v1/activities?api_token=' . DB_PIPEDRIVE_API_KEY;
     $data = '{
       "subject": "' . $subject . '",
       "done": "' . $done . '",
       "type": "' . $type . '",
       "deal_id": "' . $deal_id . '",
       "person_id": "' . $user_id . '",
       "note": "' . $message . '"
     }';
     return pipedrive_api("POST", $url, $data);
   }

   protected function create_deal($data) {
     $url = 'https://api.pipedrive.com/v1/deals?api_token=' . DB_PIPEDRIVE_API_KEY;
     return pipedrive_api("POST", $url, $data);
   }

   private function pipedrive_api($request_type, $url, $data) {
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
  public function find_user_ids_by_fingerprint($fingerprint) {
    write_log('get users by fingerprint');
    $user_ids = find_users('26dccb2d4b7b701a77f418266af26599c970a414', $fingerprint);
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

  public function merge_2_users($user_id1, $user_id2) {
    // maintain the oldest user by lowest ID number
    // TODO if the older record to be used has the name "Unknown" use the name from the merged record.
    if ($user_id1 < $user_id2) {
      $user_to_be_merged = $user_id2;
      $user_id = $user_id1;
    }
    else {
      $user_to_be_merged = $user_id1;
      $user_id = $user_id2;
    }
    write_log('user ' . $user_to_be_merged . ' to be merged into ' . $user_id);
    merge_users($user_to_be_merged, $user_id);
    $user_fingerprints = get_user_fingerprints_by_id($user_id);
    set_user_fingerprints_by_id($user_fingerprints, $user_id);
    return $user_id;
  }

  public function set_user_fingerprints_by_id($fingerprints, $user_id) {
    write_log('set user fingerprints');
    $fingerprints = Processing::clean_user_fingerprints($fingerprints);
    $data = '{"26dccb2d4b7b701a77f418266af26599c970a414":"' . $fingerprints . '"}';
    $response = update_user($user_id, $data)
    write_log('added fingerprints to user was a success:'.$response['success']);
  }

  public function create_pipedrive_user($data) {
    write_log('creating user');
    create_person($data);
    write_log('added new user was a success:'.$response['success']);
    return $response;
  }

  public function find_user_id_by_email($email) {
    write_log('find user by email');
    $response = find_user('email', $email);
    if (isset($response['data'][0]['id'])) {
      write_log('found user by email!');
      return $response['data'][0]['id'];
    }

    else{ write_log('cant find user by email '.$email); return null; }
    return null;
  }

  // public function get_user_by_id($user_id) {
  //   $url = 'https://api.pipedrive.com/v1/persons/' . $user_id . '?api_token=' . DB_PIPEDRIVE_API_KEY;
  //   $data = null;
  //   $user = json_decode(pipedrive_api("GET", $url, $data), true);
  //   return get_user()
  // }


}

class Activity extends Pipedrive {
  $subject = '';
  $done = '1';
  $type = '';
  $deal_id = '';
  $user_id = '';
  $message = '';
  function record_hit_by_user_id($user_id) {
    write_log('record hit');
    $subject = 'Website Hit';
    $done = 1;
    $type = 'website_hit';
    $response = create_activity($subject, $done, $type, $deal_id, $user_id, $message);
    write_log('recorded hit successfully:' . $response['success']);
  }

  function record_form_submission($user_id, $deal_id, $message) {
    write_log('record form submission');
    $type = 'website_form_submission';
    $subject = 'Website Form Submission';
    $response = create_activity($subject, $done, $type, $deal_id, $user_id, $message);
    write_log('recorded web form submission details successfully: ' . $response["success"]);
    return $response;
  }
}

class Deal extends Pipedrive {
  function create_pipedrive_deal($data) {
    $response = create_deal($data);
    write_log('creating a new deal was a success:'.$response['success']);
    return $response;
  }
}


















?>
