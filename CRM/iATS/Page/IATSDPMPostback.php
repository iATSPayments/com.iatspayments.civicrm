<?php // this page should only ever get called from iATS, it receives information and returns nothing

require_once 'CRM/Core/Page.php';

class CRM_iATS_Page_IATSDPMPostback extends CRM_Core_Page {
  function run() {
    CRM_Core_Error::debug_log_message('Got PostBack from iATS DPM');
    CRM_Core_Error::debug_var('POST', $_POST);
    require_once("CRM/iATS/iATSService.php");
    $result = $_POST;
    // TODO: update/create the contact's billing information?
    // handle recurring => client token creation request
    if (trim($result['IATS_Result']) == 'TokenCreated') {
      $invoice_id = trim($result['IATS_Item2']);
      $customer_ip = trim($result['IATS_Item1']);
      $customer_code = trim($result['IATS_ResultDetail']);
      $email = trim($result['IATS_Email']);
      $expiry = sprintf('%02d%02d', ($result['IATS_ExpiryYear'] % 100), $result['IATS_ExpiryMonth']);
      $amount = trim($result['IATS_Amount']);
      $account_number = trim($result['IATS_AccountNumber']);
      try { // save my client code for future transactions
        $params = array('invoice_id' => $invoice_id, 'return' => 'id,contribution_recur_id,contact_id,is_test,currency');
        $contribution = civicrm_api3('Contribution', 'getsingle', $params);
        // CRM_Core_Error::debug_var('Contribution.', $contribution);
        $params = array('id' => $contribution['contribution_recur_id'], 'return' => 'id,payment_processor_id,frequency_interval,frequency_unit,contact_id');
        $contribution_recur = civicrm_api3('ContributionRecur', 'getsingle', $params);
        $params = array('customer_code' => $customer_code,
          'customerIPAddress' => $customer_ip,
          'expiry' => $expiry,
          'contactID' => $contribution['contact_id'],
          'email' => $email,
          'contributionRecurID' => $contribution['contribution_recur_id'], 
        );
        civicrm_api3('IatsPayments', 'customercodeadd', $params);
      }
      catch (Exception $e) {
        // Don't throw an exception here, there's no one to catch it!
        CRM_Core_Error::debug_var('Exception while adding a new customer code DPM postback.', $e->getMessage());
      }
      // now try to take the first contribution, unless it's using a fixed-day schedule
      $settings = CRM_Core_BAO_Setting::getItem('iATS Payments Extension', 'iats_settings');
      $allow_days = empty($settings['days']) ? array('-1') : $settings['days'];
      $failure_count = 0;
      if (max($allow_days) <= 0) { // try to run the transaction immediately
        try {
          $credentials = iATS_Service_Request::credentials($contribution_recur['payment_processor_id'], $contribution['is_test']);
          $iats = new iATS_Service_Request(array('type' => 'process', 'method' => 'cc_with_customer_code', 'iats_domain' => $credentials['domain'], 'currencyID' => $contribution['currency']));
          $request = array('invoiceNum' => $invoice_id);
          $request['total'] = sprintf('%01.2f', CRM_Utils_Rule::cleanMoney($amount));
          $request['customerCode'] = $customer_code;
          $request['customerIPAddress'] = $customer_ip;
          $response = $iats->request($credentials, $request);
          $result = $iats->result($response);
          if ($result['status']) { // complete the contribution
            $auth_result = $result['auth_result'];
            $remote_id = trim($result['remote_id']);
            $trxn_id = $remote_id . ':' . time();
            $complete = array('id' => $contribution['id'], 'trxn_id' =>  $trxn_id);
            $contributionResult = civicrm_api3('contribution', 'completetransaction', $complete);
            $next_sched_contribution_ts = strtotime('+' . $contribution_recur['frequency_interval'] . ' ' . $contribution_recur['frequency_unit']);
          }
          else {
            $failure_count = 1;
            CRM_Core_Error::debug_var('Unable to run a transaction with a new customer code on DPM postback.', $result['reasonMessage']);
            // optimistically re-schedule
            $next_sched_contribution_ts = time();
          }
        }
        catch (Exception $e) {
          // Don't throw an exception here, there's no one to catch it!
          CRM_Core_Error::debug_var('Exception on DPM complete transaction attempt.', $e->getMessage());
        }
      }
      else { // I've got a schedule to adhere to!
        $next_sched_contribution_ts = _iats_contributionrecur_next(time(),$allow_days);
      }
      $next_sched_contribution = date('Ymd', $next_sched_contribution_ts).'030000';
      /* in both cases, update the next scheduled contribution date */
      /* regardless of the result, I'm going to ensure my recurring contribution record is updated to in-progress */
      $contribution_recur_set = array('contact_id' => $contribution_recur['contact_id'], 'id' => $contribution_recur['id'], 'failure_count' => $failure_count, 'next_sched_contribution_date' => $next_sched_contribution, 'contribution_status_id' => 'In Progress');
      CRM_Core_Error::debug_var('Completing contribution recur.', $contribution_recur_set);
      try {
        $result = civicrm_api3('ContributionRecur', 'create', $contribution_recur_set);
        if ($result['is_error']) {
          CRM_Core_Error::debug_var('result from updating contribution recur entry', $result);
        }
      }
      catch (Exception $e) {
        // Don't throw an exception here, there's no one to catch it!
        CRM_Core_Error::debug_var('Exception on DPM update recurring contribution.', $e->getMessage());
      }
    }
    else { // one-time contribution, the contribution has been completed
      $amount = trim($result['IATS_Amount']);
      $account_number = trim($result['IATS_AccountNumber']);
      $invoice_id = trim($result['IATS_Invoice']);
      $customer_ip = trim($result['IATS_Item1']);
      $trxn_id = trim($result['IATS_TransID']) . ':' . time();
      $auth_result = trim($result['IATS_Result']);
      $remote_id = trim($result['IATS_TransID']);
      $status = (substr($auth_result,0,2) == iATS_Service_Request::iATS_TXN_OK) ? 1 : 0;
      if ($status) { /* success */
        // $complete['is_email_receipt'] = empty($options['is_email_receipt']) ? 0 : 1;
        try {
          $params = array('invoice_id' => $invoice_id, 'return' => 'id');
          $contribution = civicrm_api3('Contribution', 'getsingle', $params);
          $complete = array('id' => $contribution['id'], 'trxn_id' => $trxn_id);
          $contributionResult = civicrm_api3('contribution', 'completetransaction', $complete);
        }
        catch (Exception $e) {
          // Don't throw an exception here, there's no one to catch it!
          CRM_Core_Error::debug_var('Exception on DPM complete transaction attempt.', $e->getMessage());
        }
      }
      else { // unexpected behaviour!
        CRM_Core_Error::debug_var('auth_result', $auth_result);
      }
      // log to iats request and response logs
      $query_params = array(
        1 => array($invoice_id, 'String'),
        2 => array($customer_ip, 'String'),
        3 => array($account_number, 'String'),
        4 => array('', 'String'),
        5 => array($amount, 'String'),
      );
      CRM_Core_DAO::executeQuery("INSERT INTO civicrm_iats_request_log
        (invoice_num, ip, cc, customer_code, total, request_datetime) VALUES (%1, %2, %3, %4, %5, NOW())", $query_params);
      // response
      $query_params = array(
        1 => array($invoice_id, 'String'),
        2 => array($auth_result, 'String'),
        3 => array($remote_id, 'String'),
      );
      CRM_Core_DAO::executeQuery("INSERT INTO civicrm_iats_response_log
        (invoice_num, auth_result, remote_id, response_datetime) VALUES (%1, %2, %3, NOW())", $query_params);
    }
    // restore my source field that ipn irritatingly overwrites, and make sure that the trxn_id is set also
    // civicrm_api3('contribution','setvalue', array('id' => $contribution_id, 'value' => $contribution['source'], 'field' => 'source'));
    // civicrm_api3('contribution','setvalue', array('id' => $contribution_id, 'value' => $trxn_id, 'field' => 'trxn_id'));
    exit(); // abruptly!
  }
}
