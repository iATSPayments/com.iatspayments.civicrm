<?php

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
    $fname(&$form);
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
    if (('Contribution' == $objectName) && !empty($params['contribution_page_id'])) {
      // watchdog('iats_civicrm','hook_civicrm_pre for Contribution page @id',array('@id' => $params['contribution_page_id']));
      if (2 == $params['contribution_status_id']
          && !empty($params['contribution_recur_id'])
          && !empty($params['contribution_page_id'])
      ) {
        // watchdog('iats_civicrm','hook_civicrm_pre for Contribution recur @id',array('@id' => $params['contribution_recur_id']));
        if ($payment_processor_id = _iats_civicrm_get_payment_processor_id($params['contribution_recur_id'])) {
          // watchdog('iats_civicrm','hook_civicrm_pre for PP id @id',array('@id' => $payment_processor_id));
          if (_iats_civicrm_is_iats($payment_processor_id)) {
            // watchdog('iats_civicrm','Convert to status of 1');
            $params['contribution_status_id'] = 1;
          }
        }
      }
    }
    elseif ('ContributionRecur' == $objectName) {
      // watchdog('iats_civicrm','hook_civicrm_pre for ContributionRecur params @id',array('@id' => print_r($params, TRUE)));
      if (2 == $params['contribution_status_id']
          && !empty($params['payment_processor_id'])
      ) {
        if (_iats_civicrm_is_iats($params['payment_processor_id'])) {
          $params['contribution_status_id'] = 1;
          // we have already taken the first payment, so calculate the next one
          $next = strtotime('+'.$params['frequency_interval'].' '.$params['frequency_unit']);
          $params['next_sched_contribution'] = date('YmdHis',$next);
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
  return ('Payment_iATSService' == $result['class_name']) ? TRUE : FALSE;
}

/* ACH/EFT modifications from the default direct debit form */
function iats_civicrm_buildForm_CRM_Contribute_Form_Contribution_Main(&$form) {
  $params = array('version' => 3, 'id' => $form->_values['payment_processor']);
  $result = civicrm_api('PaymentProcessor', 'getsingle', $params);
  // print_r($result); die();
  if (empty($result['class_name']) || ('Payment_iATSServiceACHEFT' != $result['class_name'])) {
    return;
  }
  //$form->getElement('is_recur')->setValue(1); // recurring contrib as an option
  if (isset($form->_elementIndex['is_recur'])) {
    $form->removeElement('is_recur'); // force recurring contrib
  }
  $form->addElement('hidden','is_recur',1);
  // TODO: add legal requirements for electronic acceptance of 
  //workaround the notice message, as ContributionBase assumes these fields exist in the confirm step
  /* foreach (array("account_holder","bank_identification_number","bank_name","bank_account_number") as $field){
    $form->addElement("hidden",$field);
  } */
  // CRM_Core_Region::instance('page-header')->add(array('script' => $js));
}
