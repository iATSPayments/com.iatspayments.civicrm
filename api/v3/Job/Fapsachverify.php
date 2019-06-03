<?php
use CRM_Iats_ExtensionUtil as E;

/**
 * Job.FapsACHVerify API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_job_fapsachverify_spec(&$spec) {
  // no parameters
}

/**
 * Job.FapsACHVerify API
 *
 * Look up all incomplete or pending (status = 2) contributions of a certain age
 * and see if they've been rejected.
 * Update the corresponding recurring contribution record to status = 1 (or 4)
 * This works for the initial contribution and subsequent contributions of recurring contributions, as well as one offs.
 * TODO: what kind of alerts should be provided if it fails?
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_job_fapsachverify($params) {

  /* get a list of all active/non-test iATS FAPS payment processors of type ACH, quit if there are none */
  /* We'll make sure they are unique from iATS FAPS point of view (i.e. distinct processorId = username) */
  $ach_processors = iats_civicrm_processors(array(), 'FAPSACH', array('is_active' => 1, 'is_test' => 0))
  if (empty($ach_processors)) {
    return;
  }
  $ach_accounts = array();
  foreach ($ach_processors as $payment_processor) {
    $user_name = $payment_processor['user_name'];
    if (empty($ach_accounts[$user_name])) {
      $ach_accounts[$user_name] = $payment_processor;
    }
  }

  $settings = CRM_Core_BAO_Setting::getItem('iATS FAPS Payments Extension', 'faps_settings');
  $receipt_recurring = $settings['receipt_recurring'];
  $verify_days = define('FAPS_VERIFY_DAYS', 30);
  // Get all the contributions that may need approval within the last verify_days.
  // Count the number of each kind found.
  $processed = array(1 => 0, 4 => 0);
  // Save all my api error result messages.
  $error_log = array();
  // Generate the api parameters for the contributions request
  $select_params = array(
    'sequential' => 1,
    'receive_date' => array('>' => "now - $verify_days day"),
    'options' => array('limit' => 0),
    'contribution_status_id' => array('IN' => array('Pending')),
    'trxn_id' => array('IS NOT NULL' => 1),
    'contribution_test' => 0,
    'payment_instrument_id' => ["Debit Card", "EFT"],
    'return' => array('trxn_id', 'invoice_id', 'contribution_recur_id', 'contact_id', 'source'),
  );
  $message = '';
  try {
    $contributions_verify = civicrm_api3('Contribution', 'get', $select_params);
    $message .= '<br />' . ts('Found %1 contributions to verify.', array(1 => count($contributions_verify['values'])));
    // CRM_Core_Error::debug_var('Verifying contributions', $contributions_verify);
    foreach ($contributions_verify['values'] as $contribution) {
      // CRM_Core_Error::debug_var('Verifying contribution', $contribution);
      list($faps_transactionId,$timestamp) = explode(':',$contribution['trxn_id'],2);
      $journal_matches = civicrm_api3('FapsTransaction', 'get', array(
        'transactionId' => $faps_transactionId,
      ));
      if ($journal_matches['count'] > 0) {
        $is_recur = empty($pending_contribution['contribution_recur_id']) ? FALSE : TRUE;
        // I only use the first one to determine the new status of the contribution.
        // TODO, deal with multiple partial payments
        $journal_entry = reset($journal_matches['values']);
        $transaction_id = $journal_entry['tnid'];
        $contribution_status_id = (int) $journal_entry['status_id'];
        // Keep track of how many of each time I've processed.
        $processed[$contribution_status_id]++;
        switch ($contribution_status_id) {
          case 1: // i.e. complete
            // Updating a contribution status to complete needs some extra bookkeeping.
            // Note that I'm updating the timestamp portion of the transaction id here, since this might be useful at some point
            // Should I update the receive date to when it was actually received? Would that confuse membership dates?
            $trxn_id = $transaction_id . ':' . time();
            $complete = array('version' => 3, 'id' => $contribution['id'], 'trxn_id' => $trxn_id, 'receive_date' => $contribution['receive_date']);
            if ($is_recur) {
              // For email receipting, use either my iats extension global, or the specific setting for this schedule.
              $is_email_receipt = $receipt_recurring;
              if ($is_email_receipt >= 2) {
                try {
                  $is_email_receipt = civicrm_api3('ContributionRecur', 'getvalue', array(
                    'return' => 'is_email_receipt',
                    'id' => $contribution['contribution_recur_id'],
                  ));
                }
                catch (CiviCRM_API3_Exception $e) {
                  $is_email_receipt = 0;
                  $error_log[] = $e->getMessage() . "\n";
                }
              }
              $complete['is_email_receipt'] = $is_email_receipt;
            }
            try {
              $contributionResult = civicrm_api3('contribution', 'completetransaction', $complete);
            }
            catch (CiviCRM_API3_Exception $e) {
              $error_log[] = 'Failed to complete transaction: ' . $e->getMessage() . "\n";
            }
      */
  }
  catch (Exception $e) {
    // Ignore this, though perhaps I should log it.
  }

  if (1) {
    // Spec: civicrm_api3_create_success($values = 1, $params = array(), $entity = NULL, $action = NULL)
    return civicrm_api3_create_success($returnValues, $params, 'NewEntity', 'NewAction');
  }
  else {
    throw new API_Exception(/*errorMessage*/ 'Everyone knows that the magicword is "sesame"', /*errorCode*/ 1234);
  }
}
