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
   -- should try to find person in pipedrive from email and fingerprint from submission and tie them to original record.
   --- if it finds them, it adds the new data to them.
   - doesn't seem to work in chrome
   - update mailchimp updater to include user IDs so I can create dynamic URLs for them
   - make post submission stuff async: https://github.com/techcrunch/wp-async-task

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

   include(plugin_dir_path( __FILE__ ) . 'gated_content.php');

   add_action('init', 'myStartSession', 1);
    function myStartSession() {
        if(!session_id()) {
            session_start();
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
    $fingerprint_global = $fingerprint;
    $_SESSION["fingerprint_session"] = $fingerprint;
    if(isset($user_id)) {$_SESSION["user_id"] = $user_id;}
    write_log('fingerprint: '.$fingerprint.' user id: '.$user_id.' company id: '.$company_id);
    if (isset($user_id) && isset($fingerprint)) {
      write_log('found finerprint and user id');
      $user_fingerprints = get_user_fingerprints_by_id($user_id);
      array_push($user_fingerprints,$fingerprint);
      set_user_fingerprints_by_id($user_fingerprints, $user_id);
      record_hit_by_user_id($user_id);
      write_log('check for duplicates');
      $user_id_array = find_user_ids_by_fingerprint($fingerprint); //returns array of user ids.
      if (count($user_id_array) >= 2) {
        merge_2_users($user_id_array[0], $user_id_array[1]);
      }
      else {
        write_log('didnt find any duplicates');
      }
    }
    elseif (isset($fingerprint)) {
      write_log('no user id present, using fingerprint only');
      $user_id_array = find_user_ids_by_fingerprint($fingerprint);
      if (is_array($user_id_array)) {
        foreach($user_id_array as $user_id) {
          record_hit_by_user_id($user_id);
        }
      }
      else {
        write_log('create new user in pipedrive with fingerprint');
        $data = '{"name": "Unknown", "visible_to": "3", "26dccb2d4b7b701a77f418266af26599c970a414":"' . $fingerprint . '"}';
        $user = create_pipedrive_user($data);
        record_hit_by_user_id($user["data"]["id"]);
      }
    }
  }
  die();
}

function record_hit_by_user_id($user_id) {
  write_log('record hit');
  $url = 'https://api.pipedrive.com/v1/activities?api_token=' . DB_PIPEDRIVE_API_KEY;
  $data = '{
    "subject": "Website Hit",
    "done": "1",
    "type": "website_hit",
    "person_id": "' . $user_id . '"
  }';
  $response = curl_request_pipedrive("POST", $url, $data);
  write_log('recorded hit successfully:'.$response['success']);
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
  write_log($data);
  $response = curl_request_pipedrive("POST", $url, $data);
  // write_log('recorded web form submission details successfully: ' . $response["success"]);
  // return $response;
}

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
    return false;
  }
}

function merge_2_users($user_id1, $user_id2) {
  // maintain the oldest user by lowest ID number
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

// Set's a user's fingerprints
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

// Returns an array of a user's fingerprints
function get_user_fingerprints_by_id($user_id) {
  write_log('get users fingerprints');
  $url = 'https://api.pipedrive.com/v1/persons/' . $user_id . '?api_token=' . DB_PIPEDRIVE_API_KEY;
  $data = null;
  $user = json_decode(curl_request_pipedrive("GET", $url, $data), true);
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

function clean_user_fingerprints($user_fingerprints) {
  write_log('clean users fingerprints');
  //remove excess spaces and duplicates
  return array_unique(array_filter(str_replace(' ', '', $user_fingerprints)));
}

function create_pipedrive_user($data) {
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



 if (!function_exists('write_log')) {

     function write_log($log) {
         if (true === WP_DEBUG_LOG) {
             if (is_array($log) || is_object($log)) {
                 error_log(print_r($log, true));
             } else {
                 error_log($log);
             }
         }
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

add_action( 'wp_ajax_nopriv_fingerprinting_ajax_request', 'fingerprinting_ajax_request' );
?>
