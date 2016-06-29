<?php

/*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.3                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2013                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
*/

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2013
 * $Id$
 *
 */
class CRM_Core_Payment_AuthorizeNetIPN extends CRM_Core_Payment_BaseIPN {
  function __construct() {
    parent::__construct();
  }

  function pogstone_log_details() {
    // Pogstone added:
    // $tmp_server_path =   realpath($_SERVER['DOCUMENT_ROOT'].'/../');
    // $filename_prefix = date('Y-m-d');

    //$logfile = $tmp_server_path."/".$filename_prefix."__pogstone_auth_net_log.txt";
    //print "<br>Log file path: ".$logfile;

    //   $auth_net_log_handle =   fopen(  $logfile , "a+") ;

    $now = date('Y-m-d  H:i:s');

    //   if($auth_net_log_handle){
    //  fwrite ( $auth_net_log_handle, $now);
    //  fwrite(  $auth_net_log_handle , '  ');

    // Flag if this is an ARB transaction. Set to false by default.
    $arb = false;

    // Store the posted values in an associative array
    $fields = array();

    $raw_msg = '';

    foreach ($_REQUEST as $name => $value) {
      // Create our associative array
      $fields[$name] = $value;
      $tmp = "Name: ".$name."  ---  Value: ".$value." ----------";

      $raw_msg = $raw_msg.$tmp;
      //fwrite(  $auth_net_log_handle ,$tmp) ;
    }

    //  fwrite($auth_net_log_handle, "\n-----------------------------------------------------------\n\n");
    // Get all the URL parameters/fields into variables.
    //  }

    //   ``,
    $x_response_code = $fields['x_response_code'];
    $x_response_reason_code = $fields['x_response_reason_code'];
    $x_response_reason_text = $fields['x_response_reason_text'];
    $x_avs_code = $fields['x_avs_code'];
    $x_auth_code = $fields['x_auth_code'];
    $x_trans_id = $fields['x_trans_id'];
    $x_method = $fields['x_method'];
    $x_card_type = $fields['x_card_type'];

    $x_account_number = $fields['x_account_number'];
    $x_first_name = $fields['x_first_name'] ;
    $x_last_name = $fields['x_last_name'];
    $x_company = $fields['x_company'];
    $x_address = $fields['x_address'];
    $x_city = $fields['x_city'];
    $x_state = $fields['x_state'];
    $x_zip = $fields['x_zip'] ;
    $x_country = $fields['x_country'];
    $x_phone = $fields['x_phone'];
    $x_fax = $fields['x_fax'];
    $x_email = $fields['x_email'];
    $x_invoice_num = $fields['x_invoice_num'];
    $x_description = $fields['x_description'];
    $x_type = $fields['x_type'];
    $x_cust_id = $fields['x_cust_id'];
    $x_ship_to_first_name = $fields['x_ship_to_first_name'];
    $x_ship_to_last_name = $fields['x_ship_to_last_name'];
    $x_ship_to_company = $fields['x_ship_to_company'];
    $x_ship_to_address = $fields['x_ship_to_address'];
    $x_ship_to_city = $fields['x_ship_to_city'];
    $x_ship_to_state = $fields['x_ship_to_state'];
    $x_ship_to_zip = $fields['x_ship_to_zip'];
    $x_ship_to_country = $fields['x_ship_to_country'];
    $x_amount = $fields['x_amount'];
    $x_tax = $fields['x_tax'];
    $x_duty = $fields['x_duty'];
    $x_freight = $fields['x_freight'];
    $x_tax_exempt = $fields['x_tax_exempt'];
    $x_po_num = $fields['x_po_num'];
    $x_MD5_Hash = $fields['x_MD5_Hash'];
    $x_cvv2_resp_code = $fields['x_cvv2_resp_code'];
    $x_cavv_response = $fields['x_cavv_response'];
    $x_test_request = $fields['x_test_request'];
    $x_subscription_id = $fields['x_subscription_id'];
    $x_subscription_paynum = $fields['x_subscription_paynum'];

    // Check for refunds. For refunds, record the amount as a negative number.
    if ($x_type == 'credit') {
      $amount_as_num = (float) $x_amount;
      $x_amount = 0 - $amount_as_num;
    }

    if (strlen($x_amount) == 0) {
      $x_amount = "0";
    }

    if( strlen( $x_subscription_id) == 0){
      $x_subscription_id = "0";
    }

    if (strlen($x_subscription_paynum) == 0) {
       $x_subscription_paynum = "0";
    }

    $sql = "INSERT INTO pogstone_authnet_messages (`civicrm_contribution_id`, `civicrm_recur_id`, `rec_type`, `message_date`,
      `x_response_code`, `x_response_reason_code`, `x_response_reason_text`, `x_avs_code`, `x_auth_code`, `x_trans_id`, `x_method`, `x_card_type`,
       `x_account_number`, `x_first_name`, `x_last_name`, `x_company`, `x_address`, `x_city`, `x_state`, `x_zip`, `x_country`, `x_phone`, `x_fax`,
        `x_email`, `x_invoice_num`, `x_description`, `x_type`, `x_cust_id`, `x_ship_to_first_name`, `x_ship_to_last_name`, `x_ship_to_company`,
        `x_ship_to_address`, `x_ship_to_city`, `x_ship_to_state`, `x_ship_to_zip`, `x_ship_to_country`, `x_amount`, `x_tax`, `x_duty`, `x_freight`,
        `x_tax_exempt`, `x_po_num`, `x_MD5_Hash`, `x_cvv2_resp_code`, `x_cavv_response`, `x_test_request`, `x_subscription_id`, `x_subscription_paynum`, message_raw)
         VALUES ('', '', 'authorize.net', CURRENT_TIMESTAMP,
         '$x_response_code', '$x_response_reason_code', '$x_response_reason_text', '$x_avs_code', '$x_auth_code', '$x_trans_id', '$x_method',
          '$x_card_type',
          '$x_account_number', '$x_first_name', '$x_last_name', '$x_company', '$x_address', '$x_city',
          '".$x_state."', '".$x_zip."', '".$x_country."', '".$x_phone."', '".$x_fax."',
           '".$x_email."', '".$x_invoice_num."', '".$x_description."', '".$x_type."', '".$x_cust_id."', '".$x_ship_to_first_name."', '".$x_ship_to_last_name."', '".$x_ship_to_company."',
           '".$x_ship_to_address."', '".$x_ship_to_city."', '".$x_ship_to_state."', '".$x_ship_to_zip."', '$x_ship_to_country', '$x_amount' , '$x_tax', '".$x_duty."', '".$x_freight."',
           '".$x_tax_exempt."', '".$x_po_num."', '".$x_MD5_Hash."', '".$x_cvv2_resp_code."', '$x_cavv_response', '$x_test_request', '".$x_subscription_id."', '".$x_subscription_paynum."', '".$raw_msg."');" ;

    $params = array();

    //print "<br>sql: ".$sql;
    //print "<br>About to execute logging sql.";
    $dao = CRM_Core_DAO::executeQuery( $sql, $params );

    //print "<br>done with sql logging.<br>";
    $dao->free();
  }

  function main($component = 'contribute') {
    // Pogstone added:
    require_once 'CRM/Utils/Request.php';
    self::pogstone_log_details();

    // Pogstone note: Bypass the usual CiviCRM core processing because its VERY buggy on 4.3.x.
    // Instead there is a Pogstone CRON job that will process the messages from the log database table.

    // end of Pogstone section.
    require_once('utils/Entitlement.php');
    $tmpEntitlement = new Entitlement();

    if($tmpEntitlement->isRunningCiviCRM_4_3()) {
      // Do nothing. CRON job will process the data into contributions.
    }
    else {
      print "<br><br>ERROR: This code is only meant for CiviCRM 4.3.x";

      // This code only executes on version 4.2.x
      //we only get invoice num as a key player from payment gateway response.
      //for ARB we get x_subscription_id and x_subscription_paynum
      $x_subscription_id = self::retrieve('x_subscription_id', 'String');

      if ($x_subscription_id) {
        //Approved

        $ids = $objects = array();
        $input['component'] = $component;

        // load post vars in $input
        $this->getInput($input, $ids);

        // load post ids in $ids
        $this->getIDs($ids, $input);

        $paymentProcessorID = CRM_Core_DAO::getFieldValue('CRM_Financial_DAO_PaymentProcessorType',
          'AuthNet', 'id', 'name'
        );

        if (!$this->validateData($input, $ids, $objects, TRUE, $paymentProcessorID)) {
          return FALSE;
        }

        if ($component == 'contribute' && $ids['contributionRecur']) {
          // check if first contribution is completed, else complete first contribution
          $first = TRUE;
          if ($objects['contribution']->contribution_status_id == 1) {
            $first = FALSE;
          }
          return $this->recur($input, $ids, $objects, $first);
        }
      }
    }
  }

  function recur(&$input, &$ids, &$objects, $first) {
    $recur = &$objects['contributionRecur'];

    // do a subscription check
    if ($recur->processor_id != $input['subscription_id']) {
      CRM_Core_Error::debug_log_message("Unrecognized subscription.");
      echo "Failure: Unrecognized subscription<p>";
      return FALSE;
    }

    // At this point $object has first contribution loaded.
    // Lets do a check to make sure this payment has the amount same as that of first contribution.
    if ($objects['contribution']->total_amount != $input['amount']) {
      CRM_Core_Error::debug_log_message("Subscription amount mismatch.");
      echo "Failure: Subscription amount mismatch<p>";
      return FALSE;
    }

    $contributionStatus = CRM_Contribute_PseudoConstant::contributionStatus(NULL, 'name');

    $transaction = new CRM_Core_Transaction();

    $now = date('YmdHis');

    // fix dates that already exist
    $dates = array('create_date', 'start_date', 'end_date', 'cancel_date', 'modified_date');
    foreach ($dates as $name) {
      if ($recur->$name) {
        $recur->$name = CRM_Utils_Date::isoToMysql($recur->$name);
      }
    }

    //load new contribution object if required.
    if (!$first) {
      // create a contribution and then get it processed
      $contribution = new CRM_Contribute_BAO_Contribution();
      $contribution->contact_id = $ids['contact'];
            $contribution->financial_type_id  = $objects['contributionType']->id;
      $contribution->contribution_page_id = $ids['contributionPage'];
      $contribution->contribution_recur_id = $ids['contributionRecur'];
      $contribution->receive_date = $now;
      $contribution->currency = $objects['contribution']->currency;
      $contribution->payment_instrument_id = $objects['contribution']->payment_instrument_id;
      $contribution->amount_level = $objects['contribution']->amount_level;
      $contribution->address_id = $objects['contribution']->address_id;
      $objects['contribution'] = &$contribution;
    }
    $objects['contribution']->invoice_id = md5(uniqid(rand(), TRUE));
    $objects['contribution']->total_amount = $input['amount'];
    $objects['contribution']->trxn_id = $input['trxn_id'];

    // since we have processor loaded for sure at this point,
    // check and validate gateway MD5 response if present
    $this->checkMD5($ids, $input);

    $sendNotification = FALSE;
    if ($input['response_code'] == 1) {
      // Approved
      if ($first) {
        $recur->start_date = $now;
        $recur->trxn_id = $recur->processor_id;
        $sendNotification = TRUE;
        $subscriptionPaymentStatus = CRM_Core_Payment::RECURRING_PAYMENT_START;
      }
      $statusName = 'In Progress';
      if (($recur->installments > 0) &&
        ($input['subscription_paynum'] >= $recur->installments)
      ) {
        // this is the last payment
        $statusName = 'Completed';
        $recur->end_date = $now;
        $sendNotification = TRUE;
        $subscriptionPaymentStatus = CRM_Core_Payment::RECURRING_PAYMENT_END;
      }
      $recur->modified_date = $now;
      $recur->contribution_status_id = array_search($statusName, $contributionStatus);
      $recur->save();
    }
    else {
      // Declined
      // failed status
      $recur->contribution_status_id = array_search('Failed', $contributionStatus);
      $recur->cancel_date = $now;
      $recur->save();

      CRM_Core_Error::debug_log_message("Subscription payment failed - '{$input['response_reason_text']}'");

      // the recurring contribution has declined a payment or has failed
      // so we just fix the recurring contribution and not change any of
      // the existing contribiutions
      // CRM-9036
      return TRUE;
    }

    // check if contribution is already completed, if so we ignore this ipn
    if ($objects['contribution']->contribution_status_id == 1) {
      $transaction->commit();
      CRM_Core_Error::debug_log_message("returning since contribution has already been handled");
      echo "Success: Contribution has already been handled<p>";
      return TRUE;
    }

    $this->completeTransaction($input, $ids, $objects, $transaction, $recur);

    if ($sendNotification) {
      $autoRenewMembership = FALSE;
      if ($recur->id &&
        isset($ids['membership']) && $ids['membership']
      ) {
        $autoRenewMembership = TRUE;
      }

      //send recurring Notification email for user
      CRM_Contribute_BAO_ContributionPage::recurringNotify($subscriptionPaymentStatus,
        $ids['contact'],
        $ids['contributionPage'],
        $recur,
        $autoRenewMembership
      );
    }
  }

  function getInput(&$input, &$ids) {
    $input['amount'] = self::retrieve('x_amount', 'String');
    $input['subscription_id'] = self::retrieve('x_subscription_id', 'Integer');
    $input['response_code'] = self::retrieve('x_response_code', 'Integer');
    $input['MD5_Hash'] = self::retrieve('x_MD5_Hash', 'String', FALSE, '');
    $input['fee_amount'] = self::retrieve('x_fee_amount', 'Money', FALSE, '0.00');
    $input['net_amount'] = self::retrieve('x_net_amount', 'Money', FALSE, '0.00');
    $input['response_reason_code'] = self::retrieve('x_response_reason_code', 'String', FALSE);
    $input['response_reason_text'] = self::retrieve('x_response_reason_text', 'String', FALSE);
    $input['subscription_paynum'] = self::retrieve('x_subscription_paynum', 'Integer', FALSE, 0);
    $input['trxn_id'] = self::retrieve('x_trans_id', 'String', FALSE);
    if ($input['trxn_id']) {
      $input['is_test'] = 0;
    }
    else {
      $input['is_test'] = 1;
      $input['trxn_id'] = md5(uniqid(rand(), TRUE));
    }

    if (!$this->getBillingID($ids)) {
      return FALSE;
    }
    $billingID = $ids['billing'];
    $params = array(
      'first_name' => 'x_first_name',
      'last_name' => 'x_last_name',
      "street_address-{$billingID}" => 'x_address',
      "city-{$billingID}" => 'x_city',
      "state-{$billingID}" => 'x_state',
      "postal_code-{$billingID}" => 'x_zip',
      "country-{$billingID}" => 'x_country',
      "email-{$billingID}" => 'x_email',
    );
    foreach ($params as $civiName => $resName) {
      $input[$civiName] = self::retrieve($resName, 'String', FALSE);
    }
  }

  function getIDs(&$ids, &$input) {
    $ids['contact'] = self::retrieve('x_cust_id', 'Integer');
    $ids['contribution'] = self::retrieve('x_invoice_num', 'Integer');

    // joining with contribution table for extra checks
    $sql = "
    SELECT cr.id, cr.contact_id
      FROM civicrm_contribution_recur cr
INNER JOIN civicrm_contribution co ON co.contribution_recur_id = cr.id
     WHERE cr.processor_id = '{$input['subscription_id']}' AND
           (cr.contact_id = {$ids['contact']} OR co.id = {$ids['contribution']})
     LIMIT 1";
    $contRecur = CRM_Core_DAO::executeQuery($sql);
    $contRecur->fetch();
    $ids['contributionRecur'] = $contRecur->id;
    if($ids['contact_id'] != $contRecur->contact_id){
      CRM_Core_Error::debug_log_message("Recurring contribution appears to have been re-assigned from id {$ids['contact_id']} to {$contRecur->contact_id}
        Continuing with {$contRecur->contact_id}
      ");
      $ids['contact_id'] = $contRecur->contact_id;
    }
    if (!$ids['contributionRecur']) {
      CRM_Core_Error::debug_log_message("Could not find contributionRecur id: ".print_r($input, TRUE));
      echo "Failure: Could not find contributionRecur<p>";
      exit();
    }

    // get page id based on contribution id
    $ids['contributionPage'] = CRM_Core_DAO::getFieldValue('CRM_Contribute_DAO_Contribution',
      $ids['contribution'],
      'contribution_page_id'
    );

    if ($input['component'] == 'event') {
      // FIXME: figure out fields for event
    }
    else {
      // get the optional ids

      // Get membershipId. Join with membership payment table for additional checks
      $sql = "
    SELECT m.id
      FROM civicrm_membership m
INNER JOIN civicrm_membership_payment mp ON m.id = mp.membership_id AND mp.contribution_id = {$ids['contribution']}
     WHERE m.contribution_recur_id = {$ids['contributionRecur']}
     LIMIT 1";
      if ($membershipId = CRM_Core_DAO::singleValueQuery($sql)) {
        $ids['membership'] = $membershipId;
      }

      // FIXME: todo related_contact and onBehalfDupeAlert. Check paypalIPN.
    }
  }

  static function retrieve($name, $type, $abort = TRUE, $default = NULL, $location = 'POST') {
    static $store = NULL;
    $value = CRM_Utils_Request::retrieve($name, $type, $store,
      FALSE, $default, $location
    );
    if ($abort && $value === NULL) {
      CRM_Core_Error::debug_log_message("Could not find an entry for $name in $location");
      CRM_Core_Error::debug_var('POST', $_POST);
      CRM_Core_Error::debug_var('REQUEST', $_REQUEST);
      echo "Failure: Missing Parameter<p>";
      exit();
    }
    return $value;
  }

  function checkMD5($ids, $input) {
    $paymentProcessor = CRM_Financial_BAO_PaymentProcessor::getPayment($ids['paymentProcessor'],
      $input['is_test'] ? 'test' : 'live'
    );
    $paymentObject = CRM_Core_Payment::singleton($input['is_test'] ? 'test' : 'live', $paymentProcessor);

    if (!$paymentObject->checkMD5($input['MD5_Hash'], $input['trxn_id'], $input['amount'], TRUE)) {
      CRM_Core_Error::debug_log_message("MD5 Verification failed.");
      echo "Failure: Security verification failed<p>";
      exit();
    }
    return TRUE;
  }
}
