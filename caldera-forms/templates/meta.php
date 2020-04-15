<?php
  /**
   * This file is a 'meta_template' used by Caldera Forms, because we've referenced
   * it in the 'meta_template' property of our processor definition.
   * See includes/class-cfreviewp.php, add_filter('caldera_forms_get_form_processors' ...).
   *
   * This template is processed by handlebars.js.
   * Custom handlebars helpers are defined in admin/js/toplevel_page_caldera-forms.js
   */
  $nonce = wp_create_nonce('cfreviewp_review_entry');
?>
<div class="entry-line cfreviewp-meta-{{safeStringify meta_key}}">
  <label>{{meta_key}}</label>
  <div>
    <span class="meta-value">{{meta_value}}</span>&nbsp;
    {{#ifEquals meta_key "Review Status"}}
      <button id="cfreviewp-button-accept" class="ajax-trigger" data-entry="{{entry_id}}" data-nonce="<?= $nonce ?>" data-action="cfreviewp_review_entry" data-response="1" data-request="cfreviewp_review" {{#ifEquals meta_value "ACCEPTED"}}style="display: none;"{{/ifEquals}}>Accept</button>
      <button id="cfreviewp-button-reject" class="ajax-trigger" data-entry="{{entry_id}}" data-nonce="<?= $nonce ?>" data-action="cfreviewp_review_entry" data-response="0" data-request="cfreviewp_review" {{#ifEquals meta_value "UNREVIEWED"}}{{else}}style="display: none;"{{/ifEquals}}>Reject</button>
      <img id="cfreviewp-spinner" src="/wp-includes/images/spinner.gif" style="display:none;"/>
    {{/ifEquals}}
    {{#ifEquals meta_key "Matter ID"}}
      <button id="cfreviewp-button-openMatter" class="ajax-trigger" data-request="cfreviewp_open_matter" {{#ifEquals meta_value "0"}}style="display: none;"{{/ifEquals}}>View Matter</button>
    {{/ifEquals}}
  </div>
</div>
