<?php

/**
 * Job.IatsACHEFTVerify API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRM/API+Architecture+Standards
 */
function _civicrm_api3_job_iatsacheftverify_spec(&$spec) {
  // todo - call this job with optional parameters?
}

/**
 * Job.IatsACHEFTVerify API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception

 * Look up all pending (status = 2) ACH/EFT contributions and see if they've been approved or rejected
 * Update the corresponding recurring contribution record to status = 1 (or 4) 
 * This works for both the initial contribution and subsequent contributions of recurring contributions, as well as one offs.
 * TODO: what kind of alerts should be provided if it fails?
 *
 * Also lookup new UK direct debit series, and new contributions from existing series.
 */
function civicrm_api3_job_iatsacheftverify($iats_service_params) {

  // find all pending iats acheft contributions, and their corresponding recurring contribution id 
  // Note: I'm not going to bother checking for is_test = 1 contributions, since these are never verified 
  $select = 'SELECT c.*, cr.contribution_status_id as cr_contribution_status_id, icc.customer_code as customer_code, icc.cid as icc_contact_id, pp.is_test 
      FROM civicrm_contribution c 
      INNER JOIN civicrm_contribution_recur cr ON c.contribution_recur_id = cr.id
      INNER JOIN civicrm_payment_processor pp ON cr.payment_processor_id = pp.id
      INNER JOIN civicrm_iats_customer_codes icc ON cr.id = icc.recur_id
      WHERE 
        c.contribution_status_id = 2
        AND pp.class_name = %1
        AND pp.is_test = 0
        AND (cr.end_date IS NULL OR cr.end_date > NOW())
      ORDER BY c.id';
  $args = array(
    1 => array('Payment_iATSServiceACHEFT', 'String'),
  );

  $dao = CRM_Core_DAO::executeQuery($select,$args);
  $acheft_pending = array();
  while ($dao->fetch()) {
    /* we will ask iats if this ach/eft is approved, if so update both the contribution and recurring contribution status id's to 1 */
    /* todo: get_object_vars is a lazy way to do this! */
    if (empty($acheft_pending[$dao->customer_code])) {
      $acheft_pending[$dao->customer_code] = array();
    }
    $acheft_pending[$dao->customer_code][] = get_object_vars($dao);
  }

  // also get the one-off "QuickClients" that still need approval
  $select = 'SELECT id,trxn_id,invoice_id,contact_id
      FROM civicrm_contribution  
      WHERE 
        contribution_status_id = 2
        AND payment_instrument_id = 2
        AND is_test = 0';
  $args = array();
  $dao = CRM_Core_DAO::executeQuery($select,$args);
  $quick = array();
  while ($dao->fetch()) {
    /* we assume that the iATS transaction id is a unique field for matching */
    $key = substr($dao->trxn_id,0,8); // split on the colon instead?
    $quick[$key] = get_object_vars($dao);
  }

  // and all the recent UK DD recurring contributions. I've added an extra 2 days to be sure i've got them all.
  $select = 'SELECT c.*, cr.contribution_status_id as cr_contribution_status_id, icc.customer_code as customer_code, icc.cid as icc_contact_id, iukddv.acheft_reference_num as reference_num, pp.is_test 
      FROM civicrm_contribution c 
      INNER JOIN civicrm_contribution_recur cr ON c.contribution_recur_id = cr.id
      INNER JOIN civicrm_payment_processor pp ON cr.payment_processor_id = pp.id
      INNER JOIN civicrm_iats_customer_codes icc ON cr.id = icc.recur_id
      INNER JOIN civicrm_iats_ukdd_validate iukddv ON cr.id = iukddv.recur_id
      WHERE 
        pp.class_name = %1
        AND pp.is_test = 0
        AND c.receive_date > %2';
  $args = array(
    1 => array('Payment_iATSServiceUKDD', 'String'),
    2 => array(date('c',strtotime('-32 days')), 'String'),
  );
  $dao = CRM_Core_DAO::executeQuery($select,$args);
  $ukdd_contribution = array();
  while ($dao->fetch()) {
    if (empty($ukdd_contribution[$dao->customer_code])) {
      $ukdd_contribution[$dao->customer_code] = array();
    }
    $key = $dao->trxn_id;
    $ukdd_contribution[$dao->customer_code][$key] = get_object_vars($dao);
  }
  // and now get all the non-completed UKDD sequences, in order to track new contributions from iATS
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
    1 => array('Payment_iATSServiceUKDD', 'String')
  );
  $dao = CRM_Core_DAO::executeQuery($select,$args);
  $ukdd_contribution_recur = array();
  while ($dao->fetch()) {
    $ukdd_contribution_recur[$dao->customer_code] = get_object_vars($dao);
  }
  /* get "recent" approvals and rejects from iats and match them up with my pending list, or one-offs, or UK DD via the customer code */
  require_once("CRM/iATS/iATSService.php");
  // an array of methods => contribution status of the records retrieved
  $process_methods = array('acheft_journal_csv' => 1,'acheft_payment_box_journal_csv' => 1, 'acheft_payment_box_reject_csv' => 4);
  /* initialize some values so I can report at the end */
  $error_count = 0;
  // count the number of each record from iats analysed, and the number of each kind found
  $processed = array_fill_keys(array_keys($process_methods),0);
  $found = array('recur' => 0, 'quick' => 0, 'new' => 0);
  // save all my api result messages as well
  $output = array();
  /* do this loop for each relevant payment processor of type ACHEFT or UKDD (usually only one or none) */
  /* since test payments are NEVER verified by iATS, don't bother checking them [unless/until they change this] */
  $select = 'SELECT id,url_site,is_test FROM civicrm_payment_processor WHERE (class_name = %1 OR class_name = %2) AND is_test = 0';
  $args = array(
    1 => array('Payment_iATSServiceACHEFT', 'String'),
    2 => array('Payment_iATSServiceUKDD', 'String'),
  );
  $dao = CRM_Core_DAO::executeQuery($select,$args);
  // watchdog('civicrm_iatspayments_com', 'pending: <pre>!pending</pre>', array('!pending' => print_r($acheft_pending,TRUE)), WATCHDOG_NOTICE);   
  while ($dao->fetch()) {
    /* get approvals from yesterday, approvals from previous days, and then rejections for this payment processor */
    $iats_service_params = array('type' => 'report', 'iats_domain' => parse_url($dao->url_site, PHP_URL_HOST)) + $iats_service_params;
    /* the is_test below should always be 0, but I'm leaving it in, in case eventually we want to be verifying tests */
    $credentials = iATS_Service_Request::credentials($dao->id, $dao->is_test);
    foreach ($process_methods as $method => $contribution_status_id) {
      // TODO: this is set to capture approvals and cancellations from the past month, for testing purposes
      // it doesn't hurt, but on a live environment, this maybe should be limited to the past week, or less?
      // or, it could be configurable for the job
      $iats_service_params['method'] = $method;
      $iats = new iATS_Service_Request($iats_service_params);
      // I'm now using the new v2 version of the payment_box_journal, so hack removed here
      switch($method) {
        case 'acheft_journal_csv': // special case to get today's transactions, so we're as real-time as we can be
          $request = array(
            'date' => date('Y-m-d').'T23:59:59+00:00', 
            'customerIPAddress' => (function_exists('ip_address') ? ip_address() : $_SERVER['REMOTE_ADDR']),
          );
          break;
        default: // box journals only got up to the end of yesterday
          $request = array(
            'fromDate' => date('Y-m-d',strtotime('-30 days')).'T00:00:00+00:00', 
            'toDate' => date('Y-m-d',strtotime('-1 day')).'T23:59:59+00:00', 
            'customerIPAddress' => (function_exists('ip_address') ? ip_address() : $_SERVER['REMOTE_ADDR']),
          );
          break;
      }
      // make the soap request, should return a csv file
      $response = $iats->request($credentials,$request);
      $transactions = $iats->getCSV($response, $method);
      $processed[$method]+= count($transactions);
      foreach($transactions as $transaction_id => $transaction) {
        // skip any rows that don't include a customer code - TODO: log this as an error?
        if (empty($transaction->customer_code)) continue;
        $contribution = NULL; // use this later to trigger an activity if it's not NULL
        // deal with three possibilities: it's a one-off "quick client" ach/eft, it's an acheft from a series, or it's a new uk direct debit
        if ('quick client' == strtolower($transaction->customer_code)) {
          /* a one off : try to update the contribution status */
          /* todo: extra testing of datetime value? */
          if (!empty($quick[$transaction_id])) {
            $found['quick']++;
            $contribution = $quick[$transaction_id];
            $params = array('version' => 3, 'sequential' => 1, 'contribution_status_id' => $contribution_status_id);
            $params['id'] = $contribution['id'];
            $result = civicrm_api('Contribution', 'create', $params); // update the contribution
            if (TRUE) { // always log these requests in civicrm for auditing type purposes
              $query_params = array(
                1 => array($transaction->customer_code, 'String'),
                2 => array($contribution['contact_id'], 'Integer'),
                3 => array($contribution['id'], 'Integer'),
                4 => array(0, 'Integer'),
                5 => array($contribution_status_id, 'Integer'),
              );
              CRM_Core_DAO::executeQuery("INSERT INTO civicrm_iats_verify
                (customer_code, cid, contribution_id, recur_id, contribution_status_id, verify_datetime) VALUES (%1, %2, %3, %4, %5, NOW())", $query_params);
            }
          }
        }
        // I'm only interested in acheft customer codes that are still in a pending state, or new ones (e.g. UK DD)
        elseif (isset($acheft_pending[$transaction->customer_code])) {
          foreach($acheft_pending[$transaction->customer_code] as $i => $test) {
            // match if: the invoice id matches and the date is within a day
            $ts = strtotime($test['receive_date']);
            $invoice_test = substr($test['invoice_id'],0,10); // iats only stores the first 10 characters of my civicrm invoice id
            if ((abs($ts - $transaction->receive_date) < 60 * 60 * 24) && ($invoice_test == $transaction->invoice)) {
              unset($acheft_pending[$transaction->customer_code][$i]);
              $contribution = $test;
              break;
            }
          }
          if (!empty($contribution)) {
            $found['recur']++;
            // first update the contribution status
            $params = array('version' => 3, 'sequential' => 1, 'contribution_status_id' => $contribution_status_id);
            $params['id'] = $contribution['id'];
            $result = civicrm_api('Contribution', 'create', $params); // update the contribution
            // now see if I need to update the corresponding recurring contribution
            if ($contribution_status_id != $contribution['cr_contribution_status_id']) {
              // TODO: log this separately
              $params = array('version' => 3, 'sequential' => 1, 'contribution_status_id' => $contribution_status_id);
              $params['id'] = $contribution['contribution_recur_id'];
              $result = civicrm_api('ContributionRecur', 'create', $params);
            }
            if (TRUE) { // always log these requests in civicrm for auditing type purposes
              $query_params = array(
                1 => array($transaction->customer_code, 'String'),
                2 => array($contribution['contact_id'], 'Integer'),
                3 => array($contribution['id'], 'Integer'),
                4 => array($contribution['contribution_recur_id'], 'Integer'),
                5 => array($contribution_status_id, 'Integer'),
              );
              CRM_Core_DAO::executeQuery("INSERT INTO civicrm_iats_verify
                (customer_code, cid, contribution_id, recur_id, contribution_status_id, verify_datetime) VALUES (%1, %2, %3, %4, %5, NOW())", $query_params);
              if ($contribution_status_id != $contribution['cr_contribution_status_id']) {
                $query_params[3][0] = 0; // the recurring contribution itself got changed
                CRM_Core_DAO::executeQuery("INSERT INTO civicrm_iats_verify
                  (customer_code, cid, contribution_id, recur_id, contribution_status_id, verify_datetime) VALUES (%1, %2, %3, %4, %5, NOW())", $query_params);
              }
            }
          }
        }
        elseif (isset($ukdd_contribution_recur[$transaction->customer_code])) {
          // it's a (possibly) new recurring UKDD contribution triggered from iATS
          // check my existing ukdd_contribution list in case it's the first one that just needs to be updated, or has already been processed
          // I also confirm that it's got the right ach reference field, which i get from the ukdd_contribution_recur record
          $contribution_recur = $ukdd_contribution_recur[$transaction->customer_code];
          // build the civicrm invoice id, also used for the transaction id
          $invoice_id = $transaction->id.':iATSUKDD:'.date('Y-m-d',$transaction->receive_date);
          // now do a couple of tests:
          if ($contribution_recur['reference_num'] != $transaction->achref) {
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
          elseif (isset($ukdd_contribution[$transaction->customer_code][$invoice_id])) {
            // I can ignore it, i've already created this one
            // TODO: show I confirm status?
          }
          else { // save my contribution in civicrm
            $contribution = array(
              'version'        => 3,
              'contact_id'       => $contribution_recur['contact_id'],
              'receive_date'       => date('c',$transaction->receive_date),
              'total_amount'       => $transaction->amount,
              'payment_instrument_id'  => $contribution_recur['payment_instrument_id'],
              'contribution_recur_id'  => $contribution_recur['id'],
              'trxn_id'        => $invoice_id, // because it has to be unique, use my invoice id
              'invoice_id'       => $invoice_id,
              'source'         => 'iATS UK DD Reference: '.$contribution_recur['reference_num'],
              'contribution_status_id' => $contribution_status_id, 
              'currency'  => $contribution_recur['currency'], // better be GBP!
              'payment_processor'   => $contribution_recur['payment_processor_id'],
              'is_test'        => 0, 
            );
            if (isset($dao->contribution_type_id)) {  // 4.2
               $contribution['contribution_type_id'] = $contribution_recur['contribution_type_id'];
            }
            else { // 4.3+
               $contribution['financial_type_id'] = $contribution_recur['financial_type_id'];
            }
            $result = civicrm_api('contribution', 'create', $contribution);
            if ($result['is_error']) {
              $output[] = $result['error_message'];
            }
            else {
              $found['new']++;
            }
          }
        }
        // if one of the above was true and I've got a new or confirmed contribution:
        if (!empty($contribution)) {
          // log it as an activity for administrative reference
          $subject_string = empty($contribution['id']) ? 'Found new iATS Payments UK DD contribution for contact id %3' : '%1 iATS Payments ACH/EFT contribution id %2 for contact id %3';
          $subject = ts($subject_string,
              array(
                1 => (($contribution_status_id == 4) ? ts('Cancelled') : ts('Verified')),
                2 => $contribution['id'],
                3 => $contribution['contact_id'],
              ));
          $result = civicrm_api('activity', 'create', array(
            'version'       => 3,
            'activity_type_id'  => 6, // 6 = contribution
            'source_contact_id'   => $contribution['contact_id'],
            'assignee_contact_id' => $contribution['contact_id'],
            'subject'       => $subject,
            'status_id'       => 2, // TODO: what should this be?
            'activity_date_time'  => date("YmdHis"),
          ));
          if ($result['is_error']) {
            $output[] = ts(
              'An error occurred while creating activity record for contact id %1: %2',
              array(
                1 => $contribution['contact_id'],
                2 => $result['error_message']
              )
            );
            ++$error_count;
          } 
          else {
            $output[] = $subject;
          }
        }
        // otherwise ignore it
      }
    }
  }
  $message = '<br />'. ts('Completed with %1 errors.',
    array(
      1 => $error_count,
    )
  );
  $message .= '<br />'. ts('Processed %1 approvals from yesterday, %2 approval and %3 rejection records from the previous month.',
    array(
      1 => $processed['acheft_journal_csv'],
      2 => $processed['acheft_payment_box_journal_csv'],
      3 => $processed['acheft_payment_box_reject_csv'],
    )
  );
  // If errors ..
  if ($error_count) {
    return civicrm_api3_create_error($message .'</br />'. implode('<br />', $output));
  }
  // If no errors and some records processed ..
  if (array_sum($processed) > 0) {
    if (count($acheft_pending) > 0) {
      $message .= '<br />'. ts('For %1 pending ACH/EFT recurring contributions from %2 contacts, %3 results applied.',
        array(
          1 => count($acheft_pending, COUNT_RECURSIVE),
          2 => count($acheft_pending),
          3 => $found['recur'],
        )
      );
    }
    if (count($quick) > 0) {
      $message .= '<br />'. ts('For %1 pending one-off ACH/EFT contributions, %2 results applied.',
        array(
          1 => count($quick),
          2 => $found['quick'],
        )
      );
    }
    if (count($ukdd_contribution_recur) > 0) {
      $message .= '<br />'. ts('For %1 recurring UK direct debit contribution series, %2 new contributions found.',
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
  // No records processed
  return civicrm_api3_create_success(ts('No records found to process.'));
 
}
