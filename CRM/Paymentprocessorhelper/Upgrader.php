<?php

/**
 * Collection of upgrade steps
 */
class CRM_Paymentprocessorhelper_Upgrader extends CRM_Paymentprocessorhelper_Upgrader_Base {

  // By convention, functions that look like "function upgrade_NNNN()" are
  // upgrade tasks. They are executed in order (like Drupal's hook_update_N).
  public function install() {
  	$new_table_sql = array();
	
	
	$new_table_sql[] = "CREATE TABLE IF NOT EXISTS `pogstone_authnet_messages` (
  `civicrm_contribution_id` varchar(256) COLLATE utf8_unicode_ci NOT NULL,
  `civicrm_recur_id` varchar(256) COLLATE utf8_unicode_ci NOT NULL,
  `rec_type` varchar(256) COLLATE utf8_unicode_ci NOT NULL,
  `message_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `x_response_code` varchar(256) COLLATE utf8_unicode_ci NOT NULL,
  `x_response_reason_code` varchar(256) COLLATE utf8_unicode_ci NOT NULL,
  `x_response_reason_text` varchar(800) COLLATE utf8_unicode_ci NOT NULL,
  `x_avs_code` varchar(256) COLLATE utf8_unicode_ci NOT NULL,
  `x_auth_code` varchar(256) COLLATE utf8_unicode_ci NOT NULL,
  `x_trans_id` varchar(256) COLLATE utf8_unicode_ci NOT NULL,
  `x_method` varchar(256) COLLATE utf8_unicode_ci NOT NULL,
  `x_card_type` varchar(256) COLLATE utf8_unicode_ci NOT NULL,
  `x_account_number` varchar(256) COLLATE utf8_unicode_ci NOT NULL,
  `x_first_name` varchar(256) COLLATE utf8_unicode_ci NOT NULL,
  `x_last_name` varchar(256) COLLATE utf8_unicode_ci NOT NULL,
  `x_company` varchar(800) COLLATE utf8_unicode_ci NOT NULL,
  `x_address` varchar(256) COLLATE utf8_unicode_ci NOT NULL,
  `x_city` varchar(256) COLLATE utf8_unicode_ci NOT NULL,
  `x_state` varchar(256) COLLATE utf8_unicode_ci NOT NULL,
  `x_zip` varchar(256) COLLATE utf8_unicode_ci NOT NULL,
  `x_country` varchar(256) COLLATE utf8_unicode_ci NOT NULL,
  `x_phone` varchar(256) COLLATE utf8_unicode_ci NOT NULL,
  `x_fax` varchar(256) COLLATE utf8_unicode_ci NOT NULL,
  `x_email` varchar(800) COLLATE utf8_unicode_ci NOT NULL,
  `x_invoice_num` varchar(256) COLLATE utf8_unicode_ci NOT NULL,
  `x_description` varchar(800) COLLATE utf8_unicode_ci NOT NULL,
  `x_type` varchar(256) COLLATE utf8_unicode_ci NOT NULL,
  `x_cust_id` varchar(256) COLLATE utf8_unicode_ci NOT NULL,
  `x_ship_to_first_name` varchar(256) COLLATE utf8_unicode_ci NOT NULL,
  `x_ship_to_last_name` varchar(256) COLLATE utf8_unicode_ci NOT NULL,
  `x_ship_to_company` varchar(256) COLLATE utf8_unicode_ci NOT NULL,
  `x_ship_to_address` varchar(256) COLLATE utf8_unicode_ci NOT NULL,
  `x_ship_to_city` varchar(256) COLLATE utf8_unicode_ci NOT NULL,
  `x_ship_to_state` varchar(256) COLLATE utf8_unicode_ci NOT NULL,
  `x_ship_to_zip` varchar(256) COLLATE utf8_unicode_ci NOT NULL,
  `x_ship_to_country` varchar(256) COLLATE utf8_unicode_ci NOT NULL,
  `x_amount` decimal(12,2) NOT NULL,
  `x_tax` varchar(256) COLLATE utf8_unicode_ci NOT NULL,
  `x_duty` varchar(256) COLLATE utf8_unicode_ci NOT NULL,
  `x_freight` varchar(256) COLLATE utf8_unicode_ci NOT NULL,
  `x_tax_exempt` varchar(256) COLLATE utf8_unicode_ci NOT NULL,
  `x_po_num` varchar(256) COLLATE utf8_unicode_ci NOT NULL,
  `x_MD5_Hash` varchar(256) COLLATE utf8_unicode_ci NOT NULL,
  `x_cvv2_resp_code` varchar(256) COLLATE utf8_unicode_ci NOT NULL,
  `x_cavv_response` varchar(256) COLLATE utf8_unicode_ci NOT NULL,
  `x_test_request` varchar(256) COLLATE utf8_unicode_ci NOT NULL,
  `x_subscription_id` varchar(256) COLLATE utf8_unicode_ci NOT NULL,
  `x_subscription_paynum` int(11) NOT NULL,
  `message_raw` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  KEY `x_subscription_id` (`x_subscription_id`(255)),
  KEY `x_trans_id` (`x_trans_id`(255)),
  KEY `message_date` (`message_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";

  $new_table_sql[] = "CREATE TABLE IF NOT EXISTS `pogstone_paypal_messages` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `rec_type` varchar(25) COLLATE utf8_unicode_ci NOT NULL,
  `message_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `mc_gross` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `mc_fee` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `txn_id` varchar(800) COLLATE utf8_unicode_ci NOT NULL,
  `recurring_payment_id` varchar(800) COLLATE utf8_unicode_ci NOT NULL,
  `amount` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `payment_date` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `payment_status` varchar(25) COLLATE utf8_unicode_ci NOT NULL,
  `first_name` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `last_name` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `payer_email` varchar(800) COLLATE utf8_unicode_ci NOT NULL,
  `txn_type` varchar(25) COLLATE utf8_unicode_ci NOT NULL,
  `period_type` varchar(25) COLLATE utf8_unicode_ci NOT NULL,
  `payment_fee` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `payment_gross` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `currency_code` varchar(25) COLLATE utf8_unicode_ci NOT NULL,
  `mc_currency` varchar(25) COLLATE utf8_unicode_ci NOT NULL,
  `outstanding_balance` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `next_payment_date` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `protection_eligibility` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `payment_cycle` varchar(25) COLLATE utf8_unicode_ci NOT NULL,
  `tax` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `payer_id` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `product_name` varchar(256) COLLATE utf8_unicode_ci NOT NULL,
  `charset` varchar(25) COLLATE utf8_unicode_ci NOT NULL,
  `rp_invoice_id` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `notify_version` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `amount_per_cycle` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `payer_status` varchar(256) COLLATE utf8_unicode_ci NOT NULL,
  `business` varchar(500) COLLATE utf8_unicode_ci NOT NULL,
  `verify_sign` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `initial_payment_amount` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `profile_status` varchar(25) COLLATE utf8_unicode_ci NOT NULL,
  `payment_type` varchar(25) COLLATE utf8_unicode_ci NOT NULL,
  `receiver_email` varchar(500) COLLATE utf8_unicode_ci NOT NULL,
  `receiver_id` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `residence_country` varchar(25) COLLATE utf8_unicode_ci NOT NULL,
  `receipt_id` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `transaction_subject` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `shipping` varchar(25) COLLATE utf8_unicode_ci NOT NULL,
  `product_type` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `time_created` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `ipn_track_id` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `civicrm_contribution_id` varchar(256) COLLATE utf8_unicode_ci NOT NULL,
  `civicrm_recur_id` varchar(256) COLLATE utf8_unicode_ci NOT NULL,
  `message_raw` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `civicrm_processed` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ;";

  foreach($new_table_sql as $cur_sql){
  		$dao  =  & CRM_Core_DAO::executeQuery( $cur_sql,    CRM_Core_DAO::$_nullArray ) ;
		$dao->free();
  	
  	}
  	
  }

  /**
   * Add an `id` primary key column to pogstone_authnet_messages.
   *
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_4300() {
    $this->ctx->log->info('Applying update 4300');
    CRM_Core_DAO::executeQuery('ALTER TABLE  `pogstone_authnet_messages` ADD  `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST');
    CRM_Core_DAO::executeQuery("ALTER TABLE  `pogstone_authnet_messages` ADD  `processed` DATETIME NULL DEFAULT NULL");
    CRM_Core_DAO::executeQuery('ALTER TABLE  `pogstone_iats_messages` CHANGE  `row_id`  `id` INT( 11 ) NOT NULL AUTO_INCREMENT');
    CRM_Core_DAO::executeQuery("ALTER TABLE  `pogstone_iats_messages` ADD  `processed` DATETIME NULL DEFAULT NULL");
    CRM_Core_DAO::executeQuery("ALTER TABLE  `pogstone_paypal_messages` ADD  `processed` DATETIME NULL DEFAULT NULL");
    return TRUE;
  } 

  /**
   * Example: Run an external SQL script when the module is installed
   *
  public function install() {
    $this->executeSqlFile('sql/myinstall.sql');
  }

  /**
   * Example: Run an external SQL script when the module is uninstalled
   *
  public function uninstall() {
   $this->executeSqlFile('sql/myuninstall.sql');
  }

  /**
   * Example: Run a simple query when a module is enabled
   *
  public function enable() {
    CRM_Core_DAO::executeQuery('UPDATE foo SET is_active = 1 WHERE bar = "whiz"');
  }

  /**
   * Example: Run a simple query when a module is disabled
   *
  public function disable() {
    CRM_Core_DAO::executeQuery('UPDATE foo SET is_active = 0 WHERE bar = "whiz"');
  }

  /**
   * Example: Run a couple simple queries
   *
   * @return TRUE on success
   * @throws Exception
   *
  public function upgrade_4200() {
    $this->ctx->log->info('Applying update 4200');
    CRM_Core_DAO::executeQuery('UPDATE foo SET bar = "whiz"');
    CRM_Core_DAO::executeQuery('DELETE FROM bang WHERE willy = wonka(2)');
    return TRUE;
  } // */


  /**
   * Example: Run an external SQL script
   *
   * @return TRUE on success
   * @throws Exception
  public function upgrade_4201() {
    $this->ctx->log->info('Applying update 4201');
    // this path is relative to the extension base dir
    $this->executeSqlFile('sql/upgrade_4201.sql');
    return TRUE;
  } // */


  /**
   * Example: Run a slow upgrade process by breaking it up into smaller chunk
   *
   * @return TRUE on success
   * @throws Exception
  public function upgrade_4202() {
    $this->ctx->log->info('Planning update 4202'); // PEAR Log interface

    $this->addTask(ts('Process first step'), 'processPart1', $arg1, $arg2);
    $this->addTask(ts('Process second step'), 'processPart2', $arg3, $arg4);
    $this->addTask(ts('Process second step'), 'processPart3', $arg5);
    return TRUE;
  }
  public function processPart1($arg1, $arg2) { sleep(10); return TRUE; }
  public function processPart2($arg3, $arg4) { sleep(10); return TRUE; }
  public function processPart3($arg5) { sleep(10); return TRUE; }
  // */


  /**
   * Example: Run an upgrade with a query that touches many (potentially
   * millions) of records by breaking it up into smaller chunks.
   *
   * @return TRUE on success
   * @throws Exception
  public function upgrade_4203() {
    $this->ctx->log->info('Planning update 4203'); // PEAR Log interface

    $minId = CRM_Core_DAO::singleValueQuery('SELECT coalesce(min(id),0) FROM civicrm_contribution');
    $maxId = CRM_Core_DAO::singleValueQuery('SELECT coalesce(max(id),0) FROM civicrm_contribution');
    for ($startId = $minId; $startId <= $maxId; $startId += self::BATCH_SIZE) {
      $endId = $startId + self::BATCH_SIZE - 1;
      $title = ts('Upgrade Batch (%1 => %2)', array(
        1 => $startId,
        2 => $endId,
      ));
      $sql = '
        UPDATE civicrm_contribution SET foobar = whiz(wonky()+wanker)
        WHERE id BETWEEN %1 and %2
      ';
      $params = array(
        1 => array($startId, 'Integer'),
        2 => array($endId, 'Integer'),
      );
      $this->addTask($title, 'executeSql', $sql, $params);
    }
    return TRUE;
  } // */

}