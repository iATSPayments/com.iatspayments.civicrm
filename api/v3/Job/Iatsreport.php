<?php

/**
 * Job.IatsReport API specification (optional)
 * 
 * Pull in the iATS transaction journal and save it in the corresponding table 
 * for local access for easier verification, auditing and reporting.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRM/API+Architecture+Standards
 */
function _civicrm_api3_job_iatsreport_spec(&$spec) {
  // no arguments
  // TODO: configure for a date range, report, etc.
}

/**
 * Job.IatsReport API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception

 * Fetch all recent transactions from iATS for the purposes of auditing (in separate jobs).
 * This addresses multiple needs:
 * 1. Pull recent contributions that went through but weren't reported to CiviCRM due to unexpected connection/code breakage.
 * 2. Pull recurring contributions managed by iATS
 * 3. Pull one-time contributions that did not go through CiviCRM
 * 4. Audit for remote changes in iATS.
 *
 */
function civicrm_api3_job_iatsreport($params) {

  /* get a list of all active/non-test iATS payment processors of any type, quit if there are none */
  /* We'll make sure they are unique from iATS point of view (i.e. distinct agent codes = username) */
  try {
    $result = civicrm_api3('PaymentProcessor', 'get', array(
      'sequential' => 1,
      'class_name' => array('LIKE' => 'Payment_iATSService%'),
      'is_active' => 1,
      'is_test' => 0,
    ));
  }
  catch (CiviCRM_API3_Exception $e) {
    throw new API_Exception('Unexpected error getting payment processors: ' . $e->getMessage()); //  . "\n" . $e->getTraceAsString()); 
  }
  if (empty($result['values'])) {
    return;
  }
  $payment_processors = array();
  foreach($result['values'] as $payment_processor) {
    $user_name = $payment_processor['user_name'];
    $type = $payment_processor['payment_type']; // 1 for cc, 2 for ach/eft
    $id = $payment_processor['id'];
    if (empty($payment_processors[$user_name])) {
      $payment_processors[$user_name] = array();
    }
    if (empty($payment_processors[$user_name][$type])) {
      $payment_processors[$user_name][$type] = array();
    }
    $payment_processors[$user_name][$type][$id] = $payment_processor;
  }
  CRM_Core_Error::debug_var('Payment Processors', $payment_processors);
  // get the settings: TODO allow more detailed configuration of which transactions to import?
  $settings = CRM_Core_BAO_Setting::getItem('iATS Payments Extension', 'iats_settings');
  foreach(array('quick', 'recur', 'series') as $setting) {
    $import[$setting] = empty($settings['import_'.$setting]) ? 0 : 1;
  }
  require_once("CRM/iATS/iATSService.php");
  // an array of types => methods => payment status of the records retrieved
  $process_methods = array(
    1 => array('cc_journal_csv' => 1,'cc_payment_box_journal_csv' => 1, 'cc_payment_box_reject_csv' => 4),
    2 => array('acheft_journal_csv' => 1, 'acheft_payment_box_journal_csv' => 1, 'acheft_payment_box_reject_csv' => 4)
  );
  /* initialize some values so I can report at the end */
  $error_count = 0;
  // count the number of records from each iats account analysed, and the number of each kind found ('action')
  $processed = array(); // array_fill_keys(array_keys($process_methods),0);
  // save all my api result messages as well
  $output = array();
  // watchdog('civicrm_iatspayments_com', 'pending: <pre>!pending</pre>', array('!pending' => print_r($iats_cc_recur_pending,TRUE)), WATCHDOG_NOTICE);
  foreach($payment_processors as $user_name => $payment_processors_per_user) {
    foreach ($payment_processors_per_user as $type => $payment_processors_per_user_type) {
      // we might have multiple payment processors by type e.g. SWIPE or separate codes for 
      // one-time and recurring contributions, I only want to process once per user_name + type
      $payment_processor = reset($payment_processors_per_user_type);
      $process_methods_per_type = $process_methods[$type];
      $iats_service_params = array('type' => 'report', 'iats_domain' => parse_url($payment_processor['url_site'], PHP_URL_HOST)); // + $iats_service_params;
      /* the is_test below should always be 0, but I'm leaving it in, in case eventually we want to be verifying tests */
      $credentials = iATS_Service_Request::credentials($payment_processor['id'], $payment_processor['is_test']);
      foreach($process_methods_per_type as $method => $payment_status_id) {
        // initialize my counts
        $processed[$type][$method] = array('ignore' => array(), 'update' => array(), 'match' => array(), 'quick' => array(), 'recur' => array(), 'series' => array());
        // watchdog('civicrm_iatspayments_com', 'pp: <pre>!pp</pre>', array('!pp' => print_r($payment_processor,TRUE)), WATCHDOG_NOTICE);
        /* get approvals from yesterday, approvals from previous days, and then rejections for this payment processor */
        /* we're going to assume that all the payment_processors_per_type are using the same server */
        $iats_service_params['method'] = $method;
        $iats = new iATS_Service_Request($iats_service_params);
        switch($method) {
          case 'acheft_journal_csv': // special case to get today's transactions, so we're as real-time as we can be
          case 'cc_journal_csv': 
            $request = array(
              'date' => date('Y-m-d').'T23:59:59+00:00',
              'customerIPAddress' => (function_exists('ip_address') ? ip_address() : $_SERVER['REMOTE_ADDR']),
            );
            break;
          default: // box journals (approvals and rejections) only go up to the end of yesterday
            $request = array(
              'startIndex' => 0,
              'endIndex' => 1000,
              'fromDate' => date('Y-m-d',strtotime('-2 days')).'T00:00:00+00:00',
              'toDate' => date('Y-m-d',strtotime('-1 day')).'T23:59:59+00:00',
              'customerIPAddress' => (function_exists('ip_address') ? ip_address() : $_SERVER['REMOTE_ADDR']),
            );
            break;
        }
        // make the soap request, should return a csv file
        $response = $iats->request($credentials,$request);
        // use my iats object to parse the result into an array of transaction ojects
        $transactions = $iats->getCSV($response, $method);
        foreach($transactions as $transaction) {
          try {
            civicrm_api3('IatsPayments', 'journal', get_object_vars($transaction));
          }
          catch (CiviCRM_API3_Exception $e) {
            // todo: log these?
          }
        }
      }
    }
  }
  // watchdog('civicrm_iatspayments_com', 'found: <pre>!found</pre>', array('!found' => print_r($processed,TRUE)), WATCHDOG_NOTICE);
  foreach($processed as $user_name => $p) {
    foreach ($p as $type => $ps) {
      $message .= '<br />'. ts('For account %4, processed %1 approvals from today, and %2 approval and %3 rejection records from the previous 3 days.',
        array(
          1 => $ps['cc_journal_csv'],
          2 => $ps['cc_payment_box_journal_csv'],
          3 => $ps['cc_payment_box_reject_csv'],
          4 => $user_name,
        ));
    }
  }
  // If errors ..
  if ($error_count) {
    return civicrm_api3_create_error($message .'</br />'. implode('<br />', $output));
  }
}
