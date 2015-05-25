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
