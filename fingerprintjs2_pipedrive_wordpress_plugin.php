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

  // function enqueue_scripts() {
  //   wp_enqueue_script( 'fingerprintjs2', 'https://cdn.jsdelivr.net/npm/fingerprintjs2@1.8.0/dist/fingerprint2.min.js');
  //   wp_enqueue_script( 'finterprintjs2_pipedrive_wp', plugin_dir_url(__FILE__) . 'finterprintjs2_pipedrive_wp.js', array('jquery'));
  //   // wp_localize_script( 'finterprintjs2_pipedrive_wp', 'fingerprint_obj', array('ajaxurl' => admin_url( 'admin-ajax.php' )));
  // }
  // add_action( 'wp_enqueue_scripts', 'enqueue_scripts' );

  include(plugin_dir_path( __FILE__ ) . 'gated_content.php');
  include(plugin_dir_path( __FILE__ ) . 'pipedrive_functions.php');
  include(plugin_dir_path( __FILE__ ) . 'data_processing.php');
  include(plugin_dir_path( __FILE__ ) . 'assets.php');
  include(plugin_dir_path( __FILE__ ) . 'ajax_functions.php');

  add_action('init', 'myStartSession', 1);
  function myStartSession() {
    if(!session_id()) {
      session_start();
    }
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

?>
