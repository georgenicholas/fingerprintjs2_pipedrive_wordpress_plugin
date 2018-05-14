jQuery(document).ready(function($) {
    // We'll pass this variable to the PHP function example_ajax_request
    new Fingerprint2().get(function(result, components) {

      //get URL parameters
      var getUrlParameter = function getUrlParameter(sParam) {
          var sPageURL = decodeURIComponent(window.location.search.substring(1)),
              sURLVariables = sPageURL.split('&'),
              sParameterName,
              i;

          for (i = 0; i < sURLVariables.length; i++) {
              sParameterName = sURLVariables[i].split('=');

              if (sParameterName[0] === sParam) {
                  return sParameterName[1] === undefined ? true : sParameterName[1];
              }
          }
      };

      // This does the ajax request
      $.ajax({
          url: fingerprinting_ajax_obj.ajaxurl, // or example_ajax_obj.ajaxurl if using on frontend
          data: {
              'action': 'fingerprinting_ajax_request',
              'fingerprint' : result,
              'user_id' : getUrlParameter('user_id'),
              'company_id' : getUrlParameter('company_id')
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
