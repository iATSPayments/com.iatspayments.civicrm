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

 * Look up all pending ACH/EFT contributions and see if they've been approved
 * TODO: update the corresponding recurring contribution record to status = 1 if it's the first one (or - always check?).
 * TODO: what kind of alerts should be provide if it fails?
 */
function civicrm_api3_job_iatsacheftverify($params) {
  // find all pending iats acheft contributions
  $select = 'SELECT c.*, icc.customer_code as customer_code, icc.cid as icc_contact_id FROM civicrm_contribution c 
      INNER JOIN civicrm_contribution_recur cr ON c.contribution_recur_id = cr.id
      INNER JOIN civicrm_payment_processor pp ON cr.payment_processor_id = pp.id
      INNER JOIN civicrm_iats_customer_codes icc ON cr.id = icc.recur_id
      WHERE 
        c.contribution_status_id = 2
        AND cr.contribution_status_id = %1
        AND pp.class_name = %2
        AND pp.is_test = 0
        AND (cr.end_date IS NULL OR cr.end_date > NOW())';
  $args = array(
    1 => array('2', 'Int'),
    2 => array('Payment_iATSServiceACHEFT', 'String'),
  );

  $dao = CRM_Core_DAO::executeQuery($select,$args);
  /* $counter = 0;
  $error_count  = 0;
  $output  = array(); */
  $pending = array();
  while ($dao->fetch()) {
    /* ask iats if this ach/eft is approved, if so update both the contribution and recurring contribution status id's to 1 */
    $pending[$dao->customer_code] = get_object_vars($dao);
  }

  /* get my most recent approvals and rejects from iats */

  require_once("CRM/iATS/iATSService.php");
  $iats = new iATS_Service_Request('acheft_payment_box_reject_csv', array('type' => 'report', 'log' => array('all' => 1),'trace' => TRUE));
  $request = array(
    'fromDate' => date('Y-m-d',strtotime('-30 days')), // '2013-11-26', //date('m/d/Y',strtotime('-2 day')), 
    'toDate' => date('Y-m-d',strtotime('-1 day')), // '12/09/2013', //date('m/d/Y',strtotime('-2 day')), 
  );
  $request['customerIPAddress'] = (function_exists('ip_address') ? ip_address() : $_SERVER['REMOTE_ADDR']);
  $select = 'SELECT id FROM civicrm_payment_processor WHERE class_name = %1 AND is_test = 0';
  $args = array(
    1 => array('Payment_iATSServiceACHEFT', 'String'),
  );
  $error_count = 0;
  $counter = 0;
  $found = 0;
  $output = array();
  $result = '';
  $dao = CRM_Core_DAO::executeQuery($select,$args);
  while ($dao->fetch()) {
    $credentials = $iats->credentials($dao->id);
    // make the soap request
    $response = $iats->request($credentials,$request);
    if (is_object($response)) {
      $result = preg_split("/\r\n|\n|\r/", $iats->file($response));
      if (count($result) > 1) {
        // data is an array of rows, the first of which is the column labels
        $labels = array_flip(str_getcsv($result[0]));
        // print_r($labels);
        for ($i = 1; $i < count($result); $i++) {
          $counter++;
          $data = str_getcsv($result[$i]);
          // print_r($data);
          // TODO: turn this 4 into a constant or look it up from the labels array
          $customer_code = $data[4];
          if (isset($pending[$customer_code])) {
            $found++;
            $contribution = $pending[$customer_code];
            // pending contribution and recurring contribution both cancelled
            $params = array('version' => 3, 'sequential' => 1, 'contribution_status_id' => 4);
            $params['id'] = $contribution['id'];
            $result = civicrm_api('Contribution', 'create', $params);
            $params = array('version' => 3, 'sequential' => 1, 'contribution_status_id' => 4);
            $params['id'] = $contribution['contribution_recur_id'];
            $result = civicrm_api('ContributionRecur', 'create', $params);
            $result = civicrm_api('activity', 'create',
              array(
                'version'       => 3,
                'activity_type_id'  => 6,
                'source_contact_id'   => $contribution['contact_id'],
                'assignee_contact_id' => $contribution['contact_id'],
                'subject'       => "Rejection of iATS Payments ACH/EFT Recurring Contribution for " . $contribution['total_amount'],
                'status_id'       => 2,
                'activity_date_time'  => date("YmdHis"),
              )
            );
            if ($result['is_error']) {
              $output[] = ts(
                'An error occurred while creating activity record for contact id %1: %2',
                array(
                  1 => $dao->contact_id,
                  2 => $result['error_message']
                )
              );
              ++$error_count;
            } 
            else {
              $output[] = ts('Cancelled ACH/EFT recurring contribution for contact id %1', array(1 => $contribution['contact_id']));
            }
          }
        }
      }
    }
    else {
      $error_count++;
      $output[] = 'Unexpected SOAP error';
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
        '%1, %2 rejection record(s) were analysed, %3 applied.',
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
