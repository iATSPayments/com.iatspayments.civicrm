<?php
// This file declares a managed database record of type "ReportTemplate".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate. For more details, see "hook_civicrm_managed" at:
// http://wiki.civicrm.org/confluence/display/CRMDOC42/Hook+Reference
return array (
  0 =>
  array (
    'name' => 'CRM_iATS_Form_Report_Migrate2iATS',
    'entity' => 'ReportTemplate',
    'params' =>
    array (
      'version' => 3,
      'label' => 'Migrate2iATS',
      'description' => 'Migrate2iATS - upload CSV file with Customer Codes [tokens] (com.iatspayments.civicrm)',
      'class_name' => 'CRM_iATS_Form_Report_Migrate2iATS',
      'report_url' => 'iATS/Migrate2iATS',
      'component' => 'CiviContribute',
    ),
  ),
);
