<?php
/* Copyright iATS Payments (c) 2016
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
class CRM_Core_Payment_iATSServiceDPM extends CRM_Core_Payment_iATSService {

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
    $this->_processorName = ts('iATS Payments DPM');

    // get merchant data from config
    $config = CRM_Core_Config::singleton();
    // live or test
    $this->_profile['mode'] = $mode;
    // we only use the domain of the configured url, which is different for NA vs. UK
    $this->_profile['iats_domain'] = parse_url($this->_paymentProcessor['url_site'], PHP_URL_HOST);
  }

  static function &singleton($mode, &$paymentProcessor, &$paymentForm = NULL, $force = FALSE) {
    $processorName = $paymentProcessor['name'];
    if (self::$_singleton[$processorName] === NULL) {
      self::$_singleton[$processorName] = new CRM_Core_Payment_iATSServiceDPM($mode, $paymentProcessor);
    }
    return self::$_singleton[$processorName];
  }

  // Add a doTransferCheckout function

  function doTransferCheckout(&$params, $component = 'contribute') {
    if (!$this->_profile) {
      return self::error('Unexpected error, missing profile');
    }
    $this->_component = strtolower($component);
    /* save stuff for when I retrun, then redirect to the locally hosted payment page that postss to IATS */
    // $this->_paymentProcessor['payment_processor_type'] = ?
    // $this->setProcessorFields();
    // $this->setTransactionID(CRM_Utils_Array::value('contributionID', $params));
    // $this->storeReturnUrls($params['qfKey'], CRM_Utils_Array::value('participantID', $params), CRM_Utils_Array::value('eventID', $params));
    // $this->saveBillingAddressIfRequired($params);
    try {
      CRM_Core_Session::storeSessionObjects(FALSE); // don't reset
      CRM_Core_Session::singleton()->set('iats_dpm_' . $params['qfKey'], json_encode($params));
      // $this->storeTransparentRedirectFormData($params['qfKey'], array()); /* $response->getRedirectData() + array(
      //      'payment_processor_id' => $this->_paymentProcessor['id'],
      //      'post_submit_url' => $response->getRedirectURL(),
       //   )); 
      $iatsdpm = array(
       'is_test' => ($this->_profile['mode'] == 'test') ? 1 : 0,
       'key' => $params['qfKey'],
      );
      CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/payment/iatsdpm',$iatsdpm)); 
    }
    catch (\Exception $e) {
      // internal error, log exception and display a generic message to the customer
      //@todo - looks like invalid credit card numbers are winding up here too - we could handle separately by capturing that exception type - what is good fraud practice?
      return $this->handleError('error', 'unknown processor error ' . $this->_paymentProcessor['payment_processor_type'], array($e->getCode() => $e->getMessage()), $e->getCode(), 'Sorry, there was an error processing your payment. Please try again later.');
    }
  }

}
