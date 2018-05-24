<?php
 class Pipedrive {

   protected static function get_user($user_id) {
     $url = 'https://api.pipedrive.com/v1/persons/' . $user_id . '?api_token=' . DB_PIPEDRIVE_API_KEY;
     $data = null;
     return Pipedrive::pipedrive_api("GET", $url, $data);
   }

   // returns an array of ids
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

   protected static function pipedrive_api($request_type, $url, $data) {
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
  // TODO make this class object oriented.
  // returns user id.
  public static function find_user_id_by_fingerprint($fingerprint) {
    write_log('get user by fingerprint');
    return Persons::find_user('26dccb2d4b7b701a77f418266af26599c970a414', $fingerprint);
  }

  // returns user id.
  public static function find_user_id_by_email($email) {
    write_log('find user by email');
    return Persons::find_user('email', $email);
  }

  // returns a user id.
  private static function find_user($field_key, $field_term) {
    $response = Pipedrive::find_users($field_key, $field_term);
    if (isset($response['data'][0]['id'])) {
      write_log('found users!');
      $user_ids = [];
      foreach($response['data'] as $user) { array_push($user_ids, $user['id']); }
      return Persons::merge_users_by_id($user_ids);
    }
    else { write_log('cant find any users '.$field_term); return null; }
  }

  // accepts array of user ids.
  // returns a user id.
  private static function merge_users_by_id($user_ids) {
    if (count($user_ids) >= 2) {
      write_log('duplicates found, merge');
      $users = [];
      foreach($user_ids as $user_id) { array_push($users, Persons::get_pipedrive_user($user_id)); };
      $user = Persons::merge_pipedrive_users($users);
      return $user['data']['id'];
    }
    else {
      $user_id = $user_ids['0'];
      write_log('no duplicates found, return');
      return $user_id;
    }
  }

  // accepts array of users.
  // returns a user.
  private static function merge_pipedrive_users($user_array) {
    write_log('merge users');
    $ordered_users = Processing::order_those_to_be_merged($user_array);
    $master_record = array_shift($ordered_users);
    foreach($ordered_users as $user) {
      Pipedrive::merge_users($user['data']['id'], $master_record['data']['id']);
    }
    write_log('get fingerprints from merge, run them through the cleaner, reattach to the user');
    // $user_fingerprints = Persons::get_user_fingerprints_by_id($master_record['data']['id']);
    // Persons::set_user_fingerprints_by_id($user_fingerprints, $master_record['data']['id']);
    write_log('master record ID: '.$master_record['data']['id']);
    $_SESSION["user_id"] = $master_record['data']['id'];
    $_SESSION["fingerprint_session"] = $user_fingerprints;
    return $master_record;
  }

  //accepts a user id
  //returns an array of fingerprints
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

  // accepts a fingerprint and a user id.
  public static function set_user_fingerprints_by_id($fingerprint, $user_id) {
    $fingerprints = [];
    foreach(Persons::get_user_fingerprints_by_id($user_id) as $this) { array_push($fingerprints, $this); }
    array_push($fingerprints, $fingerprint);
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
}

class Activity extends Pipedrive {

  public $subject = '';
  public $done = 1;
  public $type = '';
  public $deal_id = '';
  public $user_id = '';
  public $message = '';
  public $return = '';

  public function __construct() { write_log('creating activiy object'); }

  public function set_subject($data) { $this->subject = $data; }
  public function set_done($data) { $this->done = $data; }
  public function set_type($data) { $this->type = $data; }
  public function set_deal_id($data) { $this->deal_id = $data; }
  public function set_user_id($data) { $this->user_id = $data; }
  public function set_message($data) {
    $data = preg_replace('/\\\\/', '', $data);
    $data = preg_replace( "/\r|\n/", "<br>", $data );
    $data = preg_replace("/\'|\"|:|;|\/|-|_|\+|=|\\|\||\^|#|%|~|`|\*/", "", $data );
    $this->message = $data;
  }

  public function create_activity() {
    $url = 'https://api.pipedrive.com/v1/activities?api_token=' . DB_PIPEDRIVE_API_KEY;
    $data = '{
      "subject": "' . $this->subject . '",
      "done": "' . $this->done . '",
      "type": "' . $this->type . '",
      "deal_id": "' . $this->deal_id . '",
      "person_id": "' . $this->user_id . '",
      "note": "' . $this->message . '"
    }';
    $this->return = parent::pipedrive_api("POST", $url, $data);
  }
}

class Deal extends Pipedrive {

  public $title = '';
  public $person_id = '';
  public $visible_to = '3';
  public $pipeline_id = '13';
  public $return = '';

  public function __construct() { write_log('creating deal object'); }

  public function set_title($data) { $this->title = $data; }
  public function set_person_id($data) { $this->person_id = $data; }
  public function set_visible_to($data) { $this->visible_to = $data; }
  public function set_pipeline_id($data) { $this->pipeline_id = $data; }

  public function create_deal() {
    $data = '{
      "title": "' . $this->title . '",
      "person_id": "' . $this->person_id . '",
      "visible_to": "' . $this->visible_to . '",
      "pipeline_id": "' . $this->pipeline_id . '"
    }';
    $url = 'https://api.pipedrive.com/v1/deals?api_token=' . DB_PIPEDRIVE_API_KEY;
    $this->return = parent::pipedrive_api("POST", $url, $data);
  }
}

?>
