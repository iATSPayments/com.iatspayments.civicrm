{literal}
<script type="text/javascript">
cj(function( ) {
  var acheftBL = cj('form#Search input[name=acheft_backoffice_links]');
  if (0 < acheftBL.length) {
    var boLinks = cj.parseJSON(acheftBL.val());
    acheftBL.remove();
    cj.each(boLinks, function(index, value) {
       cj('form#Search #help').append('<div class="acheft-backend-link">Click <a href="'+value.url+'">'+value.title+'</a> to process a new ACH/EFT contribution or recurring contribution from this contact.</div>');
    });
  }
});
</script>{/literal}
