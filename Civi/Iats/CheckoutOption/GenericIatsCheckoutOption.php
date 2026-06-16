<?php

namespace Civi\Iats\CheckoutOption;

use Civi\Payment\Checkout\CheckoutOption;
use Civi\Payment\Checkout\CheckoutSession;
use CRM_Core_Payment;

class GenericIatsCheckoutOption extends CheckoutOption {

  protected array $connection;
  protected CRM_Core_Payment $quickformProcessor;

  public function __construct($connection, $quickformProcessor) {
    $this->connection = $connection;
    $this->quickformProcessor = $quickformProcessor;
  }

  public function getLabel(): string {
    return $this->connection['title'];
  }

  public function getFrontendLabel(): string {
    return $this->connection['frontend_title'];
  }

  public function getPaymentProcessorId(): ?int {
    return $this->connection['id'];
  }

  public function startCheckout(CheckoutSession $session): void {
    $params = $session->getCheckoutParams();
    //TODO: which params are needed
    $this->quickformProcessor->doPayment($params);
  }

  public function getQuickformProcessor(): ?CRM_Core_Payment {
    return $this->quickformProcessor;
  }

}