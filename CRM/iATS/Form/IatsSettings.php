<?php

require_once 'CRM/Core/Form.php';

/**
 * Form controller class
 *
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC43/QuickForm+Reference
 */
class CRM_iATS_Form_IatsSettings extends CRM_Core_Form {
  function buildQuickForm() {

    // add form elements
    $this->add(
      'text', // field type
      'email_recurring_failure_report', // field name
      ts('Email this address with recurring failure reports.')
    );
    $this->addRule('email_recurring_failure_report', ts('Email address is not a valid format.'), 'email');
    $this->add(
      'text', // field type
      'recurring_failure_threshhold', // field name
      ts('When failure count is equal to or greater than this number, push the next scheduled date forward.')
    );
    $this->addRule('recurring_failure_threshhold', ts('Threshhold must be a positive integer.'), 'integer');
    $this->add(
      'checkbox', // field type
      'receipt_recurring', // field name
      ts('Enable email receipting for each recurring contribution.')
    );

    $this->add(
      'checkbox', // field type
      'no_edit_extra', // field name
      ts('Disable extra edit fields for recurring contributions.')
    );

    $days = array('-1' => 'disabled');
    for ($i = 1; $i <= 28; $i++) {
      $days["$i"] = "$i";
    }
    $attr =  array('size' => 29,
         'style' => 'width:150px',
         'required' => FALSE);
    $day_select = $this->add(
      'select', // field type
      'days', // field name
      ts('Restrict allowable days of the month for recurring contributions.'),
      $days,
      FALSE,
      $attr
    );

    $day_select->setMultiple(TRUE);
    $day_select->setSize(29);
    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => ts('Submit'),
        'isDefault' => TRUE,
      ),
    ));

    $result = CRM_Core_BAO_Setting::getItem('iATS Payments Extension', 'iats_settings');
    $defaults = (empty($result)) ? array() : $result;
    if (empty($defaults['recurring_failure_threshhold'])) {
      $defaults['recurring_failure_threshhold'] = 3;
    }
    $this->setDefaults($defaults);
    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => ts('Submit'),
        'isDefault' => TRUE,
      ),
    ));

    // export form elements
    $this->assign('elementNames', $this->getRenderableElementNames());
    parent::buildQuickForm();
  }

  function postProcess() {
    $values = $this->exportValues();
    foreach(array('qfKey','_qf_default','_qf_IatsSettings_submit','entryURL') as $key) {
      if (isset($values[$key])) {
        unset($values[$key]);
      }
    }
    CRM_Core_BAO_Setting::setItem($values, 'iATS Payments Extension', 'iats_settings');
    parent::postProcess();
  }

  /**
   * Get the fields/elements defined in this form.
   *
   * @return array (string)
   */
  function getRenderableElementNames() {
    // The _elements list includes some items which should not be
    // auto-rendered in the loop -- such as "qfKey" and "buttons".  These
    // items don't have labels.  We'll identify renderable by filtering on
    // the 'label'.
    $elementNames = array();
    foreach ($this->_elements as $element) {
      $label = $element->getLabel();
      if (!empty($label)) {
        $elementNames[] = $element->getName();
      }
    }
    return $elementNames;
  }
}
