
var cfreviewp_review = function cfreviewp_review(trigger) {
  var button = trigger.trigger[0];
  var data = jQuery(button).data();

  jQuery.ajax({
    type : "post",
    dataType : "json",
    url : cfreviewpAjax.ajaxUrl,
    data : data,
    success: function(response) {
      jQuery('div#cfreviewp-review-status-' + response.entry_id +' span.meta-value').html(response.review_status)
      jQuery('button#cfreviewp-button-accept').hide();
      jQuery('button#cfreviewp-button-reject').hide();
      if (response.review_status == 'ACCEPTED') {
        jQuery('button#cfreviewp-button-reject').show();
      }
      else if (response.review_status == 'REJECTED') {
        jQuery('button#cfreviewp-button-accept').show();
      }
    },
  })
}

jQuery(document).ready( function() {
  jQuery(document).ajaxComplete(function( event, xhr, settings, data ) {
    console.log('length', jQuery('#view_entry_baldrickModal').length);
  });

  // Register ifEquals helper, used in meta.php template.
  Handlebars.registerHelper('ifEquals', function(arg1, arg2, options) {
    return (arg1 == arg2) ? options.fn(this) : options.inverse(this);
  });
});


