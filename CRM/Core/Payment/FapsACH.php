<?php

require_once 'CRM/Core/Payment.php';

class CRM_Core_Payment_FapsACH extends CRM_Core_Payment_Faps {

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
    $this->_processorName    = ts('iATS Payments 1st American Payment System Interface, ACH');
    $this->use_cryptogram    = faps_get_setting('use_cryptogram');
  }

  /**
   * Get array of fields that should be displayed on the payment form for credit cards.
   * Use FAPS cryptojs to gather the senstive card information, if enabled.
   *
   * @return array
   */

  protected function getDirectDebitFormFields() {
    $fields =  $this->use_cryptogram ? array('cryptogram') : parent::getDirectDebitFormFields();
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
    $is_test = ($this->_mode == 'test' ? 1 : 0); 
    // FAPS has a funny thing called a 'category' that needs to be included with any ACH request.
    // The category is auto-generated in the getCategoryText function, using some default settings that can be overridden on the FAPS settings page.
    // Store it in params, will be used by my convert request call(s) later
    $params['ach_category_text'] = self::getCategoryText($credentials, $is_test, $ipAddress);

    $vault_key = $vault_id = '';
    if (($hasIsRecur  && $usingCrypto) || $isRecur) {
      // Store the params in a vault before attempting payment
      $options = array(
        'action' => 'VaultCreateAchRecord',
        'test' => $is_test,
      );
      $vault_request = new CRM_Iats_FapsRequest($options);
      $request = $this->convertParams($params, $options['action']);
      // auto-generate a compliant vault key  
      $vault_key = CRM_Iats_Transaction::generateVaultKey($request['ownerEmail']);
      $request['vaultKey'] = $vault_key;
      $request['ipAddress'] = $ipAddress;
      // Make the request.
      //CRM_Core_Error::debug_var('vault request', $request);
      $result = $vault_request->request($credentials, $request);
      // unset the cryptogram param, we can't use it again and don't want to return it anyway.
      unset($params['cryptogram']);
      //CRM_Core_Error::debug_var('vault result', $result);
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
        'action' => 'AchDebitUsingVault',
        'test' => ($this->_mode == 'test' ? 1 : 0),
      );
    }
    else { // set the simple sale option for taking the money
      $options = array(
        'action' => 'AchDebit',
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
      // put the old return param in just to be sure
      $params['contribution_status_id'] = 'Pending';
      // For versions >= 4.6.6, the proper key.
      $params['payment_status_id'] = 'Pending';
      $params['trxn_id'] = trim($result['data']['referenceNumber']).':'.time();
      $params['gross_amount'] = $params['amount'];
      // Core assumes that a pending result will have no transaction id, but we have a useful one.
      if (!empty($params['contributionID'])) {
        $contribution_update = array('id' => $params['contributionID'], 'trxn_id' => $params['trxn_id']);
        try {
          $result = civicrm_api3('Contribution', 'create', $contribution_update);
        }
        catch (CiviCRM_API3_Exception $e) {
          // Not a critical error, just log and continue.
          $error = $e->getMessage();
          Civi::log()->info('Unexpected error adding the trxn_id for contribution id {id}: {error}', array('id' => $recur_id, 'error' => $error));
        }
      }
      return $params;
    }
    else {
      return self::error($result);
    }
  }

  /**
   * Get the category text. 
   * Before I return it, check that the category text exists, and create it if it doesn't.
   *
   * FAPS has a funny thing called a 'category' that needs to be included with any ACH request.
   * This function will test if a category text string exists and create it if it doesn't
   *
   * @param string $ach_category_text
   * @param array $credentials
   *
   * @return none
   */
  public static function getCategoryText($credentials, $is_test, $ipAddress = NULL) {
    static $ach_category_text_saved;
    if (!empty($ach_category_text_saved)) {
      return $ach_category_text_saved;
    } 
    $ach_category_text = faps_get_setting('ach_category_text');
    $ach_category_text = empty($ach_category_text) ? FAPS_DEFAULT_ACH_CATEGORY_TEXT : $ach_category_text;
    $ach_category_exists = FALSE;
    // check if it's setup
    $options = array(
      'action' => 'AchGetCategories',
      'test' => $is_test,
    );
    $categories_request = new CRM_Iats_FapsRequest($options);
    $request = empty($ipAddress) ? array() : array('ipAddress' => $ipAddress);
    $result = $categories_request->request($credentials, $request);
    // CRM_Core_Error::debug_var('categories request result', $result);
    if (!empty($result['isSuccess']) && !empty($result['data'])) {
      foreach($result['data'] as $category) {
        if ($category['achCategoryText'] == $ach_category_text) {
          $ach_category_exists = TRUE;
          break;
        }
      }
    }
    if (!$ach_category_exists) { // set it up!
      $options = array(
        'action' => 'AchCreateCategory',
        'test' => $is_test,
      );
      $categories_request = new CRM_Iats_FapsRequest($options);
      // I've got some non-offensive defaults in here.
      $request = array(
        'achCategoryText' => $ach_category_text,
        'achClassCode' => 'WEB',
        'achEntry' => 'CiviCRM',
      );
      if (!empty($ipAddress)) {
        $request['ipAddress'] = $ipAddress;
      }
      $result = $categories_request->request($credentials, $request);
      // I'm being a bit naive and assuming it succeeds.
    }
    return $ach_category_text_saved = $ach_category_text;
  }

  /**
   * Convert the values in the civicrm params to the request array with keys as expected by FAPS
   * ACH has different fields from credit card.
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
      'achCryptogram' => 'cryptogram',
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
    $request['transactionAmount'] = sprintf('%01.2f', CRM_Utils_Rule::cleanMoney($params['amount']));
    $request['categoryText'] = $params['ach_category_text'];
    return $request;
  }

}
