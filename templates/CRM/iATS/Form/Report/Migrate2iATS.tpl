{*
 Upload IATSCustomer.csv file - template
*}
<table class="form-layout-compressed">

  <div class="crm-block crm-form-block  crm-contribution-import-uploadfile-form-block id="upload-file">

  <div id="help">
    {ts}Upload iATS Customer Codes [tokens] and create recurring contribution series for existing credit card and/or ACH/EFT from your previous credit card and/or ACH/EFT payment processor.{/ts}
    {ts}Files to be imported must be in the 'comma-separated-values' format (CSV) and must contain all data required.{/ts}
  </div>
  <table class="form-layout-compressed">
    <tr><td class="label">{$form.uploadFile.label}</td><td class="html-adjust"> {$form.uploadFile.html}<br />
        <span class="description">{ts}File format must be comma-separated-values (CSV).{/ts}</span></td></tr>
    <tr><td class="label"></td><td>{ts 1=$uploadSize}Maximum Upload File Size: %1 MB{/ts}</td></tr>
  </table>
  </div>


</table>
<div class="crm-submit-buttons">{include file="CRM/common/formButtons.tpl" location="bottom"}</div>
</div>
