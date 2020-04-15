/**
 * Baldrick.js data-request handler for 'accept' and 'reject' buttons
 *
 * Reference: https://github.com/DavidCramer/BaldrickJS/blob/master/how_to.md
 */
var cfreviewp_review = function cfreviewp_review(trigger) {
  var button = trigger.trigger[0];
  var data = jQuery(button).data();

  // Hide buttons and show spinner; we're going on an ajax adventure.
  jQuery('button#cfreviewp-button-accept').hide();
  jQuery('button#cfreviewp-button-reject').hide();
  jQuery('img#cfreviewp-spinner').show();

  // Send the ajax call to process the review.
  jQuery.ajax({
    type: "post",
    dataType: "json",
    url: cfreviewpAjax.ajaxUrl,
    data: data,
    success: function (response) {
      // On ajax success:
      // Set value for status and matter ID.
      jQuery('div.cfreviewp-meta-ReviewStatus span.meta-value').html(response.review_status)
      jQuery('div.cfreviewp-meta-MatterID span.meta-value').html(response.matter_id);
      // Display appropriate buttons.
      if ((response.matter_id * 1) > 0) {
        jQuery('button#cfreviewp-button-openMatter').show();
      }
      if (response.review_status == 'REJECTED') {
        jQuery('button#cfreviewp-button-accept').show();
      }
      if (response.review_status == 'UNREVIEWED') {
        jQuery('button#cfreviewp-button-accept').show();
        jQuery('button#cfreviewp-button-reject').show();
      }
    },
    complete: function() {
      // Success or no, hide the spinner; ajax is done.
      jQuery('img#cfreviewp-spinner').hide();
    }
  })
}

/**
 * Baldrick.js data-request handler for 'view matter' button. Don't really use
 * baldrick, could have just used on-click, but anyway.
 *
 * Reference: https://github.com/DavidCramer/BaldrickJS/blob/master/how_to.md
 */
var cfreviewp_open_matter = function() {
  var matterId = jQuery('div.cfreviewp-meta-MatterID span.meta-value').html();
  if (matterId * 1 > 0) {
    // Because we've included civicrm core resources, CRM.url() is available; use
    // it to create the url, and then go to.
    url = CRM.url('civicrm/matter/view', {'reset': 1, 'action': 'view', 'id': matterId});
    window.open(url, '_blank');
  }
}

/**
 * Jquery on-load handler.
 */
jQuery(document).ready(function () {
  // Register ifEquals helper, used in meta.php template.
  Handlebars.registerHelper('ifEquals', function (arg1, arg2, options) {
    return (arg1 == arg2) ? options.fn(this) : options.inverse(this);
  });

  // Register safeStringify helper, used in meta.php template.
  Handlebars.registerHelper('safeStringify', function (arg1, options) {
    return arg1.replace(' ', '');
  });
});
