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
 * Also lookup new UK direct debit series, and new contributions from existing series.
 */
function civicrm_api3_job_iatsacheftverify($iats_service_params) {

  $settings = CRM_Core_BAO_Setting::getItem('iATS Payments Extension', 'iats_settings');
  $receipt_recurring = $settings['receipt_recurring'];
  define('IATS_VERIFY_DAYS', 30);
  // I've added an extra 2 days when getting candidates from CiviCRM to be sure i've got them all.
  $civicrm_verify_days = IATS_VERIFY_DAYS + 2;
  // Get all the pending direct debit contributions that still need approval within the last civicrm_verify_days.
  // Re issue #211, include contribution_status_id so I can update it after verification, and later on check if it is already completed.
  $select = 'SELECT c.id, c.trxn_id, c.invoice_id, c.contact_id, c.contribution_recur_id, c.receive_date, cr.is_email_receipt, c.contribution_status_id
      FROM civicrm_contribution c
      LEFT JOIN civicrm_contribution_recur cr ON c.contribution_recur_id = cr.id
      WHERE
        c.contribution_status_id = 2
        AND NOT(ISNULL(c.invoice_id))
        AND c.payment_instrument_id = 2
        AND c.receive_date > %1
        AND c.is_test = 0';
  $args = array(
    1 => array(date('c', strtotime('-' . $civicrm_verify_days . ' days')), 'String'),
  );
  $dao = CRM_Core_DAO::executeQuery($select, $args);
  $acheft_pending = array();
  while ($dao->fetch()) {
    /* We index in the invoice_id which (now) is stored in iATS */
    $key = $dao->invoice_id;
    $acheft_pending[$key] = array('id' => $dao->id, 'trxn_id' => $dao->trxn_id, 'invoice_id' => $dao->invoice_id, 'contact_id' => $dao->contact_id, 'contribution_recur_id' => $dao->contribution_recur_id, 'receive_date' => $dao->receive_date, 'is_email_receipt' => $dao->is_email_receipt, 'contribution_status_id' => $dao->contribution_status_id);
  }
  // And some recent UK DD recurring contributions.
  $select = 'SELECT c.id, c.contribution_status_id, c.trxn_id, c.invoice_id, icc.customer_code
      FROM civicrm_contribution c
      INNER JOIN civicrm_contribution_recur cr ON c.contribution_recur_id = cr.id
      INNER JOIN civicrm_payment_processor pp ON cr.payment_processor_id = pp.id
      INNER JOIN civicrm_iats_customer_codes icc ON cr.id = icc.recur_id
      WHERE
        c.receive_date > %1
        AND pp.class_name = %2
        AND pp.is_test = 0';
  $args[2] = array('Payment_iATSServiceUKDD', 'String');
  $dao = CRM_Core_DAO::executeQuery($select, $args);
  $ukdd_contribution = array();
  while ($dao->fetch()) {
    if (empty($ukdd_contribution[$dao->customer_code])) {
      $ukdd_contribution[$dao->customer_code] = array();
    }
    // I want to key on my trxn_id that I can match up with data from iATS, but use the invoice_id for that initial pending one.
    $key = (empty($dao->trxn_id)) ? $dao->invoice_id : $dao->trxn_id;
    $ukdd_contribution[$dao->customer_code][$key] = array('id' => $dao->id, 'contribution_status_id' => $dao->contribution_status_id, 'invoice_id' => $dao->invoice_id);
  }
  // And now get all the non-completed UKDD sequences, in order to track new contributions from iATS.
  $select = 'SELECT cr.*, icc.customer_code as customer_code, icc.cid as icc_contact_id, iukddv.acheft_reference_num as reference_num, pp.is_test
      FROM civicrm_contribution_recur cr
      INNER JOIN civicrm_payment_processor pp ON cr.payment_processor_id = pp.id
      INNER JOIN civicrm_iats_customer_codes icc ON cr.id = icc.recur_id
      INNER JOIN civicrm_iats_ukdd_validate iukddv ON cr.id = iukddv.recur_id
      WHERE
        pp.class_name = %1
        AND pp.is_test = 0
        AND (cr.end_date IS NULL OR cr.end_date > NOW())';
  $args = array(
    1 => array('Payment_iATSServiceUKDD', 'String'),
  );
  $dao = CRM_Core_DAO::executeQuery($select, $args);
  $ukdd_contribution_recur = array();
  while ($dao->fetch()) {
    $ukdd_contribution_recur[$dao->customer_code] = get_object_vars($dao);
  }
  /* get "recent" approvals and rejects from iats and match them up with my pending list, or one-offs, or UK DD via the customer code */
  require_once "CRM/iATS/iATSService.php";
  // An array of methods => contribution status of the records retrieved.
  $process_methods = array('acheft_journal_csv' => 1, 'acheft_payment_box_journal_csv' => 1, 'acheft_payment_box_reject_csv' => 4);
  /* initialize some values so I can report at the end */
  $error_count = 0;
  // Count the number of each record from iats analysed, and the number of each kind found.
  $processed = array_fill_keys(array_keys($process_methods), 0);
  $found = array('recur' => 0, 'quick' => 0, 'new' => 0);
  // Save all my api result messages as well.
  $output = array();
  /* do this loop for each relevant payment processor of type ACHEFT or UKDD */
  /* since test payments are NEVER verified by iATS, don't bother checking them [unless/until they change this?] */
  $select = 'SELECT id,url_site,is_test FROM civicrm_payment_processor WHERE (class_name = %1 OR class_name = %2) AND is_test = 0';
  $args = array(
    1 => array('Payment_iATSServiceACHEFT', 'String'),
    2 => array('Payment_iATSServiceUKDD', 'String'),
  );
  $dao = CRM_Core_DAO::executeQuery($select, $args);
  // watchdog('civicrm_iatspayments_com', 'pending: <pre>!pending</pre>', array('!pending' => print_r($iats_acheft_recur_pending,TRUE)), WATCHDOG_NOTICE);.
  while ($dao->fetch()) {
    /* get approvals from the 4 most recent full days, then approvals from other previous days, and then rejections for this payment processor */
    $iats_service_params = array('type' => 'report', 'iats_domain' => parse_url($dao->url_site, PHP_URL_HOST)) + $iats_service_params;
    /* the is_test below should always be 0, but I'm leaving it in, in case eventually we want to be verifying tests */
    $credentials = iATS_Service_Request::credentials($dao->id, $dao->is_test);
    foreach ($process_methods as $method => $contribution_status_id) {
      // TODO: this is set to capture approvals and cancellations from the past month, for testing purposes
      // it doesn't hurt, but on a live environment, this maybe should be limited to the past week, or less?
      // or, it could be configurable for the job.
      $iats_service_params['method'] = $method;
      $iats = new iATS_Service_Request($iats_service_params);
      // I'm now using the new v2 version of the payment_box_journal, so a previous hack here is now removed.
      switch ($method) {
        // Special case to get today's transactions, so we're as real-time as we can be.
        // We actually also do this for the four previous days as well as a followup.
        case 'acheft_journal_csv':
          $request = array(
            'date' => date('Y-m-d') . 'T23:59:59+00:00',
            'customerIPAddress' => (function_exists('ip_address') ? ip_address() : $_SERVER['REMOTE_ADDR']),
          );
          break;

        // Box journals only go up to the end of yesterday, and we need to set start/end indices.
        case 'acheft_payment_box_journal_csv':
        case 'acheft_payment_box_reject_csv':
          $request = array(
            'startIndex' => 0,
            'endIndex' => 499,
            'fromDate' => date('Y-m-d', strtotime('-' . IATS_VERIFY_DAYS . ' days')) . 'T00:00:00+00:00',
            'toDate' => date('Y-m-d', strtotime('-1 day')) . 'T23:59:59+00:00',
            'customerIPAddress' => (function_exists('ip_address') ? ip_address() : $_SERVER['REMOTE_ADDR']),
          );
          break;
        default:
          throw new API_Exception('Unexpected method: '.$method);
          break;
      }
      // Make the soap request, should return a csv file.
      $response = $iats->request($credentials, $request);
      $transactions = $iats->getCSV($response, $method);

      // special case followups per method
      switch($method) {
        case 'acheft_journal_csv':
          // Also pull the previous 4 days for e.g. cases of stat holidays + long weekends
          for ($days_before = -1; $days_before > -5; $days_before--) {
            $request['date'] = date('Y-m-d', strtotime($days_before.' day')) . 'T23:59:59+00:00';
            $response = $iats->request($credentials, $request);
            $transactions = array_merge($transactions, $iats->getCSV($response, $method));
          }
          break;
        default: // pulling box journals only 100 at a time, get more if necessary.
          $max_pulls = 100; // Don't pull more than 100 x 500 = 50,000 approvals or rejections over the past 30 days.
          $last = $transactions;
          while((0 < $max_pulls--) && (count($last) == 500)) {
            $request['startIndex'] = count($transactions);
            $request['endIndex'] = $request['startIndex'] + 499;
            $response = $iats->request($credentials, $request);
            $last = $iats->getCSV($response, $method);
            $transactions = array_merge($transactions, $last);
          }
          break;
      }

      $processed[$method] += count($transactions);
      // watchdog('civicrm_iatspayments_com', 'transactions: <pre>!trans</pre>', array('!trans' => print_r($transactions,TRUE)), WATCHDOG_NOTICE);.
      foreach ($transactions as $transaction_id => $transaction) {
        // skip any transactions that don't have a (civicrm-possible) invoice, they (shouldn't) ever match one of our pending transactions
        if (empty($transaction->invoice) || (32 != strlen($transaction->invoice))) {
          continue;
        }
        $invoice_id = $transaction->invoice;
        // CRM_Core_Error::debug_var('transaction',$transaction);
        // Use this later to trigger an activity if it's not NULL.
        $contribution = NULL;
        // First deal with acheft_pending, [and the corresponding recur sequence ? no].
        if (!empty($acheft_pending[$invoice_id])) {
          /* found a matching pending contribution in CiviCRM, update the contribution to complete or failed accordingly */
          $is_recur = ('quick client' != strtolower($transaction->customer_code));
          $found[$is_recur ? 'recur' : 'quick']++;
          $contribution = $acheft_pending[$invoice_id];
          // Updating a contribution status to complete needs some extra bookkeeping.
          if (1 == $contribution_status_id) {
            // Re issue #211: don't try to complete a transaction if it is already completed. This could happen when the same transaction was returned in both acheft_journal_csv and acheft_payment_box_journal_csv
            if( $acheft_pending[$invoice_id]->contribution_status_id != 1 ){
              // Note that I'm updating the timestamp portion of the transaction id here, since this might be useful at some point
              // should I update the receive date to when it was actually received? Would that confuse membership dates?
              $trxn_id = $transaction_id . ':' . time();
              $complete = array('version' => 3, 'id' => $contribution['id'], 'trxn_id' => $trxn_id, 'receive_date' => $contribution['receive_date']);
              if ($is_recur) {
                // For email receipting, use either my iats extension global, or the specific setting for this schedule.
                $complete['is_email_receipt'] = ($receipt_recurring < 2) ? $receipt_recurring : $contribution['is_email_receipt'];
              }
              try {
                $contributionResult = civicrm_api3('contribution', 'completetransaction', $complete);
                // Re issue #211: update contribution status to completed in the array, so that the next loop will not try to complete this transaction again and throw a "contribution already completed" exception
                $acheft_pending[$invoice_id]->contribution_status_id = $contribution_status_id;
              }
              catch (Exception $e) {
                throw new API_Exception('Failed to complete transaction: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
              }

              // Restore source field and trxn_id that completetransaction overwrites
              civicrm_api3('contribution', 'create', array(
                'id' => $contribution['id'], 
                'source' => $contribution['source'],
                'trxn_id' => $trxn_id
              ));
            }
          }
          else { // the other option is 4 == failed, just update the contribution status.
            civicrm_api3('Contribution', 'create', array(
              'id' => $contribution['id'],
              'contribution_status_id' => $contribution_status_id
            ));
            // Re issue #211: update contribution status to failed in the array, just to be consistent with what we did when completing a transaction
            $acheft_pending[$invoice_id]->contribution_status_id = $contribution_status_id;
          }
          // Always log these requests in my cutom civicrm table for auditing type purposes
          // watchdog('civicrm_iatspayments_com', 'contribution: <pre>!contribution</pre>', array('!contribution' => print_r($query_params,TRUE)), WATCHDOG_NOTICE);.
          $query_params = array(
            1 => array($transaction->customer_code, 'String'),
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
        // otherwise, test if it's a new uk direct debit.
        elseif (isset($ukdd_contribution_recur[$transaction->customer_code])) {
          // it's a (possibly) new recurring UKDD contribution triggered from iATS
          // check my existing ukdd_contribution list in case it's the first one that just needs to be updated, or has already been processed
          // I also confirm that it's got the right ach reference field, which i get from the ukdd_contribution_recur record.
          $contribution_recur = $ukdd_contribution_recur[$transaction->customer_code];
          // Build the (unique) civicrm trxn id that we can use to match up against civicrm-stored transactions.
          $trxn_id = $transaction->id . ':iATSUKDD:' . $transaction->customer_code;
          // Sanity check against the ACH Reference number, but only if I get it from iATS.
          if (!empty($transaction->achref) && ($contribution_recur['reference_num'] != $transaction->achref)) {
            $output[] = ts(
              'Unexpected error: ACH Ref. %1 does not match for customer code %2 (should be %3)',
              array(
                1 => $transaction->achref,
                2 => $transaction->customer_code,
                3 => $contribution_recur['reference_num'],
              )
            );
            ++$error_count;
          }
          elseif (isset($ukdd_contribution[$transaction->customer_code][$trxn_id])) {
            // I can ignore it, i've already created this one.
          }
          // Save my contribution in civicrm.
          else {
            $contribution = array(
              'version'        => 3,
              'contact_id'       => $contribution_recur['contact_id'],
              'receive_date'       => date('c', $transaction->receive_date),
              'total_amount'       => $transaction->amount,
              'payment_instrument_id'  => $contribution_recur['payment_instrument_id'],
              'contribution_recur_id'  => $contribution_recur['id'],
              'trxn_id'        => $trxn_id,
              'invoice_id'       => md5(uniqid(rand(), TRUE)),
              'source'         => 'iATS UK DD Reference: ' . $contribution_recur['reference_num'],
              'contribution_status_id' => $contribution_status_id,
            // Better be GBP!
              'currency'  => $contribution_recur['currency'],
              'payment_processor'   => $contribution_recur['payment_processor_id'],
              'is_test'        => 0,
            );
            // 4.2.
            if (isset($dao->contribution_type_id)) {
              $contribution['contribution_type_id'] = $contribution_recur['contribution_type_id'];
            }
            // 4.3+.
            else {
              $contribution['financial_type_id'] = $contribution_recur['financial_type_id'];
            }
            // If I have an outstanding pending contribution for this series, I'll recycle and update it here.
            foreach ($ukdd_contribution[$transaction->customer_code] as $key => $contrib_ukdd) {
              // it's pending.
              if ($contrib_ukdd['contribution_status_id'] == 2) {
                $contribution['id'] = $contrib_ukdd['id'];
                // don't change my invoice id in this case.
                unset($contribution['invoice_id']);
                // Ensure I don't pull this trick more than once somehow.
                unset($ukdd_contribution[$transaction->customer_code][$key]);
                // And note that I ignore everything else about the pending contribution in civicrm.
                break;
              }
            }
            // Otherwise I'll make do with a template if available.
            $contribution_template = array();
            if (empty($contribution['id'])) {
              // Populate my contribution from a template if possible.
              $contribution_template = _iats_civicrm_getContributionTemplate(array('contribution_recur_id' => $contribution_recur['id'], 'total_amount' => $transation->amount));
              $get_from_template = array('contribution_campaign_id', 'amount_level');
              foreach ($get_from_template as $field) {
                if (isset($contribution_template[$field])) {
                  $contribution[$field] = $contribution_template[$field];
                }
              }
              if (!empty($contribution_template['line_items'])) {
                $contribution['skipLineItem'] = 1;
                $contribution['api.line_item.create'] = $contribution_template['line_items'];
              }
            }
            if ($contribution_status_id == 1) {
              // Create or update as pending and then complete.
              $contribution['contribution_status_id'] = 2;
              $result = civicrm_api('contribution', 'create', $contribution);
              $complete = array('version' => 3, 'id' => $result['id'], 'trxn_id' => $trxn_id, 'receive_date' => $contribution['receive_date']);
              $complete['is_email_receipt'] = $receipt_recurring; /* send according to my configuration */
              try {
                $contributionResult = civicrm_api('contribution', 'completetransaction', $complete);
                // Restore my source field that ipn irritatingly overwrites, and make sure that the trxn_id is set also.
                civicrm_api('contribution', 'setvalue', array('version' => 3, 'id' => $contribution['id'], 'value' => $contribution['source'], 'field' => 'source'));
                civicrm_api('contribution', 'setvalue', array('version' => 3, 'id' => $contribution['id'], 'value' => $trxn_id, 'field' => 'trxn_id'));
              }
              catch (Exception $e) {
                throw new API_Exception('Failed to complete transaction: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
              }
            }
            else {
              // Create or update.
              $result = civicrm_api('contribution', 'create', $contribution);
            }
            if ($result['is_error']) {
              $output[] = $result['error_message'];
            }
            else {
              $found['new']++;
            }
          }
        }
        // If one of the above was true and I've got a new or confirmed contribution:
        // so log it as an activity for administrative reference.
        if (!empty($contribution)) {
          $subject_string = empty($contribution['id']) ? 'Found new iATS Payments UK DD contribution for contact id %3' : '%1 iATS Payments ACH/EFT contribution id %2 for contact id %3';
          $subject = ts($subject_string,
              array(
                1 => (($contribution_status_id == 4) ? ts('Cancelled') : ts('Verified')),
                2 => $contribution['id'],
                3 => $contribution['contact_id'],
              ));
          $result = civicrm_api('activity', 'create', array(
            'version'       => 3,
          // 6 = contribution.
            'activity_type_id'  => 6,
            'source_contact_id'   => $contribution['contact_id'],
            'assignee_contact_id' => $contribution['contact_id'],
            'subject'       => $subject,
          // TODO: what should this be?
            'status_id'       => 2,
            'activity_date_time'  => date("YmdHis"),
          ));
          if ($result['is_error']) {
            $output[] = ts(
              'An error occurred while creating activity record for contact id %1: %2',
              array(
                1 => $contribution['contact_id'],
                2 => $result['error_message'],
              )
            );
            ++$error_count;
          }
          else {
            $output[] = $subject;
          }
        }
        // Otherwise ignore it.
      }
    }
  }
  $message = '<br />' . ts('Completed with %1 errors.',
    array(
      1 => $error_count,
    )
  );
  $message .= '<br />' . ts('Processed %1 approvals from today and past 4 days, %2 approval and %3 rejection records from the previous ' . IATS_VERIFY_DAYS . ' days.',
    array(
      1 => $processed['acheft_journal_csv'],
      2 => $processed['acheft_payment_box_journal_csv'],
      3 => $processed['acheft_payment_box_reject_csv'],
    )
  );
  // If errors ..
  if ($error_count) {
    return civicrm_api3_create_error($message . '</br />' . implode('<br />', $output));
  }
  // If no errors and some records processed ..
  if (array_sum($processed) > 0) {
    if (count($acheft_pending) > 0) {
      $message .= '<br />' . ts('For %1 pending ACH/EFT contributions, %2 non-recuring and %3 recurring contribution results applied.',
        array(
          1 => count($acheft_pending),
          2 => $found['quick'],
          3 => $found['recur'],
        )
      );
    }
    if (count($ukdd_contribution_recur) > 0) {
      $message .= '<br />' . ts('For %1 recurring UK direct debit contribution series, %2 new contributions found.',
        array(
          1 => count($ukdd_contribution_recur),
          2 => $found['new'],
        )
      );
    }
    return civicrm_api3_create_success(
      $message . '<br />' . implode('<br />', $output)
    );
  }
  // No records processed.
  return civicrm_api3_create_success(ts('No records found to process.'));

}
