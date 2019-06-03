/* 
 * custom js so we can use the FAPS cryptojs script
 *
 */

/*jslint indent: 2 */
/*global CRM, ts */

cj(function ($) {
  'use strict';
  // move the iframe to the payment information section
  cj('#firstpay-iframe').appendTo('#payment_information .billing_mode-group');
  // handle messages from the iframe, supporting multiple javascript versions
  if (window.addEventListener) {
    window.addEventListener("message",fapsIframeMessage, false);
  }
  else {
    window.attachEvent("onmessage", fapsIframeMessage);
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
