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
    <div class="content"><strong>{ts domain='com.iatspayments.civicrm'}Note: {/ts}</strong>{ts domain='com.iatspayments.civicrm'}All Direct Debits are protected by a guarantee. In future, if there is a change to the date, amount of frequency of your Direct Debit, we will always give you 5 working days notice in advance of your account being debited. In the event of any error, you are entitled to an immediate refund from your Bank of Building Society. You have the right to cancel at any time and this guarantee is offered by all the Banks and Building Societies that accept instructions to pay Direct Debits. A copy of the safeguards under the Direct Debit Guarantee will be sent to you with our confirmation letter.{/ts}
    </div>
    <div><br/></div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.payer_validate_contact.label}</div>
    <div class="content"><strong>{ts domain='com.iatspayments.civicrm'}Contact Information: {/ts}</strong>{$form.payer_validate_contact.html}</div>
    <div class="clear"></div>
  </div>
  </fieldset>
</div>

<div id="iats-direct-debit-extra">
  <div class="crm-section cad-instructions-section">
    <div class="label"><em>{ts domain='com.iatspayments.civicrm'}You can find your Account Number and Sort Code by inspecting a cheque.{/ts}</em></div>
    <div class="content"><img width=500 height=303 src="{crmResURL ext=com.iatspayments.civicrm file=templates/CRM/iATS/GBP_cheque_500x.jpg}"></div>
    <div class="clear"></div>
  </div>
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
  <div class="crm-section payer-validate-start-date" style="display: none">
    <div class="label">{$form.payer_validate_start_date.label}</div>
    <div class="content">{$form.payer_validate_start_date.html}</div>
    <div class="clear"></div>
  </div>
  <input name="payer_validate_url" type="hidden" value="{crmURL p='civicrm/iatsjson' q='reset=1'}">
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
    /* initiate a payer validation: check for required fields, then do an ajax call to retrieve bank info */
    cj('#payer_validate_initiate').click(function() {
      cj('#payer-validate-required').html('');
      cj('#Main .billing_name_address-group input:visible, #Main input.required:visible').each(function() {
        // console.log(this.value.length);
        if (0 == this.value.length) {
          if ('installments' == this.id) {
            var myLabel = 'Installments';
          }
          else {
            var myLabel = $(this).parent('.content').prev('.label').find('label').text().replace('*','');
          }
          cj('#payer-validate-required').append('<li>' + myLabel + ' is a required field.</li>');
        }
      })
      if (0 == cj('#payer-validate-required').html().length) {
        cj('#iats-direct-debit-gbp-continue .crm-error').hide();
        var validatePayer = {};
        validatePayer.beginDate = cj('#payer_validate_start_date').val();
        var endDate = new Date(validatePayer.beginDate);
        var frequencyInterval = cj('input[name=frequency_interval]').val();
        var frequencyUnit = cj('[name="frequency_unit"]').val();
        var installments = cj('input[name="installments"]').val();
        switch(frequencyUnit) {
          case 'year':
            var myYear = endDate.getFullYear() + (frequencyInterval * installments);
            endDate.setFullYear(myYear);
            break;
          case 'month':
            var myMonth = endDate.getMonth() + (frequencyInterval * installments);
            endDate.setMonth(myMonth);
            break;
          case 'week':
            var myDay = endDate.getDate() + (frequencyInterval * installments * 7);
            endDate.setDate(myDay);
            break;
          case 'day':
            var myDay = endDate.getDate() + (frequencyInterval * installments * 1);
            endDate.setDate(myDay);
            break;
        }
        validatePayer.endDate = endDate.toISOString();
        validatePayer.firstName = cj('#billing_first_name').val();
        validatePayer.lastName = cj('#billing_last_name').val();
        validatePayer.address = cj('input[name|="billing_street_address"]').val();
        validatePayer.city = cj('input[name|="billing_city"]').val();
        validatePayer.zipCode = cj('input[name|="billing_postal_code"]').val();
        validatePayer.country = cj('input[name|="billing_country_id"]').find('selected').text();
        validatePayer.accountCustomerName = cj('#account_holder').val();
        validatePayer.accountNum = cj('#bank_identification_number').val() + cj('#bank_account_number').val();
        validatePayer.email = cj('input[name|="email"]').val();
        validatePayer.ACHEFTReferenceNum = '';
        validatePayer.companyName = '';
        validatePayer.type = 'customer';
        validatePayer.method = 'direct_debit_acheft_payer_validate';
        validatePayer.payment_processor_id = cj('input[name="payment_processor"]').val();
        var payerValidateUrl = cj('input[name="payer_validate_url"]').val();
        // console.log(payerValidateUrl);
        // console.log(validatePayer);
        cj.post(payerValidateUrl,validatePayer,function( result ) {
          // console.log(result);
          cj('#payer_validate_reference').val(result.ACHREFNUM).change();
          cj('#bank_name').val(result.BANK_NAME);
          cj('#payer_validate_address').val(result.BANK_BRANCH + "\n" + result.BANKADDRESS1 + "\n" + result.BANK_CITY + ", " + result.BANK_STATE + "\n" + result.BANK_POSTCODE);
        },'json');
      }
      else { // add alert symbol
        cj('#iats-direct-debit-gbp-continue .crm-error').show();
      }
    });
    cj('#payer_validate_reference').change(function() {
      cj('#payer-validate-required').html('').hide();
      if ($(this).val().length) {
        cj('#iats-direct-debit-gbp-continue').hide();
        cj('#iats-direct-debit-gbp-payer-validate').show();
        cj('#crm-submit-buttons .crm-button').show();
      }
      // for testing only!
      else {
        cj('#iats-direct-debit-gbp-continue').show();
        cj('#iats-direct-debit-gbp-payer-validate').hide();
        cj('#crm-submit-buttons .crm-button').hide();
      }
    });

  });
  cj( function( ) {
    /* move my custom fields up where they belong */
    cj('.direct_debit_info-section').prepend(cj('#iats-direct-debit-extra'));
    /* hide the bank identiication number field */
  });

  {/literal}
</script>

