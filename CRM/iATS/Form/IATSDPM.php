<?php

require_once 'CRM/Core/Form.php';

/**
 * Form controller class
 *
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC43/QuickForm+Reference
 */
class CRM_iATS_Form_IATSDPM extends CRM_Core_Form {

  public $_myMatch = '';

  private function cleaner_url($url) {
    return str_replace('amp;','',$url);
  }

  /**
   * Get the field names and labels expected by iATS DPM,
   * and the corresponding fields in CiviCRM
   *
   * @return array
   */
  public function getFields() {
    $civicrm_fields = array(
      'IATS_DPM_FirstName' => 'first_name',
      'IATS_DPM_LastName' => 'last_name',
      'IATS_DPM_Email' => 'email',
      'IATS_DPM_Address' => 'street_address',
      'IATS_DPM_City' => 'city',
      'IATS_DPM_Province' => 'state_province',
      'IATS_DPM_Country' => 'country',
      'IATS_DPM_ZipCode' => 'postal_code',
      'IATS_DPM_Phone' => 'phone',
      'IATS_DPM_AccountNumber' => 'credit_card_number',
      'IATS_DPM_ExpiryDate' => 'credit_card_expiry',
      'IATS_DPM_ExpiryDate' => 'credit_card_expiry',
      'IATS_DPM_Amount' => 'amount',
      'IATS_DPM_Invoice' => 'invoiceID',
      /* 'IATS_DPM_mop' => 'credit_card_type', */
    );
    $labels = array(
      //'IATS_DPM_firstName' => 'First Name',
      // 'IATS_DPM_lastName' => 'Last Name',
      'IATS_DPM_FirstName' => 'First Name',
      'IATS_DPM_LastName' => 'Last Name',
      'IATS_DPM_Email' => 'Email',
      'IATS_DPM_Address' => 'Street Address',
      'IATS_DPM_City' => 'City',
      'IATS_DPM_Province' => 'State or Province',
      'IATS_DPM_ZipCode' => 'Postal Code or Zip Code',
      'IATS_DPM_AccountNumber' => 'Credit Card Number',
      'IATS_DPM_ExpiryDate' => 'Credit Card Expiry Date',
      'IATS_DPM_CVV2' => 'CVV',
    /*  'IATS_DPM_mop' => 'Credit Card Type', */
    );
    return array($civicrm_fields, $labels);
  }

  protected function getCustomerCodeDetail($params) {
    require_once("CRM/iATS/iATSService.php");
    $credentials = iATS_Service_Request::credentials($params['paymentProcessorId'], $params['is_test']);
    $iats_service_params = array('type' => 'customer', 'iats_domain' => $credentials['domain'], 'method' => 'get_customer_code_detail');
    $iats = new iATS_Service_Request($iats_service_params);
    // print_r($iats); die();
    $request = array('customerCode' => $params['customerCode']);
    // make the soap request
    $response = $iats->request($credentials,$request);
    $customer = $iats->result($response, FALSE); // note: don't log this to the iats_response table
    if (empty($customer['ac1'])) {
      $alert = ts('Unable to retrieve card details from iATS.<br />%1', array(1 => $customer['AUTHORIZATIONRESULT']));
      throw new Exception($alert);
    }
    $ac1 = $customer['ac1']; // this is a SimpleXMLElement Object
    $card = get_object_vars($ac1->CC);
    return $customer + $card;
  }

  protected function updateCreditCardCustomer($params) {
    require_once("CRM/iATS/iATSService.php");
    $credentials = iATS_Service_Request::credentials($params['paymentProcessorId'], $params['is_test']);
    unset($params['paymentProcessorId']);
    unset($params['is_test']);
    unset($params['domain']);
    $iats_service_params = array('type' => 'customer', 'iats_domain' => $credentials['domain'], 'method' => 'update_credit_card_customer');
    $iats = new iATS_Service_Request($iats_service_params);
    // print_r($iats); die();
    $params['updateCreditCardNum'] = (0 < strlen($params['creditCardNum']) && (FALSE === strpos($params['creditCardNum'],'*'))) ? 1 : 0;
    if (empty($params['updateCreditCardNum'])) {
      unset($params['creditCardNum']);
      unset($params['updateCreditCardNum']);
    }
    $params['customerIPAddress'] = (function_exists('ip_address') ? ip_address() : $_SERVER['REMOTE_ADDR']);
    foreach(array('qfKey','entryURL','firstName','lastName','_qf_default','_qf_IATSDPM_submit') as $key) {
      if (isset($params[$key])) {
        unset($params[$key]);
      }
    }
    // make the soap request
    $response = $iats->request($credentials,$params);
    $result = $iats->result($response, TRUE); // note: don't log this to the iats_response table
    return $result;
  }   

  function myMatch($match) {
    return strpos($match, $this->_myMatch) === 0;
  }

  function buildQuickForm() {
    require_once("CRM/iATS/iATSService.php");
    $session_key = CRM_Utils_Request::retrieve('key', 'String');
    $success = CRM_Utils_Request::retrieve('success', 'String');
    $params = json_decode(CRM_Core_Session::singleton()->get('iats_dpm_' . $session_key),TRUE); // , json_encode($params));
    $params['is_test'] = CRM_Utils_Request::retrieve('is_test', 'Integer');
    if ('1' === $success) {
      // redirect to thank you page
      CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/contribute/transact', "_qf_ThankYou_display=1&qfKey=$session_key", TRUE, NULL, FALSE));
    }
    elseif ('0' === $success) { // get the most recent response from the log
      $auth_result = civicrm_api3('IatsPayments', 'getresponse', array('invoice_id' => $params['invoiceID']));
      $error = empty($auth_result) ? '' : iATS_Service_Request::reasonMessage($auth_result['values']);
      $alert = ts('Unable to process card, please try again.<br />%1', array(1 => $error));
      CRM_Core_Session::setStatus($alert, ts('Warning'), 'alert');
    }
    $param_keys = array_keys($params);
    list($civicrm_fields, $labels) = $this->getFields();
    foreach(array('email','street_address','city','state_province','postal_code','country') as $key) {
      if (!isset($params[$key])) {
        $this->_myMatch = $key.'-';
        $filtered = array_filter($param_keys, array($this,'myMatch'));
        $value = $params[current($filtered)];
        // for state_prov and country, convert code to value
        if ($key == 'state_province' || $key == 'country') {
          $value = CRM_Core_Pseudoconstant::getLabel('CRM_Core_BAO_Address', $key.'_id', $value);
        }
        $params[$key] = $value;
      }
    }
    foreach($labels as $name => $label) {
      $this->add('text', $name, $label);
    }

    $credentials = iATS_Service_Request::credentials($params['payment_processor_id'], $params['is_test']);
    $this->setFormAction('https://'.$credentials['domain'].iATS_Service_Request::iATS_URL_DPMPROCESS);
    $this->add('hidden','IATS_DPM_ProcessID');
    $this->add('hidden','IATS_DPM_PostBackURL');
    $this->add('hidden','IATS_DPM_SuccessRedirectURL');
    $this->add('hidden','IATS_DPM_FailedRedirectURL');
    $this->add('hidden','IATS_DPM_Amount');
    $this->add('hidden','IATS_DPM_Invoice');
    $this->add('hidden','IATS_DPM_Item1');
    $iatsdpm = array(
      'is_test' => $params['is_test'],
      'key' => $session_key
    );
    $postback_url = $this->cleaner_url(CRM_Utils_System::url('civicrm/payment/iatsdpm_postback',$iatsdpm, TRUE));
    $iatsdpm['success'] = '1';
    $success_redirect_url = $this->cleaner_url(CRM_Utils_System::url('civicrm/payment/iatsdpm',$iatsdpm, TRUE));
    $iatsdpm['success'] = '0';
    $failed_redirect_url = $this->cleaner_url(CRM_Utils_System::url('civicrm/payment/iatsdpm',$iatsdpm, TRUE));
    $defaults = array(
      'IATS_DPM_Amount' => $params['amount'],
      'IATS_DPM_Invoice' => $params['invoiceID'],
      'IATS_DPM_ProcessID' => $credentials['signature'],
      'IATS_DPM_PostBackURL' => $postback_url,
      'IATS_DPM_SuccessRedirectURL' => $success_redirect_url,
      'IATS_DPM_FailedRedirectURL' =>  $failed_redirect_url,
      'IATS_DPM_Item1' => (function_exists('ip_address') ? ip_address() : $_SERVER['REMOTE_ADDR']),
    );
    foreach($civicrm_fields as $iats_key => $civicrm_key) {
      if (!empty($params[$civicrm_key])) {
        $defaults[$iats_key] = $params[$civicrm_key];
      }
    }
    $this->setDefaults($defaults);
    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => ts('Submit'),
        'isDefault' => TRUE,
      ),
      array(
        'type' => 'cancel',
        'name' => ts('Back')
      )
    ));
    // export form elements
    $this->assign('elementNames', $this->getRenderableElementNames());
    parent::buildQuickForm();
  }

  function postProcess() {
    $values = $this->exportValues();
    // send update to iATS
    print_r($values); die();
    $result = $this->updateCreditCardCustomer($values);
    $message = '<pre>'.print_r($result,TRUE).'</pre>';
    CRM_Core_Session::setStatus($message, 'Customer Updated'); // , $type, $options);
    if ($result['AUTHORIZATIONRESULT'] == 'OK') {
      // update my copy of the expiry date
      list($month,$year) = explode('/',$values['creditCardExpiry']);
      $exp = sprintf('%02d%02d', $year, $month);
      $query_params = array(
        1 => array($values['customerCode'], 'String'),
        2 => array($exp, 'String'),
      );
      CRM_Core_DAO::executeQuery("UPDATE civicrm_iats_customer_codes SET expiry = %2 WHERE customer_code = %1", $query_params);
    }
    parent::postProcess();
  }

  /**
   * Get the fields/elements defined in this form.
   *
   * @return array (string)
   */
  function getRenderableElementNames() {
    // The _elements list includes some items which should not be
    // auto-rendered in the loop -- such as "qfKey" and "buttons".  These
    // items don't have labels.  We'll identify renderable by filtering on
    // the 'label'.
    $elementNames = array();
    foreach ($this->_elements as $element) {
      $label = $element->getLabel();
      if (!empty($label)) {
        $elementNames[] = $element->getName();
      }
    }
    return $elementNames;
  }
}
