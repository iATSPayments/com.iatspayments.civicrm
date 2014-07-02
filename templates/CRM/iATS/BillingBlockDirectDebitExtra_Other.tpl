{*
 Extra fields for iats direct debit, template, for unknown currencies
*}
    <div id="iats-direct-debit-extra">
      <div class="description">Your currency is not supported by this iATS Payment processor, proceed at your own risk.</div>
    </div>

     <script type="text/javascript">
     {literal}

cj( function( ) { /* move my account type box up where it belongs */
  cj('.direct_debit_info-section').prepend(cj('#iats-direct-debit-extra'));
});
{/literal}
</script>
