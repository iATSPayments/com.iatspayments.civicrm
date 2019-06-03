<?php

require_once 'iats.civix.php';
use CRM_Iats_ExtensionUtil as E;

define('FAPS_DEFAULT_ACH_CATEGORY_TEXT','CiviCRM ACH');

/**
 * Implements hook_civicrm_config().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function iats_civicrm_config(&$config) {
  _iats_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_xmlMenu().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function iats_civicrm_xmlMenu(&$files) {
  _iats_civix_civicrm_xmlMenu($files);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function iats_civicrm_install() {
  _iats_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_postInstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_postInstall
 */
function iats_civicrm_postInstall() {
  _iats_civix_civicrm_postInstall();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function iats_civicrm_uninstall() {
  _iats_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function iats_civicrm_enable() {
  _iats_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function iats_civicrm_disable() {
  _iats_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function iats_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _iats_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
function iats_civicrm_managed(&$entities) {
  _iats_civix_civicrm_managed($entities);
}

/**
 * Implements hook_civicrm_caseTypes().
 *
 * Generate a list of case-types.
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function iats_civicrm_caseTypes(&$caseTypes) {
  _iats_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implements hook_civicrm_angularModules().
 *
 * Generate a list of Angular modules.
 *
 * Note: This hook only runs in CiviCRM 4.5+. It may
 * use features only available in v4.6+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_angularModules
 */
function iats_civicrm_angularModules(&$angularModules) {
  _iats_civix_civicrm_angularModules($angularModules);
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function iats_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _iats_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

/**
 * Implements hook_civicrm_entityTypes().
 *
 * Declare entity types provided by this module.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_entityTypes
 */
function iats_civicrm_entityTypes(&$entityTypes) {
  _iats_civix_civicrm_entityTypes($entityTypes);
}

// --- Functions below this ship commented out. Uncomment as required. ---

/**
 * Implements hook_civicrm_preProcess().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_preProcess
 *
function iats_civicrm_preProcess($formName, &$form) {

} // */

/**
 * Implements hook_civicrm_navigationMenu().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_navigationMenu
 *
function iats_civicrm_navigationMenu(&$menu) {
  _iats_civix_insert_navigation_menu($menu, 'Mailings', array(
    'label' => E::ts('New subliminal message'),
    'name' => 'mailing_subliminal_message',
    'url' => 'civicrm/mailing/subliminal',
    'permission' => 'access CiviMail',
    'operator' => 'OR',
    'separator' => 0,
  ));
  _iats_civix_navigationMenu($menu);
} // */

function iats_get_setting($key = NULL) {
  static $settings;
  if (empty($settings)) { 
    $settings = CRM_Core_BAO_Setting::getItem('iATS FAPS Payments Extension', 'iats_settings');
  }
  return empty($key) ?  $settings : (isset($settings[$key]) ? $settings[$key] : '');
}

/**
 * Internal utility function: return the id's of any iATS processors matching various conditions.
 *
 * Processors: an array of payment processors indexed by id to filter by,
 *             or if NULL, it searches through all
 * subtype: the service class name type or subtype
 * params: an array of additional params to pass to the api call.
 */
function iats_civicrm_processors($processors, $subtype = '', $params = array()) {
  $list = array();
  $match_all = ('*' == $subtype) ? TRUE : FALSE;
  if (!$match_all) {
    $params['class_name'] = 'Payment_' . $subtype;
  }

  // Set the domain id if not passed in.
  if (!array_key_exists('domain_id', $params)) {
    $params['domain_id']    = CRM_Core_Config::domainID();
  }

  $result = civicrm_api3('PaymentProcessor', 'get', $params);
  if (0 == $result['is_error'] && count($result['values']) > 0) {
    foreach ($result['values'] as $paymentProcessor) {
      $id = $paymentProcessor['id'];
      if ((is_null($processors)) || !empty($processors[$id])) {
        if (!$match_all || (0 === strpos($paymentProcessor['class_name'], 'Payment_Faps'))) {
          $list[$id] = $paymentProcessor;
        }
      }
    }
  }
  return $list;
}

/**
 * Hook_civicrm_buildForm.
 * Do a Drupal 7 style thing so we can write smaller functions.
 */
function iats_civicrm_buildForm($formName, &$form) {
  // But start by grouping a few forms together for nicer code.
  switch ($formName) {
    case 'CRM_Event_Form_Participant':
    case 'CRM_Member_Form_Membership':
    case 'CRM_Contribute_Form_Contribution':
      // Override normal convention, deal with all these backend credit card contribution forms the same way.
      $fname = 'iats_civicrm_buildForm_Contribution';
      break;

    case 'CRM_Contribute_Form_Contribution_Main':
    case 'CRM_Event_Form_Registration_Register':
    case 'CRM_Financial_Form_Payment':
      // Override normal convention, deal with all these front-end contribution forms the same way.
      $fname = 'iats_civicrm_buildForm_Contribution';
      break;
    default:
      $fname = 'iats_civicrm_buildForm_' . $formName;
      break;
  }
  if (function_exists($fname)) {
    $fname($form);
  }
  // Else echo $fname;.
}

/**
 * Add the magic sauce to cc and ach forms if I'm using FAPS
 */
function iats_civicrm_buildForm_Contribution(&$form) {
  // Skip if i don't have any processors.
  // echo '<pre>'; print_r(array_keys(get_object_vars($form))); die();
  if (empty($form->_processors)) {
   // return;
  }
  $form_class = get_class($form);
  //  die($form_class);

  if ($form_class == 'CRM_Financial_Form_Payment') {
    // We're on CRM_Financial_Form_Payment, we've got just one payment processor
    $id = $form->_paymentProcessor['id'];
    $iats_processors = iats_civicrm_processors(array($id => $form->_paymentProcessor), 'FAPS');
  }
  else {
    // Handle the event and contribution page forms
    if (empty($form->_paymentProcessors)) {
      if (empty($form->_paymentProcessorIDs)) {
        return;
      }
      else {
        $form_payment_processors = array_fill_keys($form->_paymentProcessorIDs,1);
      }
    }
    else {
      $form_payment_processors = $form->_paymentProcessors;
    }
    $iats_processors = iats_civicrm_processors($form_payment_processors, 'FAPS');
  }
  if (empty($iats_processors)) {
    return;
  }
  // print_r($iats_processors); die();
  // die('test');
  if (empty($form->_submitValues['payment_processor_id'])) {
    if (empty($form->_defaults['payment_processor_id'])) {
      $payment_processor_ids = array_keys($iats_processors);
      $payment_processor_id = reset($payment_processor_ids);
    }
    else {
      $payment_processor_id = $form->_defaults['payment_processor_id'];
    }
  }
  else {
    $payment_processor_id = $form->_submitValues['payment_processor_id'];
  }
  $iats_processor = $iats_processors[$payment_processor_id];
  $is_cc = ($iats_processor['payment_instrument_id'] == 1);
  $is_test = ($iats_processor['is_test'] == 1);
  $has_is_recur = $form->elementExists('is_recur');
  if (iats_get_setting('use_cryptogram')) {
    // CRM_Core_Error::debug_var('generate cryptogram html', $iats_processors);
    // CRM_Core_Error::debug_var('form class', $form_class);
    // CRM_Core_Error::debug_var('form', $form);
    $credentials = array(
      'transcenterId' => $iats_processor['password'],
      'processorId' => $iats_processor['user_name']
    );
    $iats_domain = parse_url($iats_processor['url_site'], PHP_URL_HOST);
    $cryptojs = 'https://'.$iats_domain.'/secure/PaymentHostedForm/Scripts/firstpay/firstpay.cryptogram.js';
    $transaction_type = $has_is_recur ? ($is_cc ? 'Auth' : 'Vault') : ($is_cc ? 'Sale' : 'AchDebit');
    $iframe_src = 'https://'.$iats_domain. '/secure/PaymentHostedForm/v3/' .($is_cc ? 'CreditCard' : 'Ach');
    $iframe_style = 'width: 100%;'; // height: 100%;';
    $markup = sprintf("<iframe id=\"firstpay-iframe\" src=\"%s\" style=\"%s\" data-transcenter-id=\"%s\" data-processor-id=\"%s\" data-transaction-type=\"%s\" data-manual-submit=\"false\"></iframe>\n", $iframe_src, $iframe_style,$credentials['transcenterId'], $credentials['processorId'], $transaction_type);
    // $markup = "<iframe id=\"firstpay-iframe\" src=\"%s\" style=\"width: 100%; height: 100%\" data-transcenter-id=\"%s\" data-processor-id=\"%s\" data-transaction-type=\"%s\" data-manual-submit=\"false\"></iframe>\n";
    // print_r('<pre>'.$markup.'</pre>'); die();
    CRM_Core_Resources::singleton()->addScriptUrl($cryptojs);
    // $markup = print_r($iats_processors, TRUE);
    CRM_Core_Resources::singleton()->addScriptFile('com.iatspayments.faps', 'js/crypto.js', 10);
    CRM_Core_Resources::singleton()->addStyleFile('com.iatspayments.faps', 'css/crypto.css', 10);
    CRM_Core_Region::instance('page-body')->add(array(
          'name' => 'firstpay-iframe',
          'type' => 'markup',
          'markup' => $markup,
          'weight' => 11,
          'region' => 'page-body',
        )); 
  }
}
