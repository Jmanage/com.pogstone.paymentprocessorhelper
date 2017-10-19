<?php
/*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.7                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2017                                |
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
 * @copyright CiviCRM LLC (c) 2004-2017
 */
class CRM_Core_Payment_PayPalProIPN extends CRM_Core_Payment_BaseIPN {

  static $_paymentProcessor = NULL;

  /**
   * Input parameters from payment processor. Store these so that
   * the code does not need to keep retrieving from the http request
   * @var array
   */
  protected $_inputParameters = array();

  /**
   * Store for the variables from the invoice string.
   * @var array
   */
  protected $_invoiceData = array();

  /**
   * Is this a payment express transaction.
   */
  protected $_isPaymentExpress = FALSE;

  /**
   * Are we dealing with an event an 'anything else' (contribute)
   * @var string component
   */
  protected $_component = 'contribute';

  /**
   * Constructor function.
   *
   * @param array $inputData
   *   Contents of HTTP REQUEST.
   *
   * @throws CRM_Core_Exception
   */
  public function __construct($inputData) {
    $this->setInputParameters($inputData);
    $this->setInvoiceData();
    parent::__construct();
  }

  /**
   * get the values from the rp_invoice_id string.
   *
   * @param string $name
   *   E.g. i, values are stored in the string with letter codes.
   * @param bool $abort
   *   Throw exception if not found
   *
   * @throws CRM_Core_Exception
   * @return mixed
   */
  public function getValue($name, $abort = TRUE) {
    if ($abort && empty($this->_invoiceData[$name])) {
      throw new CRM_Core_Exception("Failure: Missing Parameter $name");
    }
    else {
      return CRM_Utils_Array::value($name, $this->_invoiceData);
    }
  }

  /**
   * Set $this->_invoiceData from the input array
   */
  public function setInvoiceData() {
    if (empty($this->_inputParameters['rp_invoice_id'])) {
      $this->_isPaymentExpress = TRUE;
      return;
    }
    $rpInvoiceArray = explode('&', $this->_inputParameters['rp_invoice_id']);
    // for clarify let's also store without the single letter unreadable
    //@todo after more refactoring we might ditch storing the one letter stuff
    $mapping = array(
      'i' => 'invoice_id',
      'm' => 'component',
      'c' => 'contact_id',
      'b' => 'contribution_id',
      'r' => 'contribution_recur_id',
      'p' => 'participant_id',
      'e' => 'event_id',
    );
    foreach ($rpInvoiceArray as $rpInvoiceValue) {
      $rpValueArray = explode('=', $rpInvoiceValue);
      $this->_invoiceData[$rpValueArray[0]] = $rpValueArray[1];
      $this->_inputParameters[$mapping[$rpValueArray[0]]] = $rpValueArray[1];
      // p has been overloaded & could mean contribution page or participant id. Clearly we need an
      // alphabet with more letters.
      // the mode will always be resolved before the mystery p is reached
      if ($rpValueArray[1] == 'contribute') {
        $mapping['p'] = 'contribution_page_id';
      }
    }
    if (empty($this->_inputParameters['component'])) {
      $this->_isPaymentExpress = TRUE;
    }
  }

  /**
   * @param string $name
   *   Of variable to return.
   * @param string $type
   *   Data type.
   *   - String
   *   - Integer
   * @param string $location
   *   Deprecated.
   * @param bool $abort
   *   Abort if empty.
   *
   * @throws CRM_Core_Exception
   * @return mixed
   */
  public function retrieve($name, $type, $location = 'POST', $abort = TRUE) {
    $value = CRM_Utils_Type::validate(
      CRM_Utils_Array::value($name, $this->_inputParameters),
      $type,
      FALSE
    );
    if ($abort && $value === NULL) {
      throw new CRM_Core_Exception("Could not find an entry for $name in $location");
    }
    return $value;
  }

  /**
   * Process recurring contributions.
   * @param array $input
   * @param array $ids
   * @param array $objects
   * @param bool $first
   * @return bool
   */
  public function recur(&$input, &$ids, &$objects, $first) {
    if (!isset($input['txnType'])) {
      CRM_Core_Error::debug_log_message("Could not find txn_type in input request");
      echo "Failure: Invalid parameters<p>";
      return FALSE;
    }

    $recur = &$objects['contributionRecur'];

    // make sure the invoice ids match
    // make sure the invoice is valid and matches what we have in
    // the contribution record
    if ($recur->invoice_id != $input['invoice']) {
      CRM_Core_Error::debug_log_message("Invoice values dont match between database and IPN request recur is " . $recur->invoice_id . " input is " . $input['invoice']);
      echo "Failure: Invoice values dont match between database and IPN request recur is " . $recur->invoice_id . " input is " . $input['invoice'];
      return FALSE;
    }

    $now = date('YmdHis');

    // fix dates that already exist
    $dates = array('create', 'start', 'end', 'cancel', 'modified');
    foreach ($dates as $date) {
      $name = "{$date}_date";
      if ($recur->$name) {
        $recur->$name = CRM_Utils_Date::isoToMysql($recur->$name);
      }
    }

    $sendNotification = FALSE;
    $subscriptionPaymentStatus = NULL;
    //List of Transaction Type
    /*
    recurring_payment_profile_created          RP Profile Created
    recurring_payment           RP Successful Payment
    recurring_payment_failed                               RP Failed Payment
    recurring_payment_profile_cancel           RP Profile Cancelled
    recurring_payment_expired         RP Profile Expired
    recurring_payment_skipped        RP Profile Skipped
    recurring_payment_outstanding_payment      RP Successful Outstanding Payment
    recurring_payment_outstanding_payment_failed          RP Failed Outstanding Payment
    recurring_payment_suspended        RP Profile Suspended
    recurring_payment_suspended_due_to_max_failed_payment  RP Profile Suspended due to Max Failed Payment
     */

    //set transaction type
    $txnType = $this->retrieve('txn_type', 'String');
    //Changes for paypal pro recurring payment
    $contributionStatuses = civicrm_api3('contribution', 'getoptions', array('field' => 'contribution_status_id'));
    $contributionStatuses = $contributionStatuses['values'];
    switch ($txnType) {
      case 'recurring_payment_profile_created':
        if (in_array($recur->contribution_status_id, array(
              array_search('Pending', $contributionStatuses),
              array_search('In Progress', $contributionStatuses),
            ))
          && !empty($recur->processor_id)
        ) {
          echo "already handled";
          return FALSE;
        }
        $recur->create_date = $now;
        $recur->contribution_status_id = 2;
        $recur->processor_id = $this->retrieve('recurring_payment_id', 'String');
        $recur->trxn_id = $recur->processor_id;
        $subscriptionPaymentStatus = CRM_Core_Payment::RECURRING_PAYMENT_START;
        $sendNotification = TRUE;
        break;

      case 'recurring_payment':
        if ($first) {
          $recur->start_date = $now;
        }
        else {
          $input['invoice_id'] = md5(uniqid(rand(), TRUE));
          $input['original_contribution_id'] = $ids['contribution'];
          $input['contribution_recur_id'] = $ids['contributionRecur'];

          if ($input['paymentStatus'] != 'Completed') {
            throw new CRM_Core_Exception("Ignore all IPN payments that are not completed");
          }
          // In future moving to create pending & then complete, but this OK for now.
          // Also consider accepting 'Failed' like other processors.
          $input['contribution_status_id'] = 1;

          civicrm_api3('Contribution', 'repeattransaction', $input);
          return;
        }

        //contribution installment is completed
        if ($this->retrieve('profile_status', 'String') == 'Expired') {
          if (!empty($recur->end_date)) {
            echo "already handled";
            return FALSE;
          }
          $recur->contribution_status_id = 1;
          $recur->end_date = $now;
          $sendNotification = TRUE;
          $subscriptionPaymentStatus = CRM_Core_Payment::RECURRING_PAYMENT_END;
        }

        // make sure the contribution status is not done
        // since order of ipn's is unknown
        if ($recur->contribution_status_id != 1) {
          $recur->contribution_status_id = 5;
        }
        break;
    }

    $recur->save();

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

    if ($txnType != 'recurring_payment') {
      return TRUE;
    }

    if (!$first) {
      //check if this contribution transaction is already processed
      //if not create a contribution and then get it processed
      $contribution = new CRM_Contribute_BAO_Contribution();
      $contribution->trxn_id = $input['trxn_id'];
      if ($contribution->trxn_id && $contribution->find()) {
        CRM_Core_Error::debug_log_message("returning since contribution has already been handled");
        echo "Success: Contribution has already been handled<p>";
        return TRUE;
      }

      $contribution->contact_id = $recur->contact_id;
      $contribution->financial_type_id = $objects['contributionType']->id;
      $contribution->contribution_page_id = $ids['contributionPage'];
      $contribution->contribution_recur_id = $ids['contributionRecur'];
      $contribution->currency = $objects['contribution']->currency;
      $contribution->payment_instrument_id = $objects['contribution']->payment_instrument_id;
      $contribution->amount_level = $objects['contribution']->amount_level;
      $contribution->campaign_id = $objects['contribution']->campaign_id;
      $objects['contribution'] = &$contribution;
      $contribution->invoice_id = md5(uniqid(rand(), TRUE));
    }
    // CRM-13737 - am not aware of any reason why payment_date would not be set - this if is a belt & braces
    $objects['contribution']->receive_date = !empty($input['payment_date']) ? date('YmdHis', strtotime($input['payment_date'])) : $now;

    $this->single($input, $ids, $objects,
      TRUE, $first
    );
  }

  /**
   * @param $input
   * @param $ids
   * @param $objects
   * @param bool $recur
   * @param bool $first
   *
   * @return bool
   */
  public function single(&$input, &$ids, &$objects, $recur = FALSE, $first = FALSE) {
    $contribution = &$objects['contribution'];

    // make sure the invoice is valid and matches what we have in the contribution record
    if ((!$recur) || ($recur && $first)) {
      if ($contribution->invoice_id != $input['invoice']) {
        CRM_Core_Error::debug_log_message("Invoice values dont match between database and IPN request");
        echo "Failure: Invoice values dont match between database and IPN request<p>contribution is" . $contribution->invoice_id . " and input is " . $input['invoice'];
        return FALSE;
      }
    }
    else {
      $contribution->invoice_id = md5(uniqid(rand(), TRUE));
    }

    if (!$recur) {
      if ($contribution->total_amount != $input['amount']) {
        CRM_Core_Error::debug_log_message("Amount values dont match between database and IPN request");
        echo "Failure: Amount values dont match between database and IPN request<p>";
        return FALSE;
      }
    }
    else {
      $contribution->total_amount = $input['amount'];
    }

    $transaction = new CRM_Core_Transaction();

    $status = $input['paymentStatus'];
    if ($status == 'Denied' || $status == 'Failed' || $status == 'Voided') {
      return $this->failed($objects, $transaction);
    }
    elseif ($status == 'Pending') {
      return $this->pending($objects, $transaction);
    }
    elseif ($status == 'Refunded' || $status == 'Reversed') {
      return $this->cancelled($objects, $transaction);
    }
    elseif ($status != 'Completed') {
      return $this->unhandled($objects, $transaction);
    }

    // check if contribution is already completed, if so we ignore this ipn
    if ($contribution->contribution_status_id == 1) {
      $transaction->commit();
      CRM_Core_Error::debug_log_message("returning since contribution has already been handled");
      echo "Success: Contribution has already been handled<p>";
      return TRUE;
    }

    $this->completeTransaction($input, $ids, $objects, $transaction, $recur);
  }

  /**
   * Gets PaymentProcessorID for PayPal
   *
   * @return int
   */
  public function getPayPalPaymentProcessorID() {
    // This is an unreliable method as there could be more than one instance.
    // Recommended approach is to use the civicrm/payment/ipn/xx url where xx is the payment
    // processor id & the handleNotification function (which should call the completetransaction api & by-pass this
    // entirely). The only thing the IPN class should really do is extract data from the request, validate it
    // & call completetransaction or call fail? (which may not exist yet).
    $paymentProcessorTypeID = CRM_Core_DAO::getFieldValue('CRM_Financial_DAO_PaymentProcessorType',
      'PayPal', 'id', 'name'
    );
    return (int) civicrm_api3('PaymentProcessor', 'getvalue', array(
      'is_test' => 0,
      'options' => array('limit' => 1),
      'payment_processor_type_id' => $paymentProcessorTypeID,
      'return' => 'id',
    ));

  }

  /**
   * This is the main function to call. It should be sufficient to instantiate the class
   * (with the input parameters) & call this & all will be done
   *
   * @todo the references to POST throughout this class need to be removed
   * @return bool
   */
  public function main() {
    CRM_Core_Error::debug_log_message('paymentprocessorhelper: PayPalProIPN: Start '. __FUNCTION__);
    CRM_Core_Error::debug_var('paymentprocessorhelper: PayPalProIPN: GET', $_GET, TRUE, TRUE);
    CRM_Core_Error::debug_var('paymentprocessorhelper: PayPalProIPN: POST', $_POST, TRUE, TRUE);
    if ($this->_isPaymentExpress) {
      $this->handlePaymentExpress();
      CRM_Core_Error::debug_log_message('paymentprocessorhelper: PayPalProIPN: Return in line '. __LINE__ .': '. __FUNCTION__);
      return FALSE;
    }

    // Pogstone added:
    self::pogstone_log_details();
    // end of Pogstone section.

    $objects = $ids = $input = array();
    $this->_component = $input['component'] = self::getValue('m');
    $input['invoice'] = self::getValue('i', TRUE);
    // get the contribution and contact ids from the GET params
    $ids['contact'] = self::getValue('c', TRUE);
    $ids['contribution'] = self::getValue('b', TRUE);

    $this->getInput($input, $ids);

    if ($this->_component == 'event') {
      $ids['event'] = self::getValue('e', TRUE);
      $ids['participant'] = self::getValue('p', TRUE);
      $ids['contributionRecur'] = self::getValue('r', FALSE);
    }
    else {
      // get the optional ids
      //@ how can this not be broken retrieving from GET as we are dealing with a POST request?
      // copy & paste? Note the retrieve function now uses data from _REQUEST so this will be included
      $ids['membership'] = self::retrieve('membershipID', 'Integer', 'GET', FALSE);
      $ids['contributionRecur'] = self::getValue('r', FALSE);
      $ids['contributionPage'] = self::getValue('p', FALSE);
      $ids['related_contact'] = self::retrieve('relatedContactID', 'Integer', 'GET', FALSE);
      $ids['onbehalf_dupe_alert'] = self::retrieve('onBehalfDupeAlert', 'Integer', 'GET', FALSE);
    }

    if (!$ids['membership'] && $ids['contributionRecur']) {
      $sql = "
    SELECT m.id
      FROM civicrm_membership m
INNER JOIN civicrm_membership_payment mp ON m.id = mp.membership_id AND mp.contribution_id = %1
     WHERE m.contribution_recur_id = %2
     LIMIT 1";
      $sqlParams = array(
        1 => array($ids['contribution'], 'Integer'),
        2 => array($ids['contributionRecur'], 'Integer'),
      );
      if ($membershipId = CRM_Core_DAO::singleValueQuery($sql, $sqlParams)) {
        $ids['membership'] = $membershipId;
      }
    }

    // This is an unreliable method as there could be more than one instance.
    // Recommended approach is to use the civicrm/payment/ipn/xx url where xx is the payment
    // processor id & the handleNotification function (which should call the completetransaction api & by-pass this
    // entirely). The only thing the IPN class should really do is extract data from the request, validate it
    // & call completetransaction or call fail? (which may not exist yet).

    $paymentProcessorID = self::getPayPalPaymentProcessorID();

    if (!$this->validateData($input, $ids, $objects, TRUE, $paymentProcessorID)) {
      CRM_Core_Error::debug_log_message('paymentprocessorhelper: PayPalProIPN: Return in line '. __LINE__ .': '. __FUNCTION__);
      return FALSE;
    }

    self::$_paymentProcessor = &$objects['paymentProcessor'];
    //?? how on earth would we not have component be one of these?
    // they are the only valid settings & this IPN file can't even be called without one of them
    // grepping for this class doesn't find other paths to call this class
    if ($this->_component == 'contribute' || $this->_component == 'event') {
      if ($ids['contributionRecur']) {
        // check if first contribution is completed, else complete first contribution
        $first = TRUE;
        if ($objects['contribution']->contribution_status_id == 1) {
          $first = FALSE;
        }
        CRM_Core_Error::debug_log_message('paymentprocessorhelper: PayPalProIPN: Return in line '. __LINE__ .': '. __FUNCTION__);
        return $this->recur($input, $ids, $objects, $first);
      }
      else {
        CRM_Core_Error::debug_log_message('paymentprocessorhelper: PayPalProIPN: Return in line '. __LINE__ .': '. __FUNCTION__);
        return $this->single($input, $ids, $objects, FALSE, FALSE);
      }
    }
    else {
      CRM_Core_Error::debug_log_message('paymentprocessorhelper: PayPalProIPN: Return in line '. __LINE__ .': '. __FUNCTION__);
      return $this->single($input, $ids, $objects, FALSE, FALSE);
    }
    CRM_Core_Error::debug_log_message('paymentprocessorhelper: PayPalProIPN: End: '. __FUNCTION__);
  }

  /**
   * @param $input
   * @param $ids
   *
   * @return bool
   * @throws CRM_Core_Exception
   */
  public function getInput(&$input, &$ids) {

    if (!$this->getBillingID($ids)) {
      return FALSE;
    }

    $input['txnType'] = self::retrieve('txn_type', 'String', 'POST', FALSE);
    $input['paymentStatus'] = self::retrieve('payment_status', 'String', 'POST', FALSE);

    $input['amount'] = self::retrieve('mc_gross', 'Money', 'POST', FALSE);
    $input['reasonCode'] = self::retrieve('ReasonCode', 'String', 'POST', FALSE);

    $billingID = $ids['billing'];
    $lookup = array(
      "first_name" => 'first_name',
      "last_name" => 'last_name',
      "street_address-{$billingID}" => 'address_street',
      "city-{$billingID}" => 'address_city',
      "state-{$billingID}" => 'address_state',
      "postal_code-{$billingID}" => 'address_zip',
      "country-{$billingID}" => 'address_country_code',
    );
    foreach ($lookup as $name => $paypalName) {
      $value = self::retrieve($paypalName, 'String', 'POST', FALSE);
      $input[$name] = $value ? $value : NULL;
    }

    $input['is_test'] = self::retrieve('test_ipn', 'Integer', 'POST', FALSE);
    $input['fee_amount'] = self::retrieve('mc_fee', 'Money', 'POST', FALSE);
    $input['net_amount'] = self::retrieve('settle_amount', 'Money', 'POST', FALSE);
    $input['trxn_id'] = self::retrieve('txn_id', 'String', 'POST', FALSE);
    $input['payment_date'] = $input['receive_date'] = self::retrieve('payment_date', 'String', 'POST', FALSE);
  }

  /**
   * Handle payment express IPNs.
   * For one off IPNS no actual response is required
   * Recurring is more difficult as we have limited confirmation material
   * lets look up invoice id in recur_contribution & rely on the unique transaction id to ensure no
   * duplicated
   * this may not be acceptable to all sites - e.g. if they are shipping or delivering something in return
   * then the quasi security of the ids array might be required - although better to
   * http://stackoverflow.com/questions/4848227/validate-that-ipn-call-is-from-paypal
   * but let's assume knowledge on invoice id & schedule is enough for now esp for donations
   * only contribute is handled
   */
  public function handlePaymentExpress() {
    //@todo - loads of copy & paste / code duplication but as this not going into core need to try to
    // keep discreet
    // also note that a lot of the complexity above could be removed if we used
    // http://stackoverflow.com/questions/4848227/validate-that-ipn-call-is-from-paypal
    // as membership id etc can be derived by the load objects fn
    $objects = $ids = $input = array();
    $isFirst = FALSE;
    $input['txnType'] = $this->retrieve('txn_type', 'String');
    if ($input['txnType'] != 'recurring_payment') {
      throw new CRM_Core_Exception('Paypal IPNS not handled other than recurring_payments');
    }
    $input['invoice'] = self::getValue('i', FALSE);
    $this->getInput($input, $ids);
    if ($this->transactionExists($input['trxn_id'])) {
      throw new CRM_Core_Exception('This transaction has already been processed');
    }

    $contributionRecur = civicrm_api3('contribution_recur', 'getsingle', array(
        'return' => 'contact_id, id',
        'invoice_id' => $input['invoice'],
      ));
    $ids['contact'] = $contributionRecur['contact_id'];
    $ids['contributionRecur'] = $contributionRecur['id'];
    $result = civicrm_api3('contribution', 'getsingle', array('invoice_id' => $input['invoice']));

    $ids['contribution'] = $result['id'];
    //@todo hard - coding 'pending' for now
    if ($result['contribution_status_id'] == 2) {
      $isFirst = TRUE;
    }
    // arg api won't get this - fix it
    $ids['contributionPage'] = CRM_Core_DAO::singleValueQuery("SELECT contribution_page_id FROM civicrm_contribution WHERE invoice_id = %1", array(
        1 => array(
          $ids['contribution'],
          'Integer',
        ),
      ));
    // only handle component at this stage - not terribly sure how a recurring event payment would arise
    // & suspec main function may be a victom of copy & paste
    // membership would be an easy add - but not relevant to my customer...
    $this->_component = $input['component'] = 'contribute';
    $input['trxn_date'] = date('Y-m-d-H-i-s', strtotime(self::retrieve('time_created', 'String')));
    $paymentProcessorID = self::getPayPalPaymentProcessorID();

    if (!$this->validateData($input, $ids, $objects, TRUE, $paymentProcessorID)) {
      throw new CRM_Core_Exception('Data did not validate');
    }
    return $this->recur($input, $ids, $objects, $isFirst);
  }

  /**
   * Function check if transaction already exists.
   * @param string $trxn_id
   * @return bool|void
   */
  public function transactionExists($trxn_id) {
    if (CRM_Core_DAO::singleValueQuery("SELECT count(*) FROM civicrm_contribution WHERE trxn_id = %1",
      array(
        1 => array($trxn_id, 'String'),
      ))
    ) {
      return TRUE;
    }
  }

  public function pogstone_log_details() {
    CRM_Core_Error::debug_log_message('paymentprocessorhelper: PayPalProIPN: Begin '. __FUNCTION__);
    // Pogstone added:   

    // Flag if this is an ARB transaction. Set to false by default.
    $arb = FALSE;

    // Store the posted values in an associative array
    $fields = array();

    foreach ($_REQUEST as $name => $value) {
      // Create our associative array
      $fields[$name] = $value;
    }

    // Get all the URL parameters/fields into variables.

    /*  Sample message:
      Name: amount  ---  Value: 101.00 ----------
      Name: initial_payment_amount  ---  Value: 0.00 ----------
      Name: profile_status  ---  Value: Active ----------
      Name: payer_id  ---  Value: GZTBAAAA22XJA ----------
      Name: product_type  ---  Value: 1 ----------
      Name: ipn_track_id  ---  Value: cd1b4e8d492c3 ----------
      Name: outstanding_balance  ---  Value: 0.00 ----------
      Name: shipping  ---  Value: 0.00 ----------
      Name: charset  ---  Value: windows-1252 ----------
      Name: period_type  ---  Value:  Regular ----------
      Name: currency_code  ---  Value: USD ----------
      Name: verify_sign  ---  Value: An5ns1Kso7MWUdW4ErQKJJJ4qi4-ARvSxrjLPbNkKDXdDhVUrIPcQaci ----------
      Name: payment_cycle  ---  Value: Monthly ----------
      Name: txn_type  ---  Value: recurring_payment_skipped ----------
      Name: payer_status  ---  Value: unverified ----------
      Name: first_name  ---  Value: Joe ----------
      Name: product_name  ---  Value: : 101 Per 1 month ----------
      Name: amount_per_cycle  ---  Value: 101.00 ----------
      Name: rp_invoice_id  ---  Value: i=7421f5f4bf2ee2e64b268e41afa8815f&m=contribute&c=16204&r=30&b=2655&p= ----------
      Name: last_name  ---  Value: Kaplan ----------
      Name: time_created  ---  Value: 07:50:16 Sep 27, 2012 PDT ----------
      Name: resend  ---  Value: true ----------
      Name: notify_version  ---  Value: 3.7 ----------
      Name: recurring_payment_id  ---  Value: I-3asdaksdhGN4D5K ----------
      Name: receiver_email  ---  Value: director@mygroup.org ----------
      Name: next_payment_date  ---  Value: 02:00:00 Jan 06, 2013 PST ----------
      Name: tax  ---  Value: 0.00 ----------
      Name: residence_country  ---  Value: US ----------
      Name: payer_email   --- Value: kaplan@aol.com --------------

      -------------------------------------------------------------------------------------------
      --------------------------------------------------------------------------------------------
      Another sample message:

      Name: amount  ---  Value: 500.00 ----------
      Name: txn_id  ---  Value: 3BR37624H48498902 ----------
      Name: recurring_payment_id  ---  Value: I-TVN3U312ANA6 ----------
      Name: payment_date  ---  Value: 06:12:14 Jul 01, 2013 PDT ----------
      Name: payment_status  ---  Value: Completed ----------
      Name: mc_gross  ---  Value: 500.00 ----------
      Name: mc_fee  ---  Value: 11.30 ----------
      Name: first_name  ---  Value: Eyal ----------
      Name: last_name  ---  Value: Yechezkell ----------
      Name: payer_email  ---  Value: eyal@snackablemedia.com ----------
      Name: txn_type  ---  Value: recurring_payment ----------
      Name: period_type  ---  Value:  Regular ----------
      Name: payment_fee  ---  Value: 11.30 ----------
      Name: payment_gross  ---  Value: 500.00 ----------
      Name: currency_code  ---  Value: USD ----------
      Name: mc_currency  ---  Value: USD ----------
      Name: outstanding_balance  ---  Value: 0.00 ----------
      Name: next_payment_date  ---  Value: 03:00:00 Aug 01, 2013 PDT ----------
      Name: protection_eligibility  ---  Value: Ineligible ----------
      Name: payment_cycle  ---  Value: Monthly ----------
      Name: tax  ---  Value: 0.00 ----------
      Name: payer_id  ---  Value: VKZF3PBWH9S7N ----------
      Name: product_name  ---  Value: : 500 Per 1 month ----------
      Name: charset  ---  Value: windows-1252 ----------
      Name: rp_invoice_id  ---  Value: i=061d7c81ba600d0e22c78972d7d085ea&m=contribute&c=30965&r=44&b=3217&p= ----------
      Name: notify_version  ---  Value: 3.7 ----------
      Name: amount_per_cycle  ---  Value: 500.00 ----------
      Name: payer_status  ---  Value: unverified ----------
      Name: business  ---  Value: director@gjarn.org ----------
      Name: verify_sign  ---  Value: AF667Peh-E4CB3mj8gEMghDit1bpA0kvK7yBIFvIJYhZBeNyWJbcWxfn ----------
      Name: initial_payment_amount  ---  Value: 0.00 ----------
      Name: profile_status  ---  Value: Active ----------
      Name: payment_type  ---  Value: instant ----------
      Name: receiver_email  ---  Value: director@gjarn.org ----------
      Name: receiver_id  ---  Value: GZHV3YNKLZJEQ ----------
      Name: residence_country  ---  Value: US ----------
      Name: receipt_id  ---  Value: 1327-5802-0001-2198 ----------
      Name: transaction_subject  ---  Value:  ----------
      Name: shipping  ---  Value: 0.00 ----------
      Name: product_type  ---  Value: 1 ----------
      Name: time_created  ---  Value: 06:16:35 May 01, 2013 PDT ----------
      Name: ipn_track_id  ---  Value: 97970d3f5c07a ----------
      -----------------------------------------------------------

     */

    /*
      // Check for refunds. For refunds, record the amount as a negative number.
      if($x_type == 'credit'){
      $amount_as_num = (float) $x_amount;
      $x_amount = 0 - $amount_as_num;
      }

     */
    CRM_Core_Error::debug_var('paymentprocessorhelper: PayPalProIPN: ' . __FUNCTION__ . ': Fields', $fields);

    $sql = "INSERT INTO pogstone_paypal_messages (`rec_type`, `message_date`, amount, txn_id, recurring_payment_id,
    		payment_date, payment_status, mc_gross, mc_fee, first_name, last_name, payer_email, txn_type, period_type, payment_fee, payment_gross, currency_code,
    		mc_currency, outstanding_balance, next_payment_date, protection_eligibility, payment_cycle, tax, payer_id, product_name, charset,
    		 rp_invoice_id, notify_version, amount_per_cycle, payer_status, business, verify_sign, initial_payment_amount, profile_status,
    		 payment_type, receiver_email, receiver_id, residence_country, receipt_id,
    		 transaction_subject,
    		  shipping, product_type, time_created, ipn_track_id)
    		VALUES ('paypal', CURRENT_TIMESTAMP, '" . $fields['amount'] . "', '" . $fields['txn_id'] . "', '" . $fields['recurring_payment_id'] . "',
    		'" . $fields['payment_date'] . "',
    		 '" . $fields['payment_status'] . "', '" . $fields['mc_gross'] . "', '" . $fields['mc_fee'] . "', '" . $fields['first_name'] . "', '" . $fields['last_name'] . "',
    		 '" . $fields['payer_email'] . "', '" . $fields['txn_type'] . "', '" . $fields['period_type'] . "', '" . $fields['payment_fee'] . "',
    		 '" . $fields['payment_gross'] . "',
    		 '" . $fields['currency_code'] . "', '" . $fields['mc_currency'] . "',
    		 '" . $fields['outstanding_balance'] . "', '" . $fields['next_payment_date'] . "', '" . $fields['protection_eligibility'] . "',
    		 '" . $fields['payment_cycle'] . "', '" . $fields['tax'] . "', '" . $fields['payer_id'] . "', '" . $fields['product_name'] . "', '" . $fields['charset'] . "',
    		 '" . $fields['rp_invoice_id'] . "', '" . $fields['notify_version'] . "', '" . $fields['amount_per_cycle'] . "', '" . $fields['payer_status'] . "',
    		 '" . $fields['business'] . "',
    		 '" . $fields['verify_sign'] . "', '" . $fields['initial_payment_amount'] . "', '" . $fields['profile_status'] . "',
    		  '" . $fields['payment_type'] . "', '" . $fields['receiver_email'] . "',
    		 '" . $fields['receiver_id'] . "', '" . $fields['residence_country'] . "', '" . $fields['receipt_id'] . "', '" . $fields['transaction_subject'] . "',
    		 '" . $fields['shipping'] . "',
    		 '" . $fields['product_type'] . "', '" . $fields['time_created'] . "', '" . $fields['ipn_track_id'] . "');";

    CRM_Core_Error::debug_log_message('paymentprocessorhelper: PayPalProIPN: '. __FUNCTION__ . ': Log using SQL: '. $sql);
    $dao = CRM_Core_DAO::executeQuery($sql);
    CRM_Core_Error::debug_log_message('paymentprocessorhelper: PayPalProIPN: '. __FUNCTION__ . ': Log SQL result: '. (!is_a($dao, 'DB_Error')));
    CRM_Core_Error::debug_log_message('paymentprocessorhelper: PayPalProIPN: End '. __FUNCTION__);
  }

}
