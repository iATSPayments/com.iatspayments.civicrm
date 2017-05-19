<?php

require_once('CRM/Report/Form.php');

/**
 * @file
 */

/**
 *
 * $Id$
 */
class CRM_iATS_Form_Report_Verify extends CRM_Report_Form {

  static private $contributionStatus = array(); 
  static private $transaction_types = array(
    'VISA' => 'Visa',
    'ACHEFT' => 'ACH/EFT',
    'UNKNOW' => 'Uknown',
    'MC' => 'MasterCard',
    'AMX' => 'AMEX',
    'DSC' => 'Discover',
  );

  /**
   *
   */
  public function __construct() {
    self::$contributionStatus = CRM_Contribute_BAO_Contribution::buildOptions('contribution_status_id');
    $this->_columns = array(
      'civicrm_iats_verify' =>
        array(
          'fields' =>
            array(
              'id' => array('title' => 'CiviCRM Verify Id', 'default' => TRUE),
              'customer_code' => array('title' => 'Customer code', 'default' => TRUE),
              'cid' => array('title' => 'Contact', 'default' => TRUE),
              'contribution_id' => array('title' => 'Contribution', 'default' => TRUE),
              'recur_id' => array('title' => 'Recurring Contribution Id', 'default' => TRUE),
              'contribution_status_id' => array('title' => 'Payment Status', 'default' => TRUE),
              'verification_datetime' => array('title' => 'Verification date time', 'default' => TRUE),
            ),
          'order_bys' => 
            array(
              'id' => array('title' => ts('CiviCRM Verify Id'), 'default' => TRUE, 'default_order' => 'DESC'),
              'verification_datetime' => array('title' => ts('Verification Date Time')),
            ),
          'filters' =>
             array(
               'verification_datetime' => array(
                 'title' => 'Verification date time', 
                 'operatorType' => CRM_Report_Form::OP_DATE,
                 'type' => CRM_Utils_Type::T_DATE,
               ),
               'contribution_status_id' => array(
                 'title' => ts('Payment Status'),
                 'operatorType' => CRM_Report_Form::OP_MULTISELECT,
                 'options' => self::$contributionStatus,
                 'type' => CRM_Utils_Type::T_INT,
               ),
             ),
        ),
    );
    parent::__construct();
  }

  /**
   *
   */
  public function getTemplateName() {
    return 'CRM/Report/Form.tpl';
  }

  /**
   *
   */
  public function from() {
    $this->_from = "FROM civicrm_iats_verify  {$this->_aliases['civicrm_iats_verify']}";
  }

}
