<?php

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
    'module' => 'ca.civicrm.iats',
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
    'module' => 'ca.civicrm.iats',
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
  return _iats_civix_civicrm_managed($entities);
}

function iats_civicrm_navigationMenu(&$params) {
  // get the maximum key of $params
  $maxKey = 1 + (max(array_keys($params)));
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
 * hook_civicrm_pre
 *
 * CiviCRM assumes all recurring contributions need to be reverified
 * using the IPN mechanism.
 * After saving any contribution, test for status = 2 and using IATS Payments
 * and set to status = 1 instead.
 * Applies only to the initial contribution and the recurring contribution record.
 * The recurring contribution status id is set explicitly in the job that creates it, and doesn't need this modification.
 */

function iats_civicrm_pre($op, $objectName, $objectId, &$params) {
  if ('create' == $op) {
    if (('Contribution' == $objectName) 
      && !empty($params['contribution_status_id']) 
      && !empty($params['contribution_recur_id'])) {
      if (2 == $params['contribution_status_id']) {
        // watchdog('iats_civicrm','hook_civicrm_pre for Contribution recur @id',array('@id' => $params['contribution_recur_id']));
        if ($payment_processor_id = _iats_civicrm_get_payment_processor_id($params['contribution_recur_id'])) {
          // watchdog('iats_civicrm','hook_civicrm_pre for PP id @id',array('@id' => $payment_processor_id));
          if ($type = _iats_civicrm_is_iats($payment_processor_id)) {
            // watchdog('iats_civicrm','Convert to status of 1');
            switch ($type) {
              case 'iATSService': // cc
                $params['contribution_status_id'] = 1;
                break;
              case 'iATSServiceACHEFT': // cc
                $params['payment_instrument_id'] = 2;
                // watchdog('iats_civicrm_regular','<pre>'.print_r($params,TRUE).'</pre>');
                // $params['contribution_status_id'] = 1;
                break;
            }
            
          }
        }
      }
    }
    elseif ('ContributionRecur' == $objectName) {
      // watchdog('iats_civicrm','hook_civicrm_pre for ContributionRecur params @id',array('@id' => print_r($params, TRUE)));
      if (2 == $params['contribution_status_id']
          && !empty($params['payment_processor_id'])
      ) {
        if ($type = _iats_civicrm_is_iats($params['payment_processor_id'])) {
          // watchdog('iats_civicrm','Convert to status of 1');
          switch ($type) {
            case 'iATSService': // cc
              // we have already taken the first payment, so calculate the next one
              $params['contribution_status_id'] = 1;
              $next = strtotime('+'.$params['frequency_interval'].' '.$params['frequency_unit']);
              $params[IATS_CIVICRM_NSCD_FID] = date('YmdHis',$next);
              break;
            case 'iATSServiceACHEFT': // 
              // watchdog('iats_civicrm_recur','<pre>'.print_r($params,TRUE).'</pre>');
              $params['payment_instrument_id'] = 2;
              $params['contribution_status_id'] = 1;
              $next = strtotime('+'.$params['frequency_interval'].' '.$params['frequency_unit']);
              $params[IATS_CIVICRM_NSCD_FID] = date('YmdHis',$next);
              break;
          }
        }
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

/* ACH/EFT modifications from the default direct debit form */
function iats_civicrm_buildForm_CRM_Contribute_Form_Contribution_Main(&$form) {
  if (empty($form->_paymentProcessors)) {
    return;
  }
  $acheft = array();
  foreach($form->_paymentProcessors as $id => $paymentProcessor) {
    $params = array('version' => 3, 'sequential' => 1, 'id' => $id);
    $result = civicrm_api('PaymentProcessor', 'getsingle', $params);
    if (!empty($result['class_name']) && ('Payment_iATSServiceACHEFT' == $result['class_name'])) {
      $acheft[$id] = TRUE; 
      break;
    }
  }
  // I only need to mangle forms that allow ACH/EFT
  if (0 == count($acheft)) {
    return;
  }
  if (isset($form->_elementIndex['is_recur'])) {
    $form->getElement('is_recur')->setValue(1); // force recurring contrib option
    $form->getElement('is_recur')->freeze(); 
  }
  elseif (empty($form->_values['is_recur'])) {
    CRM_Core_Session::setStatus(ts('You must configure iATS ACH/EFT for recurring contributions.'), ts('Invalid form setting!'), 'alert');
  }
 
  // In addition, I need to mangle the ajax-bit of the form if I've just selected an ach/eft option
  if (!empty($acheft[$form->_paymentProcessor['id']])){ 
    /* TODO: this is only for Canada */
    $element = $form->getElement('account_holder');
    $element->setLabel(ts('Name of Account Holder'));
    $element = $form->getElement('bank_identification_number');
    $element->setLabel(ts('Bank number + branch transit number'));
    //$element = $form->getElement('bank_name');
    //$element->setLabel(ts('Bank name'));
    $form->addElement('select', 'bank_account_type', ts('Account type'), array('CHECKING' => 'Checking', 'SAVING' => 'Saving'));
    $form->addRule('bank_account_type', ts('%1 is a required field.', array(1 => ts('Account type'))), 'required');
    CRM_Core_Region::instance('billing-block')->add(array(
      'template' => 'CRM/iATS/BillingBlockDirectDebitExtra.tpl'
    ));

    // watchdog('iats_acheft',kprint_r($form,TRUE));
  }
  // TODO: add legal requirements for electronic acceptance of 
  //workaround the notice message, as ContributionBase assumes these fields exist in the confirm step
  /* foreach (array("account_holder","bank_identification_number","bank_name","bank_account_number") as $field){
    $form->addElement("hidden",$field);
  } */
}

function _iats_civicrm_domain_info($key) {
  static $domain;
  if (empty($domain)) {
    $domain = civicrm_api('Domain', 'getsingle', array('version' => 3));
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
