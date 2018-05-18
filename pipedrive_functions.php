<?php
 class Pipedrive_Person {
   function find_user_ids_by_fingerprint($fingerprint) {
     write_log('check if finger print exists');
     $url = 'https://api.pipedrive.com/v1/searchResults/field?term=' . $fingerprint . '&exact_match=0&field_type=personField&field_key=26dccb2d4b7b701a77f418266af26599c970a414&return_item_ids=1&start=0&api_token=' . DB_PIPEDRIVE_API_KEY;
     $data = null;
     $response = json_decode(curl_request_pipedrive("GET", $url, $data), true);
     write_log('check if there are any fingerprint matches in pipedrive');
     if (isset($response['data'][0]['id'])) {
       $user_id_array = [];
       foreach($response['data'] as $user_id) {
         array_push($user_id_array,$user_id['id']);
       }
       return $user_id_array;
     }
     else {
       write_log('cant find fingerprint');
       return null;
     }
   }

   function merge_2_users($user_id1, $user_id2) {
     $user_to_merge = determine_which_users_to_merge($user_id1, $user_id2);
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
     $url = 'https://api.pipedrive.com/v1/persons/' . $user_to_be_merged . '/merge?api_token=' . DB_PIPEDRIVE_API_KEY;
     $data = '{"merge_with_id":"' . $user_id . '"}';
     $response = json_decode(curl_request_pipedrive("PUT", $url, $data), true);
     write_log('merged users success status: '.$response['success']);
     $user_fingerprints = get_user_fingerprints_by_id($user_id);
     set_user_fingerprints_by_id($user_fingerprints, $user_id);
     return $user_id;
   }

   function set_user_fingerprints_by_id($fingerprints, $user_id) {
     write_log('set user fingerprints');
     $fingerprints = clean_user_fingerprints($fingerprints);
     write_log('flatten array to string');
     $fingerprints = implode(",",$fingerprints);
     write_log('submit fingerprints to pipedrive');
     $url = 'https://api.pipedrive.com/v1/persons/' . $user_id . '?api_token=' . DB_PIPEDRIVE_API_KEY;
     $data = '{"26dccb2d4b7b701a77f418266af26599c970a414":"' . $fingerprints . '"}';
     $response = json_decode(curl_request_pipedrive("PUT", $url, $data), true);
     write_log('added fingerprints to user was a success:'.$response['success']);
   }

   function create_pipedrive_user($data) {
     write_log('creating user');
     $url = 'https://api.pipedrive.com/v1/persons?api_token=' . DB_PIPEDRIVE_API_KEY;
     $response = json_decode(curl_request_pipedrive("POST", $url, $data), true);
     write_log('added new user was a success:'.$response['success']);
     return $response;
   }

   function find_user_id_by_email($email) {
     write_log('find user by email');
     $url = 'https://api.pipedrive.com/v1/persons/find?term=' . $email . '&start=0&search_by_email=1&api_token=' . DB_PIPEDRIVE_API_KEY;
     $data = null;
     $response = json_decode(curl_request_pipedrive("GET", $url, $data), true);
     if (isset($response['data'][0]['id'])) {
       write_log('found user by email!');
       return $response['data'][0]['id'];
     }
     else{ write_log('cant find user by email '.$email); return null; }

   }

   function get_user_by_id() {
     $url = 'https://api.pipedrive.com/v1/persons/' . $user_id . '?api_token=' . DB_PIPEDRIVE_API_KEY;
     $data = null;
     $user = json_decode(curl_request_pipedrive("GET", $url, $data), true);
   }
 }

 class Pipedrive_Activity {
   function record_hit_by_user_id($user_id) {
     write_log('record hit');
     $url = 'https://api.pipedrive.com/v1/activities?api_token=' . DB_PIPEDRIVE_API_KEY;
     $data = '{
       "subject": "Website Hit",
       "done": "1",
       "type": "website_hit",
       "person_id": "' . $user_id . '"
     }';
     $response = json_decode(curl_request_pipedrive("POST", $url, $data), true);
     write_log('recorded hit successfully:' . $response['success']);
   }


   function record_form_submission($user_id, $deal_id, $message) {
     write_log('record form submission');
     $url = 'https://api.pipedrive.com/v1/activities?api_token=' . DB_PIPEDRIVE_API_KEY;
     $data = '{
       "subject": "Website Form Submission",
       "done": "1",
       "type": "website_form_submission",
       "deal_id": "' . $deal_id . '",
       "person_id": "' . $user_id . '",
       "note": "' . $message . '"
     }';
     $response = json_decode(curl_request_pipedrive("POST", $url, $data), true);
     write_log('recorded web form submission details successfully: ' . $response["success"]);
     return $response;
   }
  }

 class Pipedrive_Deal {
   function create_deal($data) {
      $url = 'https://api.pipedrive.com/v1/deals?api_token=' . DB_PIPEDRIVE_API_KEY;
      $response = json_decode(curl_request_pipedrive("POST", $url, $data), true);
      write_log('creating a new deal was a success:'.$response['success']);
      return $response;
    }
 }















  function curl_request_pipedrive($request_type, $url, $data) {
    write_log('calling pipedrive');
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $request_type);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json;', 'Content-Type: application/json'));
    curl_setopt($ch, CURLOPT_POST,           1 );
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    return curl_exec($ch);
  }

?>
