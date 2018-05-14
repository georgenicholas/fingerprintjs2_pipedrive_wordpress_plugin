jQuery(document).ready(function($) {

    // We'll pass this variable to the PHP function example_ajax_request
    new Fingerprint2().get(function(result, components) {



    // This does the ajax request
      $.ajax({
          url: example_ajax_obj.ajaxurl, // or example_ajax_obj.ajaxurl if using on frontend
          data: {
              'action': 'example_ajax_request',
              'fruit' : result
          },
          success:function(data) {
              // This outputs the result of the ajax request
              console.log('success');
              console.log(data);
          },
          error: function(errorThrown){
            console.log('failure');
              console.log(errorThrown);
          }
      });
  });
});
