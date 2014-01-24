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
 * This works for both the initial contribution and subsequent contributions.
 * TODO: what kind of alerts should be provide if it fails?
 */
function civicrm_api3_job_iatsacheftverify($iats_service_params) {

  // find all pending iats acheft contributions, and their corresponding recurring contribution id 
  // TODO: needs to be updated if we ever accept one-off ach/eft
  $select = 'SELECT c.*, cr.contribution_status_id as cr_contribution_status_id, icc.customer_code as customer_code, icc.cid as icc_contact_id FROM civicrm_contribution c 
      INNER JOIN civicrm_contribution_recur cr ON c.contribution_recur_id = cr.id
      INNER JOIN civicrm_payment_processor pp ON cr.payment_processor_id = pp.id
      INNER JOIN civicrm_iats_customer_codes icc ON cr.id = icc.recur_id
      WHERE 
        c.contribution_status_id = 2
        AND pp.class_name = %1
        AND pp.is_test = 0
        AND (cr.end_date IS NULL OR cr.end_date > NOW())';
  $args = array(
    1 => array('Payment_iATSServiceACHEFT', 'String'),
  );

  $dao = CRM_Core_DAO::executeQuery($select,$args);
  $pending = array();
  while ($dao->fetch()) {
    /* we will ask iats if this ach/eft is approved, if so update both the contribution and recurring contribution status id's to 1 */
    /* todo: get_object_vars is a lazy way to do this! */
    $pending[$dao->customer_code] = get_object_vars($dao);
  }

  /* get "recent" approvals and rejects from iats and match them up with my pending list via the customer code */
  require_once("CRM/iATS/iATSService.php");
  /* initialize some values so I can report at the end */
  $error_count = 0;
  $counter = 0; // number of reject/accept records from iats analysed
  $found = 0;
  $output = array();
  /* do this loop for each relevant payment processor of type ACHEFT (usually only one or none) */
  $select = 'SELECT id,url_site FROM civicrm_payment_processor WHERE class_name = %1 AND is_test = 0';
  $args = array(
    1 => array('Payment_iATSServiceACHEFT', 'String'),
  );
  $dao = CRM_Core_DAO::executeQuery($select,$args);
  while ($dao->fetch()) {
    /* get rejections and then approvals for this payment processor */
    $iats_service_params = array('type' => 'report', 'iats_domain' => parse_url($dao->url_site, PHP_URL_HOST)) + $iats_service_params;
    foreach (array('acheft_payment_box_reject_csv' => 4, 'acheft_payment_box_journal_csv' => 1) as $method => $contribution_status_id) {
      // TODO: this is set to capture approvals and canellations from the past month, for testing purposes
      // it doesn't hurt, but on a live environment, this maybe should be limited to the past week, or less?
      // or, it could be configurable for the job
      $iats_service_params['method'] = $method;
      $iats = new iATS_Service_Request($iats_service_params);
      $credentials = $iats->credentials($dao->id);
      /* Initialize the default values for the iATS service request */
      /* note: iATS service is finicky about order! */
      $request = array(
        'fromDate' => date('Y-m-d',strtotime('-30 days')), 
        'toDate' => date('Y-m-d',strtotime('-1 day')), 
        'customerIPAddress' => (function_exists('ip_address') ? ip_address() : $_SERVER['REMOTE_ADDR']),
      );
      /* if ($contribution_status_id == 1) { 
        $request['fromDate'] = '2012-04-25';
        $request['toDate'] = '2012-04-25';
      } */
      // make the soap request, should return a csv file
      $response = $iats->request($credentials,$request);
      if (is_object($response)) {
        $box = preg_split("/\r\n|\n|\r/", $iats->file($response));
        if (1 < count($box)) {
          // data is an array of rows, the first of which is the column labels
          // TODO: all I care about it my customer code, but maybe I should check some other columns as a sanity check?
          // $labels = array_flip(str_getcsv($result[0]));
          for ($i = 1; $i < count($box); $i++) {
            $counter++;
            $data = str_getcsv($box[$i]);
            // skip any rows that don't include a custom code - TODO: log this as an error?
            if (empty($data[iATS_SERVICE_REQUEST::iATS_CSV_CUSTOMER_CODE_COLUMN])) continue;
            $customer_code = $data[iATS_SERVICE_REQUEST::iATS_CSV_CUSTOMER_CODE_COLUMN];
            // I'm only interested in custom codes that are still in a pending state
            if (isset($pending[$customer_code])) {
              $found++;
              $contribution = $pending[$customer_code];
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
                  1 => array($customer_code, 'String'),
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
              // also log it as an activity for administrative reference
              $result = civicrm_api('activity', 'create', array(
                'version'       => 3,
                'activity_type_id'  => 6, // 6 = contribution
                'source_contact_id'   => $contribution['contact_id'],
                'assignee_contact_id' => $contribution['contact_id'],
                'subject'       => ts('Updated status of iATS Payments ACH/EFT Recurring Contribution %1 to status %2 for contact %3',
                  array(
                    1 => $contribution['id'],
                    2 => $contribution_status_id,
                    3 => $contribution['contact_id'],
                  )),
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
                $output[] = ts('%1 ACH/EFT contribution id %2 for contact id %3', array(1 => ($contribution_status_id == 4 ? ts('Cancelled') : ts('Verified')), 2 => $contribution['id'], 3 => $contribution['contact_id']));
              }
            }
            // else ignore - it's not one of my pending transactions
          }
        }
      }
      else {
        $error_count++;
        $output[] = 'Unexpected SOAP error';
      }
    }
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
        'For %1 pending contributions, %2 rejection record(s) were analysed, %3 applied.',
        array(
          1 => count($pending),
          2 => $counter,
          3 => $found
        )
      ) . "<br />" . implode("<br />", $output)
    );
  }
  // No records processed
  return civicrm_api3_create_success(ts('No ACH/EFT records were processed or verified.'));
 
}
