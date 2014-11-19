{*
 iATS direct debit UK customization
 Extra fields in Confirmation Screen
*}
<div id="iats-direct-debit-gbp-payer-validate">
  <div class="crm-section payer-validate-address">
    <div class="label">{$form.payer_validate_address.label}</div>
    <div class="content">{$form.payer_validate_address.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section payer-validate-service-user-number">
    <div class="label">{$form.payer_validate_service_user_number.label}</div>
    <div class="content">{$form.payer_validate_service_user_number.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section payer-validate-reference">
    <div class="label">{$form.payer_validate_reference.label}</div>
    <div class="content">{$form.payer_validate_reference.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section payer-validate-instruction">
    <div class="label">{$form.payer_validate_instruction.label}</div>
    <div class="content">{$form.payer_validate_instruction.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section payer-validate-date">
    <div class="label">{$form.payer_validate_date.label}</div>
    <div class="content">{$form.payer_validate_date.html}</div>
    <div class="clear"></div>
  </div>
</div>

<script type="text/javascript">
  {literal}
  cj( function( ) { 
    /* move my custom fields up where they belong */
  });
  {/literal}
</script>

