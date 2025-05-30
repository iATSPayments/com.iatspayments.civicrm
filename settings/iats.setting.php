<?php

use CRM_Iats_ExtensionUtil as E;

return [
  'iats_email_recurring_failure_report' => [
    'name' => 'iats_email_recurring_failure_report',
    'type' => 'String',
    'default' => '',
    'html_type' => 'text',
    'title' => E::ts('Email Recurring Contribution failure reports to this Email address'),
    'is_domain' => 1,
    'is_contact' => 0,
    'settings_pages' => ['iats' => ['weight' => 10]],
  ],
  'iats_bcc_email_recurring_failure_report' => [
    'name' => 'iats_bcc_email_recurring_failure_report',
    'type' => 'String',
    'default' => '',
    'html_type' => 'email',
    'title' => E::ts('BCC Email Recurring Contribution failure reports to this Email address.'),
    'is_domain' => 1,
    'is_contact' => 0,
    'settings_pages' => ['iats' => ['weight' => 20]],
  ],
  'iats_recurring_failure_threshhold' => [
    'name' => 'iats_recurring_failure_threshhold',
    'type' => 'Integer',
    'default' => 3,
    'html_type' => 'text',
    'title' => E::ts('When failure count is equal to or greater than this number, push the next scheduled contribution date forward'),
    'is_domain' => 1,
    'is_contact' => 0,
    'settings_pages' => ['iats' => ['weight' => 30]],
  ],
  'iats_receipt_recurring' => [
    'name' => 'iats_receipt_recurring',
    'type' => 'String',
    'default' => '',
    'html_type' => 'select',
    'title' => E::ts('Email receipt for a Contribution in a Recurring Series'),
    'is_domain' => 1,
    'is_contact' => 0,
    'settings_pages' => ['iats' => ['weight' => 40]],
    'options' => array('0' => E::ts('Never'), '1' => E::ts('Always'), '2' => E::ts('As set for a specific Contribution Series'))
  ],
  'iats_email_failure_contribution_receipt' => [
    'name' => 'iats_email_failure_contribution_receipt',
    'type' => 'Boolean',
    'default' => 0,
    'html_type' => 'checkbox',
    'title' => E::ts('Email receipt for a Contribution if Recurring payment fails - with error message'),
    'is_domain' => 1,
    'is_contact' => 0,
    'settings_pages' => ['iats' => ['weight' => 50]],
  ],
  'iats_disable_cryptogram' => [
    'name' => 'iats_disable_cryptogram',
    'type' => 'Boolean',
    'default' => 0,
    'html_type' => 'checkbox',
    'title' => E::ts('Disable use of cryptogram (only applies to FirstAmerican).'),
    'is_domain' => 1,
    'is_contact' => 0,
    'settings_pages' => ['iats' => ['weight' => 60]],
  ],
  'iats_ach_category_text' => [
    'name' => 'iats_ach_category_text',
    'type' => 'String',
    'default' => FAPS_DEFAULT_ACH_CATEGORY_TEXT,
    'html_type' => 'text',
    'title' => E::ts('ACH Category Text (only applies to FirstAmerican).'),
    'is_domain' => 1,
    'is_contact' => 0,
    'settings_pages' => ['iats' => ['weight' => 70]],
  ],
  'iats_no_edit_extra' => [
    'name' => 'iats_no_edit_extra',
    'type' => 'Boolean',
    'default' => 0,
    'html_type' => 'checkbox',
    'title' => E::ts('Disable extra edit fields for Recurring Contributions'),
    'is_domain' => 1,
    'is_contact' => 0,
    'settings_pages' => ['iats' => ['weight' => 80]],
  ],
  'iats_enable_update_subscription_billing_info' => [
    'name' => 'iats_enable_update_subscription_billing_info',
    'type' => 'Boolean',
    'default' => 0,
    'html_type' => 'checkbox',
    'title' => E::ts('Enable self-service updates to recurring contribution Contact Billing Info.'),
    'is_domain' => 1,
    'is_contact' => 0,
    'settings_pages' => ['iats' => ['weight' => 90]],
  ],
  'iats_enable_change_subscription_amount' => [
    'name' => 'iats_enable_change_subscription_amount',
    'type' => 'Boolean',
    'default' => 0,
    'html_type' => 'checkbox',
    'title' => E::ts('Allow self-service updates to recurring contribution amount.'),
    'is_domain' => 1,
    'is_contact' => 0,
    'settings_pages' => ['iats' => ['weight' => 100]],
  ],
  'iats_enable_cancel_recurring' => [
    'name' => 'iats_enable_cancel_recurring',
    'type' => 'Boolean',
    'default' => 0,
    'html_type' => 'checkbox',
    'title' => E::ts('Enable self-service cancellation of a recurring contribution.'),
    'is_domain' => 1,
    'is_contact' => 0,
    'settings_pages' => ['iats' => ['weight' => 110]],
  ],
  'iats_enable_public_future_recurring_start' => [
    'name' => 'iats_enable_public_future_recurring_start',
    'type' => 'Boolean',
    'default' => 0,
    'html_type' => 'checkbox',
    'title' => E::ts('Enable public selection of future recurring start dates.'),
    'is_domain' => 1,
    'is_contact' => 0,
    'settings_pages' => ['iats' => ['weight' => 120]],
  ],
  'iats_days' => [
    'name' => 'iats_days',
    'type' => 'String',
    'default' => '-1',
    'serialize' => CRM_Core_DAO::SERIALIZE_SEPARATOR_BOOKEND,
    'html_type' => 'select',
    'html_attributes' => [
      'class' => 'crm-form-multiselect',
      'size' => '29',
      'style' => 'width:150px',
      'multiple' => 1,
    ],
    'title' => E::ts('Restrict allowable days of the month for Recurring Contributions'),
    'is_domain' => 1,
    'is_contact' => 0,
    'settings_pages' => ['iats' => ['weight' => 130]],
    'pseudoconstant' => [
      'callback' => 'CRM_Iats_Utils::settingDateOptions',
    ]
  ],
];
