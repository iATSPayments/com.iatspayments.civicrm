<?php
use CRM_Iats_ExtensionUtil as E;
return [
  [
    'name' => 'PaymentProcessorType_iATS_Payments_1stPay_ACH',
    'entity' => 'PaymentProcessorType',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'iATS Payments 1stPay ACH',
        'title' => E::ts('iATS Payments 1stPay ACH'),
        'description' => E::ts('iATS Payments ACH Processor using 1stPay'),
        'user_name_label' => 'Processor ID',
        'password_label' => 'Transaction Center ID',
        'signature_label' => 'Merchant Key',
        'class_name' => 'Payment_FapsACH',
        'url_site_default' => 'https://secure.1stpaygateway.net/secure/RestGW/Gateway/Transaction/',
        'url_site_test_default' => 'https://secure-v.goemerchant.com/secure/RestGW/Gateway/Transaction/',
        'billing_mode' => 1,
        'is_recur' => TRUE,
        'payment_type' => 2,
        'payment_instrument_id:name' => 'Debit Card',
      ],
      'match' => ['name'],
    ],
  ],
];
