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
    if ('Contribution' == $objectName) {
      // watchdog('iats_civicrm','hook_civicrm_pre for Contribution page @id',array('@id' => $params['contribution_page_id']));
      if (2 == $params['contribution_status_id']
          && !empty($params['contribution_recur_id'])
          && !empty($params['contribution_page_id'])
      ) {
        // watchdog('iats_civicrm','hook_civicrm_pre for Contribution recur @id',array('@id' => $params['contribution_recur_id']));
        if ($payment_processor_id = _iats_civicrm_get_payment_processor_id($params['contribution_page_id'])) {
          // watchdog('iats_civicrm','hook_civicrm_pre for PP id @id',array('@id' => $payment_processor_id));
          if (_iats_civicrm_is_iats($payment_processor_id)) {
            // watchdog('iats_civicrm','Convert to status of 1');
            $params['contribution_status_id'] = 1;
          }
        }
      }
    }
    elseif ('ContributionRecur' == $objectName) {
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
  }
}

/* 
 * The contribution itself doesn't tell you which payment processor it came from
 * So we have to dig back via the contribution_page_id that it is associated with.
 */
function _iats_civicrm_get_payment_processor_id($contribution_page_id) {
  $params = array(
    'version' => 3,
    'sequential' => 1,
    'id' => $contribution_page_id,
  );
  $result = civicrm_api('ContributionPage', 'getsingle', $params);
  if (empty($result['payment_processor'])) {
    return FALSE;
    // TODO: log error
  }
  return $result['payment_processor'];
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
