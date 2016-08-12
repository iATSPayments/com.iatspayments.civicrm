<?php
/* Copyright iATS Payments (c) 2014
 * @author Alan Dixon
 *
 * This file is a part of CiviCRM published extension.
 *
 * This extension is free software; you can copy, modify, and distribute it
 * under the terms of the GNU Affero General Public License
 * Version 3, 19 November 2007.
 *
 * It is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License with this program; if not, see http://www.gnu.org/licenses/
 *
 * This code provides glue between CiviCRM payment model and the iATS Payment model encapsulated in the iATS_Service_Request object
 *
 */
class CRM_Core_Payment_iATSService extends CRM_Core_Payment {

  /**
   * We only need one instance of this object. So we use the singleton
   * pattern and cache the instance in this variable
   *
   * @var object
   * @static
   */
  static private $_singleton = NULL;

  /**
   * Constructor.
   *
   * @param string $mode the mode of operation: live or test
   *
   * @param array $paymentProcessor
   */
  public function __construct($mode, &$paymentProcessor, &$paymentForm = NULL, $force = FALSE) {
    $this->_paymentProcessor = $paymentProcessor;
    $this->_processorName = ts('iATS Payments');
    // override the billing mode depending on the signature? Not useful since this constructor not called when I need it
    // $this->_paymentProcessor['billing_mode'] = (empty($paymentProcessor['signature']))  ? 1 : 4;
    // get merchant data from config
    $config = CRM_Core_Config::singleton();
    // live or test
    $this->_profile['mode'] = $mode;
    // we only use the domain of the configured url, which is different for NA vs. UK
    $this->_profile['iats_domain'] = parse_url($this->_paymentProcessor['url_site'], PHP_URL_HOST);
  }

  static function &singleton($mode, &$paymentProcessor, &$paymentForm = NULL, $force = FALSE) {
    // override the billing mode depending on the signature
    // $paymentProcessor['billing_mode'] = (empty($paymentProcessor['signature']))  ? 1 : 4;
    $processorName = $paymentProcessor['name'];
    if (self::$_singleton[$processorName] === NULL) {
      self::$_singleton[$processorName] = new CRM_Core_Payment_iATSService($mode, $paymentProcessor);
    }
    return self::$_singleton[$processorName];
  }

  function doDirectPayment(&$params, $component = 'contribute') {
    if (!$this->_profile) {
      return self::error('Unexpected error, missing profile');
    }
    // use the iATSService object for interacting with iATS. Recurring contributions go through a more complex process.
    require_once("CRM/iATS/iATSService.php");
    $isRecur = CRM_Utils_Array::value('is_recur', $params) && $params['contributionRecurID'];
    $methodType = $isRecur ? 'customer' : 'process';
    $method = $isRecur ? 'create_credit_card_customer' : 'cc';
    $iats = new iATS_Service_Request(array('type' => $methodType, 'method' => $method, 'iats_domain' => $this->_profile['iats_domain'], 'currencyID' => $params['currencyID']));
    $request = $this->convertParams($params, $method);
    $request['customerIPAddress'] = (function_exists('ip_address') ? ip_address() : $_SERVER['REMOTE_ADDR']);
    $credentials = array(
      'agentCode' => $this->_paymentProcessor['user_name'],
      'password'  => $this->_paymentProcessor['password'],
    );
    // Get the API endpoint URL for the method's transaction mode.
    // TODO: enable override of the default url in the request object
    // $url = $this->_paymentProcessor['url_site'];

    // make the soap request
    $response = $iats->request($credentials, $request);
    if (!$isRecur) {
      // process the soap response into a readable result, logging any credit card transactions
      $result = $iats->result($response);
      if ($result['status']) {
        $params['contribution_status_id'] = 1; // success
        $params['payment_status_id'] = 1; // for versions >= 4.6.6, the proper key
        $params['trxn_id'] = trim($result['remote_id']) . ':' . time();
        $params['gross_amount'] = $params['amount'];
        return $params;
      }
      else {
        return self::error($result['reasonMessage']);
      }
    }
    else { 
      // save the client info in my custom table, then (maybe) run the transaction
      $customer = $iats->result($response, FALSE);
      // print_r($customer);
      if ($customer['status']) {
        $processresult = $response->PROCESSRESULT;
        $customer_code = (string) $processresult->CUSTOMERCODE;
        $exp = sprintf('%02d%02d', ($params['year'] % 100), $params['month']);
        $email = '';
        if (isset($params['email'])) {
          $email = $params['email'];
        }
        elseif (isset($params['email-5'])) {
          $email = $params['email-5'];
        }
        elseif (isset($params['email-Primary'])) {
          $email = $params['email-Primary'];
        }
        $customercode_params = array(
          'customer_code' => $customer_code,
          'customerIPAddress' => $request['customerIPAddress'],
          'expiry' => $exp,
          'contactID' => $params['contactID'],
          'email' => $email,
          'contributionRecurID' => $parms['contributionRecurId'],
        );
        civicrm_api3('IatsPayments', 'customercodeadd', $customercode_params);
        $settings = CRM_Core_BAO_Setting::getItem('iATS Payments Extension', 'iats_settings');
        $allow_days = empty($settings['days']) ? array('-1') : $settings['days'];
        if (max($allow_days) <= 0) { // run the transaction immediately
          $iats = new iATS_Service_Request(array('type' => 'process', 'method' => 'cc_with_customer_code', 'iats_domain' => $this->_profile['iats_domain'], 'currencyID' => $params['currencyID']));
          $request = array('invoiceNum' => $params['invoiceID']);
          $request['total'] = sprintf('%01.2f', CRM_Utils_Rule::cleanMoney($params['amount']));
          $request['customerCode'] = $customer_code;
          $request['customerIPAddress'] = (function_exists('ip_address') ? ip_address() : $_SERVER['REMOTE_ADDR']);
          $response = $iats->request($credentials, $request);
          $result = $iats->result($response);
          if ($result['status']) {
            $params['contribution_status_id'] = 1; // success
            $params['payment_status_id'] = 1; // for versions >= 4.6.6, the proper key
            $params['trxn_id'] = trim($result['remote_id']) . ':' . time();
            $params['gross_amount'] = $params['amount'];
            $params['next_sched_contribution'] = strtotime('+' . $params['frequency_interval'] . ' ' . $params['frequency_unit']);
            return $params;
          }
          else {
            return self::error($result['reasonMessage']);
          }
        }
        else { // I've got a schedule to adhere to!
          $params['contribution_status_id'] = 2; // pending
          $params['payment_status_id'] = 2; // for versions >= 4.6.6, the proper key
          $from_time = _iats_contributionrecur_next(time(),$allow_days);
          $params['next_sched_contribution'] = $params['receive_date'] = date('Ymd', $from_time).'030000';
          return $params;
        }
        return self::error('Unexpected error');
      }
      else {
        return self::error($customer['reasonMessage']);
      }
    }
  }

  // Add a doTransferCheckout function
  function doTransferCheckout(&$params, $component = 'contribute') {
    if (!$this->_profile) {
      return self::error('Unexpected error, missing profile');
    }
    $this->_component = strtolower($component);
    /* save stuff for when I return, then redirect to the locally hosted payment page that direct posts to IATS */
    // todo: save Billing Address If Required
    try {
      CRM_Core_Session::storeSessionObjects(FALSE); // FALSE == don't reset
      CRM_Core_Session::singleton()->set('iats_dpm_' . $params['qfKey'], json_encode($params));
      $iatsdpm = array(
       'is_test' => ($this->_profile['mode'] == 'test') ? 1 : 0,
       'key' => $params['qfKey'],
      );
      CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/payment/iatsdpm',$iatsdpm)); 
    }
    catch (\Exception $e) {
      // internal error, log exception and display a generic message to the customer
      return $this->handleError('error', 'unknown processor error ' . $this->_paymentProcessor['payment_processor_type'], array($e->getCode() => $e->getMessage()), $e->getCode(), 'Sorry, there was an error processing your payment. Please try again later.');
    }
  }

  function changeSubscriptionAmount(&$message = '', $params = array()) {
    $userAlert = ts('You have updated the amount of this recurring contribution.');
    CRM_Core_Session::setStatus($userAlert, ts('Warning'), 'alert');
    return TRUE;
  }

  function cancelSubscription(&$message = '', $params = array()) {
    $userAlert = ts('You have cancelled this recurring contribution.');
    CRM_Core_Session::setStatus($userAlert, ts('Warning'), 'alert');
    return TRUE;
  }

  /*
   * We support backoffice even if we're using DPM
   */
  protected function supportsBackoffice() {
    return TRUE;
  }

  /*
   * We support future recurring start dates
   */
  protected function supportsFutureRecurStartDate() {
    return TRUE;
  }

  function &error($error = NULL) {
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
    elseif (is_string($error)) {
      $e->push(9002,
        0, NULL,
        $error
      );
    }
    else {
      $e->push(9001, 0, NULL, "Unknown System Error.");
    }
    return $e;
  }

  /**
   * Get array of fields that should be displayed on the payment form.
   *
   * @return array
   * @throws CiviCRM_API3_Exception
   */
  public function getPaymentFormFields() {
    if (!empty($this->_paymentProcessor['signature'])) {
      // using DPM
      return array();
    }
    return $this->getCreditCardFormFields();
  }


  /**
   * This function checks to see if we have the right config values.
   *
   * @return string
   *   The error message if any
   */
  public function checkConfig() {
    $error = array();

    if (empty($this->_paymentProcessor['user_name'])) {
      $error[] = ts('Agent Code is not set in the Administer CiviCRM &raquo; System Settings &raquo; Payment Processors.');
    }

    if (empty($this->_paymentProcessor['password'])) {
      $error[] = ts('Password is not set in the Administer CiviCRM &raquo; System Settings &raquo; Payment Processors.');
    }

    if (!empty($error)) {
      return implode('<p>', $error);
    }
    else {
      return NULL;
    }
  }

  /**
   * Convert the values in the civicrm params to the request array with keys as expected by iATS.
   *
   * @param array $params
   * @param string $method
   *
   * @return array
   */
  protected function convertParams($params, $method) {
    $request = array();
    $convert = array(
      'firstName' => 'billing_first_name',
      'lastName' => 'billing_last_name',
      'address' => 'street_address',
      'city' => 'city',
      'state' => 'state_province',
      'zipCode' => 'postal_code',
      'country' => 'country',
      'invoiceNum' => 'invoiceID',
      'creditCardNum' => 'credit_card_number',
      'cvv2' => 'cvv2',
    );

    foreach ($convert as $r => $p) {
      if (isset($params[$p])) {
        $request[$r] = htmlspecialchars($params[$p]);
      }
    }
    $request['creditCardExpiry'] = sprintf('%02d/%02d', $params['month'], ($params['year'] % 100));
    $request['total'] = sprintf('%01.2f', CRM_Utils_Rule::cleanMoney($params['amount']));
    // place for ugly hacks
    switch ($method) {
      case 'cc_create_customer_code':
        $request['ccNum'] = $request['creditCardNum'];
        unset($request['creditCardNum']);
        $request['ccExp'] = $request['creditCardExpiry'];
        unset($request['creditCardExpiry']);
        break;
      case 'cc_with_customer_code':
        foreach(array('creditCardNum','creditCardExpiry','mop') as $key) {
          if (isset($request[$key])) {
            unset($request[$key]);
          }
        }
        break;
    }
    if (!empty($params['credit_card_type'])) {
      $mop = array(
        'Visa' => 'VISA',
        'MasterCard' => 'MC',
        'Amex' => 'AMX',
        'Discover' => 'DSC',
      );
      $request['mop'] = $mop[$params['credit_card_type']];
    }
    // print_r($request); print_r($params); die();
    return $request;
  }

}

