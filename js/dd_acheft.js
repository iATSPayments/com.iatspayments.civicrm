/*jslint indent: 2 */
/*global CRM, ts */

function iatsACHEFTRefresh() {
  cj(function ($) {
    'use strict';
    function iatsSetBankIdenficationNumber() {
      var bin = $('#cad_bank_number').val() + $('#cad_transit_number').val();
      // console.log('bin: '+bin);
      $('#bank_identification_number').val(bin);
    }
    if (0 < $('#iats-direct-debit-extra').length) {
      /* move my custom fields up where they belong */
      $('.direct_debit_info-section').prepend($('#iats-direct-debit-extra'));
      /* hide the bank identiication number field */
      $('.bank_identification_number-section').hide();
      iatsSetBankIdenficationNumber();
    }
  });
}
