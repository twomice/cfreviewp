<?php
  $nonce = wp_create_nonce('cfreviewp_review_entry');
?>
<div class="entry-line">
  <label>{{meta_key}}</label>
  <div {{#ifEquals meta_key "Review Status"}}id="cfreviewp-review-status-{{entry_id}}"{{/ifEquals}}>
    <span class="meta-value">{{meta_value}}</span>&nbsp;
    {{#ifEquals meta_key "Review Status"}}
      <button id="cfreviewp-button-accept" class="ajax-trigger" data-entry="{{entry_id}}" data-nonce="<?= $nonce ?>" data-action="cfreviewp_review_entry" data-response="1" data-request="cfreviewp_review" {{#ifEquals meta_value "ACCEPTED"}}style="display: none;"{{/ifEquals}}>Accept</button>
      <button id="cfreviewp-button-reject" class="ajax-trigger" data-entry="{{entry_id}}" data-nonce="<?= $nonce ?>" data-action="cfreviewp_review_entry" data-response="0" data-request="cfreviewp_review" {{#ifEquals meta_value "REJECTED"}}style="display: none;"{{/ifEquals}}>Reject</button>
    {{/ifEquals}}
  </div>
</div>
