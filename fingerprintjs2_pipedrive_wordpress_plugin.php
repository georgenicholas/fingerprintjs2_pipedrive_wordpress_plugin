<?php
   /*
   Plugin Name: Fingerprinting
   Plugin URI: https://github.com/georgenicholas/fingerprintjs2_pipedrive_wordpress_plugin
   description: >-
  uses fingerprintjs2 library and pipedrive to create gated forms to track visits and record those in pipedrive
   Version: 0.0.1
   Author: George Nicholas
   Author URI: https://github.com/georgenicholas
   License:
   */

   /*TODO:
   - load js library async
   - add ability to do gated content
   -- should try to find person in pipedrive from email and fingerprint from submission
   --- if it finds them, it adds the new data to them.
   - should merge people who have the same fingerprint.

   NOTE: User Storie:
   - When a user visits the site wtih no arguments
   -- the program trys to find that user's fingerprint in pipedrive.
   --- if it can't it does nothing.
   --- if it can, it adds a hit to that user it finds
   - When a user visits the site with a user ID argument
   -- the program trys to get that user
   --- if it finds and gets the user, it looks to see if that user has a fingerprint
   ---- if the user has a fingrprint, it gets the fingerprint and sees if their old fingerprint matches their new one.
   ----- if the fingerprints match, it does nothing
   ----- if the fingerprints don't match, it adds the new fingerprint to the array with the old fingerprints and adds those to the user
   ---
   */


   // Async load
 // function ikreativ_async_scripts($url)
 // {
 //     if ( strpos( $url, '#asyncload') === false )
 //         return $url;
 //     else if ( is_admin() )
 //         return str_replace( '#asyncload', '', $url );
 //     else
 // 	return str_replace( '#asyncload', '', $url )."' async='async";
 //     }
 // add_filter( 'clean_url', 'ikreativ_async_scripts', 11, 1 );


   // function enqueue_scripts() {
   //   wp_enqueue_script( 'fingerprintjs2', 'https://cdn.jsdelivr.net/npm/fingerprintjs2@1.8.0/dist/fingerprint2.min.js');
   //   wp_enqueue_script( 'finterprintjs2_pipedrive_wp', plugin_dir_url(__FILE__) . 'finterprintjs2_pipedrive_wp.js', array('jquery'));
   //   // wp_localize_script( 'finterprintjs2_pipedrive_wp', 'fingerprint_obj', array('ajaxurl' => admin_url( 'admin-ajax.php' )));
   // }
   // add_action( 'wp_enqueue_scripts', 'enqueue_scripts' );
   function debug($message) {
     if (DB_HOST == 'localhost') {
       if (is_array($message)) {
         print_r(json_encode($message));
       }
       else {
         print_r(' | '.$message.' | ');
       }
     }
   }

   function fingerprinting_enqueue() {
	// Enqueue javascript on the frontend.
	wp_enqueue_script(
		'fingerprinting',
		plugin_dir_url(__FILE__) . 'fingerprinting.js',
		array('jquery')
	);
  wp_enqueue_script( 'fingerprintjs2', 'https://cdn.jsdelivr.net/npm/fingerprintjs2@1.8.0/dist/fingerprint2.min.js');
	wp_localize_script(
		'fingerprinting',
		'fingerprinting_ajax_obj',
		array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) )
	);
}
add_action( 'wp_enqueue_scripts', 'fingerprinting_enqueue' );

function fingerprinting_ajax_request() {
  if ( isset($_REQUEST) ) {
    $fingerprint = $_REQUEST['fingerprint'];
    $user_id = $_REQUEST['user_id'];
    $company_id = $_REQUEST['company_id'];
    debug('fingerprint: '.$fingerprint.' user id: '.$user_id.' company id: '.$company_id);
    if (isset($user_id) && isset($fingerprint)) {
      debug('found finerprint and user id');
      set_user_fingerprint($fingerprint, $user_id);
      record_hit($user_id);
    }
    elseif (isset($fingerprint)) {
      debug('no user id present, using fingerprint only');
      $user_id = check_if_fingerprint_exists($fingerprint);
      if ($user_id != false) {
        record_hit($user_id);
      }
      else {
        debug('create new user in pipedrive with fingerprint');
        $user = pipedrive_create_user($fingerprint);
        record_hit($user["data"]["id"]);
      }
    }
  }
  die();
}

function record_hit($user_id) {
  debug('record hit');
  $url = 'https://api.pipedrive.com/v1/activities?api_token=' . DB_PIPEDRIVE_API_KEY;
  $data = '{
    "subject": "Website Hit",
    "done": "1",
    "type": "website_hit",
    "person_id": "' . $user_id . '"
  }';
  $response = curl_request_pipedrive("POST", $url, $data);
  debug('recorded hit successfully:'.$response['success']);
}

function check_if_fingerprint_exists($fingerprint) {
  debug('check if finger print exists');
  $url = 'https://api.pipedrive.com/v1/searchResults/field?term=' . $fingerprint . '&exact_match=0&field_type=personField&field_key=26dccb2d4b7b701a77f418266af26599c970a414&return_item_ids=1&start=0&api_token=' . DB_PIPEDRIVE_API_KEY;
  $data = null;
  $response = json_decode(curl_request_pipedrive("GET", $url, $data), true);
  if (isset($response['data'][0]['id'])) {
    debug('found fingerprint in pipedrive!');
    $user_id = $response['data'][0]['id'];
    return $user_id;
  }
  else {
    debug('cant find fingerprint');
    return false;
  }
}

// Set's a user's fingerprints
function set_user_fingerprint($fingerprint, $user_id) {
  debug('set user fingerprints');
  debug('get users current logged fingerprints');
  $user_fingerprints = get_users_fingerprints($user_id);
  debug('did the user already have fingerprints?');
  if (isset($user_fingerprints)) {
    debug('the user already had fingerprints, is the new one unique?');
    if (in_array($fingerprint, $user_fingerprints)) {
      debug('the fingerprint has already been logged, return');
      return;
    }
    else {
      debug('the user already has fingerprints, but this new one is unique and should be added');
      array_push($user_fingerprints,$fingerprint);
    }
  }
  else {
    debug('this user has no fingerprints, so this new fingerprint must be unique.');
    $user_fingerprints = [];
    array_push($user_fingerprints,$fingerprint);
  }

  debug('flatten array to string');
  $user_fingerprints_string = implode(",",$user_fingerprints);
  debug('submit fingerprints to pipedrive');
  $url = 'https://api.pipedrive.com/v1/persons/' . $user_id . '?api_token=' . DB_PIPEDRIVE_API_KEY;
  $data = '{"26dccb2d4b7b701a77f418266af26599c970a414":"' . $user_fingerprints_string . '"}';
  $response = json_decode(curl_request_pipedrive("PUT", $url, $data), true);
  debug('added fingerprints to user was a success:'.$response['success']);
}

// Returns an array of a user's fingerprints
function get_users_fingerprints($user_id) {
  debug('get users fingerprints');
  $url = 'https://api.pipedrive.com/v1/persons/' . $user_id . '?api_token=' . DB_PIPEDRIVE_API_KEY;
  $data = null;
  $user = json_decode(curl_request_pipedrive("GET", $url, $data), true);
  if (isset($user['data']['26dccb2d4b7b701a77f418266af26599c970a414'])) {
    debug('found a fingerprint!');
    $user_fingerprints_string = $user['data']['26dccb2d4b7b701a77f418266af26599c970a414'];
    return explode(',', $user_fingerprints_string);
  }
  else {
    debug('user has no fingerprints');
    return null;
  }
}

function pipedrive_create_user($fingerprint) {
  $url = 'https://api.pipedrive.com/v1/persons?api_token=' . DB_PIPEDRIVE_API_KEY;
  $data = '{"name": "Unknown", "visible_to": "3", "26dccb2d4b7b701a77f418266af26599c970a414":"' . $fingerprint . '"}';
  $response = json_decode(curl_request_pipedrive("POST", $url, $data), true);
  debug('added new user was a success:'.$response['success']);
  return $response;
}

function curl_request_pipedrive($request_type, $url, $data) {
  debug('calling pipedrive');
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $request_type);
  curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json;', 'Content-Type: application/json'));
  curl_setopt($ch, CURLOPT_POST,           1 );
  curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
  return curl_exec($ch);
}

add_action( 'wp_ajax_nopriv_fingerprinting_ajax_request', 'fingerprinting_ajax_request' );
?>
