{*
 Extra fields for iats direct debit UK
*}
<div id="iats-direct-debit-gbp-declaration">
  <fieldset class="iats-direct-debit-gbp-declaration">
  <legend>Declaration</legend>
  <div class="crm-section">
    <div class="content"><p><strong>{ts domain='com.iatspayments.civicrm'}Note: {/ts}</strong>{ts domain='com.iatspayments.civicrm'}
If you are not the account holder or your account requires more than one signature a paper Direct Debit Instructoin will be required to be completed and posted to us. <a href="#">Click here</a> to print off a paper Drect Debit Instruction.</p>{/ts}
<p><strong>{ts  domain='com.iatspayments.civicrm'}OR{/ts}</strong></p>
<p>{ts domain='com.iatspayments.civicrm'}Continue with the details below{/ts}</p>
    </div>
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
    <div class="content">
<img width=166 height=61 src="{crmResURL ext=com.iatspayments.civicrm file=templates/CRM/iATS/bacs.png}">
<img width=148 height=57 src="{crmResURL ext=com.iatspayments.civicrm file=templates/CRM/iATS/direct-debit.jpg}">
<img width=134 height=55 src="{crmResURL ext=com.iatspayments.civicrm file=templates/CRM/iATS/iats.jpg}">
</div>
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
<div id="iats-direct-debit-start-date">
  <div class="crm-section payer-validate-start-date">
    <div class="label">{$form.start_date.label}</div>
    <div class="content">{$form.start_date.html}</div>
    <div class="content">{ts domain='com.iatspayments.civicrm'}If you wish, you can modify this date to make it later. This is the earliest date upon which your contributions can start.{/ts}</div>
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
    <div class="label">{$form.payer_validate_reference_display.label}</div>
    <div class="content">{$form.payer_validate_reference_display.html}</div>
    {$form.payer_validate_reference.html}
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
    {$form.payer_validate_amend.html}
  </div>
</div>

<script type="text/javascript">
  {literal}
  cj( function( ) { /* move my custom fields around and make it a multistep form experience via javascript */
    /* move my custom fields up where they belong */
    var pgDeclaration = cj('#iats-direct-debit-gbp-declaration');
    var pgNonDeclaration = cj('.crm-contribution-main-form-block');
    var pgPayerValidate = cj('#billing-payment-block');
    // var pgPayerValidateHide = $('#billing-payment-block');
    /* i don't want to show my default civicrm payment notice */
    cj('#payment_notice').hide();
    /* move some fields around to better flow like the iATS DD samples */
    cj('input[name|="email"]').parents('.crm-section').prependTo('.billing_name_address-section');
    cj('.direct_debit_info-section').before(cj('#iats-direct-debit-extra'));
    cj('.is_recur-section').after(cj('#iats-direct-debit-start-date'));
    cj('.crm-contribution-main-form-block').before(cj('#iats-direct-debit-gbp-declaration'));
    cj('#payer_validate_amend').hide();
    /* page 1: Declaration */
    if (cj('#payer_validate_declaration').is(':checked')) {
      pgDeclaration.hide();
    }
    else {
      pgNonDeclaration.hide();
    }
    cj('#payer_validate_declaration').change(function() {
      if (this.checked) {
        pgDeclaration.hide('slow');
        pgNonDeclaration.show('slow');
      }
      else {
        pgDeclaration.hide('slow');
        pgNonDeclaration.show('slow');
      }
    });
    /* page 2: Payer validate */
    if (0 < cj('input[name=payer_validate_reference]').val().length) {
      /* ready for page 3, review validation info */
      // TODO: hide some other fields or make them non-editable?
      // cj('#iats-direct-debit-gbp-continue').hide();
    }
    else { /* show page 2, input validation info */
      // pgNonDeclaration.children().not('#billing-payment-block').hide();
      cj('#iats-direct-debit-gbp-continue .crm-error').hide();
      cj('#iats-direct-debit-gbp-payer-validate').hide();
      cj('.bank_name-section').hide();
      cj('#crm-submit-buttons .crm-button').hide();
    }
    /* initiate a payer validation: check for required fields, then do an ajax call to retrieve bank info */
    cj('#payer_validate_initiate').click(function() {
      cj('#payer-validate-required').html('');
      var startDateStr = cj('#start_date').val();
      var startDate = new Date(startDateStr);
      var defaultStartDate = new Date(cj('#start_date').prop('defaultValue'));
      if (isNaN(startDate)) {
        cj('#payer-validate-required').append('<li>Please write your start date in a recognizable format.</li>');
      }
      else if (startDate < defaultStartDate) {
        cj('#payer-validate-required').append('<li>You must choose a start date after the default value, resetting it.</li>');
        cj('#start_date').val(cj('#start_date').prop('defaultValue'));
      }
      else {
        cj('#start_date').val(cj.datepicker.formatDate('MM d, yy',startDate));
      }
      // the billing address group is all required (except middle name) but doesn't have the class marked
      cj('#Main .billing_name_address-group input:visible, #Main input.required:visible').each(function() {
        // console.log(this.value.length);
        if (0 == this.value.length && this.id != 'billing_middle_name') {
          if ('installments' == this.id) { // todo: check out other exceptions 
            var myLabel = 'Installments';
          }
          else {
            var myLabel = cj(this).parent('.content').prev('.label').find('label').text().replace('*','');
          }
          cj('#payer-validate-required').append('<li>' + myLabel + ' is a required field.</li>');
        }
      })
      if (0 == cj('#payer-validate-required').html().length) {
        cj('#iats-direct-debit-gbp-continue .crm-error').hide();
        var validatePayer = {};
        validatePayer.beginDate = cj.datepicker.formatDate('yy-mm-dd',startDate);
        var endDate = startDate;
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
        cj(this).val('Processing ...').prop('disabled',true);
        cj.post(payerValidateUrl,validatePayer,function( result ) {
          // console.log(result);
          // TODO: deal with validate errors
          if ('string' == typeof(result.ACHREFNUM)) {
            cj('#bank_name').val(result.BANK_NAME);
            cj('#payer_validate_address').val(result.BANK_BRANCH + "\n" + result.BANKADDRESS1 + "\n" + result.BANK_CITY + ", " + result.BANK_STATE + "\n" + result.BANK_POSTCODE);
            cj('input[name=payer_validate_reference]').val(result.ACHREFNUM).change();
            cj('#payer_validate_reference_display').val(result.ACHREFNUM).change();
            cj('#payer_validate_initiate').val('Continue').prop('disabled',false);
          }
          else {
            cj('#payer-validate-required').append('<li>' + result.reasonMessage + '</li>');
            cj('#iats-direct-debit-gbp-continue .crm-error').show('slow');
            cj('#payer_validate_initiate').val('Retry').prop('disabled',false);
            // show error
            console.log(result);
          }
        },'json');
      }
      else { // add alert symbol
        cj('#iats-direct-debit-gbp-continue .crm-error').show('slow');
      }
    });
    /* clear the reference to go back */
    cj('#payer_validate_amend').click(function() {
      cj('#payer_validate_reference_display').val('')
      cj('input[name=payer_validate_reference]').val('').change();
    });
    cj('input[name=payer_validate_reference]').change(function() {
      cj('#payer-validate-required').html('').hide();
      if (cj(this).val().length) { /* i've got a refrence number, time for the user to confirm or amend */
        cj('#iats-direct-debit-gbp-continue .crm-error').hide();
        cj('#payer_validate_initiate').hide();
        cj('#payer_validate_admend').show();
        cj('#iats-direct-debit-gbp-payer-validate').show('slow');
        cj('#crm-submit-buttons .crm-button').show().find('input').val('Confirm');
        CRM.alert(ts('Please review your bank details and then click Contribute below.'));
      }
      // requires amendment
      else {
        cj('.crm-button.payer-validate-admend').hide();
        cj('#iats-direct-debit-gbp-continue').show();
        cj('#iats-direct-debit-gbp-payer-validate').hide('slow');
        cj('#crm-submit-buttons .crm-button').hide();
      }
    });
  });
  {/literal}
</script>

