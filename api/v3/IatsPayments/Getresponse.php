<?php
/**
 * Action IatsPayments GetResponse
 *
 * @param array $params
 *
 * @return array
 *   API result array.
 * @throws CiviCRM_API3_Exception
 */
function civicrm_api3_iats_payments_getresponse($params) {
  try {
    $query_params = array(
      1 => array($params['invoice_id'], 'String')
    );
    $result = CRM_Core_DAO::singleValueQuery("SELECT auth_result FROM civicrm_iats_response_log WHERE invoice_num = %1 ORDER BY id DESC limit 1", $query_params);
  }
  catch (Exception $e) {
    throw API_Exception('iATS Payments response get failed.');
  }
  return civicrm_api3_create_success($result, $params);
}
/**
 * Action getresponse
 *
 * @param array $params
 *
 * @return array
 */
function _civicrm_api3_iats_payments_getresponse_spec(&$params) {
  $params['invoice_id']['api.required'] = 1;
}
