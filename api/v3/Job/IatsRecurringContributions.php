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

  // $config = &CRM_Core_Config::singleton();
  // $debug  = false;
  // do my calculations based on yyyymmddhhmmss representation of the time
  // not sure about time-zone issues, may this next line tries to fix that?
  $dtCurrentDay    = date("Ymd", mktime(0, 0, 0, date("m") , date("d") , date("Y")));
  $dtCurrentDayStart = $dtCurrentDay."000000";
  $dtCurrentDayEnd   = $dtCurrentDay."235959";
  $expiry_limit = date('ym');
  // Select the recurring payments for iATSService, where current date is equal to next scheduled date
  $select = 'SELECT cr.*, icc.customer_code, icc.expiry as icc_expiry, icc.cid as icc_contact_id, pp.class_name as pp_class_name, pp.url_site as url_site FROM civicrm_contribution_recur cr 
      INNER JOIN civicrm_payment_processor pp ON cr.payment_processor_id = pp.id
      INNER JOIN civicrm_iats_customer_codes icc ON cr.id = icc.recur_id
      WHERE 
        cr.contribution_status_id = 1
        AND pp.class_name LIKE %1
        AND pp.is_test = 0
        AND (cr.end_date IS NULL OR cr.end_date > NOW())';
  $args = array(
    1 => array('Payment_iATSService%', 'String'),
  );
  if (!empty($params['recur_id'])) { // can be called to execute a specific recurring contribution id
    $select .= ' AND icc.recur_id = %2';
    $args[2] = array($params['recur_id'], 'Int');
  }
  else { // if (!empty($params['scheduled'])) { 
    //normally, process all recurring contributions due today
    $select .= ' AND cr.'.IATS_CIVICRM_NSCD_FID.' <= %2';
    $args[2] = array($dtCurrentDayEnd, 'String');
    // ' AND cr.next_sched_contribution >= %2 
    // $args[2] = array($dtCurrentDayStart, 'String');
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
    // $sourceURL = CRM_Utils_System::url('civicrm/contact/view/contributionrecur', 'reset=1&id='. $dao->id .'&cid='. $dao->contact_id .'&context=contribution');
    $subtype = substr($dao->pp_class_name,19);
    $source = "iATS Payments $subtype Recurring Contribution (id=$contribution_recur_id)"; 
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
      if (($dao->icc_expiry != '0000') && ($dao->icc_expiry < $expiry_limit)) {
        $errors[] = ts('Recur id %1 is has an expired cc for the customer code.', array(1 => $contribution_recur_id));
      }
    }
    if (count($errors)) {
      $source .= ' Errors: '.implode(' ',$errors);
    }
    $contribution = array(
      'version'        => 3,
      'contact_id'       => $contact_id,
      'receive_date'       => $receive_date,
      'total_amount'       => $total_amount,
      'payment_instrument_id'  => $dao->payment_instrument_id,
      'contribution_recur_id'  => $contribution_recur_id,
      'trxn_id'        => $hash, /* placeholder: just something unique that can also be seen as the same as invoice_id */
      'invoice_id'       => $hash,
      'source'         => $source,
      'contribution_status_id' => 4, /* default is failed, unless we actually take the money successfully */
      'currency'  => $dao->currency,
      'payment_processor'   => $dao->payment_processor_id,
    );
    if (isset($dao->contribution_type_id)) {  // 4.2
       $contribution['contribution_type_id'] = $dao->contribution_type_id;
    }
    else { // 4.3+
       $contribution['financial_type_id'] = $dao->financial_type_id;
    }
    if (count($errors)) {
      ++$error_count;
      ++$counter;
      /* create the failed contribution record */
      $result = civicrm_api('contribution', 'create', $contribution);
      if ($result['is_error']) {
        $errors[] = $result['error_message'];
      }
      continue;
    }
    else { 
      // so far so, good ... now try to trigger the payment on iATS
      require_once("CRM/Iats/iATSService.php");
      switch($subtype) {
        case 'ACHEFT':
          $method = 'acheft_with_customer_code';
          break;
        default:
          $method = 'cc_with_customer_code';
          break;
      }
      $iats_service_params = array('method' => $method, 'type' => 'process', 'iats_domain' => parse_url($dao->url_site, PHP_URL_HOST));
      $iats = new iATS_Service_Request($iats_service_params);
      // build the request array
      $request = array(
        'customerCode' => $dao->customer_code,
        'invoiceNum' => $hash,
        'total' => $total_amount,
      );
      $request['customerIPAddress'] = (function_exists('ip_address') ? ip_address() : $_SERVER['REMOTE_ADDR']);

      $credentials = $iats->credentials($dao->payment_processor_id);
      // make the soap request
      $response = $iats->request($credentials,$request);
      // process the soap response into a readable result
      $result = $iats->result($response);
      if (empty($result['status'])) {
        /* create the contribution record in civicrm with the failed status */
        $contribution['source'] .= ' '.$result['reasonMessage'];
        civicrm_api('contribution', 'create', $contribution);
        $output[] = ts('Failed to process recurring contribution id %1: ', array(1 => $contribution_recur_id)).$result['reasonMessage'];
      } 
      else {
        /* success, create the contribution record with corrected status + trxn_id */
        $contribution['trxn_id'] = $result['remote_id'] . ':' . time();
        $contribution['contribution_status_id'] = 1; 
        civicrm_api('contribution','create', $contribution);
        $output[] = ts('Successfully processed recurring contribution id %1: ', array(1 => $contribution_recur_id)).$result['auth_result'];
      }
    }

    //$mem_end_date = $member_dao->end_date;
    // $temp_date = strtotime($dao->next_sched_contribution);
    /* calculate the next collection date. You could use the previous line instead if you wanted to catch up with missing contributions instead of just moving forward from the present */
    $temp_date = time();
    $next_collectionDate = strtotime ("+$dao->frequency_interval $dao->frequency_unit", $temp_date);
    $next_collectionDate = date('YmdHis', $next_collectionDate);

    CRM_Core_DAO::executeQuery("
      UPDATE civicrm_contribution_recur 
         SET ".IATS_CIVICRM_NSCD_FID." = %1 
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
        'subject'       => "Attempted iATS Payments $subtype Recurring Contribution for " . $total_amount,
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
