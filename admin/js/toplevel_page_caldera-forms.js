/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
console.log('toplevel_page_caldera-forms.js', cfreviewpAjax);

var cfreviewp_review = function cfreviewp_review(trigger) {
  var button = trigger.trigger[0];
  var data = jQuery(button).data();

  jQuery.ajax({
     type : "post",
     dataType : "json",
     url : cfreviewpAjax.ajaxUrl,
     data : data,
     success: function(response) {
       console.log('response', response);
//        if(response.type == "success") {
//           jQuery("#vote_counter").html(response.vote_count)
//        }
//        else {
//           alert("Your vote could not be added")
//        }
     }
  })     
// cfreviewp_reviewer.cfreview_send_review(1);
}

jQuery(document).ready( function() {
  jQuery(document).ajaxComplete(function( event, xhr, settings, data ) {
    console.log('length', jQuery('#view_entry_baldrickModal').length);
//    console.log('settings', settings);
//    console.log('event', event);
//    console.log('xhr', xhr);
//    console.log('data', data);
  });
});


