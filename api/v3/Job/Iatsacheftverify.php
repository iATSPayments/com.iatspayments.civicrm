<?php

/**
 * @file
 * Contains the ACH/EFT Verification API Job.
 */

/**
 * Job.IatsACHEFTVerify API specification (optional).
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 *
 * @return void
 *
 * @see http://wiki.civicrm.org/confluence/display/CRM/API+Architecture+Standards
 */
function _civicrm_api3_job_iatsacheftverify_spec(&$spec) {
  // Todo - call this job with optional parameters?
}

/**
 * Job.IatsACHEFTVerify API.
 *
 * @param array $params
 *
 * @return array API result descriptor
 *
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 *
 * @throws API_Exception
 *  * Look up all pending (status = 2) ACH/EFT contributions and see if they've been approved or rejected
 * Update the corresponding recurring contribution record to status = 1 (or 4)
 * This works for both the initial contribution and subsequent contributions of recurring contributions, as well as one offs.
 * TODO: what kind of alerts should be provided if it fails?
 *
 */
function civicrm_api3_job_iatsacheftverify($iats_service_params) {

  $settings = CRM_Core_BAO_Setting::getItem('iATS Payments Extension', 'iats_settings');
  $receipt_recurring = $settings['receipt_recurring'];
  define('IATS_VERIFY_DAYS', 30);
  // I've added an extra 2 days when getting candidates from CiviCRM to be sure i've got them all.
  $civicrm_verify_days = IATS_VERIFY_DAYS + 2;
  // Get all the pending direct debit contributions that still need approval within the last civicrm_verify_days.
  // And see if they are approved in my iATS Journal
  // Count the number of each kind found.
  $processed = array(1 => 0, 4 => 0);
  // Save all my api error result messages.
  $error_log = array();
  try {
    $acheft_pending = civicrm_api3('Contribution', 'get', array(
      'sequential' => 1,
      'receive_date' => array('>' => "now - $civicrm_verify_days day"),
      'options' => array('limit' => 0),
      'contribution_status_id' => "Pending",
      'invoice_id' => array('IS NOT NULL' => 1),
      'contribution_test' => 0,
      'payment_instrument_id' => 'Debit Card',
      'return' => array('trxn_id', 'invoice_id', 'contribution_recur_id', 'contact_id', 'source'),
    ));
    $message = '<br />' . ts('Found %1 pending ACH/EFT contributions to verify.', array(1 => count($acheft_pending['values'])));
    // CRM_Core_Error::debug_var('Pending ACHEFT', $acheft_pending);
    foreach ($acheft_pending['values'] as $contribution) {
      $journal_matches = civicrm_api3('IatsPayments', 'get_journal', array(
        'sequential' => 1,
        'inv' => $contribution['invoice_id'],
      ));
      if ($journal_matches['count'] > 0) {
        /* found a matching journal entry, we can approve or fail it */
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
                  $error_log[] = $e->getMessage() ."\n";
                }
              }
              $complete['is_email_receipt'] = $is_email_receipt;
            }
            try {
              $contributionResult = civicrm_api3('contribution', 'completetransaction', $complete);
            }
            catch (CiviCRM_API3_Exception $e) {
              $error_log[] = 'Failed to complete transaction: '. $e->getMessage() ."\n";
            }

            // Restore source field and trxn_id that completetransaction overwrites
            civicrm_api3('contribution', 'create', array(
              'id' => $contribution['id'], 
              'source' => $contribution['source'],
              'trxn_id' => $trxn_id
            ));
          case 4: // failed, just update the contribution status.
            civicrm_api3('Contribution', 'create', array(
              'id' => $contribution['id'],
              'contribution_status_id' => $contribution_status_id
            ));
        }
        // Always log these requests in my cutom civicrm table for auditing type purposes
        $query_params = array(
          1 => array($journal_entry['cstc'], 'String'),
          2 => array($contribution['contact_id'], 'Integer'),
          3 => array($contribution['id'], 'Integer'),
          4 => array($contribution_status_id, 'Integer'),
          5 => array($contribution['contribution_recur_id'], 'Integer'),
        );
        if (empty($contribution['contribution_recur_id'])) {
          unset($query_params[5]);
          CRM_Core_DAO::executeQuery("INSERT INTO civicrm_iats_verify
            (customer_code, cid, contribution_id, contribution_status_id, verify_datetime) VALUES (%1, %2, %3, %4, NOW())", $query_params);
        }
        else {
          CRM_Core_DAO::executeQuery("INSERT INTO civicrm_iats_verify
            (customer_code, cid, contribution_id, contribution_status_id, verify_datetime, recur_id) VALUES (%1, %2, %3, %4, NOW(), %5)", $query_params);
        }
      }
    }
  }
  catch (CiviCRM_API3_Exception $e) {
    $error_log[] = $e->getMessage() ."\n";
  }
  $message .= '<br />' . ts('Completed with %1 errors.',
    array(
      1 => count($error_log),
    )
  );
  $message .= '<br />' . ts('Processed %1 approvals and %2 rejection records from the previous ' . IATS_VERIFY_DAYS . ' days.', 
    array(
      1 => $processed[1],
      2 => $processed[4],
    )
  );
  // If errors ..
  if (count($error_log) > 0) {
    return civicrm_api3_create_error($message . '</br />' . implode('<br />', $error_log));
  }
  return civicrm_api3_create_success($message);
}
