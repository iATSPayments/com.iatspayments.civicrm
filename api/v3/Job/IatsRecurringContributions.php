<?php

/**
 * Job.iATSRecurringContributions API specification
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRM/API+Architecture+Standards
 */
function _civicrm_api3_job_iatsrecurringcontributions_spec(&$spec) {
  $spec['recur_id'] = array(
    'name' => 'recur_id',
    'title' => 'Recurring payment id',
    'type' => 1,
  );
  $spec['scheduled'] = array(
    'name' => 'scheduled',
    'title' => 'Only scheduled contributions.',
    'type' => 1,
  );
}

/**
 * Job.iATSRecurringContributions API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_job_iatsrecurringcontributions($params) {
  // TODO: what kind of extra security do we want or need here to prevent it from being triggered inappropriately? Or does it matter?

  $config = &CRM_Core_Config::singleton();
  $debug  = false;
  // do my calculations based on yyyymmddhhmmss representation of the time
  // not sure about time-zone issues, may this next line tries to fix that?
  $dtCurrentDay    = date("Ymd", mktime(0, 0, 0, date("m") , date("d") , date("Y")));
  $dtCurrentDayStart = $dtCurrentDay."000000";
  $dtCurrentDayEnd   = $dtCurrentDay."235959";
  $expiry_limit = date('ym');
  // Select the recurring payments for iATSService, where current date is equal to next scheduled date
  $select = 'SELECT cr.*, icc.customer_code, icc.expiry as icc_expiry, icc.cid as icc_contact_id FROM civicrm_contribution_recur cr 
      INNER JOIN civicrm_payment_processor pp ON cr.payment_processor_id = pp.id
      INNER JOIN civicrm_iats_customer_codes icc ON cr.id = icc.recur_id
      WHERE 
        cr.contribution_status_id = 1
        AND pp.class_name = %1
        AND pp.is_test = 0
        AND (cr.end_date IS NULL OR cr.end_date > NOW())';
  $args = array(
    1 => array('Payment_iATSService', 'String'),
  );
  if (!empty($params['recur_id'])) { // can be called to execute a specific recurring contribution id
    $select .= ' AND icc.recur_id = %2';
    $args[2] = array($params['recur_id'], 'Int');
  }
  else { // if (!empty($params['scheduled'])) { 
    //normally, process all recurring contributions due today
    $select .= ' AND cr.next_sched_contribution >= %2 
        AND cr.next_sched_contribution <= %3';
    $args[2] = array($dtCurrentDayStart, 'String');
    $args[3] = array($dtCurrentDayEnd, 'String');
  }
  // NOTE: if called with neither parameter - all recurring payments will be invoked!
  $dao = CRM_Core_DAO::executeQuery($select,$args);
  $counter = 0;
  $error_count  = 0;
  $output  = array();

  while ($dao->fetch()) {

    // Strategy: create the contribution record with status = 2 (= pending), try the payment, and update the status to 1 if successful
    $contact_id = $dao->contact_id;
    $total_amount = $dao->amount;
    $hash = md5(uniqid(rand(), true));
    $contribution_recur_id    = $dao->id;
    $source = "iATS Payments Recurring Contribution";
    $receive_date = date("YmdHis"); // i.e. now
    // check if we already have an error
    $errors = array();
    if (empty($dao->customer_code)) {
      $errors[] = ts('Recur id %1 is missing a customer code.', array(1 => $contribution_recur_id));
    } 
    else {
      if ($dao->contact_id != $dao->icc_contact_id) {
        $errors[] = ts('Recur id %1 is has a mismatched contact id for the customer code.', array(1 => $contribution_recur_id));
      }
      if ($dao->icc_expiry < $expiry_limit) {
        $errors[] = ts('Recur id %1 is has an expired cc for the customer code.', array(1 => $contribution_recur_id));
      }
    }
    if (count($errors)) {
      $source .= ' Errors: '.implode(' ',$error);
    }
    $contribution = array(
      'version'        => 3,
      'contact_id'       => $contact_id,
      'receive_date'       => $receive_date,
      'total_amount'       => $total_amount,
      'payment_instrument_id'  => $dao->payment_instrument_id,
      'contribution_recur_id'  => $contribution_recur_id,
      'trxn_id'        => $hash,
      'invoice_id'       => $hash,
      'source'         => $source,
      'contribution_status_id' => 2,
      'currency'  => $dao->currency,
      //'contribution_page_id'   => $entity_id
    );
    if (isset($dao->contribution_type_id)) {
       $contribution['contribution_type_id'] = $dao->contribution_type_id;
    }
    else {
       $contribution['financial_type_id'] = $dao->financial_type_id;
    }
    $result = civicrm_api('contribution', 'create', $contribution);
    if ($result['is_error']) {
      $errors[] = $result['error_message'];
    }
    if (count($errors)) {
      ++$error_count;
      ++$counter;
      continue;
    }
    else { 
      // now try to trigger the payment, update the status on success, or provide error message on failure
      $contribution = reset($result['values']);
      $contribution_id = $contribution['id'];
      $output[] = ts('Created contribution record for contact id %1, recurring contribution id %2', array(1 => $contact_id, 2 => $contribution_recur_id));
      require_once("CRM/iATS/iATSService.php");
      $method = 'cc_with_customer_code';
      // to add debugging info in the drupal log, assign 1 to log['all'] below
      $iats = new iATS_Service_Request($method,array('log' => array('all' => 1),'trace' => TRUE));
      // build the request array
      $request = array(
        'customerCode' => $dao->customer_code,
  /*      'cvv2' => $dao->icc_cvv2, */
        'invoiceNum' => $hash,
        'total' => $total_amount,
      );
      $request['customerIPAddress'] = (function_exists('ip_address') ? ip_address() : $_SERVER['REMOTE_ADDR']);

      $credentials = _civicrm_api3_job_iatsrecurringcontributions_credentials($dao->payment_processor_id);
      // TODO: enable override of the default url in the request object
      // $url = $this->_paymentProcessor['url_site'];
      // make the soap request
      $response = $iats->request($credentials,$request);
      // process the soap response into a readable result
      $result = $iats->result($response);
      if (empty($result['status'])) {
        $output[] = ts('Failed to process recurring contribution id %1: ', array(1 => $contribution_recur_id)).$result['reasonMessage'];
      } 
      // success, update the contribution record
      civicrm_api('contribution','create',array('version' => 3, 'id' => $contribution_id, 'trxn_id' => $result['auth_result'],'contribution_status_id' => 1));
    }

    //$mem_end_date = $member_dao->end_date;
    $temp_date = strtotime($dao->next_sched_contribution);

    $next_collectionDate = strtotime ("+$dao->frequency_interval $dao->frequency_unit", $temp_date);
    $next_collectionDate = date('YmdHis', $next_collectionDate);

    CRM_Core_DAO::executeQuery("
      UPDATE civicrm_contribution_recur 
         SET next_sched_contribution = %1 
       WHERE id = %2
    ", array(
         1 => array($next_collectionDate, 'String'),
         2 => array($dao->id, 'Integer')
       )
    );

    $result = civicrm_api('activity', 'create',
      array(
        'version'       => 3,
        'activity_type_id'  => 6,
        'source_contact_id'   => $contact_id,
        'assignee_contact_id' => $contact_id,
        'subject'       => "Attempted iATS Payments Recurring Contribution for " . $total_amount,
        'status_id'       => 2,
        'activity_date_time'  => date("YmdHis"),
      )
    );
    if ($result['is_error']) {
      $output[] = ts(
        'An error occurred while creating activity record for contact id %1: %2',
        array(
          1 => $contact_id,
          2 => $result['error_message']
        )
      );
      ++$error_count;
    } else {
      $output[] = ts('Created activity record for contact id %1', array(1 => $contact_id));

    }
    ++$counter;
  }

  // If errors ..
  if ($error_count) {
    return civicrm_api3_create_error(
      ts("Completed, but with %1 errors. %2 records processed.",
        array(
          1 => $error_count,
          2 => $counter
        )
      ) . "<br />" . implode("<br />", $output)
    );
  }
  // If no errors and records processed ..
  if ($counter) {
    return civicrm_api3_create_success(
      ts(
        '%1 contribution record(s) were processed.',
        array(
          1 => $counter
        )
      ) . "<br />" . implode("<br />", $output)
    );
  }
  // No records processed
  return civicrm_api3_create_success(ts('No contribution records were processed.'));

}

function _civicrm_api3_job_iatsrecurringcontributions_credentials($payment_processor_id) {
  static $credentials = array();
  if (empty($credentials[$payment_processor_id])) {
    $select = 'SELECT user_name, password FROM civicrm_payment_processor WHERE id = %1';
    $args = array(
      1 => array($payment_processor_id, 'Int'),
    );
    $dao = CRM_Core_DAO::executeQuery($select,$args);
    if ($dao->fetch()) {
      $cred = array(
        'agentCode' => $dao->user_name,
        'password' => $dao->password,
      );
      $credentials[$payment_processor_id] = $cred;
      return $cred;
    }
    return;
  }
  return $credentials[$payment_processor_id];
} 
