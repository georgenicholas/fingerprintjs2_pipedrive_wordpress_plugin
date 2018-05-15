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

   NOTE: User Storie:
   - When a user visits the site for the first time
   --


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
	// The wp_localize_script allows us to output the ajax_url path for our script to use.
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
      // try to find visitor by fingerprint
      debug('no user id present, using fingerprint only');
      $user_id = check_if_fingerprint_exists($fingerprint);
      if ($user_id != false) {
        record_hit($user_id);
      }
    }
  }
  die();
}

function curl_request_pipedrive($request_type, $url, $data) {
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $request_type);
  curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json;', 'Content-Type: application/json'));
  curl_setopt($ch, CURLOPT_POST,           1 );
  curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
  return curl_exec($ch);

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
    debug('cant find fingerprint, end program');
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

add_action( 'wp_ajax_nopriv_fingerprinting_ajax_request', 'fingerprinting_ajax_request' );
?>
