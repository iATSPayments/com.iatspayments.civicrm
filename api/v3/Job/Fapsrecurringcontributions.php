<?php
use CRM_Iats_ExtensionUtil as E;

/**
 * Job.Fapsrecurringcontributions API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_job_Fapsrecurringcontributions_spec(&$spec) {
  $spec['recur_id'] = array(
    'name' => 'recur_id',
    'title' => 'Recurring payment id',
    'api.required' => 0,
    'type' => 1,
  );
  $spec['cycle_day'] = array(
    'name' => 'cycle_day',
    'title' => 'Only contributions that match a specific cycle day.',
    'api.required' => 0,
    'type' => 1,
  );
  $spec['failure_count'] = array(
    'name' => 'failure_count',
    'title' => 'Filter by number of failure counts',
    'api.required' => 0,
    'type' => 1,
  );
  $spec['catchup'] = array(
    'title' => 'Process as if in the past to catch up.',
    'api.required' => 0,
  );
  $spec['ignoremembership'] = array(
    'title' => 'Ignore memberships',
    'api.required' => 0,
  );
}

/**
 * Job.Fapsrecurringcontributions API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_job_Fapsrecurringcontributions($params) {
  // Running this job in parallell could generate bad duplicate contributions.
  $lock = new CRM_Core_Lock('civicrm.job.Fapsrecurringcontributions');

  if (!$lock->acquire()) {
    return civicrm_api3_create_success(ts('Failed to acquire lock. No contribution records were processed.'));
  }
  // use catchup mode to calculate next scheduled contribution based on current value rather than current date
  $catchup = !empty($params['catchup']);
  unset($params['catchup']);
  // do memberships by default, i.e. copy any membership information/relationship from contribution template
  $domemberships = empty($params['ignoremembership']);
  unset($params['ignoremembership']);

  // $config = &CRM_Core_Config::singleton();
  // $debug  = false;
  // do my calculations based on yyyymmddhhmmss representation of the time
  // not sure about time-zone issues.
  $dtCurrentDay    = date("Ymd", mktime(0, 0, 0, date("m"), date("d"), date("Y")));
  $dtCurrentDayStart = $dtCurrentDay . "000000";
  $dtCurrentDayEnd   = $dtCurrentDay . "235959";
  $expiry_limit = date('ym');
  // Restrict this method of recurring contribution processing to only these payment processors.
  $result = civicrm_api3('PaymentProcessor', 'get', ['class_name' => ['IN' => ['Payment_Faps','Payment_FapsACH']]]);
  if (empty($result['values'])) {
    return;
  }
  $fapsProcessors = $result['values'];
  // TODO: before triggering payments, do some housekeeping of the civicrm_contribution_recur records?
  // Now we're ready to trigger payments
  // Select the ongoing recurring payments for FAPS where the next scheduled contribution date is before the end of of the current day.
  $get = array(
      'next_sched_contribution_date' => ['<=' => $dtCurrentDayEnd],
      'payment_processor_id' => ['IN' => array_keys($fapsProcessors)],
      'contribution_status_id' => ['IN' => ['In Progress', 'Pending', 'Overdue']],
      'options' => ['limit' => 0],
      'return' => ['id', 'contact_id', 'amount', 'processor_id', 'failure_count', 'payment_processor_id', 'next_sched_contribution_date',
      'processor_id', 'payment_instrument_id', 'is_test', 'currency', 'financial_type_id','is_email_receipt',
      'frequency_interval', 'frequency_unit'],
  );
  // additional filters that may be passed in as params
  if (!empty($params['recur_id'])) {
    $get['id'] = $params['recur_id'];
  }
  if (!empty($params['cycle_day'])) {
    $get['cycle_day'] = $params['cycle_day'];
  }
  if (isset($params['failure_count'])) {
    $get['failure_count'] = $params['failure_count'];
  }
  $recurringContributions = civicrm_api3('ContributionRecur', 'get',  $get);
  $counter = 0;
  $error_count  = 0;
  $output  = array();
  $settings = CRM_Core_BAO_Setting::getItem('iATS FAPS Payments Extension', 'faps_settings');
  $receipt_recurring = $settings['receipt_recurring'];
  $email_failure_report = empty($settings['email_recurring_failure_report']) ? '' : $settings['email_recurring_failure_report'];
  // By default, after 3 failures move the next scheduled contribution date forward.
  $failure_threshhold = empty($settings['recurring_failure_threshhold']) ? 3 : (int) $settings['recurring_failure_threshhold'];
  $failure_report_text = '';
  foreach($recurringContributions['values'] as $recurringContribution) {
    // Strategy: create the contribution record with status = 2 (= pending), try the payment, and update the status to 1 if successful
    //           also, advance the next scheduled payment before the payment attempt and pull it back if we know it fails.
    $contribution_recur_id    = $recurringContribution['id'];
    $contact_id = $recurringContribution['contact_id'];
    $total_amount = $recurringContribution['amount'];
    $payment_processor_id = $recurringContribution['payment_processor_id'];
    $vault = $recurringContribution['processor_id'];
    // Try to get a contribution template for this contribution series - if none matches (e.g. if a donation amount has been changed), we'll just be naive about it.
    $contribution_template = CRM_Iats_Transaction::getContributionTemplate(['contribution_recur_id' => $contribution_recur_id, 'total_amount' => $total_amount]);
    CRM_Core_Error::debug_var('Contribution Template', $contribution_template);
    // generate my invoice id like CiviCRM does
    $hash = md5(uniqid(rand(), TRUE));
    $original_contribution_id = empty($contribution_template['original_contribution_id']) ? 0 : $contribution_template['original_contribution_id'];
    $failure_count    = $recurringContribution['failure_count'];
    $paymentProcessor = $fapsProcessors[$payment_processor_id];
    $subtype = substr($paymentProcessor['class_name'], 12);
    $source = E::ts('iATS Payments FAPS %1 Recurring Contribution ( id = %2 )', [
      1 => $subtype,
      2 => $contribution_recur_id 
    ]);
    $receive_ts = $catchup ? strtotime($recurringContribution['next_sched_contribution_date']) : time();
    // i.e. now or whenever it was supposed to run if in catchup mode.
    $receive_date = date("YmdHis", $receive_ts);
    // Check if we already have an error.
    $errors = array();
    if (empty($vault)) {
      $errors[] = E::ts('Recur id %1 is missing a vault string.', array(1 => $contribution_recur_id));
    }
    elseif (0 === strpos($vault,':')) {
      $errors[] = E::ts('Recur id %1 has an invalid vault string.', array(1 => $contribution_recur_id));
    }
    // Todo: warn/check for expiry?
    //if (($dao->icc_expiry != '0000') && ($dao->icc_expiry < $expiry_limit)) {
      // $errors[] = ts('Recur id %1 is has an expired cc for the customer code.', array(1 => $contribution_recur_id));.
    //}
    if (count($errors)) {
      $source .= ' Errors: ' . implode(' ', $errors);
    }
    $contribution = array(
      'version'        => 3,
      'contact_id'       => $contact_id,
      'receive_date'       => $receive_date,
      'total_amount'       => $total_amount,
      'payment_instrument_id'  => $recurringContribution['payment_instrument_id'],
      'contribution_recur_id'  => $contribution_recur_id,
      'invoice_id'       => $hash,
      'source'         => $source,
      'contribution_status_id' => 2, /* initialize as pending, so we can run completetransaction after taking the money */
      'currency'  => $recurringContribution['currency'],
      'payment_processor'   => $payment_processor_id,
      'is_test'        => $recurringContribution['is_test'], /* propagate the is_test value from the recurring record */
      'financial_type_id' => $recurringContribution['financial_type_id'],
    );
    $get_from_template = ['contribution_campaign_id', 'amount_level'];
    foreach ($get_from_template as $field) {
      if (isset($contribution_template[$field])) {
        $contribution[$field] = is_array($contribution_template[$field]) ? implode(', ', $contribution_template[$field]) : $contribution_template[$field];
      }
    }
    // if we have a created a pending contribution record due to a future start time, then recycle that CiviCRM contribution record now.
    // Note that the date and amount both could have changed.
    // The key is to only match if we find a single pending contribution, with a NULL transaction id, for this recurring schedule.
    // We'll need to pay attention later that we may or may not already have a contribution id.
    try {
      $pending_contribution = civicrm_api3('Contribution', 'getsingle', array(
        'return' => array('id'),
        'trxn_id' => array('IS NULL' => 1),
        'contribution_recur_id' => $contribution_recur_id,
        'contribution_status_id' => "Pending",
      ));
      if (!empty($pending_contribution['id'])) {
        $contribution['id'] = $pending_contribution['id'];
      }
    }
    catch (Exception $e) {
      // ignore, we'll proceed normally without a contribution id
    }
    // If I'm not recycling a contribution record and my original has line_items, then I'll add them to the contribution creation array.
    // Note: if the amount of a matched pending contribution has changed, then we need to remove the line items from the contribution.
    if (empty($contribution['id']) && !empty($contribution_template['line_items'])) {
      $contribution['skipLineItem'] = 1;
      $contribution['api.line_item.create'] = $contribution_template['line_items'];
    }
    if (count($errors)) {
      ++$error_count;
      ++$counter;
      /* create a failed contribution record, don't bother talking to iats */
      $contribution['contribution_status_id'] = 4;
      $contributionResult = civicrm_api('contribution', 'create', $contribution);
      if ($contributionResult['is_error']) {
        $errors[] = $contributionResult['error_message'];
      }
      if ($email_failure_report) {
        $failure_report_text .= "\n Unexpected Errors: " . implode(' ', $errors);
      }
      continue;
    }
    // if no errors, finish the job ..
    // Assign basic options.
    $options = array(
      'is_email_receipt' => (($receipt_recurring < 2) ? $receipt_recurring : $recurringContribution['is_email_receipt']),
      'vault' => $recurringContribution['processor_id'],
      'subtype' => $subtype,
    );
    // If our template contribution is a membership payment, make this one also.
    if ($domemberships && !empty($contribution_template['contribution_id'])) {
      try {
        $membership_payment = civicrm_api('MembershipPayment', 'getsingle', array('version' => 3, 'contribution_id' => $contribution_template['contribution_id']));
        if (!empty($membership_payment['membership_id'])) {
          $options['membership_id'] = $membership_payment['membership_id'];
        }
      }
      catch (Exception $e) {
        // ignore, if will fail correctly if there is no membership payment.
      }
    }
    // So far so, good ... now use my utility function process_contribution_payment to
    // create the pending contribution and try to get the money, and then do one of:
    // update the contribution to failed, leave as pending for server failure, complete the transaction,
    // or update a pending ach/eft with it's transaction id.
    // But first: advance the next collection date now so that in case of server failure on return from a payment request I don't try to take money again.
    // Save the current value to restore in case of payment failure (perhaps ...).
    $saved_next_sched_contribution_date = $recurringContribution['next_sched_contribution_date'];
    /* calculate the next collection date, based on the recieve date (note effect of catchup mode, above)  */
    $next_collection_date = date('Y-m-d H:i:s', strtotime('+'.$recurringContribution['frequency_interval'].' '.$recurringContribution['frequency_unit'], $receive_ts));
    $contribution_recur_set = array('version' => 3, 'id' => $contribution['contribution_recur_id'], 'next_sched_contribution_date' => $next_collection_date);
    $result = CRM_Iats_Transaction::process_contribution_payment($contribution, $options, $original_contribution_id);
    if ($email_failure_report && !empty($contribution['faps_reject_code'])) {
      $failure_report_text .= "\n $result ";
    }
    $output[] = $result;

    /* in case of critical failure set the series to pending */
    if (!empty($contribution['faps_reject_code'])) {
      switch ($contribution['faps_reject_code']) {
        // Reported lost or stolen.
        case 'REJECT: 25':
          // Do not reprocess!
        case 'REJECT: 100':
          /* convert the contribution series to pending to avoid reprocessing until dealt with */
          civicrm_api('ContributionRecur', 'create',
            array(
              'version' => 3,
              'id'      => $contribution['contribution_recur_id'],
              'contribution_status_id'   => 2,
            )
          );
          break;
      }
    }

    /* by default, just set the failure count back to 0 */
    $contribution_recur_set = array('version' => 3, 'id' => $contribution['contribution_recur_id'], 'failure_count' => '0', 'next_sched_contribution_date' => $next_collection_date);
    /* special handling for failures: try again at next opportunity if we haven't failed too often */
    if (4 == $contribution['contribution_status_id']) {
      $contribution_recur_set['failure_count'] = $failure_count + 1;
      /* if it has failed and the failure threshold will not be reached with this failure, set the next sched contribution date to what it was */
      if ($contribution_recur_set['failure_count'] < $failure_threshhold) {
        // Should the failure count be reset otherwise? It is not.
        $contribution_recur_set['next_sched_contribution_date'] = $saved_next_sched_contribution_date;
      }
    }
    civicrm_api('ContributionRecur', 'create', $contribution_recur_set);
    $result = civicrm_api('activity', 'create',
      array(
        'version'       => 3,
        'activity_type_id'  => 6,
        'source_contact_id'   => $contact_id,
        'source_record_id' => $contribution['id'],
        'assignee_contact_id' => $contact_id,
        'subject'       => "Attempted iATS FAPS $subtype Recurring Contribution for " . $total_amount,
        'status_id'       => 2,
        'activity_date_time'  => date("YmdHis"),
      )
    );
    if ($result['is_error']) {
      $output[] = ts(
        'An error occurred while creating activity record for contact id %1: %2',
        array(
          1 => $contact_id,
          2 => $result['error_message'],
        )
      );
      ++$error_count;
    }
    else {
      $output[] = ts('Created activity record for contact id %1', array(1 => $contact_id));
    }
    ++$counter;
  }

  // Now update the end_dates and status for non-open-ended contribution series if they are complete (so that the recurring contribution status will show correctly)
  // This is a simplified version of what we did before the processing.
  /*
  $select = 'SELECT cr.id, count(c.id) AS installments_done, cr.installments
      FROM civicrm_contribution_recur cr
      INNER JOIN civicrm_contribution c ON cr.id = c.contribution_recur_id
      INNER JOIN civicrm_payment_processor pp ON cr.payment_processor_id = pp.id
      WHERE
        (pp.class_name = %1 OR pp.class_name = %2 OR pp.class_name = %3)
        AND (cr.installments > 0)
        AND (cr.contribution_status_id  = 5)
        AND (c.contribution_status_id IN (1,2))
      GROUP BY c.contribution_recur_id';
  $dao = CRM_Core_DAO::executeQuery($select, $args);
  while ($dao->fetch()) {
    // Check if my end date should be set to now because I have finished
    // I'm done with installments.
    if ($dao->installments_done >= $dao->installments) {
      // Set this series complete and the end_date to now.
      $update = 'UPDATE civicrm_contribution_recur SET contribution_status_id = 1, end_date = NOW() WHERE id = %1';
      CRM_Core_DAO::executeQuery($update, array(1 => array($dao->id, 'Int')));
    }
  }
  */
  $lock->release();
  // If errors ..
  if ((strlen($failure_report_text) > 0) && $email_failure_report) {
    list($fromName, $fromEmail) = CRM_Core_BAO_Domain::getNameAndEmail();
    $mailparams = array(
      'from' => $fromName . ' <' . $fromEmail . '> ',
      'to' => 'System Administrator <' . $email_failure_report . '>',
      'subject' => ts('iATS Recurring Payment job failure report: ' . date('c')),
      'text' => $failure_report_text,
      'returnPath' => $fromEmail,
    );
    // print_r($mailparams);
    CRM_Utils_Mail::send($mailparams);
  }
  // If errors ..
  if ($error_count > 0) {
    return civicrm_api3_create_error(
      ts("Completed, but with %1 errors. %2 records processed.",
        array(
          1 => $error_count,
          2 => $counter,
        )
      ) . "<br />" . implode("<br />", $output)
    );
  }
  // If no errors and records processed ..
  if ($counter) {
    return civicrm_api3_create_success(
      ts(
        '%1 contribution record(s) were processed.',
        array(
          1 => $counter,
        )
      ) . "<br />" . implode("<br />", $output)
    );
  }
  // No records processed.
  return civicrm_api3_create_success(ts('No contribution records were processed.'));
}
