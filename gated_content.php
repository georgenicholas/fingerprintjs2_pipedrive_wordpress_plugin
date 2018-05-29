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
            if (isset($formdata['your-name'])) { $name = $formdata['your-name']; }
            else { $name = null; }
            if (isset($formdata['company'])) { $company = $formdata['company']; }
            else { $company = null; }
            if (isset($formdata['email'])) { $email = $formdata['email']; }
            else { $email = null; }
            if (isset($formdata['message'])) { $message = $formdata['message']; }
            else { $message = null; }
        }
    }


  // find user by email if there is no user id set
  if (!isset($_SESSION["user_id"]) && !isset($_COOKIE['user_id'])) {
    write_log('no user_id in the session or cookie variables');
    $user_id = Persons::find_user_id_by_email($email);
    if (!isset($user_id)) {
      $user_id = Persons::find_user_id_by_fingerprint($_SESSION["fingerprint_session"]);
    }
  }
  // else if one of the user ids is set from session or cookies, set user_id with it.
  elseif (isset($_SESSION["user_id"])) { $user_id = $_SESSION["user_id"]; }
  elseif (isset($_COOKIE['user_id'])) { $user_id = $_COOKIE['user_id']; }

  if (!isset($user_id)) {
    write_log('couldnt find user by fingerprint, email, cookies or sessions');
    $user = Persons::create_pipedrive_user('{"name": "' . $name . '", "email": "' . $email . '", "visible_to": "3", "26dccb2d4b7b701a77f418266af26599c970a414":"' . $_SESSION["fingerprint_session"] . '"}');
    $user_id = $user['data']['id'];
  }

  write_log('creating deal');
  $deal = new Deal;
  $deal -> set_title('Website Form Submitted');
  $deal -> set_person_id($user_id);
  $deal -> create_deal();

  $activity = new Activity;
  $activity -> set_subject('Website Form Submission');
  $activity -> set_type('website_form_submission');
  $activity -> set_user_id($user_id);
  $activity -> set_deal_id($deal->return['data']['id']);
  $activity -> set_message('Name: ' . $name . '<br>Email: ' . $email . '<br>Company: ' . $company . '<br>Message: ' .$message);
  $activity -> create_activity();
  unset($activity);
  unset($deal);
  write_log('done adding form submission');
}


?>
