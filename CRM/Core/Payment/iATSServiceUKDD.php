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
 * for UK Direct Debit Recurring contributions ONLY
 */
class CRM_Core_Payment_iATSServiceUKDD extends CRM_Core_Payment {

  /**
   * We only need one instance of this object. So we use the singleton
   * pattern and cache the instance in this variable
   *
   * @var object
   * @static
   */
  static private $_singleton = NULL;

  /**
   * Constructor
   *
   * @param string $mode the mode of operation: live or test
   *
   * @return void
   */ 
   function __construct($mode, &$paymentProcessor) {
    $this->_paymentProcessor = $paymentProcessor;
    $this->_processorName = ts('iATS Payments UK Direct Debit');

    // get merchant data from config
    $config = CRM_Core_Config::singleton();
    // live or test
    $this->_profile['mode'] = $mode;
    // we only use the domain of the configured url, which is different for NA vs. UK
    $this->_profile['iats_domain'] = parse_url($this->_paymentProcessor['url_site'], PHP_URL_HOST);
  }

  static function &singleton($mode, &$paymentProcessor) {
    $processorName = $paymentProcessor['name'];
    if (self::$_singleton[$processorName] === NULL) {
      self::$_singleton[$processorName] = new CRM_Core_Payment_iATSServiceUKDD($mode, $paymentProcessor);
    }
    return self::$_singleton[$processorName];
  }

  function doDirectPayment(&$params) {

    if (!$this->_profile) {
      return self::error('Unexpected error, missing profile');
    }
    // use the iATSService object for interacting with iATS
    require_once("CRM/iATS/iATSService.php");
    $isRecur =  CRM_Utils_Array::value('is_recur', $params) && $params['contributionRecurID'];
    if (!$isRecur) {
      return self::error('Not a recurring contribution: you can only use UK Direct Debit with a recurring contribution.');
    }
    if ('GBP' != $params['currencyID']) {
      return self::error(ts('Invalid currency %1, must by GBP',array(1 => $params['currencyID'])));
    }
    if (empty($params['installments'])) {
      return self::error(ts('You must specify a number of installments, open-ended contributions are not allowed.'));
    } 
    if (1 >= $params['installments'])) {
      return self::error(ts('You must specify a number of installments greater than 1.'));
    } 
    // convert params recurring information into iATS equivalents  
    $scheduleType = NULL;
    $paymentsRecur = $params['installments'] - 1;
    // IATS requires begin and end date, calculated here
    // to be converted to date format later
    // begin date has to be at least 12 days from now [allow configurability?]
    $beginTime = strtotime('+12 days');
    $date = getdate($beginTime);
    $interval = $params['frequency_interval'] ? $params['frequency_interval'] : 1;
    switch ($params['frequency_unit']) {
      case 'week':
        if (1 != $interval) {
          return self::error(ts('You can only choose each week on a weekly schedule.'));
        } 
        $scheduleType = 'Weekly';
        $scheduleDate = $date['wday'] + 1;
        $endTime      = $beginTime + ($paymentsRecur * 7 * 24 * 60 * 60);
        break;

      case 'month':
        $scheduleType = 'Monthly';
        $scheduleDate = $date['mday'];
        if (3 == $interval) {
          $scheduleType = 'Quarterly';
          $scheduleDate = '';
        } 
        elseif (1 != $interval) {
          return self::error(ts('You can only choose monthly or every three months (quarterly) for a monthly schedule.'));
        }
        $date['mon'] += ($interval * $paymentsRecur);
        while ($date['mon'] > 12) {
          $date['mon'] -= 12;
          $date['year'] += 1;
        }
        $endTime = mktime($date['hours'], $date['minutes'], $date['seconds'], $date['mon'], $date['mday'], $date['year']);
        break;

      case 'year':
        if (1 != $interval) {
          return self::error(ts('You can only choose each year for a yearly schedule.'));
        }
        $scheduleType = 'Yearly';
        $scheduleDate = '';
        $date['year'] += $paymentsRecur;
        $endTime = mktime($date['hours'], $date['minutes'], $date['seconds'], $date['mon'], $date['mday'], $date['year']);
        break;

      default:
        return self::error(ts('Invalid frequency unit: %1',array(1 => $params['frequency_unit'])));
        break;

    }
    $endDate = date('Y-m-d', $endTime);
    $beginDate = date('Y-m-d', $beginTime);

    $iats = new iATS_Service_Request(array('type' => 'process', 'method' => 'direct_debit_acheft_payer_validate', 'iats_domain' => $this->_profile['iats_domain'], 'currencyID' => $params['currencyID']));
    $request = $this->convertParamsValidate($params);
    $request['beginDate'] = $beginDate;
    $request['endDate'] = $endDate;
    $request['customerIPAddress'] = (function_exists('ip_address') ? ip_address() : $_SERVER['REMOTE_ADDR']);
    $credentials = array('agentCode' => $this->_paymentProcessor['user_name'],
                         'password'  => $this->_paymentProcessor['password' ]);
    // Get the API endpoint URL for the method's transaction mode.
    // TODO: enable override of the default url in the request object
    // $url = $this->_paymentProcessor['url_site'];

    // make the soap request
    $response = $iats->request($credentials,$request); 
    // process the soap response into a readable result
    $result = $iats->result($response);
    return $params;
    /* 
    if ($result['status']) {
      $params['trxn_id'] = $result['remote_id'] . ':' . time();
      $params['gross_amount'] = $params['amount'];
      if ($isRecur) {
        // save the client info in my custom table
        // Allow further manipulation of the arguments via custom hooks,
        // before initiating processCreditCard()
        // CRM_Utils_Hook::alterPaymentProcessorParams($this, $params, $iatslink1);
        $processresult = $response->PROCESSRESULT; 
        $customer_code = $processresult->CUSTOMERCODE;
        $exp = sprintf('%02d%02d', ($params['year'] % 100), $params['month']);
        if (isset($params['email'])) {
          $email = $params['email'];
        }
        elseif(isset($params['email-5'])) {
          $email = $params['email-5'];
        }
        elseif(isset($params['email-Primary'])) {
          $email = $params['email-Primary'];
        }
        $query_params = array(
          1 => array($customer_code, 'String'),
          2 => array($request['customerIPAddress'], 'String'),
          3 => array($exp, 'String'),
          4 => array($params['contactID'], 'Integer'),
          5 => array($email, 'String'),
          6 => array($params['contributionRecurID'], 'Integer'),
        );
        CRM_Core_DAO::executeQuery("INSERT INTO civicrm_iats_customer_codes
          (customer_code, ip, expiry, cid, email, recur_id) VALUES (%1, %2, %3, %4, %5, %6)", $query_params);
        $params['contribution_status_id'] = 1;
        // also set next_sched_contribution
        $params['next_sched_contribution'] = strtotime('+'.$params['frequency_interval'].' '.$params['frequency_unit']);
      }
      return $params;
    }
    else {
      return self::error($result['reasonMessage']);
    } */
  }

  /* TODO: requires custom link
  function changeSubscriptionAmount(&$message = '', $params = array()) {
    $userAlert = ts('You have updated the amount of this recurring contribution.');
    CRM_Core_Session::setStatus($userAlert, ts('Warning'), 'alert');
    return TRUE;
  } */

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
   * This function checks to see if we have the right config values
   *
   * @param  string $mode the mode we are operating in (live or test)
   *
   * @return string the error message if any
   * @public
   */
  function checkConfig() {
    $error = array();

    if (empty($this->_paymentProcessor['user_name'])) {
      $error[] = ts('Agent Code is not set in the Administer CiviCRM &raquo; System Settings &raquo; Payment Processors.');
    }

    if (empty($this->_paymentProcessor['password'])) {
      $error[] = ts('Password is not set in the Administer CiviCRM &raquo; System Settings &raquo; Payment Processors.');
    }
    $iats_domain = parse_url($this->_paymentProcessor['url_site'], PHP_URL_HOST);
    if ('www.uk.iatspayments.com' != $iats_domain) {
      $error[] = ts('You can only use this payment processor with a UK iATS account');
    }
    if (!empty($error)) {
      return implode('<p>', $error);
    }
    else {
      return NULL;
    }
  }

  /*
   * Convert the values in the civicrm params to the request array with keys as expected by iATS
   */
  function convertParamsValidate($params) {
    $request = array();
    $convert = array(
      'firstName' => 'billing_first_name',
      'lastName' => 'billing_last_name',
      'address' => 'street_address',
      'city' => 'city',
      'state' => 'state_province',
      'zipCode' => 'postal_code',
      'country' => 'country',
    );
 
    foreach($convert as $r => $p) {
      if (isset($params[$p])) {
        $request[$r] = $params[$p];
      }
    }
    // account custom name is first name + last name, truncated to a maximum of 30 chars
    $request['accountCustomerName'] = substr($request['firstName'].' '.$request['lastName'],0,30);
    return $request;
  }
 
}

