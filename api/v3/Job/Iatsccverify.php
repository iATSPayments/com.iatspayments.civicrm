<?php

/**
 * Job.IatsCCVerify API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRM/API+Architecture+Standards
 */
function _civicrm_api3_job_iatsccverify_spec(&$spec) {
  // todo - call this job with optional parameters?
  $spec['recur_id'] = array(
    'name' => 'recur_id',
    'title' => 'Recurring payment id',
    'api.required' => 0,
    'type' => 1,
  );
  $spec['contribution_id'] = array(
    'name' => 'contribution_id',
    'title' => 'Test a single contribution by CiviCRM contribution table id.',
    'api.required' => 0,
    'type' => 1,
  );
  $spec['invoice_id'] = array(
    'name' => 'invoice_id',
    'title' => 'Test a single contribution by invoice id.',
    'api.required' => 0,
    'type' => 1,
  );
}

/**
 * Job.IatsCCVerify API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception

 * Fetch all recent cc contributions from iATS and audit corresponding contributions in CiviCRM
 * This addresses multiple needs:
 * 1. Pull recent contributions that went through but weren't reported to CiviCRM due to unexpected connection/code breakage.
 * 2. Pull recurring contributions managed by iATS
 * 3. Pull one-time contributions that did not go through CiviCRM
 *
 */
function civicrm_api3_job_iatsccverify($params) {

  /* get a list of all active/non-test iATS payment processors of type cc, quit if there are none */
  try {
    $result = civicrm_api3('PaymentProcessor', 'get', array(
      'sequential' => 1,
      'class_name' => array('LIKE' => 'Payment_iATSService%'),
      'is_active' => 1,
      'is_test' => 0,
      'payment_type' => 1,
    ));
  }
  catch (CiviCRM_API3_Exception $e) {
    throw new API_Exception('Unexpected error getting payment processors: ' . $e->getMessage()); //  . "\n" . $e->getTraceAsString()); 
  }
  if (empty($payment_processors = $result['values'])) {
    return;
  }

  // get my parameters
  $recur_id = empty($params['recur_id']) ? 0 : ((int) $params['recur_id']);
  unset($params['recur_id']);
  $contribution_id = empty($params['contribution_id']) ? 0 : ((int) $params['contribution_id']);
  unset($params['contribution_id']);
  // invoice id is not yet sanitized
  $invoice_id = empty($params['invoice_id']) ? '' : trim($params['invoice_id']);
  unset($params['invoice_id']);
  $scan_all = (!$recur_id && !$contribution_id && !$invoice_id);
  // $iats_service_params = $params;  // what's left is assumed as parameters to pass the iats service object
  // get the settings: TODO allow more detailed configuration of which transactions to import
  $settings = CRM_Core_BAO_Setting::getItem('iATS Payments Extension', 'iats_settings');
  $import_all = empty($settings['import_all']) ? 0 : 1;
  $cc_civicrm = array(); // get a collection of all relevant customer codes and contributions that might match
  if ($scan_all) {
    $contribution_recur = array();
    $select = 'SELECT icc.customer_code, cr.*
      FROM civicrm_contribution_recur cr 
      INNER JOIN civicrm_payment_processor pp ON cr.payment_processor_id = pp.id
      INNER JOIN civicrm_iats_customer_codes icc ON cr.id = icc.recur_id
      WHERE
        (pp.class_name = %1 OR pp.class_name = %2)
        AND pp.is_test = 0';
    $args = array(
      1 => array('Payment_iATSService', 'String'),
      2 => array('Payment_iATSServiceSWIPE', 'String')
    );
    $dao = CRM_Core_DAO::executeQuery($select,$args);
    while ($dao->fetch()) {
      $contribution_recur[$dao->id] = $dao;
      $cc_civicrm[$dao->customer_code] = array();
    }
    define('IATS_CC_VERIFY_DAYS',2);
    // I've added an extra 2 days when getting candidates from CiviCRM to be sure i've got them all.
    $civicrm_verify_days = IATS_CC_VERIFY_DAYS + 2;
    // get all the recent credit card contributions from a these series
    $select = 'SELECT id, contact_id, contribution_status_id, trxn_id, invoice_id, contribution_recur_id
      FROM civicrm_contribution 
      WHERE
        receive_date > %1
        AND contribution_recur_id IN (' . implode(', ', array_keys($contribution_recur)) . ')';
    $args = array(
      1 => array(date('c',strtotime('-'.$civicrm_verify_days.' days')), 'String')
    );
    $dao = CRM_Core_DAO::executeQuery($select,$args);
    while ($dao->fetch()) {
      $cr = $contribution_recur[$dao->contribution_recur_id]; 
      // I want to key on my invoice_id that I can match up with data from iATS
      $key = $dao->invoice_id;
      $cc_civicrm[$cr->customer_code][$key] = array('id' => $dao->id, 'contact_id' => $dao->id, 'contribution_recur_id' => $contribution_recur_id, 'contribution_status_id' => $dao->contribution_status_id, 'invoice_id' => $dao->invoice_id);
    }
    // get all the recent non-recurring credit card contributions that might be from iats cc
    $select = 'SELECT id, contact_id, contribution_status_id, trxn_id, invoice_id, receive_date
      FROM civicrm_contribution 
      WHERE
        receive_date > %1
        AND ISNULL(contribution_recur_id ) AND payment_instrument = 1';
    $args = array(
      1 => array(date('c',strtotime('-'.$civicrm_verify_days.' days')), 'String')
    );
    $dao = CRM_Core_DAO::executeQuery($select,$args);
    while ($dao->fetch()) {
      // I want to key on my invoice_id that I can match up with data from iATS
      $key = $dao->invoice_id;
      $cc_civicrm['quick'][$key] = array('id' => $dao->id, 'contact_id' => $dao->id, 'contribution_recur_id' => 0, 'contribution_status_id' => $dao->contribution_status_id, 'invoice_id' => $dao->invoice_id);
    }
    watchdog('civicrm_iatspayments_com', 'cc_civicrm: <pre>!cc</pre>', array('!cc' => print_r($cc_civicrm,TRUE)), WATCHDOG_NOTICE);
  } 

  //if (empty(count($cc_pending))) {
  //  return civicrm_api3_create_success(ts('No pending records found to process.'));
  //}
  /* get "recent" approvals and rejects from iats and match them up with my pending list, or one-offs, or iATS-managed series [depending on settings] */
  require_once("CRM/iATS/iATSService.php");
  // an array of methods => contribution status of the records retrieved
  $process_methods = array('cc_journal_csv' => 1,'cc_payment_box_journal_csv' => 1, 'cc_payment_box_reject_csv' => 4);
  /* initialize some values so I can report at the end */
  $error_count = 0;
  // count the number of each record from iats analysed, and the number of each kind found
  $processed = array_fill_keys(array_keys($process_methods),0);
  $found = array('recur' => 0, 'quick' => 0, 'new' => 0);
  // save all my api result messages as well
  $output = array();
  // watchdog('civicrm_iatspayments_com', 'pending: <pre>!pending</pre>', array('!pending' => print_r($iats_cc_recur_pending,TRUE)), WATCHDOG_NOTICE);
  foreach($payment_processors as $payment_processor) {
    watchdog('civicrm_iatspayments_com', 'pp: <pre>!pp</pre>', array('!pp' => print_r($payment_processor,TRUE)), WATCHDOG_NOTICE);
    /* get approvals from yesterday, approvals from previous days, and then rejections for this payment processor */
    $iats_service_params = array('type' => 'report', 'iats_domain' => parse_url($payment_processor['url_site'], PHP_URL_HOST)); // + $iats_service_params;
    /* the is_test below should always be 0, but I'm leaving it in, in case eventually we want to be verifying tests */
    $credentials = iATS_Service_Request::credentials($payment_processor['id'], $payment_processor['is_test']);
    foreach ($process_methods as $method => $contribution_status_id) {
      $iats_service_params['method'] = $method;
      $iats = new iATS_Service_Request($iats_service_params);
      switch($method) {
        case 'cc_journal_csv': // special case to get today's transactions, so we're as real-time as we can be
          $request = array(
            'date' => date('Y-m-d').'T23:59:59+00:00',
            'customerIPAddress' => (function_exists('ip_address') ? ip_address() : $_SERVER['REMOTE_ADDR']),
          );
          break;
        default: // box journals (approvals and rejections) only go up to the end of yesterday
          $request = array(
            'startIndex' => 0,
            'endIndex' => 100,
            'fromDate' => date('Y-m-d',strtotime('-'.IATS_CC_VERIFY_DAYS.' days')).'T00:00:00+00:00',
            'toDate' => date('Y-m-d',strtotime('-1 day')).'T23:59:59+00:00',
            'customerIPAddress' => (function_exists('ip_address') ? ip_address() : $_SERVER['REMOTE_ADDR']),
          );
          break;
      }
      // make the soap request, should return a csv file
      $response = $iats->request($credentials,$request);
      // watchdog('civicrm_iatspayments_com', 'response: <pre>!response</pre>', array('!trans' => print_r($response,TRUE)), WATCHDOG_NOTICE);
      $transactions = $iats->getCSV($response, $method);
      $processed[$method]+= count($transactions);
      watchdog('civicrm_iatspayments_com', 'transactions: <pre>!trans</pre>', array('!trans' => print_r($transactions,TRUE)), WATCHDOG_NOTICE);
      $transactions = array();
      foreach($transactions as $transaction_id => $transaction) {
        $trxn_id = $transaction_id.':'.time();
        $is_quick = ('quick client' != strtolower($transaction->customer_code)) && !empty($transaction->customer_code);
        $customer_code = $is_quick ? 'quick' : $transaction->customer_code;
        // if I'm looking at a contribution already known to CiviCRM ...
        if (!empty($cc_civicrm[$customer_code][$transaction->invoice])) {
          /* update the contribution status and existing matching contribution */
          /* todo: additional sanity testing? e.g. date? */
          $contribution = $cc_civicrm[$customer_code][$transaction->invoice];
          // I only care if the status is wrong, I'm not going to worry about anything else
          if ($contribution_status_id != $contribution['contribution_status_id']) {
            // modifying a contribution status to complete or failed needs some extra bookkeeping
            // note that I'm updating the timestamp portion of the transaction id here, since this might be useful at some point
            // should I update the receive date to when it was actually received? Would that confuse membership dates?
            if (1 == $contribution_status_id) {
              $complete = array('version' => 3, 'id' => $contribution['id'], 'trxn_id' => $transaction_id.':'.time(), 'receive_date' => $contribution['receive_date']);
              if ($is_recur) {
                $complete['is_email_receipt'] = $receipt_recurring; /* use my saved setting for recurring completions */
              }
              try {
                $contributionResult = civicrm_api3('contribution', 'completetransaction', $complete);
              }
              catch (Exception $e) {
                // Don't throw an exception here, I want to continue
                $output[] = ts('Unexpected api.completetransaction error for contribution id %1: %2',
                  array(
                    1 => $contribution['id'],
                    2 => $e->getMessage(),
                  )
                );
              }
            }
            elseif (4 == $contribution_status_id) { // mark as failed
              civicrm_api3('contribution','setvalue', array('id' => $contribution['id'], 'value' => $contribution_status_id, 'field' => 'contribution_status_id'));
            }
            civicrm_api3('contribution','setvalue', array('id' => $contribution['id'], 'value' => $trxn_id, 'field' => 'trxn_id'));
            // always log these requests in my custom civicrm table for auditing type purposes
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
        }
        // otherwise, if it's a recurring contribution from a known iATS series, then it might have been processed by iATS
        elseif (!$is_quick) {
          // it's a (possibly) new recurring contribution triggered from iATS
          if (!isset($cc_civicrm[$customer_code])) {
            // TODO: what to do with new or uknown customer codes? Currently ignored
          }
          else { // save my contribution in civicrm
            $cr = NULL;
            foreach($contribution_recur as $v) {
              if ($v->customer_code = $customer_code) {
                $cr = $v;
                break;
              }
            }
            $contribution = array(
              'version'        => 3,
              'contact_id'       => $cr['contact_id'],
              'receive_date'       => date('c',$transaction->receive_date),
              'total_amount'       => $transaction->amount,
              'payment_instrument_id'  => $cr->payment_instrument_id,
              'contribution_recur_id'  => $cr->id,
              'trxn_id'        => $trxn_id,
              'invoice_id'       => md5(uniqid(rand(), TRUE)), // whoa, this will be a problem ...
              'source'         => 'iATS Invoice: '.$transaction->invoice,
              'contribution_status_id' => $contribution_status_id,
              'currency'  => $cr->currency, // test for match?
              'payment_processor'   => $cr->payment_processor_id,
              'financial_type_id' => $cr->financial_type_id,
              'is_test'        => 0,
            );
            // use a template if available
            $contribution_template = array();
            if (empty($contribution['id'])) {
              // populate my contribution from a template if possible
              $contribution_template = _iats_civicrm_getContributionTemplate(array('contribution_recur_id' => $contribution_recur['id'], 'total_amount' => $transation->amount));
              $get_from_template = array('contribution_campaign_id','amount_level');
              foreach($get_from_template as $field) {
                if (isset($contribution_template[$field])) {
                  $contribution[$field] = $contribution_template[$field];
                }
              }
              if (!empty($contribution_template['line_items'])) {
                $contribution['skipLineItem'] = 1;
                $contribution[ 'api.line_item.create'] = $contribution_template['line_items'];
              }
            }
            if ($contribution_status_id == 1) {
              // create or update as pending and then complete 
              $contribution['contribution_status_id'] = 2;
              $result = civicrm_api('contribution', 'create', $contribution);
              $complete = array('version' => 3, 'id' => $result['id'], 'trxn_id' => $trxn_id, 'receive_date' => $contribution['receive_date']);
              $complete['is_email_receipt'] = $receipt_recurring; /* send according to my configuration */
              try {
                $contributionResult = civicrm_api('contribution', 'completetransaction', $complete);
                // restore my source field that ipn irritatingly overwrites, and make sure that the trxn_id is set also
                civicrm_api('contribution','setvalue', array('version' => 3, 'id' => $contribution['id'], 'value' => $contribution['source'], 'field' => 'source'));
                civicrm_api('contribution','setvalue', array('version' => 3, 'id' => $contribution['id'], 'value' => $trxn_id, 'field' => 'trxn_id'));
              }
              catch (Exception $e) {
                throw new API_Exception('Failed to complete transaction: ' . $e->getMessage() . "\n" . $e->getTraceAsString()); 
              }
            }
            else {
              // create or update 
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
        // elseif ($setting
        // if one of the above was true and I've got a new or confirmed contribution:
        // so log it as an activity for administrative reference
        if (!empty($contribution)) {
          $subject_string = empty($contribution['id']) ? 'Found new iATS Payments CC contribution for contact id %3' : '%1 iATS Payments CC contribution id %2 for contact id %3';
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
  $message .= '<br />'. ts('Processed %1 approvals from today and past 4 days, %2 approval and %3 rejection records from the previous '.IATS_CC_VERIFY_DAYS.' days.',
    array(
      1 => $processed['cc_journal_csv'],
      2 => $processed['cc_payment_box_journal_csv'],
      3 => $processed['cc_payment_box_reject_csv'],
    )
  );
  // If errors ..
  if ($error_count) {
    return civicrm_api3_create_error($message .'</br />'. implode('<br />', $output));
  }
  // If no errors and some records processed ..
  if (array_sum($processed) > 0) {
    if (count($cc_civicrm) > 0) {
      $message .= '<br />'. ts('For %1 pending CC contributions, %2 non-recuring and %3 recurring contribution results applied.',
        array(
          1 => count($cc_civicrm),
          2 => $found['quick'],
          3 => $found['recur'],
        )
      );
    }
    if (count($cc_civicrm) > 0) {
      $message .= '<br />'. ts('For %1 recurring UK direct debit contribution series, %2 new contributions found.',
        array(
          1 => count($cc_civicrm),
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
