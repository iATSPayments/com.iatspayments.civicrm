<?php

/* Copyright iATS Payments (c) 2014
 * Author: Alan Dixon
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
 */

/* this constant is used because civicrm has changed the field name of
 * the 'next scheduled contribution date' field in version 4.3 and above
 * TODO: remove this when we no longer support 4.2
 */
define('IATS_CIVICRM_NSCD_FID',_iats_civicrm_nscd_fid());

require_once 'iats.civix.php';

/**
 * Implementation of hook_civicrm_config
 */
function iats_civicrm_config(&$config) {
  _iats_civix_civicrm_config($config);
}

/**
 * Implementation of hook_civicrm_xmlMenu
 *
 * @param $files array(string)
 */
function iats_civicrm_xmlMenu(&$files) {
  _iats_civix_civicrm_xmlMenu($files);
}

/**
 * Implementation of hook_civicrm_install
 */
function iats_civicrm_install() {
  if (!class_exists('SoapClient')) {
    $session = CRM_Core_Session::singleton();
    $session->setStatus(ts('The PHP SOAP extension is not installed on this server, but is required for this extension'), ts('iATS Payments Installation'), 'error');
  }
  return _iats_civix_civicrm_install();
}

/**
 * Implementation of hook_civicrm_uninstall
 */
function iats_civicrm_uninstall() {
  return _iats_civix_civicrm_uninstall();
}

/**
 * Implementation of hook_civicrm_enable
 */
function iats_civicrm_enable() {
  if (!class_exists('SoapClient')) {
    $session = CRM_Core_Session::singleton();
    $session->setStatus(ts('The PHP SOAP extension is not installed on this server, but is required for this extension'), ts('iATS Payments Installation'), 'error');
  }
  return _iats_civix_civicrm_enable();
}

/**
 * Implementation of hook_civicrm_disable
 */
function iats_civicrm_disable() {
  return _iats_civix_civicrm_disable();
}

/**
 * Implementation of hook_civicrm_upgrade
 *
 * @param $op string, the type of operation being performed; 'check' or 'enqueue'
 * @param $queue CRM_Queue_Queue, (for 'enqueue') the modifiable list of pending up upgrade tasks
 *
 * @return mixed  based on op. for 'check', returns array(boolean) (TRUE if upgrades are pending)
 *                for 'enqueue', returns void
 */
function iats_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _iats_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implementation of hook_civicrm_managed
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 */
function iats_civicrm_managed(&$entities) {
  $entities[] = array(
    'module' => 'com.iatspayments.civicrm',
    'name' => 'iATS Payments',
    'entity' => 'PaymentProcessorType',
    'params' => array(
      'version' => 3,
      'name' => 'iATS Payments Credit Card',
      'title' => 'iATS Payments Credit Card',
      'description' => 'iATS credit card payment processor using the web services interface.',
      'class_name' => 'Payment_iATSService',
      'billing_mode' => 'form',
      'user_name_label' => 'Agent Code',
      'password_label' => 'Password',
      'url_site_default' => 'https://www.iatspayments.com/NetGate/ProcessLink.asmx?WSDL',
      'url_recur_default' => 'https://www.iatspayments.com/NetGate/ProcessLink.asmx?WSDL',
      'url_site_test_default' => 'https://www.iatspayments.com/NetGate/ProcessLink.asmx?WSDL',
      'url_recur_test_default' => 'https://www.iatspayments.com/NetGate/ProcessLink.asmx?WSDL',
      'is_recur' => 1,
      'payment_type' => 1,
    ),
  );
  $entities[] = array(
    'module' => 'com.iatspayments.civicrm',
    'name' => 'iATS Payments ACH/EFT',
    'entity' => 'PaymentProcessorType',
    'params' => array(
      'version' => 3,
      'name' => 'iATS Payments ACH/EFT',
      'title' => 'iATS Payments ACH/EFT',
      'description' => 'iATS ACH/EFT payment processor using the web services interface.',
      'class_name' => 'Payment_iATSServiceACHEFT',
      'billing_mode' => 'form',
      'user_name_label' => 'Agent Code',
      'password_label' => 'Password',
      'url_site_default' => 'https://www.iatspayments.com/NetGate/ProcessLink.asmx?WSDL',
      'url_recur_default' => 'https://www.iatspayments.com/NetGate/ProcessLink.asmx?WSDL',
      'url_site_test_default' => 'https://www.iatspayments.com/NetGate/ProcessLink.asmx?WSDL',
      'url_recur_test_default' => 'https://www.iatspayments.com/NetGate/ProcessLink.asmx?WSDL',
      'is_recur' => 1,
      'payment_type' => 2,
    ),
  );
  $entities[] = array(
    'module' => 'com.iatspayments.civicrm',
    'name' => 'iATS Payments SWIPE',
    'entity' => 'PaymentProcessorType',
    'params' => array(
      'version' => 3,
      'name' => 'iATS Payments SWIPE',
      'title' => 'iATS Payments SWIPE',
      'description' => 'iATS credit card payment processor using the encrypted USB IDTECH card reader.',
      'class_name' => 'Payment_iATSServiceSWIPE',
      'billing_mode' => 'form',
      'user_name_label' => 'Agent Code',
      'password_label' => 'Password',
      'url_site_default' => 'https://www.iatspayments.com/NetGate/ProcessLink.asmx?WSDL',
      'url_recur_default' => 'https://www.iatspayments.com/NetGate/ProcessLink.asmx?WSDL',
      'url_site_test_default' => 'https://www.iatspayments.com/NetGate/ProcessLink.asmx?WSDL',
      'url_recur_test_default' => 'https://www.iatspayments.com/NetGate/ProcessLink.asmx?WSDL',
      'is_recur' => 1,
      'payment_type' => 1,
    ),
  );
  return _iats_civix_civicrm_managed($entities);
}

function _iats_getMenuKeyMax($menuArray) {
  $max = array(max(array_keys($menuArray)));
  foreach($menuArray as $v) {
    if (!empty($v['child'])) {
      $max[] = _iats_getMenuKeyMax($v['child']);
    }
  }
  return max($max);
}

function iats_civicrm_navigationMenu(&$params) {
  // get the maximum key of $params
  $maxKey = 1 + _iats_getMenuKeyMax($params);
  foreach($params as $key => $value) {
    if ('Contributions' == $value['attributes']['name']) {
      $params[$key]['child'][$maxKey] =  array (
        'attributes' => array (
          'label'      => 'iATS Payments Admin',
          'name'       => 'iATS Payments Admin',
          'url'        => 'civicrm/iATSAdmin',
          'permission' => 'access CiviContribute,administer CiviCRM',
          'operator'   => 'AND',
          'separator'  => null,
          'parentID'   => 28,
          'navID'      => $maxKey,
          'active'     => 1
        )
      );
      $maxKey++; // just in case ...
    }
  }
}


/*
 * hook_civicrm_buildForm
 * Do a Drupal 7 style thing so we can write smaller functions
 */
function iats_civicrm_buildForm($formName, &$form) {
  $fname = 'iats_civicrm_buildForm_'.$formName;
  if (function_exists($fname)) {
    $fname($form);
  }
}

/*
 * hook_civicrm_merge
 * Deal with contact merges - our custom iats customer code table contains contact id's as a check, it might need to be updated
 */
function iats_civicrm_merge($type, &$data, $mainId = NULL, $otherId = NULL, $tables = NULL) {
  if ('cidRefs' == $type) {
    $data['civicrm_iats_customer_codes'] = array('cid');
    $data['civicrm_iats_verify'] = array('cid');
  }
}


/*
 * hook_civicrm_pre
 *
 * Handle special cases of creating contribution (regular and recurring) records when using IATS Payments
 *
 * 1. CiviCRM assumes all recurring contributions need to be confirmed using the IPN mechanism. This is not true for iATS recurring contributions.
 * So when creating a contribution that is part of a recurring series, test for status = 2, and set to status = 1 instead.
 * Do this for the initial and recurring contribution record.
 * The (subsequent) recurring contributions' status id is set explicitly in the job that creates it, and doesn't need this modification.
 *
 * 2. For ACH/EFT, we also have the opposite problem - all contributions will need to verified by iATS and only later set to status success or
 * failed via the acheft verify job. We also want to modify the payment instrument from CC to ACH/EFT
 *
 * TODO: update this code with constants for the various id values of 1 and 2.
 * TODO: CiviCRM should have nicer ways to handle this.
 */

function iats_civicrm_pre($op, $objectName, $objectId, &$params) {
  // since this function gets called a lot, quickly determine if I care about the record being created
  if (('create' == $op) && ('Contribution' == $objectName || 'ContributionRecur' == $objectName) && !empty($params['contribution_status_id'])) {
    // watchdog('iats_civicrm','hook_civicrm_pre for Contribution <pre>@params</pre>',array('@params' => print_r($params));
    // figure out the payment processor id, not nice
    $payment_processor_id = ('ContributionRecur' == $objectName) ? $params['payment_processor_id'] :
                              (!empty($params['payment_processor']) ? $params['payment_processor'] :
                                (!empty($params['contribution_recur_id']) ? _iats_civicrm_get_payment_processor_id($params['contribution_recur_id']) :
                                 0)
                              );
    if ($type = _iats_civicrm_is_iats($payment_processor_id)) {
      switch ($type.$objectName) {
        case 'iATSServiceContribution': // cc contribution, test if it's been set to status 2 on a recurring contribution
          if ((2 == $params['contribution_status_id'])
            && !empty($params['contribution_recur_id'])) {
            $params['contribution_status_id'] = 1;
          }
          break;
        case 'iATSServiceContributionRecur': // cc recurring contribution record
          // we've already taken the first payment, so calculate the next one
          $params['contribution_status_id'] = 5;
          $next = strtotime('+'.$params['frequency_interval'].' '.$params['frequency_unit']);
          $params[IATS_CIVICRM_NSCD_FID] = date('YmdHis',$next);
          break;
        case 'iATSServiceACHEFTContribution': // ach/eft contribution: update the payment instrument and ensure the status is 2 i.e. for one-time contributions
          $params['payment_instrument_id'] = 2;
          $params['contribution_status_id'] = 2;
          // watchdog('iats_civicrm_regular','<pre>'.print_r($params,TRUE).'</pre>');
          // $params['contribution_status_id'] = 1;
          break;
        case 'iATSServiceACHEFTContributionRecur': // ach/eft recurring contribution record
          // watchdog('iats_civicrm_recur','<pre>'.print_r($params,TRUE).'</pre>');
          $params['payment_instrument_id'] = 2;
          $params['contribution_status_id'] = 5; // we set this to 'in-progress' because even if the first one hasn't been verified, we still want to be attempting later ones
          $next = strtotime('+'.$params['frequency_interval'].' '.$params['frequency_unit']);
          $params[IATS_CIVICRM_NSCD_FID] = date('YmdHis',$next);
          break;
      }
    }
    // watchdog('iats_civicrm','ignoring hook_civicrm_pre for objectName @id',array('@id' => $objectName));
  }
}

/*
 * The contribution itself doesn't tell you which payment processor it came from
 * So we have to dig back via the contribution_recur_id that it is associated with.
 */
function _iats_civicrm_get_payment_processor_id($contribution_recur_id) {
  $params = array(
    'version' => 3,
    'sequential' => 1,
    'id' => $contribution_recur_id,
  );
  $result = civicrm_api('ContributionRecur', 'getsingle', $params);
  if (empty($result['payment_processor_id'])) {
    return FALSE;
    // TODO: log error
  }
  return $result['payment_processor_id'];
}

function _iats_civicrm_is_iats($payment_processor_id) {
  $params = array(
    'version' => 3,
    'sequential' => 1,
    'id' => $payment_processor_id,
  );
  $result = civicrm_api('PaymentProcessor', 'getsingle', $params);
  if (empty($result['class_name'])) {
    return FALSE;
    // TODO: log error
  }
  $type = substr($result['class_name'],0,19);
  $subtype = substr($result['class_name'],19);
  return ('Payment_iATSService' == $type) ? 'iATSService'.$subtype  : FALSE;
}

/* internal utility function: return the id's of any iATS ACH/EFT processors */
function iats_civicrm_acheft_processors($processors) {
  $acheft = array();
  foreach($processors as $id => $paymentProcessor) {
    $params = array('version' => 3, 'sequential' => 1, 'id' => $id);
    $result = civicrm_api('PaymentProcessor', 'getsingle', $params);
    if (!empty($result['class_name']) && ('Payment_iATSServiceACHEFT' == $result['class_name'])) {
      $acheft[$id] = TRUE;
      break;
    }
  }
  return $acheft;
}

/* internal utility function: return the id's of any iATS SWIPE processors */
function iats_civicrm_swipe_processors($processors) {
  $swipe = array();
  foreach($processors as $id => $paymentProcessor) {
    $params = array('version' => 3, 'sequential' => 1, 'id' => $id);
    $result = civicrm_api('PaymentProcessor', 'getsingle', $params);
    if (!empty($result['class_name']) && ('Payment_iATSServiceSWIPE' == $result['class_name'])) {
      $swipe[$id] = TRUE;
      break;
    }
  }
  return $swipe;
}

/* as above, but return all non-test, active ACH/EFT iATS processors */
function iats_civicrm_acheft_processors_live() {
  $acheft = array();
  $params = array('version' => 3, 'sequential' => 1, 'is_test' => 0, 'is_active' => 1);
  $result = civicrm_api('PaymentProcessor', 'get', $params);
  if (0 == $result['is_error'] && count($result['values']) > 0) {
    foreach($result['values'] as $paymentProcessor) {
      if (!empty($paymentProcessor['class_name']) && ('Payment_iATSServiceACHEFT' == $paymentProcessor['class_name'])) {
        $acheft[$paymentProcessor['id']] = TRUE;
      }
    }
  }
  return $acheft;
}

/*
 * Customize direct debit billing blocks, per currency
 *
 * Each country has different rules about direct debit, so only currencies that we explicitly handle will be
 * customized, others will get a warning.
 *
 * The currency-specific functions will do things like modify labels, add exta fields,
 * add legal requirement notice and perhaps checkbox acceptance for electronic acceptance of ACH/EFT, and
 * make this form nicer by include a sample check with instructions for getting the various numbers
 */

function iats_acheft_form_customize($form) {
  // $fname = 'iats_acheft_form_customize_'.$form->_values['currency'];
  if (isset($form->_values['event']['currency'])) {
    // This is an Event registration form
    $fname = 'iats_acheft_form_customize_'.$form->_values['event']['currency'];
  }
  else {
    // This is a Contribution form
    $fname = 'iats_acheft_form_customize_'.$form->_values['currency'];
  }
  /* we always want these three fields to be required, in all currencies */
  $form->addRule('account_holder', ts('%1 is a required field.', array(1 => ts('Name of Account Holder'))), 'required');
  $form->addRule('bank_account_number', ts('%1 is a required field.', array(1 => ts('Account Number'))), 'required');
  $form->addRule('bank_name', ts('%1 is a required field.', array(1 => ts('Bank Name'))), 'required');
  if (function_exists($fname)) {
    $fname($form);
  }
  else { // I'm handling an unexpected currency
    CRM_Core_Region::instance('billing-block')->add(array(
      'template' => 'CRM/iATS/BillingBlockDirectDebitExtra_Other.tpl'
    ));
  }
}

/*
 * Customization for USD ACH-EFT billing block
 */
function iats_acheft_form_customize_USD($form) {
  $form->addElement('select', 'bank_account_type', ts('Account type'), array('CHECKING' => 'Checking', 'SAVING' => 'Saving'));
  $form->addRule('bank_account_type', ts('%1 is a required field.', array(1 => ts('Account type'))), 'required');
  $element = $form->getElement('account_holder');
  $element->setLabel(ts('Name of Account Holder'));
  $element = $form->getElement('bank_account_number');
  $element->setLabel(ts('Bank Account Number'));
  $element = $form->getElement('bank_identification_number');
  $element->setLabel(ts('Bank Routing Number'));
  $form->addRule('bank_identification_number', ts('%1 is a required field.', array(1 => ts('Bank Routing Number'))), 'required');
  CRM_Core_Region::instance('billing-block')->add(array(
    'template' => 'CRM/iATS/BillingBlockDirectDebitExtra_USD.tpl'
  ));
}

/*
 * Customization for CAD ACH-EFT billing block
 */
function iats_acheft_form_customize_CAD($form) {
  $form->addElement('text', 'cad_bank_number', ts('Bank Number'));
  $form->addRule('cad_bank_number', ts('%1 is a required field.', array(1 => ts('Bank Number'))), 'required');
  $form->addElement('text', 'cad_transit_number', ts('Transit Number'));
  $form->addRule('cad_transit_number', ts('%1 is a required field.', array(1 => ts('Transit Number'))), 'required');
  $form->addElement('select', 'bank_account_type', ts('Account type'), array('CHECKING' => 'Checking', 'SAVING' => 'Saving'));
  $form->addRule('bank_account_type', ts('%1 is a required field.', array(1 => ts('Account type'))), 'required');
  /* minor customization of labels + make them required */
  $element = $form->getElement('account_holder');
  $element->setLabel(ts('Name of Account Holder'));
  $element = $form->getElement('bank_account_number');
  $element->setLabel(ts('Account Number'));
  /* the bank_identification_number is hidden and then populated using jquery, in the custom template */
  $element = $form->getElement('bank_identification_number');
  $element->setLabel(ts('Bank Number + Transit Number'));
  CRM_Core_Region::instance('billing-block')->add(array(
    'template' => 'CRM/iATS/BillingBlockDirectDebitExtra_CAD.tpl'
  ));
}

/*
 * Customization for iATS secure swipe
 */
function iats_acheft_form_customize_swipe($form) {
 // make everything unrequired:
 $form->_required = array();
 $form->addElement('textarea','encrypted_credit_card_number',ts('Encrypted'), array('cols' => '80', 'rows' => '8'));
 $form->addRule('encrypted_credit_card_number', ts('%1 is a required field.', array(1 => ts('Encrypted'))), 'required');
 $form->addRule('credit_card_exp_date', ts('%1 is a required field.', array(1 => ts('Expiry Date'))), 'required');
 CRM_Core_Region::instance('billing-block')->add(array(
   'template' => 'CRM/iATS/BillingBlockSwipe.tpl'
 ));
}

/* ACH/EFT modifications to a (public) contribution form if iATS ACH/EFT is enabled
 *  1. set recurring to be the default, if enabled
 *  2. [previously forced recurring, removed in 1.2.4]
 *  3. add extra fields/modify labels
 */
function iats_civicrm_buildForm_CRM_Contribute_Form_Contribution_Main(&$form) {
  if (empty($form->_paymentProcessors)) {
    return;
  }
  $acheft = iats_civicrm_acheft_processors($form->_paymentProcessors);
  // I only need to mangle forms that allow ACH/EFT
  if (0 == count($acheft)) {
    return;
  }
  if (isset($form->_elementIndex['is_recur'])) {
    $form->setDefaults(array('is_recur' => 1)); // make recurring contrib default to true
  }

  /* In addition, I need to mangle (in a currency-dependent way) the ajax-bit of the form if I've just selected an ach/eft option
   */
  if (!empty($acheft[$form->_paymentProcessor['id']])){
    iats_acheft_form_customize($form);
    // watchdog('iats_acheft',kprint_r($form,TRUE));
  }
}

function iats_civicrm_buildForm_CRM_Event_Form_Registration_Register(&$form) {
  if (empty($form->_paymentProcessors)) {
    return;
  }
  $acheft = iats_civicrm_acheft_processors($form->_paymentProcessors);
  // I only need to mangle forms that allow ACH/EFT
  if (0 == count($acheft)) {
    return;
  }
  if (!empty($acheft[$form->_paymentProcessor['id']])){
    iats_acheft_form_customize($form);
    // watchdog('iats_acheft',kprint_r($form,TRUE));
  }
}

/*  Fix the backend contribution form, by removing my ACH/EFT processors
 *  Now fixed in core: https://issues.civicrm.org/jira/browse/CRM-14442)
 *  TODO: test for old versions of civicrm before running this code?
 */
function iats_civicrm_buildForm_CRM_Contribute_Form_Contribution(&$form) {

  // KG
  // if iATS SWIPE payment processor exists AND is enabled (is active) remove all other payment processors
  // from this backend contribution form. Apparently CiviCRM Administrator has determined that
  // it's mandatory for staff to use SWIPE method for backend transactions during
  // e.g. tonight's fundraising event.
  $swipe = iats_civicrm_swipe_processors($form->_processors);
  if (1 == count($swipe)) {
    $pp_form_id = $form->_elementIndex['payment_processor_id'];

    $element = $form->_elements[$pp_form_id]->_options;
    foreach($element as $option_id => $option) {
      $array_keys_swipe = array_keys($swipe);
      if ($option['attr']['value'] != $array_keys_swipe[0]) {
          unset($form->_elements[$pp_form_id]->_options[$option_id]);
      }
    }

    $processors = array_keys($form->_processors);
    foreach($processors as $pp_id) {
      if ($pp_id != $array_keys_swipe[0]) {
        unset($form->_processors[$pp_id]);
      }
    }

    $recurPaymentProcessors = array_keys($form->_recurPaymentProcessors);
    foreach($recurPaymentProcessors as $pp_id) {
      if ($pp_id != $array_keys_swipe[0]) {
        unset($form->_recurPaymentProcessors[$pp_id]);
      }
    }

    iats_acheft_form_customize_swipe($form);
  }

  if (empty($form->_processors)) {
    return;
  }
  $acheft = iats_civicrm_acheft_processors($form->_processors);
// I only need to mangle the form if it (still) allows ACH/EFT
  if (0 == count($acheft)) {
    return;
  }
// yes, there's a more efficient/clever way to find the right element
// but since this code is only fixing old CiviCRM instances, let's not worry
  foreach($form->_elements as $form_id => $element) {
    if ($element->_attributes['name'] == 'payment_processor_id') {
      $pp_form_id = $form_id;
      break;
    }
  }
  foreach(array_keys($acheft) as $pp_id) {
    unset($form->_processors[$pp_id]);
    if (!empty($form->_recurPaymentProcessors[$pp_id])) {
      unset($form->_recurPaymentProcessors[$pp_id]);
    }
    $element = $form->_elements[$pp_form_id];
    if (is_array($element->_options)) {
      foreach($element->_options as $option_id => $option) {
        if ($option['attr']['value'] == $pp_id) {
          unset($element->_options[$option_id]);
        }
      }
    }
  }
}

/*
 *  Provide helpful links to backend-ish payment pages for ACH/EFT, since the backend credit card pages don't work/apply
 */
function iats_civicrm_buildForm_CRM_Contribute_Form_Search(&$form) {
  $contactID = $form->_defaultValues['contact_id'];
  $acheft = iats_civicrm_acheft_processors_live();
  $acheft_backoffice_links = array();
  // for each ACH/EFT payment processor, try to provide a different mechanism for 'backoffice' type contributions
  // note: only offer payment pages that provide iATS ACH/EFT exclusively
  foreach(array_keys($acheft) as $pp_id) {
    $params = array('version' => 3, 'sequential' => 1, 'is_active' => 1, 'payment_processor' => $pp_id);
    $result = civicrm_api('ContributionPage', 'get', $params);
    if (0 == $result['is_error'] && count($result['values']) > 0) {
      foreach($result['values'] as $page) {
        $url = CRM_Utils_System::url('civicrm/contribute/transact','reset=1&cid='.$contactID.'&id='.$page['id']);
        $acheft_backoffice_links[] = array('url' => $url, 'title' => $page['title']);
      }
    }
  }
  if (count($acheft_backoffice_links)) {
    // a hackish way to inject these links into the form, they are displayed nicely using some javascript
    // that is added using the Tab.extra.tpl mechanism
    $form->addElement('hidden','acheft_backoffice_links',json_encode($acheft_backoffice_links));
  }
}

/*
 * Modify the recurring contribution cancelation form to exclude the confusing message about sending the request to the backend
 */
function iats_civicrm_buildForm_CRM_Contribute_Form_CancelSubscription(&$form) {
  $form->removeElement('send_cancel_request');
}

function _iats_civicrm_domain_info($key) {
  static $domain;
  if (empty($domain)) {
    $domain = civicrm_api('Domain', 'getsingle', array('version' => 3, 'current_domain' => TRUE));
  }
  switch($key) {
    case 'version':
      return explode('.',$domain['version']);
    default:
      if (!empty($domain[$key])) {
        return $key;
      }
      $config_backend = unserialize($domain['config_backend']);
      return $config_backend[$key];
  }
}

function _iats_civicrm_nscd_fid() {
  $version = _iats_civicrm_domain_info('version');
  return (($version[0] <= 4) && ($version[1] <= 3)) ? 'next_sched_contribution' : 'next_sched_contribution_date';
}
