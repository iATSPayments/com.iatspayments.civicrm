
function iatsSetCreditCardNumber() {
  var bin = cj('#encrypted_credit_card_number').val();
  console.log('bin: '+bin);
  if (bin.charAt(0) == '0') {
    /* if 0 -> IDTech -> prefix = 00|@| */
    var withprefix = "00|@|"+bin;
    cj('#credit_card_number').val(withprefix);
    console.log('withprefix: '+withprefix);
  }
  if (bin.charAt(0) == '%') {
    /* if % -> MagTek -> prefix = 02|@| */
    var withprefix = "02|@|"+bin;
    cj('#credit_card_number').val(withprefix);
    console.log('withprefix: '+withprefix);
  }
}

function clearField() {
  var field = cj('#encrypted_credit_card_number').val();
  /* console.log('field: '+field); */
  if (field == 'Click here - then swipe.') {
    cj('#encrypted_credit_card_number').val('');
  }
}

cj( function( ) {
  /* move my custom fields up where they belong */
  cj('#payment_information .credit_card_info-group').prepend(cj('#iats-swipe'));

  /* hide the number credit card number field  */
  cj('.credit_card_number-section').hide();
  /* hide some ghost fields from a bad template on the front end form */
  cj('.-section').hide();  
  iatsSetCreditCardNumber();

  var defaultValue = 'Click here - then swipe.';
  cj('#encrypted_credit_card_number').val(defaultValue).focus(clearField).blur(iatsSetCreditCardNumber);

});
