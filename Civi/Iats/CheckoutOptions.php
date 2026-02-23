<?php

namespace Civi\Iats;

use Civi\Core\Event\GenericHookEvent;
use Civi\Core\Service\AutoService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @service civi.iats.checkoutOptions
 */
class CheckoutOptions extends AutoService implements EventSubscriberInterface {

  public static function getSubscribedEvents(): array {
    return [
      'civi.payment.checkoutOptions' => 'addCheckoutOptions',
    ];
  }

  public function addCheckoutOptions(GenericHookEvent $e): void {
    // find any of the old PaymentProcessorTypes
    $connections = \Civi\Api4\PaymentProcessor::get(FALSE)
      ->addWhere('payment_processor_type_id:name', 'IN', [
        'iATS Payments 1stPay ACH',
        'iATS Payments Credit Card',
        'iATS Payments 1stPay Credit Card',
        'iATS Payments 1stPay ACH',
        'iATS Payments SWIPE',
      ])
      ->addWhere('is_test', '=', $e->testMode)
      ->execute();

    foreach ($connections as $connection) {
      $mode = $e->testMode ? 'test' : 'live';
      $quickformProcessors = [
        'faps' => new \CRM_Core_Payment_Faps($mode, $connection),
        'faps_ach' => new \CRM_Core_Payment_FapsACH($mode, $connection),
        'iats_service' => new \CRM_Core_Payment_iATSService($mode, $connection),
        'iats_service_acheft' => new \CRM_Core_Payment_iATSServiceACHEFT($mode, $connection),
        'iats_service_swift' => new \CRM_Core_Payment_iATSServiceSWIPE($mode, $connection),
      ];

      foreach ($quickformProcessors as $name => $processor) {
        $optionName = "{$processor['name']}_{$name}";
        $e->options[$optionName] = new CheckoutOption\GenericIatsCheckoutOption($connection, $processor);
      }
    }


  }

}