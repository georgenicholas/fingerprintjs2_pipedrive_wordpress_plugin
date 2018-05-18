<?php

// add_action( 'wpcf7_before_send_mail', 'form_submitted' );
add_action( 'wpcf7_submit', 'form_submitted_handler' );
function form_submitted_handler($contact_form) {
  try { form_submitted($contact_form); }
  catch (Exception $e) { throw $e; }
}
// apply_filters( 'wpcf7_ajax_json_echo',  $items,  $result );
function form_submitted($contact_form) {
  write_log('form submitted');
  if (!isset($contact_form->posted_data) && class_exists('WPCF7_Submission')) {
        $submission = WPCF7_Submission::get_instance();
        if ($submission) {
            $formdata = $submission->get_posted_data();
            // write_log($formdata);
            $name = $formdata['your-name'];
            $company = $formdata['company'];
            $email = $formdata['email'];
            $message = $formdata['message'];
        }
    }


  //try to find user by submitted email
  $user_id = find_user_id_by_email($email);
  $user_id_array = find_user_ids_by_fingerprint($_SESSION["fingerprint_session"]);
  if(isset($_SESSION["user_id"])) { $user_id = $_SESSION["user_id"]; }
  if (isset($user_id)) {
    write_log('found user by email!');
    $user_fingerprints = get_user_fingerprints_by_id($user_id);
    array_push($user_fingerprints,$_SESSION["fingerprint_session"]);
    set_user_fingerprints_by_id($user_fingerprints, $user_id);
    write_log('check for duplicates');
    $user_id_array = find_user_ids_by_fingerprint($_SESSION["fingerprint_session"]);
    if (count($user_id_array) >= 2) { $user_id = merge_2_users($user_id_array[0], $user_id_array[1]); }
    else { write_log('didnt find any duplicates'); }
  }

  elseif (isset($user_id_array)) {
    write_log('didnt find user by email, found user by fingerprint');
    if (count($user_id_array) >= 2) { $user_id = merge_2_users($user_id_array[0], $user_id_array[1]); }
    elseif (isset($user_id_array[0])) {
      write_log('didnt find any duplicates');
      $user_id = $user_id_array[0];
    }
  }
  else {
    write_log('couldnt find user by fingerprint or email, create a new user');
    $user = create_pipedrive_user('{"name": "' . $name . '", "email": "' . $email . '", "visible_to": "3", "26dccb2d4b7b701a77f418266af26599c970a414":"' . $_SESSION["fingerprint_session"] . '"}');
    $user_id = $user['data']['id'];
  }
  write_log('creating deal');
  $deal = create_deal('{"title": "Website Form Submitted", "person_id": "' . $user_id . '", "visible_to": "3", "pipeline_id": "13"}');
  //remove page breaks, they break the pipedrive API
  $message = preg_replace('/\\\\/', '', $message);
  $message = preg_replace( "/\r|\n/", "<br>", $message );
  $message = preg_replace("/\'|\"|:|;|\/|-|_|\+|=|\\|\||\^|#|%|~|`|\*/", "", $message );
  record_form_submission($user_id, $deal['data']['id'], 'Name: '  . $name . '<br>Company: ' . $company . '<br>Email: ' . $email . '<br><br>' . $message );
  write_log('done adding form submission');
}

function create_deal($data) {
  $url = 'https://api.pipedrive.com/v1/deals?api_token=' . DB_PIPEDRIVE_API_KEY;
  $response = json_decode(curl_request_pipedrive("POST", $url, $data), true);
  write_log('creating a new deal was a success:'.$response['success']);
  return $response;
}

?>
