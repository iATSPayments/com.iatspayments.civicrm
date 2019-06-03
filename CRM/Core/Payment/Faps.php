<?php

require_once 'CRM/Core/Payment.php';

class CRM_Core_Payment_Faps extends CRM_Core_Payment {

  /**
   * mode of operation: live or test
   *
   * @var object
   * @static
   */
  protected $_mode = null;
  protected $use_cryptogram = FALSE;

  /**
   * Constructor
   *
   * @param string $mode the mode of operation: live or test
   *
   * @return void
   */
  function __construct( $mode, &$paymentProcessor ) {
    $this->_mode             = $mode;
    $this->_paymentProcessor = $paymentProcessor;
    $this->_processorName    = ts('iATS Payments 1st American Payment System Interface');
    $this->use_cryptogram   = faps_get_setting('use_cryptogram');
  }

  /**
   * This function checks to see if we have the right config values
   *
   * @return string the error message if any
   * @public
   */
  function checkConfig( ) {

    $error = array();

    if (empty($this->_paymentProcessor['user_name'])) {
      $error[] = ts('Processor Id is not set in the Administer CiviCRM &raquo; System Settings &raquo; Payment Processors.');
    }
    if (empty($this->_paymentProcessor['password'])) {
      $error[] = ts('Transaction Center Id is not set in the Administer CiviCRM &raquo; System Settings &raquo; Payment Processors.');
    }
    if (empty($this->_paymentProcessor['signature'])) {
      $error[] = ts('Merchant Key is not set in the Administer CiviCRM &raquo; System Settings &raquo; Payment Processors.');
    }

    if (!empty($error)) {
      return implode('<p>', $error);
    }
    else {
      return NULL;
    }
    // TODO: check urls vs. what I'm expecting?
  }

  /**
   * Get array of fields that should be displayed on the payment form for credit cards.
   * Use FAPS cryptojs to gather the senstive card information, if enabled.
   *
   * @return array
   */

  protected function getCreditCardFormFields() {
    $fields =  $this->use_cryptogram ? array('cryptogram') : parent::getCreditCardFormFields();
    return $fields;
  }

  /**
   * Return an array of all the details about the fields potentially required for payment fields.
   *
   * Only those determined by getPaymentFormFields will actually be assigned to the form
   *
   * @return array
   *   field metadata
   */
  public function getPaymentFormFieldsMetadata() {
    $metadata = parent::getPaymentFormFieldsMetadata();
    if ($this->use_cryptogram) {
      $metadata['cryptogram'] = array(
        'htmlType' => 'text',
        'cc_field' => TRUE,
        'name' => 'cryptogram',
        'title' => ts('Cryptogram'),
        'attributes' => array(
          'class' => 'cryptogram',
          'size' => 30,
          'maxlength' => 60,
        ),
        'is_required' => TRUE,
      );
    }
    return $metadata;
  }

  /**
   * function doDirectPayment
   *
   * This is the function for taking a payment using a core payment form of any kind.
   *
   * Here's the thing: if we are using the cryptogram with recurring, then the cryptogram
   * needs to be configured for use with the vault. The cryptogram iframe is created before
   * I know whether the contribution will be recurring or not, so that forces me to always
   * use the vault, if recurring is an option.
   * 
   * So: the best we can do is to avoid the use of the vault if I'm not using the cryptogram, or if I'm on a page that
   * doesn't offer recurring contributions.
   */
  public function doDirectPayment(&$params) {
    // CRM_Core_Error::debug_var('doDirectPayment params', $params);

    // Check for valid currency
    if ('USD' != $params['currencyID']) {
      return self::error('Invalid currency selection: ' . $params['currencyID']);
    }
    // flow is special when using hasIsRecur and usingCrypto, I have to use the vault
    // This is true even if the user has not chosen recur, because I have to choose the crypto iframe type when the page is generated.
    $isRecur = CRM_Utils_Array::value('is_recur', $params) && $params['contributionRecurID'];
    $hasIsRecur = (CRM_Utils_Array::value('frequency_interval', $params) > 0);
    $usingCrypto = !empty($params['cryptogram']);
    $ipAddress = (function_exists('ip_address') ? ip_address() : $_SERVER['REMOTE_ADDR']);
    $credentials = array(
      'merchantKey' => $this->_paymentProcessor['signature'],
      'processorId' => $this->_paymentProcessor['user_name']
    );
    $vault_key = $vault_id = '';
    if (($hasIsRecur  && $usingCrypto) || $isRecur) {
      // Store the params in a vault before attempting payment
      // I first have to convert the Auth crypto into a token.
      $options = array(
        'action' => 'GenerateTokenFromCreditCard',
        'test' => ($this->_mode == 'test' ? 1 : 0),
      );
      $token_request = new CRM_Iats_FapsRequest($options);
      $request = $this->convertParams($params, $options['action']);
      $request['ipAddress'] = $ipAddress;
      // Make the request.
      // CRM_Core_Error::debug_var('token request', $request);
      $result = $token_request->request($credentials, $request);
      // CRM_Core_Error::debug_var('token result', $result);
      // unset the cryptogram param and request values, we can't use the cryptogram again and don't want to return it anyway.
      unset($params['cryptogram']);
      unset($request['creditCardCryptogram']);
      unset($token_request);
      if (!empty($result['isSuccess'])) {
        // some of the result[data] is not useful, we're assuming it's not harmful to include in future requests here.
        $request = array_merge($request, $result['data']);
      }
      else {
        return self::error($result);
      }
      $options = array(
        'action' => 'VaultCreateCCRecord',
        'test' => ($this->_mode == 'test' ? 1 : 0),
      );
      $vault_request = new CRM_Iats_FapsRequest($options);
      // auto-generate a compliant vault key  
      $vault_key = CRM_Iats_Transaction::generateVaultKey($request['ownerEmail']);
      $request['vaultKey'] = $vault_key;
      $request['ipAddress'] = $ipAddress;
      // Make the request.
      // CRM_Core_Error::debug_var('vault request', $request);
      $result = $vault_request->request($credentials, $request);
      // CRM_Core_Error::debug_var('vault result', $result);
      if (!empty($result['isSuccess'])) {
        $vault_id = $result['data']['id'];
        if ($isRecur) {
          $update = array('processor_id' => ($vault_key.':'.$vault_id));
          // updateRecurring, incluing updating the next scheduled contribution date, before taking payment.
          $this->updateRecurring($params, $update);
        }
      }
      else {
        return self::error($result);
      }
      // now set the options for taking the money
      $options = array(
        'action' => 'SaleUsingVault',
        'test' => ($this->_mode == 'test' ? 1 : 0),
      );
    }
    else { // no recurring options in sight, set the simple sale option for taking the money
      $options = array(
        'action' => 'Sale',
        'test' => ($this->_mode == 'test' ? 1 : 0),
      );
    }
    // now take the money
    $payment_request = new CRM_Iats_FapsRequest($options);
    $request = $this->convertParams($params, $options['action']);
    $request['ipAddress'] = $ipAddress;
    if ($vault_id) {
      $request['vaultKey'] = $vault_key;
      $request['vaultId'] = $vault_id;
    }
    // Make the request.
    // CRM_Core_Error::debug_var('payment request', $request);
    $result = $payment_request->request($credentials, $request);
    // CRM_Core_Error::debug_var('result', $result);
    $success = (!empty($result['isSuccess']));
    if ($success) {
      // put the old version of the return param in just to be sure
      $params['contribution_status_id'] = 1;
      // For versions >= 4.6.6, the proper key.
      $params['payment_status_id'] = 1;
      $params['trxn_id'] = trim($result['data']['referenceNumber']).':'.time();
      $params['gross_amount'] = $params['amount'];
      return $params;
    }
    else {
      return self::error($result);
    }
  }

  /**
   * Todo?
   *
   * @param array $params name value pair of contribution data
   *
   * @return void
   * @access public
   *
   */
  function doTransferCheckout( &$params, $component ) {
    CRM_Core_Error::fatal(ts('This function is not implemented'));
  }

  /**
   * Support corresponding CiviCRM method
   */
  public function changeSubscriptionAmount(&$message = '', $params = array()) {
    return TRUE;
  }

  /**
   * Support corresponding CiviCRM method
   */
  public function cancelSubscription(&$message = '', $params = array()) {
    $userAlert = ts('You have cancelled this recurring contribution.');
    CRM_Core_Session::setStatus($userAlert, ts('Warning'), 'alert');
    return TRUE;
  }

  /**
   * Set additional fields when editing the schedule.
   *
   * Note: this doesn't completely replace the form hook, which is still
   * in use for additional changes, and to support 4.6.
   * e.g. the commented out fields below don't work properly here.
   */
  public function getEditableRecurringScheduleFields() {
    return array('amount',
         'installments',
         'next_sched_contribution_date',
//         'contribution_status_id',
//         'start_date',
         'is_email_receipt',
       );
  }

  /*
   * Set a useful message at the top of the schedule editing form
   */
  public function getRecurringScheduleUpdateHelpText() {
    return 'Use this form to change the amount or number of installments for this recurring contribution.<ul><li>You can not change the contribution frequency.</li><li>You can also modify the next scheduled contribution date.</li><li>You can change whether the contributor is sent an email receipt for each contribution.<li>You have an option to notify the contributor of these changes.</li></ul>';
  }

  /**
   * Convert the values in the civicrm params to the request array with keys as expected by FAPS
   *
   * @param array $params
   * @param string $action
   *
   * @return array
   */
  protected function convertParams($params, $method) {
    $request = array();
    $convert = array(
      'ownerEmail' => 'email',
      'ownerStreet' => 'street_address',
      'ownerCity' => 'city',
      'ownerState' => 'state_province',
      'ownerZip' => 'postal_code',
      'ownerCountry' => 'country',
      'orderId' => 'invoiceID',
      'cardNumber' => 'credit_card_number',
//      'cardtype' => 'credit_card_type',
      'cVV' => 'cvv2',
      'creditCardCryptogram' => 'cryptogram',
    );
    foreach ($convert as $r => $p) {
      if (isset($params[$p])) {
        $request[$r] = htmlspecialchars($params[$p]);
      }
    }
    if (empty($params['email'])) {
      if (isset($params['email-5'])) {
        $request['ownerEmail'] = $params['email-5'];
      }
      elseif (isset($params['email-Primary'])) {
        $request['ownerEmail'] = $params['email-Primary'];
      }
    }
    $request['ownerName'] = $params['billing_first_name'].' '.$params['billing_last_name'];
    if (!empty($params['month'])) {
      $request['cardExpMonth'] = sprintf('%02d', $params['month']);
    }
    if (!empty($params['year'])) {
      $request['cardExpYear'] = sprintf('%02d', $params['year'] % 100);
    }
    $request['transactionAmount'] = sprintf('%01.2f', CRM_Utils_Rule::cleanMoney($params['amount']));
    // additional method-specific values (none!)
    //CRM_Core_Error::debug_var('params for conversion', $params);
    //CRM_Core_Error::debug_var('method', $method);
    //CRM_Core_Error::debug_var('request', $request);
    return $request;
  }


  /**
   *
   */
  public function &error($error = NULL) {
    $e = CRM_Core_Error::singleton();
    if (is_object($error)) {
      $e->push($error->getResponseCode(),
        0, NULL,
        $error->getMessage()
      );
    }
    elseif ($error && is_numeric($error)) {
      $e->push($error,
        0, NULL,
        $this->errorString($error)
      );
    }
    elseif (is_array($error)) {
      $errors = array();
      if ($error['isError']) {
        foreach($error['errorMessages'] as $message) {
          $errors[] = $message;
        }
      }
      if ($error['validationHasFailed']) {
        foreach($error['validationFailures'] as $message) {
          $errors[] = 'Validation failure for '.$message['key'].': '.$message['message'];
        }
      }
      $error_string = implode('<br />',$errors);
      $e->push(9002,
        0, NULL,
        $error_string
      );
    }
    else {
      $e->push(9001, 0, NULL, "Unknown System Error.");
    }
    return $e;
  }

  /*
   * Update the recurring contribution record.
   *
   * Implemented as a function so I can do some cleanup and implement
   * the ability to set a future start date for recurring contributions.
   * This functionality will apply to back-end and front-end,
   * As enabled when configured via the iATS admin settings.
   *
   * This function will alter the recurring schedule as an intended side effect.
   * and return the modified the params.
   */
  protected function updateRecurring($params, $update) {
    // If the recurring record already exists, let's fix the next contribution and start dates,
    // in case core isn't paying attention.
    // We also set the schedule to 'in-progress' (even for ACH/EFT when the first one hasn't been verified),
    // because we want the recurring job to run for this schedule.
    if (!empty($params['contributionRecurID'])) {
      $recur_id = $params['contributionRecurID'];
      $recur_update = array(
        'id' => $recur_id,
        'contribution_status_id' => 'In Progress',
        'processor_id' => $update['processor_id'],
      );
      // use the receive date to set the next sched contribution date.
      // By default, it's empty, unless we've got a future start date.
      if (empty($update['receive_date'])) {
        $next = strtotime('+' . $params['frequency_interval'] . ' ' . $params['frequency_unit']);
        $recur_update['next_sched_contribution_date'] = date('Ymd', $next) . '030000';
      }
      else {
        $recur_update['start_date'] = $recur_update['next_sched_contribution_date'] = $update['receive_date'];
        // If I've got a monthly schedule, let's set the cycle_day for niceness
        if ('month' == $params['frequency_interval']) {
          $recur_update['cycle_day'] = date('j', strtotime($recur_update['start_date']));
        }
      }
      try {
        $result = civicrm_api3('ContributionRecur', 'create', $recur_update);
        return $result;
      }
      catch (CiviCRM_API3_Exception $e) {
        // Not a critical error, just log and continue.
        $error = $e->getMessage();
        Civi::log()->info('Unexpected error updating the next scheduled contribution date for id {id}: {error}', array('id' => $recur_id, 'error' => $error));
      }
    }
    else {
      Civi::log()->info('Unexpectedly unable to update the next scheduled contribution date, missing id.');
    }
    return false;
  }



}



