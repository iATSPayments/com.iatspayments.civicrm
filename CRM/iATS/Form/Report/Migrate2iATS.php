<?php

require_once('CRM/Report/Form.php');
require_once('CRM/Utils/Type.php');

class CRM_iATS_Form_Report_Migrate2iATS extends CRM_Report_Form {

  public function buildQuickForm() {

    //Setting Upload File Size
    $config = CRM_Core_Config::singleton();

    $uploadFileSize = CRM_Core_Config_Defaults::formatUnitSize($config->maxFileSize.'m');
    $uploadSize = round(($uploadFileSize / (1024 * 1024)), 2);

    $this->assign('uploadSize', $uploadSize);

    $this->add('File', 'uploadFile', ts('Import Data File'), 'size=30 maxlength=255', TRUE);
    $this->setMaxFileSize($uploadFileSize);
    $this->addRule('uploadFile', ts('File size should be less than %1 MBytes (%2 bytes)', array(1 => $uploadSize, 2 => $uploadFileSize)), 'maxfilesize', $uploadFileSize);
    $this->addRule('uploadFile', ts('A valid file must be uploaded.'), 'uploadedfile');
    $this->addRule('uploadFile', ts('Input file must be in CSV format'), 'utf8File');

    $this->addButtons(array(
        array(
          'type' => 'upload',
          'name' => ts('Upload iATS Customer Codes'),
          'isDefault' => TRUE,
        ),
      )
    );
  }

  function preProcess() {
    parent::preProcess();

    //check for permission to edit contributions
    if ( ! CRM_Core_Permission::check('access CiviContribute') ) {
      CRM_Core_Error::fatal(ts('You do not have permission to access this page', array('domain' => 'com.iatspayments.civicrm')));
    }
  }

  function postProcess() {
  }

}

