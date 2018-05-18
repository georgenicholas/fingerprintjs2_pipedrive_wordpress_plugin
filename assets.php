<?php

function fingerprinting_enqueue() {
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
add_action( 'wp_ajax_nopriv_fingerprinting_ajax_request', 'fingerprinting_ajax_request' );

?>
