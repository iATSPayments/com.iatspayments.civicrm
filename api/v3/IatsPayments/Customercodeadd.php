<?php
/**
 * Action IatsPayments customercode add
 *
 * @param array $params
 *
 * @return array
 *   API result array.
 * @throws CiviCRM_API3_Exception
 */
function civicrm_api3_iats_payments_customercodeadd($params) {
  try {
    $query_params = array(
      1 => array($params['customer_code'], 'String'),
      2 => array($params['customerIPAddress'], 'String'),
      3 => array($params['expiry'], 'String'),
      4 => array($params['contactID'], 'Integer'),
      5 => array($params['email'], 'String'),
      6 => array($params['contributionRecurID'], 'Integer'),
    );
    CRM_Core_DAO::executeQuery("INSERT INTO civicrm_iats_customer_codes
      (customer_code, ip, expiry, cid, email, recur_id) VALUES (%1, %2, %3, %4, %5, %6)", $query_params);
  }
  catch (Exception $e) {
    throw API_Exception('iATS Payments customer code addition failed.');
  }
  return civicrm_api3_create_success(TRUE, $params);
}
/**
 * Action payment.
 *
 * @param array $params
 *
 * @return array
 */
function _civicrm_api3_iats_payments_customercodeadd_spec(&$params) {
  $params['customer_code']['api.required'] = 1;
  $params['contributionRecurID']['api.required'] = 1;
}
