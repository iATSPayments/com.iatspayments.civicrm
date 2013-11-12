<?php
/* this administrative page provides simple access to recent transactions
   and an opportunity for the system to warn administrators about failing
   crons */

require_once 'CRM/Core/Page.php';

class CRM_iATS_Page_iATSAdmin extends CRM_Core_Page {
  function run() {
    // Example: Assign a variable for use in a template
    $this->assign('currentTime', date('Y-m-d H:i:s'));
    // Load the most recent requests and responses from the log files
    $log = $this->getLog();
    // $log[] = array('cc' => 'test', 'ip' => 'whatever', 'auth_result' => 'blah');
    $this->assign('iATSLog', $log);
    parent::run();
  }

  function getLog($n = 10) {
    // avoid sql injection attacks
    $n = (int) $n;
    $sql = "SELECT * FROM civicrm_iats_request_log request LEFT JOIN civicrm_iats_response_log response ON request.invoice_num = response.invoice_num ORDER BY request.id DESC LIMIT $n";
    $dao = CRM_Core_DAO::executeQuery($sql);
    $log = array();
    $params = array('version' => 3, 'sequential' => 1, 'return' => 'contribution_id');
    $className = get_class($dao);
    $internal = array_keys(get_class_vars($className));
    while ($dao->fetch()) {
      $entry = get_object_vars($dao);
      unset($entry['']); // ghost entry!
      foreach($internal as $key) { // remove internal fields
        unset($entry[$key]);
      }
      $params['invoice_id'] = $entry['invoice_num'];
      $result = civicrm_api('Contribution','getsingle', $params);
      if (!empty($result['contribution_id'])) {
        $entry += $result;
        $entry['contributionURL'] = CRM_Utils_System::url('civicrm/contact/view/contribution', 'reset=1&id='.$entry['contribution_id'].'&cid='.$entry['contact_id'].'&action=view&selectedChild=Contribute');
      }
      $log[] = $entry;
    }
    return $log;
  }
}
