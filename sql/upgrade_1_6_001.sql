ALTER TABLE civicrm_iats_verify ADD COLUMN `auth_result` varchar(255) COMMENT 'Authorization string from iATS' ADD INDEX (auth_result)
