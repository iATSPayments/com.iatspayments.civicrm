<?php

use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;

// KG
use Civi\Payment\System;


/**
 * FIXME - Add test description.
 *
 * Tips:
 *  - With HookInterface, you may implement CiviCRM hooks directly in the test class.
 *    Simply create corresponding functions (e.g. "hook_civicrm_post(...)" or similar).
 *  - With TransactionalInterface, any data changes made by setUp() or test****() functions will
 *    rollback automatically -- as long as you don't manipulate schema or truncate tables.
 *    If this test needs to manipulate schema or truncate tables, then either:
 *       a. Do all that using setupHeadless() and Civi\Test.
 *       b. Disable TransactionalInterface, and handle all setup/teardown yourself.
 *
 * @group headless
 */
class CRM_iATS_ContributioniATSTest extends BaseTestClass {

  public function setUpHeadless() {
    // Civi\Test has many helpers, like install(), uninstall(), sql(), and sqlFile().
    // See: https://github.com/civicrm/org.civicrm.testapalooza/blob/master/civi-test.md
    return \Civi\Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

  public function setUp() {
    $this->_apiversion = 3;
    parent::setUp();
  }

  public function tearDown() {
    parent::tearDown();
  }

  /**
   * Example: Test that a version is returned.
   */
  public function testWellFormedVersion() {
    $this->assertRegExp('/^([0-9\.]|alpha|beta)*$/', \CRM_Utils_System::version());
  }

  /**
   * Example: Test that we're using a fake CMS.
   */
  public function testWellFormedUF() {
    $this->assertEquals('UnitTests', CIVICRM_UF);
  }

  /**
   * Test a Credit Card Contribution - one time iATS Credit Card - TEST88 - Backend
   */
  public function testIATSCreditCardBackend() {

    $params = array(
      'sequential' => 1,
      'first_name' => "Can",
      'last_name' => "ada",
      'contact_type' => "Individual",
    );

    $individual = $this->callAPISuccess('contact', 'create', $params);

    // Need to create a Payment Processor - iATS Payment Credit Card is in civicrm_payment_processor_type id=13
    // iATS CC TEST88
    $this->paymentProcessor = $this->iATSCCProcessorCreate();

    $processor = $this->paymentProcessor->getPaymentProcessor();
    $this->paymentProcessorID = $processor['id'];

    $form = new CRM_Contribute_Form_Contribution();
    $form->_mode = 'Live';

    $contribution_params = array(
      'total_amount' => 1.11,
      'financial_type_id' => 1,
      'receive_date' => '08/03/2017',
      'receive_date_time' => '11:59PM',
      'contact_id' => $individual['id'],
      'payment_instrument_id' => 1,
      'contribution_status_id' => 1,
      'credit_card_number' => 4222222222222220,
      'cvv2' => 123,
      'credit_card_exp_date' => array(
        'M' => 12,
        'Y' => 2025,
      ),
      'credit_card_type' => 'Visa',
      'billing_first_name' => 'Karin',
      'billing_middle_name' => '',
      'billing_last_name' => 'G',
      'billing_street_address-5' => '39 St',
      'billing_city-5' => 'Calgary',
      'billing_state_province_id-5' => 1031,
      'billing_postal_code-5' => 10545,
      'billing_country_id-5' => 1228,
      'frequency_interval' => 1,
      'frequency_unit' => 'month',
      'installments' => '',
      'hidden_AdditionalDetail' => 1,
      'hidden_Premium' => 1,
      'receipt_date' => '',
      'receipt_date_time' => '',
      'payment_processor_id' => $this->paymentProcessorID,
      'currency' => 'USD',
      'source' => 'iATS CC TEST88',
    );

    $form->testSubmit($contribution_params, CRM_Core_Action::ADD);

    $contribution = $this->callAPISuccessGetSingle('Contribution', array(
      'contact_id' => $individual['id'],
      'contribution_status_id' => 'Completed',
    ));
    $this->assertEquals('1.11', $contribution['total_amount']);

    // Grab Financial Type -> Id
    $this->assertEquals(0, $contribution['non_deductible_amount']);

    // Make sure that we have a Transaction ID and that it contains a : (unique to iATS);
    $this->assertRegExp('/:/', $contribution['trxn_id']);

    // LineItems; Financial Tables;
  }

  /**
   * Create iATS Credit Card - TEST88 Payment Processor.
   *
   * @param array $processorParams
   *
   * @return \CRM_Core_Payment_Dummy
   *    Instance of Dummy Payment Processor
   */
  public function iATSCCProcessorCreate($processorParams = array()) {
    $paymentProcessorID = $this->processorCreate($processorParams);
    return System::singleton()->getById($paymentProcessorID);
  }

  /**
   * Create iATS Credit Card - TEST88 Payment Processor.
   *
   * @return int
   *   Id Payment Processor
   */
  public function processorCreate($params = array()) {
    $processorParams = array(
      'domain_id' => 1,
      'name' => 'iATS Credit Card - TEST88',
      'payment_processor_type_id' => 13,
      //'financial_account_id' => 12,
      'is_test' => FALSE,
      'is_active' => 1,
      'user_name' => 'TEST88',
      'password' => 'TEST88',
      'url_site' => 'https://www.iatspayments.com/NetGate/ProcessLinkv2.asmx?WSDL',
      'url_recur' => 'https://www.iatspayments.com/NetGate/ProcessLinkv2.asmx?WSDL',
      'class_name' => 'Payment_iATSService',
      'is_recur' => 1,
      'sequential' => 1,
      // 'payment_instrument_id' => 'Credit Card',
      'payment_instrument_id' => 1,
    );
    $processorParams = array_merge($processorParams, $params);
    $processor = $this->callAPISuccess('PaymentProcessor', 'create', $processorParams);
    return $processor['id'];
  }

}
