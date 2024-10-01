<?php
use CRM_Iats_ExtensionUtil as E;
return [
  [
    'name' => 'PaymentProcessorType_iATS_Payments_ACH_EFT',
    'entity' => 'PaymentProcessorType',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'iATS Payments ACH/EFT',
        'title' => E::ts('iATS Payments ACH/EFT'),
        'description' => E::ts('iATS ACH/EFT payment processor using the web services interface.'),
        'user_name_label' => 'Agent Code',
        'password_label' => 'Password',
        'class_name' => 'Payment_iATSServiceACHEFT',
        'url_site_default' => 'https://www.iatspayments.com/NetGate/ProcessLinkv2.asmx?WSDL',
        'url_recur_default' => 'https://www.iatspayments.com/NetGate/ProcessLinkv2.asmx?WSDL',
        'url_site_test_default' => 'https://www.iatspayments.com/NetGate/ProcessLinkv2.asmx?WSDL',
        'url_recur_test_default' => 'https://www.iatspayments.com/NetGate/ProcessLinkv2.asmx?WSDL',
        'billing_mode' => 1,
        'is_recur' => TRUE,
        'payment_type' => 2,
        'payment_instrument_id:name' => 'Debit Card',
      ],
      'match' => ['name'],
    ],
  ],
];
