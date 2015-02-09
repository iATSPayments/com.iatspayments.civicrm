{*
 Upload IATSCustomer.csv file - template
*}
<table class="form-layout-compressed">
  <tr class="crm-grant-form-block-start_date">
    <td class="label">{$form.start_date.label}</td>
    <td>
      {if $hideCalendar neq true}
        {include file="CRM/common/jcalendar.tpl" elementName=start_date}
      {else}
        {$form.start_date.html|crmDate}
      {/if}
    </td>
  </tr>

  <form name='main' method='POST' enctype=\"multipart/form-data\">
  <table name=criteria>
    <tr><td></td><td><input type=radio name=outputtype value=3>Upload File:</td><td><label for=\"file\">Filename:</label></td><td><input type=\"file\" name=\"file\" id=\"file\" /></td></tr>
  </table>
  </form>

  <br />

</table>
<div class="crm-submit-buttons">{include file="CRM/common/formButtons.tpl" location="bottom"}</div>
</div>
