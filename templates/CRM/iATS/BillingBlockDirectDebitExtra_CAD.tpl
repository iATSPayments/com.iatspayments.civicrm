{*
 Extra fields for iats direct debit, template for CAD
*}
    <div id="iats-direct-debit-extra">
      <div class="crm-section cad-instructions-section">
        <div class="label"><em>{ts domain='com.iatspayments.civicrm'}You can find your Transit number, Bank number and Account number by inspecting a cheque.{/ts}</em></div>
        <div class="content"><img width=292 height=148 src="{crmResURL ext=com.iatspayments.civicrm file=templates/CRM/iATS/imgCAD.jpg}"></div>
        <div class="clear"></div>
      </div>
      <div class="crm-section cad-transit-number-section">
        <div class="label">{$form.cad_transit_number.label}</div>
        <div class="content">{$form.cad_transit_number.html}</div>
        <div class="clear"></div>
      </div>

      <div class="crm-section cad-bank-number-section">
        <div class="label">{$form.cad_bank_number.label}</div>
        <div class="content">{$form.cad_bank_number.html}</div>
        <div class="clear"></div>
      </div>

      <div class="crm-section cad-account-type-section">
        <div class="label">{$form.cad_account_type.label}</div>
        <div class="content">{$form.cad_account_type.html}</div>
        <div class="clear"></div>
      </div>

    </div>


     <script type="text/javascript">
     {literal}

function iatsSetBankIdenficationNumber() {
  var bin = cj('#cad_bank_number').val() + cj('#cad_transit_number').val();
  console.log('bin: '+bin);
  cj('#bank_identification_number').val(bin);
}

cj( function( ) { 
  /* move my custom fields up where they belong */
  cj('.direct_debit_info-section').prepend(cj('#iats-direct-debit-extra'));
  /* hide the bank identiication number field */
  cj('.bank_identification_number-section').hide();
  iatsSetBankIdenficationNumber();
  cj('#cad_transit_number, #cad_bank_number').blur(iatsSetBankIdenficationNumber);
});
{/literal}
</script>
