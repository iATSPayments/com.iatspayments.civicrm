<?php

/**
 * @file
 */

/**
 * Action journal.
 *
 * @param array $params
 *
 * Record an entry from FAPS into its journal table
 */
function _civicrm_api3_faps_transaction_journal_spec(&$params) {
  // $params['transaction_id']['api.required'] = 1;
}

/**
 * Action IatsPayments VerifyLog.
 *
 * @param array $params
 *
 * @return array
 *   API result array.
 *
 * @throws CiviCRM_API3_Exception
 */

/**
 *
 */
function civicrm_api3_faps_transaction_journal($params) {
  // CRM_Core_Error::debug_var('params', $params);
  // return civicrm_api3_create_success(TRUE, array('test' => TRUE));
  try {
    $data = $params['orderInfo'];
    // There is a unique keys on transactionId
    // If I don't have one don't overwrite.
    if (empty($params['transactionId'])) {
      $transactionId = 'NULL';
      $sql_action = 'INSERT IGNORE ';
    }
    else {
      $transactionId = (int) $params['transactionId'];
      $sql_action = 'REPLACE INTO ';
    }
    $cardType = isset($params['ccInfo']['cardType']) ? $params['ccInfo']['cardType'] : '';
    $isAch = empty($params['isAch']) ? 0 : 1;
    $status_id = $data['isSuccessful'] ? ($data['authResponse'] == 'Pending' ? 2 : 1) : 4; // 1 = complete, 2 = pending, 4 = fail
    $query_params = array(
      2 => array($data['authCode'], 'String'),
      3 => array($isAch, 'Integer'),
      4 => array($cardType, 'String'),
      5 => array($params['processorId'], 'String'),
      6 => array($data['cimRefNumber'], 'String'),
      7 => array($data['orderId'], 'String'),
      8 => array($data['transDateAndTime'], 'String'),
      9 => array($data['amount'], 'String'),
      10 => array($data['authResponse'], 'String'),
      11 => array($params['currency'], 'String'),
      12 => array($status_id, 'Integer'),
    );
    $result = CRM_Core_DAO::executeQuery($sql_action . " civicrm_iats_faps_journal
        (transactionId, authCode, isAch, cardType, processorId, cimRefNumber, orderId, transDateAndTime, amount, authResponse, currency, status_id) 
        VALUES ($transactionId, %2, %3, %4, %5, %6, %7, %8, %9, %10, %11, %12)", $query_params);
  }
  catch (Exception $e) {
    CRM_Core_Error::debug_var('query params', $query_params);
    CRM_Core_Error::debug_var('params', $params);
    CRM_Core_Error::debug_var('exceptions', $e);
    // throw CiviCRM_API3_Exception('iATS FAPS journalling failed: ' . $e->getMessage());
  }
  return civicrm_api3_create_success();
}