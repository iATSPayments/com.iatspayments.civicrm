<?php // this page should only ever get called from iATS, it receives information and returns nothing

require_once 'CRM/Core/Page.php';

class CRM_iATS_Page_IATSDPMPostback extends CRM_Core_Page {
  function run() {
    // CRM_Core_Error::debug_log_message('Got PostBack from iATS DPM');
    // CRM_Core_Error::debug_var('POST', $_POST);
    require_once("CRM/iATS/iATSService.php");
    $result = $_POST;
    $invoice_id = trim($result['IATS_Invoice']);
    $customer_ip = trim($result['IATS_Item1']);
    $trxn_id = trim($result['IATS_TransID']) . ':' . time();
    $result['status'] = (substr(trim($result['IATS_Result']),0,2) == iATS_Service_Request::iATS_TXN_OK) ? 1 : 0;
    if ($result['status']) { /* success */
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
    // log to iats request and response logs
    // request
    $query_params = array(
      1 => array($invoice_id, 'String'),
      2 => array($customer_ip, 'String'),
      3 => array($result['IATS_AccountNumber'], 'String'),
      4 => array('', 'String'),
      5 => array($result['IATS_Amount'], 'String'),
    );
    CRM_Core_DAO::executeQuery("INSERT INTO civicrm_iats_request_log
      (invoice_num, ip, cc, customer_code, total, request_datetime) VALUES (%1, %2, %3, %4, %5, NOW())", $query_params);
    // response
    $query_params = array(
      1 => array($invoice_id, 'String'),
      2 => array($result['IATS_Result'], 'String'),
      3 => array($result['IATS_TransID'], 'String'),
    );
    CRM_Core_DAO::executeQuery("INSERT INTO civicrm_iats_response_log
      (invoice_num, auth_result, remote_id, response_datetime) VALUES (%1, %2, %3, NOW())", $query_params);
    // restore my source field that ipn irritatingly overwrites, and make sure that the trxn_id is set also
    // civicrm_api3('contribution','setvalue', array('id' => $contribution_id, 'value' => $contribution['source'], 'field' => 'source'));
    // civicrm_api3('contribution','setvalue', array('id' => $contribution_id, 'value' => $trxn_id, 'field' => 'trxn_id'));
    exit(); // abruptly!
  }
}
