<?php
/* IATS Service Request Object used for accessing IATS Service Interface 
 *
 * A lightweight object that encapsulates the details of the IATS Webservice interface
 *
 * Provides SOAP interface details for the various methods,
 * error messages, and cc details
 * 
 * Require the method id string on construction and any options like trace, logging.
 * Require the specific payment details, and the client credentials, on request
 *
 * TODO: provide logging options for the request, exception and response
 * 
 * Expected usage:
 * $iats = new IATS_Service_Request($method_code, $options)  
 * where: method code is 'cc', etc., options allows for logging options 
 * $response = $iats->request($credentials,$payment)
 * the request method encapsulates the soap inteface and requires IATS client details + payment info (cc + amount + billing info)
 * $result = $iats->response($response) 
 * the 'response' method converts the soap response into a nicer format 
 **/

Class IATS_Service_Request {

  /* check IATS website for additional supported currencies */
  CONST CURRENCIES = 'CAD,USD,AUD,GBP,EUR,NZD';
  // IATS transaction mode definitions:
  CONST IATS_TXN_NS = 'xmlns';
  CONST IATS_TXN_TRACE = TRUE;
  CONST IATS_TXN_SUCCESS = 'Success';
  CONST IATS_TXN_OK = 'OK';
  CONST IATS_URL_PROCESSLINK = 'https://www.iatspayments.com/NetGate/ProcessLink.asmx?WSDL';
  CONST IATS_URL_REPORTLINK = 'https://www.iatspayments.com/NetGate/ReportLink.asmx?WDSL';
  CONST IATS_URL_CUSTOMERLINK = 'https://www.iatspayments.com/NetGate/CustomerLink.asmx?WDSL';

  function __construct($method, $options = array()) {
    $this->method = $this->methodInfo($method);
    // TODO: check that the method is allowed!
    // initialize the request array
    $this->request = array(
      'clientLibrary' => 'PHP',
      'clientLibraryVersion' => phpversion(),
      'clientEnvironment' => php_uname(),
    );
    // TODO: make this url configurable on object construction?
    $this->_wsdl_url = self::IATS_URL_PROCESSLINK;
    // name space url
    $this->_wsdl_url_ns = 'https://www.iatspayments.com/NetGate/';
    // TODO: go through options and ensure defaults 
    $this->options = $options;
    $this->options['log'] = array('all' => 1);
    $this->options['trace'] = 1;
  }

  /**
   * Submits an API request through the IATS SOAP API Toolkit.
   *
   * @param $request
   *   The request object or array containing the parameters of the requested services.
   *
   * @return
   *   The response object from the API with properties pertinent to the requested
   *     services.
   */
  function request($credentials, $payment) {
    // Attempt the SOAP request and log the exception on failure.
    $method = $this->method['method']; 
    $message = $this->method['message'];
    $response = $this->method['response'];
    // TODO log request if specified 
    if (!empty($this->options['log']['all']) || !empty($this->options['log']['payment'])) {
      $log_request = $payment;
      $this->mask($log_request);
      watchdog('civicrm_ca_iats', 'Payment info: !request', array('!request' => '<pre>' . print_r($log_request, TRUE) . '</pre>'), WATCHDOG_NOTICE);
    }

    // the agent user and password only get put in here so they don't end up in a log above
    try {
      $soapClient = new SoapClient($this->_wsdl_url, array('trace' => $this->options['trace']));
      /* build the request manually as per the IATS docs */
      $xml = '<'.$message.' xmlns="'.$this->_wsdl_url_ns.'">';
      $request = array_merge($this->request,(array) $payment, (array) $credentials);
      foreach($request as $k => $v) {
         $xml .= '<'.$k.'>'.$v.'</'.$k.'>';
      }
      $xml .= '</'.$message.'>';
      if (!empty($this->options['log']['all']) || !empty($this->options['log']['xml'])) {
         watchdog('civicrm_ca_iats', 'Method info: !method', array('!method' => $method), WATCHDOG_NOTICE);
      }
      $soapRequest = new SoapVar($xml, XSD_ANYXML);
      $soapResponse = $soapClient->$method($soapRequest);
      if (!empty($this->options['log']['all']) || !empty($this->options['log']['request'])) {
         $response_log = "\n HEADER:\n";
         $response_log .= $soapClient->__getLastRequestHeaders();
         $response_log = "\n BODY:\n";
         $response_log .= $soapClient->__getLastRequest();
         $response_log = "\n BODYEND:\n";
         watchdog('civicrm_ca_iats', 'Response: !response', array('!response' => '<pre>' . $response_log . '</pre>'), WATCHDOG_NOTICE);
      }
    }
    catch (SoapFault $exception) {
      if (!empty($this->options['log']['all']) || !empty($this->options['log']['exception'])) {
        watchdog('civicrm_ca_iats', 'SoapFault: !exception', array('!exception' => '<pre>' . print_r($exception, TRUE) . '</pre>'), WATCHDOG_ERROR);
      } 
      return FALSE;
    }
  
    // Log the response if specified.
    if (!empty($this->options['log']['all']) || !empty($this->options['log']['respnose'])) {
      watchdog('commerce_ca_iats', 'IATS SOAP response: !request', array('!request' => '<pre>' . check_plain(print_r($soapResponse, TRUE)) . '</pre>', WATCHDOG_DEBUG));
    } 
    $xml_response = $soapResponse->$response->any;
    return new SimpleXMLElement($xml_response);
  }

  /*
  * Process the response to the request into a more friendly format in an array $result;
  */
 
  function result($response) {
    $processresult = $response->PROCESSRESULT;
    $auth_result = trim(current($processresult->AUTHORIZATIONRESULT));
    $result = array('auth_result' => $auth_result,
                    'remote_id' => current($processresult->TRANSACTIONID)
    );
    // If we didn't get an approval response code...
    // Note: do not use SUCCESS property, which just means IATS said "hello"
    $result['status'] = (substr($auth_result,0,2) == self::IATS_TXN_OK) ? 1 : 0; 

    // If the payment failed, display an error and rebuild the form.
    if (!$result['status']) {
      $result['reasonMessage'] = $this->reasonMessage($auth_result);
      if ($auth_result == 'REJECT: 5') {
        //drupal_set_message('You may have interrupted an authorization in progress - please contact us to process/complete your order.', 'error');
      }
      else {
        //drupal_set_message('Please enter your information again or try a different card.', 'error');
      }     
    }
    return $result;
  }

  /* 
   * Provides the soap parameters for each of the ways to process payments at IATS Services
   * Parameters are: method, message and response, these are all soap object properties
   * Title and description provide a public information interface, not used internally
   */  
  function methodInfo($method = '') {
    $desc = 'Integrates the IATS SOAP webservice: ';
    $methods = array(
      'cc' => array(
        'title' => 'Credit card',
        'description'=> $desc. 'ProcessCreditCardV1',
        'method' => 'ProcessCreditCard',
        'message' => 'ProcessCreditCardV1',
        'response' => 'ProcessCreditCardV1Result',
      ), 
      'cc_create_customer_code' => array(
        'title' => 'Credit card, saved',
        'description' => $desc. 'CreateCustomerCodeAndProcessCreditCardV1',
        'method' => 'CreateCustomerCodeAndProcessCreditCard',
        'message' => 'CreateCustomerCodeAndProcessCreditCardV1',
        'response' => 'CreateCustomerCodeAndProcessCreditCardV1Result',
      ), 
      'cc_with_customer_code' => array(
        'title' => 'Credit card using saved info',
        'description' => $desc. 'ProcessCreditCardWithCustomerCodeV1',
        'method' => 'ProcessCreditCardWithCustomerCode',
        'message' => 'ProcessCreditCardWithCustomerCodeV1',
        'response' => 'ProcessCreditCardWithCustomerCodeV1Result',
      ), 
      'acheft' => array(
        'title' => 'Debit Card',
        'description' => $desc. 'ProcessACHEFTV1',
        'method' => 'ProcessACHEFT',
        'message' => 'ProcessACHEFTV1',
        'response' => 'ProcessACHEFTV1Result',
      ), 
      'acheft_with_customer_code' => array(
        'title' => 'Debit Card with customer code',
        'description' => $desc. 'ProcessACHEFTWithCustomerCodeV1',
        'method' => 'ProcessACHEFTWithCustomerCode',
        'message' => 'ProcessACHEFTWithCustomerCodeV1',
        'response' => 'ProcessACHEFTWithCustomerCodeV1Result',
      ),
    );
    if ($method) {
      return $methods[$method];
    }
    return $methods;
  }

  
  /** 
   * Returns the message text for a credit card service reason code.
   * As per iats error codes
   * https://www.iatspayments.com/english/help/rejects.html
   * TODO: multilingual options?
   */
  function reasonMessage($code) {
    switch ($code) {
      
      case 'REJECT: 1':
         return 'Agent code has not been set up on the authorization system.';
      case 'REJECT: 2':
         return 'Unable to process transaction. Verify and re-enter credit card information.';
      case 'REJECT: 3':
         return 'Invalid Customer Code.';
      case 'REJECT: 4':
         return 'Incorrect expiration date.';
      case 'REJECT: 5':
         return 'Invalid transaction. Verify and re-enter credit card information.';
      case 'REJECT: 6':
         return 'Transaction not supported by institution.';
      case 'REJECT: 7':
         return 'Lost or stolen card.';
      case 'REJECT: 8':
         return 'Invalid card status.';
      case 'REJECT: 9': 
         return 'Restricted card status. Usually on corporate cards restricted to specific sales.';
      case 'REJECT: 10':
         return 'Error. Please verify and re-enter credit card information.';
      case 'REJECT: 11':
         return 'General decline code. Please have client call the number on the back of credit card.';
      case 'REJECT: 12':
         return 'Incorrect CVV2 or Expiry date.';
      case 'REJECT: 14':
         return 'The card is over the limit.';
      case 'REJECT: 15':
         return 'General decline code. Please have client call the number on the back of credit card.';
      case 'REJECT: 16':
         return 'Invalid charge card number. Verify and re-enter credit card information.';
      case 'REJECT: 17':
         return 'Unable to authorize transaction. Authorizer needs more information for approval.';
      case 'REJECT: 18':
         return 'Card not supported by institution.';
      case 'REJECT: 19':
         return 'Incorrect CVV2 security code.';
      case 'REJECT: 22':
         return 'Bank timeout. Bank lines may be down or busy. Re-try transaction later.';
      case 'REJECT: 23':
         return 'System error. Re-try transaction later.';
      case 'REJECT: 24':
         return 'Charge card expired.';
      case 'REJECT: 25':
         return 'Capture card. Reported lost or stolen.';
      case 'REJECT: 26':
         return 'Invalid transaction, invalid expiry date. Please confirm and retry transaction.';
      case 'REJECT: 27':
         return 'Please have cardholder call the number on the back of credit card.';
      case 'REJECT: 39':
         return 'Contact IATS 1-888-955-5455.';
      case 'REJECT: 40':
         return 'Invalid cc number. Card not supported by IATS.';
      case 'REJECT: 41':
         return 'Invalid Expiry date.';
      case 'REJECT: 42':
         return 'CVV2 required.';
      case 'REJECT: 43':
         return 'Incorrect AVS.';
      case 'REJECT: 100':
         return 'DO NOT REPROCESS.';
      case 'Timeout':
         return 'The system has not responded in the time allotted. Please contact IATS at 1-888-955-5455.';
    }
  
    return $code;
  }
  
  /**
   * Returns the message text for a CVV match.
   * This function not currently in use
   */
  function cvnResponse($code) {
    switch ($code) {
      case 'D':
        return t('The transaction was determined to be suspicious by the issuing bank.');
      case 'I':
        return t("The CVN failed the processor's data validation check.");
      case 'M':
        return t('The CVN matched.');
      case 'N':
        return t('The CVN did not match.');
      case 'P':
        return t('The CVN was not processed by the processor for an unspecified reason.');
      case 'S':
        return t('The CVN is on the card but was not included in the request.');
      case 'U':
        return t('Card verification is not supported by the issuing bank.');
      case 'X':
        return t('Card verification is not supported by the card association.');
      case '1':
        return t('Card verification is not supported for this processor or card type.');
      case '2':
        return t('An unrecognized result code was returned by the processor for the card verification response.');
      case '3':
        return t('No result code was returned by the processor.');
    }
  
    return '-';
  }
  
  function creditCardTypes() {
    return array(
      'VI' => t('Visa'),
      'MC' => t('MasterCard'),
      'AMX' => t('American Express'),
      'DISC' => t('Discover Card'),
    );
  }

  function mask(&$log_request) {
    // Mask the credit card number and CVV.
    foreach(array('creditCardNum','cvv2','ccNum') as $mask) {
      if (!empty($log_request[$mask])) {
        if (4 < strlen($log_request[$mask])) { // show the last four digits of cc numbers
          $log_request[$mask] = str_repeat('X', strlen($log_request[$mask]) - 4) . substr($log_request[$mask], -4);
        }
        else {
          $log_request[$mask] = str_repeat('X', strlen($log_request[$mask]));
        }
      }
    }
  }
} 
