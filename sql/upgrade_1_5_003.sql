UPDATE civicrm_payment_processor_type SET payment_instrument_id = 2 WHERE (class_name = 'Payment_iATSServiceACHEFT' OR class_name = 'Payment_iATSServiceUKDD');
UPDATE civicrm_payment_processor p INNER JOIN civicrm_payment_processor_type t ON p.payment_processor_type_id = t.id SET p.payment_instrument_id = 2 WHERE (t.class_name = 'Payment_iATSServiceACHEFT' OR t.class_name = 'Payment_iATSServiceUKDD');
UPDATE civicrm_contribution_recur r INNER JOIN civicrm_payment_processor p ON r.payment_processor_id = p.id INNER JOIN civicrm_payment_processor_type t ON p.payment_processor_type_id = t.id SET r.payment_instrument_id = 2 WHERE (t.class_name = 'Payment_iATSServiceACHEFT' OR t.class_name = 'Payment_iATSServiceUKDD');