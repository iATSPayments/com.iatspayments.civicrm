{literal}
<script type="text/javascript">
cj(function( ) {
  var acheftBL = cj('form#Contribution input[name=acheft_backoffice_links]');
  var boLinks = cj.parseJSON(acheftBL.val());
  acheftBL.remove();
  cj.each(boLinks, function(index, value) {
    cj('form#Contribution select#payment_processor_id').after('<div class="acheft-backend-link">Or use: '+value+'</div>');
  });
});
</script>{/literal}
