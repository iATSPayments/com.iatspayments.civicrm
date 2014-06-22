{*
 Extra fields for iats direct debit, template for USD
*}
<div id="iats-direct-debit-extra">
  <div class="description">You can find your USD Router number, Bank number and Account number by inspecting a check</div>
  <br/>
  <div>{html_image file=$IMGdir}</div>
  <br/>

  <div class="crm-section iats-transit-number-section">
    <div class="label">Routing Number</div>
    <div class="content">{$form.iats_transit_number.html}</div>
    <div class="clear"></div>
  </div>

  <div class="crm-section iats-bank-account-number-section">
    <div class="label">{$form.bank_account_number.label}</div>
    <div class="content">{$form.bank_account_number.html}</div>
    <div class="clear"></div>
  </div>

  <div class="crm-section iats-bank-account-type-section">
    <div class="label">{$form.bank_account_type.label}</div>
    <div class="content">{$form.bank_account_type.html}</div>
    <div class="clear"></div>
  </div>

  <div class="crm-section iats-bank-name-section">
    <div class="label">{$form.bank_name.label}</div>
    <div class="content">{$form.bank_name.html}</div>
    <div class="clear"></div>
  </div>

  <div class="crm-section iats-account-holder-section">
    <div class="label">{$form.account_holder.label}</div>
    <div class="content">{$form.account_holder.html}</div>
    <div class="clear"></div>
  </div>

  <fieldset>
    <legend>END iATS Debit Extra Block</legend>
  </fieldset>
</div>


<script type="text/javascript">
  {literal}

  cj( function( ) { /* move my account type box up where it belongs */
    cj('.direct_debit_info-section').prepend(cj('#iats-direct-debit-extra'));
  });
  {/literal}
</script>

