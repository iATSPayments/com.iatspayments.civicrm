{*
 Extra fields for iats direct debit, template for USD
*}
<div id="iats-direct-debit-extra">
  <div class="crm-section cad-instructions-section">
    <div class="label"><em>{ts domain='com.iatspayments.civicrm'}You can find your Bank Routing Number and Bank Account number by inspecting a check.{/ts}</em></div>
    <div class="content"><img width=292 height=151 src="{crmResURL ext=com.iatspayments.civicrm file=templates/CRM/iATS/imgUSD.jpg}"></div>
    <div class="clear"></div>
  </div>
  <div class="crm-section usd-account-type-section">
    <div class="label">{$form.usd_account_type.label}</div>
    <div class="content">{$form.usd_account_type.html}</div>
    <div class="clear"></div>
  </div>
</div>

<script type="text/javascript">
  {literal}
  cj( function( ) { /* move my account type box up where it belongs */
    cj('.direct_debit_info-section').prepend(cj('#iats-direct-debit-extra'));
  });
  {/literal}
</script>

