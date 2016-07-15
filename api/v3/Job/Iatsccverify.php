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
  $spec['reverify'] = array(
    'name' => 'reverify',
    'title' => 'Reverify contributions',
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
  /* We'll make sure they are unique from iATS point of view (i.e. distinct agent codes = usern_name) */
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
  $reverify = empty($params['reverify']) ? FALSE : TRUE;
  unset($params['reverify']);
  // Only do a full scan if I'm not verifying a specific contribution
  // TODO: handling these one-offs at all ...
  $scan_all = (!$recur_id && !$contribution_id && !$invoice_id);
  // $iats_service_params = $params;  // what's left is assumed as parameters to pass the iats service object
  // get the settings: TODO allow more detailed configuration of which transactions to import
  $settings = CRM_Core_BAO_Setting::getItem('iATS Payments Extension', 'iats_settings');
  foreach(array('quick', 'recur', 'series') as $setting) {
    $import[$setting] = empty($settings['import_'.$setting]) ? 0 : 1;
  }
  $receipt_recurring = empty($settings['receipt_recurring']) ? 0 : 1;
  $domemberships = empty($params['ignoremembership']);
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
      $contribution_recur[$dao->id] = get_object_vars($dao);
      $cc_civicrm[$dao->customer_code] = array();
    }
    // watchdog('civicrm_iatspayments_com', 'recur ids <pre>!cr</pre>', array('!cr' => print_r($contribution_recur,TRUE)), WATCHDOG_NOTICE);
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
    // watchdog('civicrm_iatspayments_com', 'cc_civicrm select: <pre>!sql</pre>, '.$args[1][0], array('!sql' => print_r($select,TRUE)), WATCHDOG_NOTICE);
    $dao = CRM_Core_DAO::executeQuery($select,$args);
    while ($dao->fetch()) {
      $cr = $contribution_recur[$dao->contribution_recur_id]; 
      // I want to key on my invoice_id that I can match up with data from iATS
      if (0 == strpos($dao->trxn_id,':')) {
        $key = $dao->invoice_id;
      }
      else {
        list($key,$time) = explode(':',$dao->trxn_id,2);
      }
      // watchdog('civicrm_iatspayments_com', "key: $key, time: $time, trxn: ".$dao->trxn_id);
      $cc_civicrm[$cr['customer_code']][$key] = array('id' => $dao->id, 'contact_id' => $dao->contact_id, 'contribution_recur_id' => $dao->contribution_recur_id, 'contribution_status_id' => $dao->contribution_status_id, 'invoice_id' => $dao->invoice_id);
    }
    // get all the recent non-recurring credit card contributions that might be from iats cc
    $select = 'SELECT id, contact_id, contribution_status_id, trxn_id, invoice_id, receive_date
      FROM civicrm_contribution 
      WHERE
        receive_date > %1
        AND ISNULL(contribution_recur_id ) AND payment_instrument_id = 1';
    $args = array(
      1 => array(date('c',strtotime('-'.$civicrm_verify_days.' days')), 'String')
    );
    $dao = CRM_Core_DAO::executeQuery($select,$args);
    while ($dao->fetch()) {
      // I want to key on my iats transaction that I can match up with data from iATS
      if (0 == strpos($dao->trxn_id,':')) {
        $key = $dao->invoice_id;
      }
      else {
        list($key,$time) = explode(':',$dao->trxn_id,2);
      }
      // watchdog('civicrm_iatspayments_com', "key: $key, time: $time, trxn: ".$dao->trxn_id);
      $cc_civicrm['quick'][$key] = array('id' => $dao->id, 'contact_id' => $dao->contact_id, 'contribution_recur_id' => 0, 'contribution_status_id' => $dao->contribution_status_id, 'invoice_id' => $dao->invoice_id);
    }
  } 
  $verified = array();
  if (!$reverify) { // get a list of all my recent verifications so I don't have to look them up again.
    $select = 'SELECT *
      FROM civicrm_iats_verify
      WHERE
        verify_datetime > %1
        AND NOT(ISNULL(auth_result))'
    $args = array(
      1 => array(date('c',strtotime('-'.$civicrm_verify_days.' days')), 'String')
    );
    $dao = CRM_Core_DAO::executeQuery($select,$args);
    while ($dao->fetch()) {
      $verified[$dao->auth_result] = array('contribution_id' => $dao->contribution_id, 'contribution_status_id' => $dao->contribution_status_id);
    }
    // watchdog('civicrm_iatspayments_com', 'cc_civicrm: <pre>!cc</pre>', array('!cc' => print_r($cc_civicrm,TRUE)), WATCHDOG_NOTICE);
    // watchdog('civicrm_iatspayments_com', 'contribution_recur: <pre>!cc</pre>', array('!cc' => print_r($contribution_recur,TRUE)), WATCHDOG_NOTICE);
  }
  //if (empty(count($cc_pending))) {
  //  return civicrm_api3_create_success(ts('No pending records found to process.'));
  //}
  /* get "recent" approvals and rejects from iats and match them up with my pending list, or one-offs, or iATS-managed series [depending on settings] */
  require_once("CRM/iATS/iATSService.php");
  // an array of methods => contribution status of the records retrieved
  $process_methods = array('cc_journal_csv' => 1,'cc_payment_box_journal_csv' => 1, 'cc_payment_box_reject_csv' => 4);
  // $process_methods = array('cc_journal_csv' => 1);
  /* initialize some values so I can report at the end */
  $error_count = 0;
  // count the number of records from each iats account analysed, and the number of each kind found ('action')
  $processed = array(); // array_fill_keys(array_keys($process_methods),0);
  // $action = array(); // array('ignore' => array(), 'update' => array(), 'match' => array(), 'quick' => array(), 'recur' => array(), 'series' => array());
  // save all my api result messages as well
  $output = array();
  // watchdog('civicrm_iatspayments_com', 'pending: <pre>!pending</pre>', array('!pending' => print_r($iats_cc_recur_pending,TRUE)), WATCHDOG_NOTICE);
  foreach($payment_processors as $payment_processor) {
    /* if I've already processed this account via a different payment processor (e.g. swipe), then don't do it again */
    $user_name = $payment_processor['user_name'];
    if (isset($processed[$user_name])) continue;
    $processed[$user_name] = array();
    foreach(array_keys($process_methods) as $method) {
      $processed[$user_name][$method] = array('ignore' => array(), 'update' => array(), 'match' => array(), 'quick' => array(), 'recur' => array(), 'series' => array());
    }
    $subtype = substr($payment_processor['class_name'],19);
    // watchdog('civicrm_iatspayments_com', 'pp: <pre>!pp</pre>', array('!pp' => print_r($payment_processor,TRUE)), WATCHDOG_NOTICE);
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
            'endIndex' => 1000,
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
      // watchdog('civicrm_iatspayments_com', 'transactions: <pre>!trans</pre>', array('!trans' => print_r($transactions,TRUE)), WATCHDOG_NOTICE);
      foreach($transactions as $transaction_id => $transaction) {
        if (isset($verified[$transaction])) {
          $processed[$user_name][$method]['ignored'][] = $transaction_id;
          continue;
        }
    
        $cr = array(); // matching corresponding recurring contribution record
        $trxn_id = $transaction_id.':'.time();
        $is_quick = ('quick client' == strtolower($transaction->customer_code)) || empty($transaction->customer_code);
        $customer_code = $is_quick ? 'quick' : $transaction->customer_code;
        // if I'm looking at a contribution already known to CiviCRM ...
        // watchdog('civicrm_iatspayments_com', 'ccc: !cc, inv: !inv', array('!cc' => $customer_code, '!inv' => $transaction->invoice), WATCHDOG_NOTICE);
        if (!empty($cc_civicrm[$customer_code][$transaction_id])) {
          /* update the contribution status and existing matching contribution */
          /* todo: additional sanity testing? e.g. date? */
          $contribution = $cc_civicrm[$customer_code][$transaction_id];
          // I only care if the status is wrong, I'm not going to worry about anything else
          if ($contribution_status_id == $contribution['contribution_status_id']) { // just count it
            // watchdog('civicrm_iatspayments_com', 'ignore matched existing transaction: <pre>!data</pre>', array('!data' => print_r($contribution,TRUE)), WATCHDOG_NOTICE);
            $contribution = array();
            $processed[$user_name][$method]['match'][] = $transaction_id;
          }
          else {
            $processed[$user_name][$method]['update'][] = $transaction_id;
            // watchdog('civicrm_iatspayments_com', 'change status of id !id: !from to !to', array('!id' => $contribution['id'], '!from' => $contribution['contribution_status_id'], '!to' => $contribution_status_id));
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
                // and just manually mark as complete, in spite of the bookkeeping issues
                civicrm_api3('contribution','setvalue', array('id' => $contribution['id'], 'value' => $contribution_status_id, 'field' => 'contribution_status_id'));
              }
            }
            elseif (4 == $contribution_status_id) { // mark as failed
              // TODO - there are likely some accounting changes involved here that need to be considered
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
          } // if contribution status changed
        }
        // otherwise, if it's a recurring contribution from a known iATS series, then it might have been from a iATS-managed recurring series
        elseif (!$is_quick) {
          // it's a (possibly) new recurring contribution triggered from iATS
          if (!isset($cc_civicrm[$customer_code])) {
            $processed[$user_name][$method]['series'][] = $transaction_id;
            if ($import['series']) { 
              // TODO: create a new series
            }
          }
          // only deal with this if I aleady had this customer code on file or if I've created one above
          if (isset($cc_civicrm[$customer_code])) { 
            $processed[$user_name][$method]['recur'][] = $transaction_id;
            if ($import['recur']) {
              // save my contribution in civicrm
              // first find my matching recurring contribution object
              // this will be easier cleaner when I'm storing my customer codes where they should be
              // watchdog('civicrm_iatspayments_com', 'new transaction: <pre>!data</pre>', array('!data' => print_r($transaction,TRUE)), WATCHDOG_NOTICE);
              foreach($contribution_recur as $v) {
                if ($v['customer_code'] == $customer_code) {
                  $cr = $v;
                  break;
                }
              }
              // watchdog('civicrm_iatspayments_com', 'matching cr: <pre>!data</pre>', array('!data' => print_r($cr,TRUE)), WATCHDOG_NOTICE);
              // I'll use the iATS invoice id if I trust it, othewise we'll need to be careful about handling finding them again
              $invoice_id = (strlen($transaction->invoice) == 32) ? $transaction->invoice : md5(uniqid(rand(), TRUE));
              $contribution = array(
                'version'        => 3,
                'contact_id'       => $cr['contact_id'],
                'receive_date'       => date('c',$transaction->receive_date), // TODO - deal with timezone
                'total_amount'       => $transaction->amount,
                'payment_instrument_id'  => $cr['payment_instrument_id'],
                'contribution_recur_id'  => $cr['id'],
                'trxn_id'        => $trxn_id,
                'invoice_id'       => $invoice_id,
                'source'         => 'iATS Invoice: '.$transaction->invoice, 
                'contribution_status_id' => 2,
                'currency'  => $cr['currency'], // test for match?
                'payment_processor'   => $cr['payment_processor_id'],
                'financial_type_id' => $cr['financial_type_id'],
                'is_test'        => 0,
              );
              // use a template if available
              $contribution_template = array();
              if (!empty($cr['id'])) {
                // populate my contribution from a template if possible
                $contribution_template = _iats_civicrm_getContributionTemplate(array('contribution_recur_id' => $cr['id'], 'total_amount' => $transaction->amount));
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
              $options = array(
                'is_email_receipt' => $receipt_recurring,
                'customer_code' => $customer_code,
                'subtype' => $subtype,
              );
              // if our template contribution is a membership payment, make this one also
              if ($domemberships && !empty($contribution_template['contribution_id'])) {
                try {
                  $membership_payment = civicrm_api('MembershipPayment','getsingle', array('version' => 3, 'contribution_id' => $contribution_template['contribution_id']));
                  if (!empty($membership_payment['membership_id'])) {
                    $options['membership_id'] = $membership_payment['membership_id'];
                  }
                }
                catch (Exception $e) {
                  // ignore, if will fail correctly if there is no membership payment
                }
              }
              // use the existing transaction result to process, as per
              // update the contribution to failed, leave as pending for server failure, complete the transaction
              $transaction_result = array('auth_result' => $transaction->data['Result'], 'remote_id' => $transaction->data['Transaction ID'], 'status' => '');
              // TODO should the status be redundant?
              $transaction_result['status'] = (iATS_Service_Request::iATS_TXN_OK == substr($transaction_result['auth_result'],0,2)) ? 1 : 0;
              $result = _iats_process_contribution_payment($contribution,$options, $transaction_result);
              if (0 && $email_failure_report && $contribution['iats_reject_code']) {
                $failure_report_text .= "\n $result ";
              }
              $output[] = $result;
              // $found['new']++;
            }
          }
        }
        else { // it's a new unrecognized quick contribution - i.e. via some other system
          $processed[$user_name][$method]['action'][] = $transaction_id;
          if ($import['quick']) { 
            // find and/or create the contact
            // watchdog('civicrm_iatspayments_com', 'ignore new transaction: <pre>!found</pre>', array('!found' => print_r($transaction->data,TRUE)), WATCHDOG_NOTICE);
            /* 
            $contribution = array(
              'version'        => 3,
              'contact_id'       => $cr['contact_id'],
              'receive_date'       => date('c',$transaction->receive_date),
              'total_amount'       => $transaction->amount,
              'payment_instrument_id'  => $cr['payment_instrument_id'],
              'contribution_recur_id'  => $cr['id'],
              'trxn_id'        => $trxn_id,
              'invoice_id'       => md5(uniqid(rand(), TRUE)), // whoa, this will be a problem ...
              'source'         => 'iATS Invoice: '.$transaction->invoice,
              'contribution_status_id' => $contribution_status_id,
              'currency'  => $cr['currency'], // test for match?
              'payment_processor'   => $cr['payment_processor_id'],
              'financial_type_id' => $cr['financial_type_id'],
              'is_test'        => 0,
            ); */
          }
        }
        // always log the verification to my cutom civicrm table so I don't redo it
        // watchdog('civicrm_iatspayments_com', 'contribution: <pre>!contribution</pre>', array('!contribution' => print_r($query_params,TRUE)), WATCHDOG_NOTICE);
        try {
          $params = array('transaction_id' => $transaction_id, 'customer_code' => $customer_code, 'contribution' => $contribution);
          civicrm_api3('IatsPayments', 'verifylog', $params);
        }
        catch (CiviCRM_API3_Exception $e) {
          // ignore
        }
        if (!empty($contribution)) {
          $subject_string = empty($contribution['id']) ? 'Found new iATS Payments CC contribution for contact id %3' : '%1 iATS Payments CC contribution id %2 for contact id %3';
          $subject = ts($subject_string,
              array(
                1 => (($contribution_status_id == 4) ? ts('Cancelled') : ts('Verified')),
                2 => ((empty($contribution['id'])) ? '' : $contribution['id']),
                3 => $contribution['contact_id'],
              ));
          try {
            $result = civicrm_api3('activity', 'create', array(
              'activity_type_id'  => 6, // 6 = contribution
              'source_contact_id'   => $contribution['contact_id'],
              'assignee_contact_id' => $contribution['contact_id'],
              'subject'       => $subject,
              'status_id'       => 2, // TODO: what should this be?
              'activity_date_time'  => date("YmdHis"),
            ));
            $output[] = $subject;
          }
          catch (CiviCRM_API3_Exception $e) {
            ++$error_count;
            // ignore
          }
        }
        // otherwise ignore it
      }
    }
  }
  watchdog('civicrm_iatspayments_com', 'found: <pre>!found</pre>', array('!found' => print_r($processed,TRUE)), WATCHDOG_NOTICE);
  $message = '<br />'. ts('Completed with %1 errors.',
    array(
      1 => $error_count,
    )
  );
  foreach($processed as $user_name => $p) {
    $message .= '<br />'. ts('For account %4, processed %1 approvals from today, and %2 approval and %3 rejection records from the previous '.IATS_CC_VERIFY_DAYS.' days.',
    array(
      1 => $p['cc_journal_csv'],
      2 => $p['cc_payment_box_journal_csv'],
      3 => $p['cc_payment_box_reject_csv'],
      4 => $user_name,
    )
  );
  // If errors ..
  if ($error_count) {
    return civicrm_api3_create_error($message .'</br />'. implode('<br />', $output));
  }
  // If no errors and some records processed ..
  if (array_sum($processed) > 0) {
    foreach($action as $user_name => $a) {
      $message .= '<br />'. ts('For account %6, %1 previous verified transactions ignored, %2 unchanged matches found, %3 contributions matched and updated, %4 new recurring contributions found, %5 new recurring sequences identified, %6 new one-time contributions found.',
        array(
          1 => count($action['ignore']),
          2 => count($action['match']),
          3 => count($action['update']),
          4 => count($action['recur']),
          5 => count($action['series']),
          5 => count($action['series']),
          6 => count($action['quick']),
          7 => $user_name,
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
