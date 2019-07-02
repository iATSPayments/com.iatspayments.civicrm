<?php

class IATSCustomerUpdateBillingInfo extends CRM_Iats_Form_IATSCustomerLink {

  public $updatedBillingInfo;

  public function __construct() {
    // no need to call all the form init stuff, we're a fake form
  }

  public function exportValues($elementList = NULL, $filterInternal = FALSE) {

    $ubi = $this->updatedBillingInfo;
    // Fix billing form update bug by forcing crid to exist
    if(empty($ubi['crid'])) {
      $ubi['crid'] = !empty($_POST['crid']) ? (int) $_POST['crid'] : (!empty($_GET['crid']) ? (int) $_GET['crid'] : 0);
    }
    // updatedBillingInfo array changed sometime after 4.7.27
    $crid = !empty($ubi['crid']) ? $ubi['crid'] : $ubi['recur_id'];
    if (empty($crid)) {
      $alert = ts('This system is unable to perform self-service updates to credit cards. Please contact the administrator of this site.');
      throw new Exception($alert);
    } 
    $mop = array(
      'Visa' => 'VISA',
      'MasterCard' => 'MC',
      'Amex' => 'AMX',
      'Discover' => 'DSC',
    );
    $contribution_recur = civicrm_api3('ContributionRecur', 'getsingle', ['id' => $crid]);
    $payment_token = $result = civicrm_api3('PaymentToken', 'getsingle', ['id' => $contribution_recur['payment_token_id']]);
    $values = array(
      'cid' => $contribution_recur['contact_id'],
      'customerCode' => $payment_token['token']
      'paymentProcessorId' => $contribution_recur['payment_processor_id'],
      'is_test' => 0,
      'creditCardCustomerName' => "{$ubi['first_name']} " . (!empty($ubi['middle_name']) ? "{$ubi['middle_name']} " : '') . $ubi['last_name'],
      'address' => $ubi['street_address'],
      'city' => $ubi['city'],
      'state' => CRM_Core_DAO::singleValueQuery("SELECT abbreviation FROM civicrm_state_province WHERE id=%1", array(1 => array($ubi['state_province_id'], 'Int'))),
      'zipCode' => $ubi['postal_code'],
      'creditCardNum' => $ubi['credit_card_number'],
      'creditCardExpiry' => sprintf('%02d/%02d', $ubi['month'], $ubi['year'] % 100),
      'mop' => $mop[$ubi['credit_card_type']],
    );

    return $values;

  }

}
