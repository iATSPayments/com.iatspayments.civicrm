{*
 Extra fields for iats direct debit UK 
*}
<div id="iats-direct-debit-gbp-declaration">
  <fieldset class="iats-direct-debit-gbp-declaration">
  <legend>Declaration</legend>
  <div class="crm-section">
    <div class="label">{$form.payer_validate_declaration.label}</div>
    <div class="content">{$form.payer_validate_declaration.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label"><em><strong>{ts domain='com.iatspayments.civicrm'}Note:{/ts}</strong></em></div>
    <div class="content">{ts domain='com.iatspayments.civicrm'}All the normal Direct Debit safeguards and guarantees apply. No changes in the amount, date, frequency to be debited can be made without notifying you at least five (5) working days in advance of your account being debited. In the event of any error, you are entitled to an immediate refund from your Bank or Building society. You have the right to cancel a Direct Debit instruction at any time simply by writing to your Bank or Building Society, with a copy to us.{/ts}
</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.payer_validate_contact.label}</div>
    <div class="content">{$form.payer_validate_contact.html}</div>
    <div class="clear"></div>
  </div>
  </fieldset>
</div>
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
</div>
<div id="iats-direct-debit-gbp-continue">
  <div class="messages crm-error">
    <div class="icon red-icon alert-icon"></div>
    {ts}Please fix the following errors in the form fields above:{/ts}
    <ul id="payer-validate-required">
    </ul>
  </div>
  <div class="clear"></div>
  <div class="crm-button payer-validate-initiate">
    {$form.payer_validate_initiate.html}
  </div>
</div>

<script type="text/javascript">
  {literal}
  cj( function( ) { /* move my custom fields around and make it a multistep form experience via javascript */
    cj('#payment_notice').hide();
    cj('.direct_debit_info-section').append(cj('#iats-direct-debit-gbp-payer-validate')); 
    cj('.crm-contribution-main-form-block').before(cj('#iats-direct-debit-gbp-declaration'));
    cj('.direct_debit_info-section').append(cj('#iats-direct-debit-gbp-payer-validate')); // .hide();
    if (!cj('#payer_validate_declaration').is(':checked')) {
      cj('.crm-contribution-main-form-block').hide();
    }
    cj('#payer_validate_declaration').change(function() {
      if (this.checked) {
        cj('.crm-contribution-main-form-block').show();
      }
      else {
        cj('.crm-contribution-main-form-block').hide();
      }
    });
    if (0 == cj('#payer_validate_reference').val().length) {
      cj('#iats-direct-debit-gbp-payer-validate').hide();
      cj('#crm-submit-buttons .crm-button').hide();
      cj('#iats-direct-debit-gbp-continue .crm-error').hide();
    }
    else {
      cj('#iats-direct-debit-gbp-continue').hide();
    }
    cj('#payer_validate_initiate').click(function() {
      cj('#payer-validate-required').html('');
      cj('#Main .billing_name_address-group input:visible, #Main input.required:visible').each(function() {
        // console.log(this.value.length);
        if (0 == this.value.length) {
          var myLabel = $(this).parent('.content').prev('.label').find('label').text().replace('*','');
          cj('#payer-validate-required').append('<li>' + myLabel + ' is a required field.</li>');
        }
      })
      if (0 == cj('#payer-validate-required').html().length) {
        cj('#iats-direct-debit-gbp-continue .crm-error').hide();
        cj('#payer_validate_reference').val('testref').change();
      }
      else { // add alert symbol
        cj('#iats-direct-debit-gbp-continue .crm-error').show();
      }
    });
    cj('#payer_validate_reference').change(function() {
      if ($(this).val().length) {
        cj('#payer-validate-required').html('').hide();
        cj('#iats-direct-debit-gbp-continue').hide();
        cj('#iats-direct-debit-gbp-payer-validate').show();
        cj('#crm-submit-buttons .crm-button').show();
      }
    });
    
  });
  {/literal}
</script>

