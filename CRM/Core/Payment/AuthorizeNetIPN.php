<?php
/*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.7                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2016                                |
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
 * @copyright CiviCRM LLC (c) 2004-2016
 */
class CRM_Core_Payment_AuthorizeNetIPN extends CRM_Core_Payment_BaseIPN {

  /**
   * Constructor function.
   *
   * @param array $inputData
   *   contents of HTTP REQUEST.
   *
   * @throws CRM_Core_Exception
   */
  public function __construct($inputData) {
    $this->setInputParameters($inputData);
    parent::__construct();
  }

  /**
   * @param string $component
   *
   * @return bool|void
   */
  public function main($component = 'contribute') {
    // Pogstone added:
    $this->pogstone_log_details();
    return TRUE;
    // End of Pogstone section.
    // NB: leaving the rest of the file intact so that it is easier to diff with future CiviCRM versions.

    //we only get invoice num as a key player from payment gateway response.
    //for ARB we get x_subscription_id and x_subscription_paynum
    $x_subscription_id = $this->retrieve('x_subscription_id', 'String');
    $ids = $objects = $input = array();

    if ($x_subscription_id) {
      //Approved

      $input['component'] = $component;

      // load post vars in $input
      $this->getInput($input, $ids);

      // load post ids in $ids
      $this->getIDs($ids, $input);

      // This is an unreliable method as there could be more than one instance.
      // Recommended approach is to use the civicrm/payment/ipn/xx url where xx is the payment
      // processor id & the handleNotification function (which should call the completetransaction api & by-pass this
      // entirely). The only thing the IPN class should really do is extract data from the request, validate it
      // & call completetransaction or call fail? (which may not exist yet).
      $paymentProcessorTypeID = CRM_Core_DAO::getFieldValue('CRM_Financial_DAO_PaymentProcessorType',
        'AuthNet', 'id', 'name'
      );
      $paymentProcessorID = (int) civicrm_api3('PaymentProcessor', 'getvalue', array(
        'is_test' => 0,
        'options' => array('limit' => 1),
        'payment_processor_type_id' => $paymentProcessorTypeID,
         'return' => 'id',
      ));

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
    return TRUE;
  }

  /**
   * @param array $input
   * @param array $ids
   * @param array $objects
   * @param $first
   *
   * @return bool
   */
  public function recur(&$input, &$ids, &$objects, $first) {
    $this->_isRecurring = TRUE;
    $recur = &$objects['contributionRecur'];
    $paymentProcessorObject = $objects['contribution']->_relatedObjects['paymentProcessor']['object'];

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
      $contribution->financial_type_id = $objects['contributionType']->id;
      $contribution->contribution_page_id = $ids['contributionPage'];
      $contribution->contribution_recur_id = $ids['contributionRecur'];
      $contribution->receive_date = $now;
      $contribution->currency = $objects['contribution']->currency;
      $contribution->payment_instrument_id = $objects['contribution']->payment_instrument_id;
      $contribution->amount_level = $objects['contribution']->amount_level;
      $contribution->address_id = $objects['contribution']->address_id;
      $contribution->campaign_id = $objects['contribution']->campaign_id;

      $objects['contribution'] = &$contribution;
    }
    $objects['contribution']->invoice_id = md5(uniqid(rand(), TRUE));
    $objects['contribution']->total_amount = $input['amount'];
    $objects['contribution']->trxn_id = $input['trxn_id'];

    $this->checkMD5($paymentProcessorObject, $input);

    if ($input['response_code'] == 1) {
      // Approved
      if ($first) {
        $recur->start_date = $now;
        $recur->trxn_id = $recur->processor_id;
        $this->_isFirstOrLastRecurringPayment = CRM_Core_Payment::RECURRING_PAYMENT_START;
      }
      $statusName = 'In Progress';
      if (($recur->installments > 0) &&
        ($input['subscription_paynum'] >= $recur->installments)
      ) {
        // this is the last payment
        $statusName = 'Completed';
        $recur->end_date = $now;
        $this->_isFirstOrLastRecurringPayment = CRM_Core_Payment::RECURRING_PAYMENT_END;
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

      $message = ts("Subscription payment failed - %1", array(1 => htmlspecialchars($input['response_reason_text'])));
      CRM_Core_Error::debug_log_message($message);

      // the recurring contribution has declined a payment or has failed
      // so we just fix the recurring contribution and not change any of
      // the existing contributions
      // CRM-9036
      return TRUE;
    }

    // check if contribution is already completed, if so we ignore this ipn
    if ($objects['contribution']->contribution_status_id == 1) {
      $transaction->commit();
      CRM_Core_Error::debug_log_message("Returning since contribution has already been handled.");
      echo "Success: Contribution has already been handled<p>";
      return TRUE;
    }

    $this->completeTransaction($input, $ids, $objects, $transaction, $recur);
  }

  /**
   * @param $input
   * @param $ids
   *
   * @return bool
   */
  public function getInput(&$input, &$ids) {
    $input['amount'] = $this->retrieve('x_amount', 'String');
    $input['subscription_id'] = $this->retrieve('x_subscription_id', 'Integer');
    $input['response_code'] = $this->retrieve('x_response_code', 'Integer');
    $input['MD5_Hash'] = $this->retrieve('x_MD5_Hash', 'String', FALSE, '');
    $input['response_reason_code'] = $this->retrieve('x_response_reason_code', 'String', FALSE);
    $input['response_reason_text'] = $this->retrieve('x_response_reason_text', 'String', FALSE);
    $input['subscription_paynum'] = $this->retrieve('x_subscription_paynum', 'Integer', FALSE, 0);
    $input['trxn_id'] = $this->retrieve('x_trans_id', 'String', FALSE);

    if ($input['trxn_id']) {
      $input['is_test'] = 0;
    }
    // Only assume trxn_id 'should' have been returned for success.
    // Per CRM-17611 it would also not be passed back for a decline.
    elseif ($input['response_code'] == 1) {
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
      $input[$civiName] = $this->retrieve($resName, 'String', FALSE);
    }
  }

  /**
   * @param $ids
   * @param $input
   */
  public function getIDs(&$ids, &$input) {
    $ids['contact'] = $this->retrieve('x_cust_id', 'Integer', FALSE, 0);
    $ids['contribution'] = $this->retrieve('x_invoice_num', 'Integer');

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
    if ($ids['contact'] != $contRecur->contact_id) {
      $message = ts("Recurring contribution appears to have been re-assigned from id %1 to %2, continuing with %2.", array(1 => $ids['contact'], 2 => $contRecur->contact_id));
      CRM_Core_Error::debug_log_message($message);
      $ids['contact'] = $contRecur->contact_id;
    }
    if (!$ids['contributionRecur']) {
      $message = ts("Could not find contributionRecur id");
      $log = new CRM_Utils_SystemLogger();
      $log->error('payment_notification', array('message' => $message, 'ids' => $ids, 'input' => $input));
      throw new CRM_Core_Exception($message);
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

  /**
   * @param string $name
   *   Parameter name.
   * @param string $type
   *   Parameter type.
   * @param bool $abort
   *   Abort if not present.
   * @param null $default
   *   Default value.
   *
   * @throws CRM_Core_Exception
   * @return mixed
   */
  public function retrieve($name, $type, $abort = TRUE, $default = NULL) {
    $value = CRM_Utils_Type::validate(
      empty($this->_inputParameters[$name]) ? $default : $this->_inputParameters[$name],
      $type,
      FALSE
    );
    if ($abort && $value === NULL) {
      throw new CRM_Core_Exception("Could not find an entry for $name");
    }
    return $value;
  }

  /**
   * Check and validate gateway MD5 response if present.
   *
   * @param CRM_Core_Payment_AuthorizeNet $paymentObject
   * @param array $input
   *
   * @throws CRM_Core_Exception
   */
  public function checkMD5($paymentObject, $input) {
    if (empty($input['trxn_id'])) {
      // For decline we have nothing to check against.
      return;
    }
    if (!$paymentObject->checkMD5($input['MD5_Hash'], $input['trxn_id'], $input['amount'], TRUE)) {
      $message = "Failure: Security verification failed";
      $log = new CRM_Utils_SystemLogger();
      $log->error('payment_notification', array('message' => $message, 'input' => $input));
      throw new CRM_Core_Exception($message);
    }
  }

  public function pogstone_log_details() {
    // Pogstone added:

    // Store the posted values in an associative array
    $fields = array();

    $raw_msg = '';

    foreach ($_REQUEST as $name => $value) {
      // Create our associative array
      $fields[$name] = $value;
      $tmp = "Name: ".$name."  ---  Value: ".$value." ----------";

      $raw_msg = $raw_msg.$tmp;
    }

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
         '$x_response_code', '$x_response_reason_code', %2, '$x_avs_code', '$x_auth_code', '$x_trans_id', '$x_method',
          '$x_card_type',
          '$x_account_number', %3, %4, %5, %6, %7,
          '".$x_state."', '".$x_zip."', '".$x_country."', '".$x_phone."', '".$x_fax."',
           '".$x_email."', '".$x_invoice_num."', %1, '".$x_type."', '".$x_cust_id."', '".$x_ship_to_first_name."', '".$x_ship_to_last_name."', '".$x_ship_to_company."',
           '".$x_ship_to_address."', '".$x_ship_to_city."', '".$x_ship_to_state."', '".$x_ship_to_zip."', '$x_ship_to_country', '$x_amount' , '$x_tax', '".$x_duty."', 
           '".$x_freight."',
           '".$x_tax_exempt."', '".$x_po_num."', '".$x_MD5_Hash."', '".$x_cvv2_resp_code."', '$x_cavv_response', '$x_test_request', '".$x_subscription_id."', 
           '".$x_subscription_paynum."', %99);" ;

    $dao = CRM_Core_DAO::executeQuery($sql, array(
      1 => array($x_description, 'String'),
      2 => array($x_response_reason_text, 'String'),
      3 => array($x_first_name, 'String'),
      4 => array($x_last_name, 'String'),
      5 => array($x_company, 'String'),
      6 => array($x_address, 'String'),
      7 => array($x_city, 'String'),
      99 => array($raw_msg, 'String'),
    ));
  }
}
