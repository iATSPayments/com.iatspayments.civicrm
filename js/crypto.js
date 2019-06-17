/* 
 * custom js so we can use the FAPS cryptojs script
 *
 */

/*jslint indent: 2 */
/*global CRM, ts */

cj(function ($) {
  'use strict';
  var isRecur = cj('#is_recur').prop('checked');
  generateFirstpayIframe(isRecur);
  cj('#is_recur').click(function() {
    isRecur = this.checked;
    generateFirstpayIframe(isRecur);
  });

  function generateFirstpayIframe(isRecur) {
    var iatsSettings = CRM.vars.iats;
    // we have four potential "transaction types" that generate different
    // iframes
    if (iatsSettings.paymentInstrumentId == '1') {
      var transactionType = isRecur ? 'Auth' : 'Sale';
    }
    else {
      var transactionType = isRecur ? 'Vault' : 'AchDebit';
    }
    // console.log(transactionType);
    // var transactionType = $has_is_recur ? ($is_cc ? 'Auth' : 'Vault') : ($is_cc ? 'Sale' : 'AchDebit');
    // generate or update an iframe below the cryptgram field
    // The iframe details will depend on cc vs. ach and also non-vs-recur
    // remove any existing iframe and message handlers first
    cj('#firstpay-iframe').remove();
    if (window.addEventListener) {
      window.removeEventListener("message",fapsIframeMessage);
    }
    else {
      window.detachEvent("onmessage", fapsIframeMessage);
    }
    cj('<iframe>', {
      'src': iatsSettings.iframe_src,
      'id':  'firstpay-iframe',
      'data-transcenter-id': iatsSettings.transcenterId,
      'data-processor-id': iatsSettings.processorId,
      'data-transaction-type': transactionType,
      'data-manual-submit': 'false',
      'frameborder': 0,
      'style': 'width: 100%',
      'scrolling': 'no'
    }).insertAfter('#payment_information .billing_mode-group .cryptogram-section');
    // handle "firstpay" messages (from iframes), supporting multiple javascript versions
    if (window.addEventListener) {
      window.addEventListener("message",fapsIframeMessage, false);
    }
    else {
      window.attachEvent("onmessage", fapsIframeMessage);
    }
  }

});


var fapsIframeMessage = function (event) {
  if (event.data.firstpay) {
    // console.log(event.data);
    switch(event.data.type) {
      case 'newCryptogram':
        // assign the cryptogram value into my special field
        var newCryptogram = event.data.cryptogram;
        // console.log(newCryptogram);
        cj('#cryptogram').val(newCryptogram);
        break;
      case 'generatingCryptogram':
        // prevent submission before it's done ?
        break;
      case 'generatingCryptogramFinished':
        // can be ignored?
        break;
      case 'cryptogramFailed':
        // alert user
        $('#cryptogram').crmError(ts(event.data.message));
        break;
    }
  }
}
