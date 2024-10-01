<?php
use CRM_Iats_ExtensionUtil as E;
return [
  [
    'name' => 'PaymentProcessorType_iATS_Payments_SWIPE',
    'entity' => 'PaymentProcessorType',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'iATS Payments SWIPE',
        'title' => E::ts('iATS Payments SWIPE'),
        'description' => E::ts('iATS credit card payment processor using the encrypted USB IDTECH card reader.'),
        'user_name_label' => 'Agent Code',
        'password_label' => 'Password',
        'signature_label' => 'Process key',
        'class_name' => 'Payment_iATSServiceSWIPE',
        'url_site_default' => 'https://www.iatspayments.com/NetGate/ProcessLinkv2.asmx?WSDL',
        'url_recur_default' => 'https://www.iatspayments.com/NetGate/ProcessLinkv2.asmx?WSDL',
        'url_site_test_default' => 'https://www.iatspayments.com/NetGate/ProcessLinkv2.asmx?WSDL',
        'url_recur_test_default' => 'https://www.iatspayments.com/NetGate/ProcessLinkv2.asmx?WSDL',
        'billing_mode' => 1,
        'is_recur' => TRUE,
        'payment_instrument_id:name' => 'Credit Card',
      ],
      'match' => ['name'],
    ],
  ],
];
