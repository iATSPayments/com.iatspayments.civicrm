-- install sql for IATS Services extension, create a table to hold custom codes

CREATE TABLE `civicrm_iats_customer_codes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT  COMMENT 'Custom code Id',
  `customer_code` varchar(255) NOT NULL COMMENT 'Customer code returned from IATS',
  `ip` varchar(255) DEFAULT NULL COMMENT 'Last IP from which this customer code was accessed or created',
  `expiry` varchar(4) DEFAULT NULL COMMENT 'CC expiry yymm',
  `cid` int(10) unsigned DEFAULT '0' COMMENT 'CiviCRM contact id',
  `email` varchar(255) DEFAULT NULL COMMENT 'Customer-constituent Email address',
  `recur_id` int(10) unsigned DEFAULT '0' COMMENT 'CiviCRM recurring_contribution table id',
  PRIMARY KEY ( `id` ),
  UNIQUE INDEX (`customer_code`),
  KEY (`cid`),
  KEY (`email`),
  KEY (`recur_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Table to store customer codes';
