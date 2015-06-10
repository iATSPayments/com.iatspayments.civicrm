cj( function($) {
  function iatsSetBankIdenficationNumber() {
    var bin = $('#cad_bank_number').val() + $('#cad_transit_number').val();
    console.log('bin: '+bin);
    $('#bank_identification_number').val(bin);
  }
  function iatsACHEFTRefresh() {
    if (0 < $('#iats-direct-debit-extra').length) {
      /* move my custom fields up where they belong */
      $('.direct_debit_info-section').prepend($('#iats-direct-debit-extra'));
      /* hide the bank identiication number field */
      $('.bank_identification_number-section').hide();
      iatsSetBankIdenficationNumber();
      $('#cad_transit_number, #cad_bank_number').blur(iatsSetBankIdenficationNumber);
    }
  }
  iatsACHEFTRefresh();
  $('input[name=payment_processor]').click(iatsACHEFTRefresh);
});
