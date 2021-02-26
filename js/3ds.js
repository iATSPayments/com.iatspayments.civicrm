/* 
 * custom js to implement 3ds
 *
 * Before submitting payment, run the 3ds gauntlet ...
 */

/*jslint indent: 2 */
/*global CRM, ts */

cj(function ($) {
  'use strict';
   console.log('3DS checks go here.');
   // iats3ds.iatsGetFingerPrintId('iATS client Id','first 6 of the CCnumber').then(result => {
	// The following parameters will be available in the returned “result” object.
	// 	1) success (indicates if the initialization was successful)
	//     2) errorMessage (error message if issue encountered)
        //    3) deviceFingerPrint (fingerprint that will be used during validation)
        // Use the following logic to determine next steps in the 3DS Authentication process with the returned “result” object.
        // If success == true and deviceFingerPrint == ‘’ then no further authentication is required, please proceed with standard transaction processing
        // If success == false, please use the errorMessage property for failure message
        // If success == true and deviceFingerPrint != ‘’ then proceed to 3DS Authentication Validation (deviceFingerPrint will be needed)
    // }):
});
