<?php
/*
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC. All rights reserved.                        |
 |                                                                    |
 | This work is published under the GNU AGPLv3 license with some      |
 | permitted exceptions and without any warranty. For full license    |
 | and copyright information, see https://civicrm.org/licensing       |
 +--------------------------------------------------------------------+
 */

use CRM_Iats_ExtensionUtil as E;
/**
 * Shared payment functions that should one day be migrated to CiviCRM core
 * Trait CRM_Core_Payment_iATSTrait (some functions copied from CRM_Core_Payment_MJWTrait
 */
trait CRM_Core_Payment_iATSTrait {

  /**
   * In some cases a payment is still submitted via the payment processor with zero amount.
   * See eg. https://lab.civicrm.org/extensions/stripe/-/issues/256.
   * When you have a 0 membership option and a confirmation page.
   * This function should be called in doPayment() before beginDoPayment()
   *
   * @param \Civi\Payment\PropertyBag $propertyBag
   *
   * @return array|false
   */
  protected function processZeroAmountPayment(\Civi\Payment\PropertyBag $propertyBag) {
    // If we have a $0 amount, skip call to processor and set payment_status to Completed.
    // https://github.com/civicrm/civicrm-core/blob/master/CRM/Core/Payment.php#L1362
    if ($propertyBag->getAmount() == 0) {
      return $this->setStatusPaymentCompleted([]);
    }
    else {
      return FALSE;
    }
  }

  /**
   * Set the payment status to Pending
   * @param array $params
   *
   * @return array
   */
  protected function setStatusPaymentPending(array $params) {
    $params['payment_status_id'] = CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Pending');
    $params['payment_status'] = 'Pending';
    return $params;
  }

  /**
   * Set the payment status to Completed
   * @param $params
   *
   * @return array
   */
  protected function setStatusPaymentCompleted(array $params) {
    $params['payment_status_id'] = CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Completed');
    $params['payment_status'] = 'Completed';
    return $params;
  }

}
