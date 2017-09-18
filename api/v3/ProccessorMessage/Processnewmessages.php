<?php

define('PROCESSNEWMESSAGES_START_DATE', '2015-01-15');

/**
 * ProccessorMessage.Processnewmessages API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_proccessor_message_processnewmessages($params) {
  $rec_count = handle_the_messages();
  $returnValues = array("record count:" . $rec_count);

  return civicrm_api3_create_success($returnValues, $params, 'ProcessorMessage', 'processnewmessages');
}

function handle_the_messages() {
  CRM_Core_Error::debug_log_message('processnewmessages: Start ' . __FUNCTION__ . "().");

  $now = date('Y-m-d  H:i:s');
  $all_message_types_tocheck = array();

  $pay_pal_type = "PayPal";
  $params = array(
    'version' => 3,
    'sequential' => 1,
    'vendor_type' => $pay_pal_type,
  );

  $result = civicrm_api('PaymentProcessorTypeHelper', 'get', $params);
  $tmp = $result['values'][0];

  if ($tmp['id'] == $pay_pal_type) {
    $bool_str = $tmp['name'];
    $pay_pal_enabled = $bool_str === 'true' ? TRUE : FALSE;
  }

  if ($pay_pal_enabled) {
    $all_message_types_tocheck[] = "PayPal";
  }

  $authnet_type = "AuthNet";
  $params = array(
    'version' => 3,
    'sequential' => 1,
    'vendor_type' => $authnet_type,
  );

  $result = civicrm_api('PaymentProcessorTypeHelper', 'get', $params);

  $tmp = $result['values'][0];
  if ($tmp['id'] == $authnet_type) {
    $bool_str = $tmp['name'];
    $authnet_enabled = $bool_str === 'true' ? TRUE : FALSE;
  }

  if ($authnet_enabled) {
    $all_message_types_tocheck[] = "AuthNet";
  }

  // For each processor type in use, process related messages
  $rec_count = 0;
  foreach ($all_message_types_tocheck as $cur_type) {

    if ($cur_type == "PayPal") {
      $messages_table_name = 'pogstone_paypal_messages';
      $sql = "
        SELECT
          msgs.id,
          msgs.txn_id,
          date_format(msgs.message_date, '%Y%m%d'  ) as message_date,
          substr(msgs.payment_date, 10, 3) as payment_date_month,
          substr(msgs.payment_date, 14, 2) as payment_date_day,
          substr(msgs.payment_date, 18, 4) as payment_date_year,
          recur.id as crm_recur_id,
          recur.amount as crm_amount
        FROM
          $messages_table_name as msgs
          LEFT JOIN civicrm_contribution ctrb ON msgs.txn_id = ctrb.trxn_id
          LEFT JOIN civicrm_contribution_recur recur ON recur.id = (
            substr(
              rp_invoice_id,
              LOCATE(
                '&r=' , rp_invoice_id
              ) + 3,
              (
                LOCATE(
                  '&b=', rp_invoice_id
                ) - 3 -
                (
                  LOCATE(
                    '&r=' , rp_invoice_id
                  )
                )
              )
            )
          )
        WHERE
          msgs.payment_status = 'Completed'
          AND length(msgs.recurring_payment_id) > 0
          AND ctrb.id IS NULL
          AND msgs.message_date >= '2013-03-01'
          AND msgs.processed IS NULL
        GROUP by msgs.ipn_track_id
      ";
    }
    elseif ($cur_type == "AuthNet") {
      $messages_table_name = 'pogstone_authnet_messages';
      $sql = "
        SELECT
          msgs.id,
          msgs.civicrm_recur_id,
          msgs.x_trans_id,
          msgs.x_amount,
          date_format(message_date, '%Y%m%d'  ) as message_date ,
          recur.id as crm_recur_id,
          recur.amount  as crm_amount
        FROM $messages_table_name as msgs
          LEFT JOIN civicrm_contribution ctrb ON msgs.x_trans_id = ctrb.trxn_id
          LEFT JOIN civicrm_contribution_recur recur ON recur.processor_id = msgs.x_subscription_id
        WHERE
          msgs.x_response_code = '1'
          AND length(msgs.x_subscription_id) > 0
          AND ctrb.id IS NULL
          AND msgs.message_date >= '2013-03-01'
          AND msgs.processed IS NULL
      ";
    }

    _processnewmessages_messages_with_existing_contributions($messages_table_name, $now);

    // print "<h2>Section: Find new payment processor messages and attempt to create contribution records</h2>";
    if (strlen($sql) > 0) {
      $dao = CRM_Core_DAO::executeQuery($sql);

      // a.k.a. "FIRST PASS"
      CRM_Core_Error::debug_log_message("processnewmessages: Beginning FIRST PASS for $messages_table_name");
      while ($dao->fetch()) {
        CRM_Core_Error::debug_log_message("processnewmessages: In FIRST PASS for $messages_table_name: found message id {$dao->id}.");
        $message_valid_to_process = TRUE;

//        $cid = $dao->recur_contact_id;
        $recur_id = $dao->crm_recur_id;
        $crm_amount = $dao->crm_amount;

        if ($cur_type == "PayPal") {
          $date_raw_year = $dao->payment_date_year;
          $date_raw_month = $dao->payment_date_month;
          $date_raw_day = $dao->payment_date_day;

          $tmp_sql_date = $date_raw_year . "-" . $date_raw_month . "-" . $date_raw_day;

          $receive_date = $tmp_sql_date;
          $trxn_id = $dao->txn_id;
          $payment_instrument_id = "1";  // Assume Credit Card
          // print "<br>Inside paypal section: amt: ".$amount;
        }
        elseif ($cur_type == "AuthNet") {

          $receive_date = $dao->message_date;
          $amount = $dao->x_amount;
          $trxn_id = $dao->x_trans_id;
          $payment_instrument_id = "1";  // Assume Credit Card

          $tmp_trans_amount = number_format($amount, 2);
          $tmp_crm_amount = number_format($crm_amount, 2);
          if ($tmp_crm_amount <> $tmp_trans_amount) {
            $message_valid_to_process = FALSE;
          }
        }
        // this is the fancy new way introduced for version 4.3.x or better
        if (strlen($recur_id) > 0) {
          if (strlen($trxn_id) == 0) {
            // trxn_id is empty.
          }
          elseif ($message_valid_to_process <> TRUE) {
            // amounts do not match.
          }
          else {
            //  print "<h2>Process for contact id: ".$cid." -- Name on Card: ".$card_billingname." -- CRM Name: ".$crm_contact_name." crm_recur_id: ".$recur_id."</h2>";
            CRM_Core_Error::debug_log_message("processnewmessages: In FIRST PASS for $messages_table_name: calling UpdateRecurringContributionSubscription() for message id {$dao->id}, for recurring ID $recur_id.");
            $rtn_code = UpdateRecurringContributionSubscription($log_handle, $recur_id, $trxn_id, $receive_date, $payment_instrument_id);

            // TODO: Check rtn_code to see if there was an error.
            $rec_count++;
          }
        }
//        else {
        // print "<br>Error: Could not find crm_recur_id for x_subscription_id: ".$processor_subscription_id;
//        }
        // Mark message as processed. Reference: https://pogstone.zendesk.com/agent/tickets/11083
        $sql = "
          UPDATE $messages_table_name
          SET processed = %1
          WHERE id = %2
        ";
        $dao_params = array(
          1 => array($now, 'String'),
          2 => array($dao->id, 'Int'),
        );
        CRM_Core_DAO::executeQuery($sql, $dao_params);
        CRM_Core_Error::debug_log_message("processnewmessages: In FIRST PASS for $messages_table_name: marked message id {$dao->id} as processed at $now.");
      }

      $dao->free();

      CRM_Core_Error::debug_log_message("processnewmessages: End FIRST PASS for $messages_table_name.");

      handle_messges_with_no_contrib($cur_type, $now);
    }
  }

  handleCancelledSubscriptions();
  CRM_Core_Error::debug_log_message('processnewmessages: End ' . __FUNCTION__ . "().");

  return $rec_count;
}

/**
 * For messages not yet associated with a contribution, associate them if possible.
 *
 *
 * @param string $cur_type e.g., 'AuthNet'
 * @param string $timestamp A mysql datetime string. Messages may have already
 *    been processed at $timestamp by handle_the_messages(), but this function
 *    will handle them once more for its own purposes; however it will not
 *    handle any messages already processed at a time other than $timestamp.
 */
function handle_messges_with_no_contrib($cur_type, $timestamp) {

  if ($cur_type == "AuthNet") {
    $messages_table_name = 'pogstone_authnet_messages';
    $message_ids = _processnewmessages_handle_authnet_first_time_recuring_failures($timestamp);

    // a.k.a. "THIRD PASS"
    CRM_Core_Error::debug_log_message('processnewmessages: Beginning THIRD PASS');
    if (!empty($message_ids)) {
      $msgs_id_where = "AND msgs.id NOT IN (" . implode(',', $message_ids) . ")";
    }
    $sql = "
      SELECT
        msgs.id,
        msgs.x_type as trans_type,
        msgs.x_amount as message_amount,
        msgs.x_response_code,
        msgs.x_response_reason_code,
        msgs.x_response_reason_text,
        msgs.x_trans_id,
        msgs.x_first_name,
        msgs.x_last_name,
        msgs.x_email,
        msgs.x_description,
        msgs.x_cust_id,
        date_format(msgs.message_date, '%Y-%m-%d'  ) as message_date,
        recur.id as crm_recur_id,
        recur_ft.id as recur_contribution_type,
        recur_contact.id as recur_contact_id
      FROM
        $messages_table_name as msgs
        LEFT JOIN civicrm_contribution ctrb ON msgs.x_trans_id = ctrb.trxn_id
        LEFT JOIN civicrm_contribution_recur recur ON recur.processor_id = msgs.x_subscription_id
        LEFT JOIN civicrm_financial_type recur_ft ON recur.financial_type_id = recur_ft.id
        LEFT JOIN civicrm_contact recur_contact ON recur.contact_id = recur_contact.id
       WHERE
        ctrb.id IS NULL
        AND date(msgs.message_date) >= '" . PROCESSNEWMESSAGES_START_DATE . "'
        AND msgs.x_trans_id <> '0'
        AND msgs.x_type IN ( 'auth_capture', 'capture_only',  'credit' )
        AND (msgs.processed IS NULL OR msgs.processed = %1)
        $msgs_id_where
        ";

    $dao_params = array(
      1 => array($timestamp, 'String'),
    );

    //  sql for messages missing a contribution:
    $dao = CRM_Core_DAO::executeQuery($sql, $dao_params);
    while ($dao->fetch()) {
      CRM_Core_Error::debug_log_message("processnewmessages: In THIRD PASS: found message id {$dao->id} in $messages_table_name.");

      $trans_id = $dao->x_trans_id;
      $trans_type = $dao->trans_type;
      $message_date = $dao->message_date;
      $response_code = $dao->x_response_code;
      $response_reason_code = $dao->x_response_reason_code;
      $response_reason_text = $dao->x_response_reason_text;
      $crm_recur_id = $dao->crm_recur_id;
      $recur_contact_id = $dao->recur_contact_id;
      $x_cust_id = $dao->x_cust_id;
      $recur_contribution_type = $dao->recur_contribution_type;
      $trans_description = $dao->x_description;
      $msg_email = $dao->x_email;
      $msg_first_name = $dao->x_first_name;
      $msg_last_name = $dao->x_last_name;
      $message_amount = $dao->message_amount;
      // print "<br><Hr> trans type: ".$trans_type;

      if ($trans_type == "auth_capture" || $trans_type == "capture_only" || $trans_type == "credit") {
        if (strlen($recur_contact_id) == 0) {
          if (strlen($x_cust_id) == 0 || (is_int($x_cust_id) == FALSE)) {

            $contact_id_tmp = get_contact_from_msg($msg_first_name, $msg_last_name, $msg_email);
          }
          else {
            $contact_id_tmp = $x_cust_id;
          }
        }
        else {
          $contact_id_tmp = $recur_contact_id;
        }

        //print "<br>At this point we have a contact id:".$contact_id_tmp;
        if ($response_code == "1") {
          // completed
          //   print "<br>completed transaction";
          $contribution_status_id = "1"; // CiviCRM Completed status
          if ($trans_type == "auth_capture" || $trans_type == "capture_only") {
            $tmp_source = "automated record-($trans_description)";
          }
          elseif ($trans_type == "credit") {
            $tmp_source = "automated record-(Auth.net credit) ($trans_description)";
          }
        }
        elseif ($response_code == "2" || $response_code == "3") {
          // failed
          //   print "<br>failed transaction";
          $contribution_status_id = "4"; // CiviCRM Failed status
          $tmp_source = "automated record-" . $response_code . "-" . $response_reason_code . "-" . $response_reason_text . " ($trans_description)";
        }

        $tmp_payment_instrument_id = "1"; // assume credit card for now

        if (strlen($recur_contribution_type) == 0) {
          // Get financial type id for "Unknown Financial";
          $tmp_financial_type_id = getFinancialTypeID_forMessage();
        }
        else {
          $tmp_financial_type_id = $recur_contribution_type;
        }
        if (strlen($tmp_financial_type_id) > 0) {
          if (strlen($crm_recur_id) > 0) {
            $contrib_params = array(
              'version' => 3,
              'sequential' => 1,
              'financial_type_id' => $tmp_financial_type_id,
              'payment_instrument_id' => $tmp_payment_instrument_id,
              'receive_date' => $message_date,
              'contribution_status_id' => $contribution_status_id,
              'contribution_source' => $tmp_source,
              'total_amount' => $message_amount,
              'contribution_recur_id' => $crm_recur_id,
              'contact_id' => $contact_id_tmp,
              'trxn_id' => $trans_id,
            );
          }
          else {

            $contrib_params = array(
              'version' => 3,
              'sequential' => 1,
              'financial_type_id' => $tmp_financial_type_id,
              'payment_instrument_id' => $tmp_payment_instrument_id,
              'receive_date' => $message_date,
              'contribution_status_id' => $contribution_status_id,
              'contribution_source' => $tmp_source,
              'total_amount' => $message_amount,
              'contact_id' => $contact_id_tmp,
              'trxn_id' => $trans_id,
            );
          }

          $result = civicrm_api3('Contribution', 'create', $contrib_params);
          CRM_Core_Error::debug_log_message("processnewmessages: In THIRD PASS: created contribution {$result['id']} from message id {$dao->id} in $messages_table_name.");
        }

        // Mark message as processed. Reference: https://pogstone.zendesk.com/agent/tickets/11083
        $sql = "
          UPDATE $messages_table_name
          SET processed = %1
          WHERE id = %2
        ";
        $dao_params = array(
          1 => array($timestamp, 'String'),
          2 => array($dao->id, 'Int'),
        );
        CRM_Core_DAO::executeQuery($sql, $dao_params);
        CRM_Core_Error::debug_log_message("processnewmessages: In THIRD PASS: marked message id {$dao->id} in $messages_table_name, as processed at $timestamp");
      }
    }
    $dao->free();
    CRM_Core_Error::debug_log_message('processnewmessages: End THIRD PASS.');

    // Now handle VOIDs
    // a.k.a, "FOURTH PASS"
    CRM_Core_Error::debug_log_message('processnewmessages: Beginning FOURTH PASS');
    $sql = "SELECT
      c.id as contribution_id,
      con.id as contact_id
    FROM
      pogstone_authnet_messages v
      join pogstone_authnet_messages m ON v.x_trans_id = m.x_trans_id AND m.x_type IN ('auth_capture', 'credit', 'capture_only' )
      LEFT JOIN civicrm_contribution c ON m.x_trans_id = c.trxn_id
      LEFT JOIN civicrm_contact con ON c.contact_id = con.id
      LEFT JOIN civicrm_contribution_recur recur ON recur.processor_id = m.x_subscription_id
      LEFT JOIN civicrm_financial_type recur_ct ON recur.financial_type_id = recur_ct.id
      LEFT JOIN civicrm_contact recur_contact ON recur.contact_id = recur_contact.id
      LEFT JOIN civicrm_financial_type ct ON c.financial_type_id = ct.id
    where
      v.x_type = 'void' and v.x_response_code = '1'
      AND m.x_response_code = '1'
      AND c.contribution_status_id IN  ( '1', '2', '5', '6')
      AND m.message_date >= '" . PROCESSNEWMESSAGES_START_DATE . "'
      AND (m.processed IS NULL OR m.processed = %1)
    ";

    $dao_params = array(
      1 => array($timestamp, 'String'),
    );

    // The user voided (ie cancelled) the transaction at Authorize.net on the same business day as the original transaction.
    // This means the original transaction is never settled. The original transaction could be 'auth_capture', 'credit' or 'capture_only'
    //      print "<hr><br><br>sql for messages that were voided: <br>".$sql."<br>";
    $dao = CRM_Core_DAO::executeQuery($sql, $dao_params);

    while ($dao->fetch()) {
      CRM_Core_Error::debug_log_message("processnewmessages: In FOURTH PASS: found message id {$dao->id} in $messages_table_name.");
      $contribution_id = $dao->contribution_id;
      $contact_id = $dao->contact_id;
      // print "<Br><br>Have a VOID for contrib id: ".$contribution_id." contact id: ".$contact_id;
      // Update the existing contribution to have a "cancelled" status
      // Mark message as processed. Reference: https://pogstone.zendesk.com/agent/tickets/11083
      $sql = "
        UPDATE $messages_table_name
        SET processed = %1
        WHERE id = %2
      ";
      $dao_params = array(
        1 => array($timestamp, 'String'),
        2 => array($dao->id, 'Int'),
      );
      CRM_Core_DAO::executeQuery($sql, $dao_params);
      CRM_Core_Error::debug_log_message("processnewmessages: In FOURTH PASS: marked message id {$dao->id} in $messages_table_name, as processed at $timestamp");
    }

    $dao->free();
    CRM_Core_Error::debug_log_message('processnewmessages: End FOURTH PASS.');
  }
}

function getFinancialTypeID_forMessage() {
  $tmp_name = "Unknown Financial";

  // require_once('./FinancialHelper.php');
  $params = array(
    'version' => 3,
    'sequential' => 1,
    'financial_type_name' => $tmp_name,
  );
  $result = civicrm_api('FinancialHelper', 'getfintype', $params);

  $tmp_ft_id = $result['values'][0];
  //print "<Br>get fin type id: ".$tmp_ft_id;
  //    $tmp_ft_id = $tmpfinhelper->getFinancialTypeId( $tmp_name) ;

  if (strlen($tmp_ft_id) == 0) {
    // need to create financial type
    // print "<br>Need to create financial type: ".$tmp_name;
    $ft_description = "";
    $ft_accounting_code = "unknown";
    $ft_is_deductible_parm = "0";

    // require_once('./FinancialHelper.php');
    $params = array(
      'version' => 3,
      'sequential' => 1,
      'financial_type_name' => $tmp_name,
    );
    $result = civicrm_api('FinancialHelper', 'createfintype', $params);
    //print "<Br>create fin type API call result : ";
    //print_r( $result);
    //$tmp_ft_id = $result['values'][0];

    if (1 == 1) {
      //$tmp_ft_id = $tmpfinhelper->getFinancialTypeId( $tmp_name) ;
      // print "<br>Created financial type with id: ".$tmp_ft_id;
    }
    else {
      //  print "<br>Unable to create financial type: ".$tmp_name;
    }
  }

  return $tmp_ft_id;
}

/**
 *
 */
function get_contact_from_msg($msg_first_name, $msg_last_name, $msg_email) {

  /*
    $params = array(
    'version' => 3,
    'sequential' => 1,
    'contact_type' => 'Individual',
    'city' => 'Palm+Harbor',
    'state_province' => 'FL',
    'postal_code' => 42345,
    'first_name' => 'Sample2',
    'last_name' => 'Last2',
    );
   */

  $contact_id = "";

  // If there is no email, do not attempt to find contact based only on first name and last name,
  // but do match on empty email.

  $sql = "select c.id as contact_id from civicrm_contact c LEFT JOIN civicrm_email e ON c.id = e.contact_id
      WHERE c.first_name = '%1'
      AND c.last_name = '%2'
      AND ifnull(e.email, '') = '%3'
      AND c.is_deleted <> 1
      LIMIT 1";
  $params = array(
    1 => array($msg_first_name, 'Text'),
    2 => array($msg_last_name, 'Text'),
    3 => array($msg_email, 'Text'),
  );

  $dao = CRM_Core_DAO::executeQuery($sql, $params);

  if ($dao->fetch()) {
    $contact_id = $dao->contact_id;
  }

  $dao->free();

  if (strlen($contact_id) == 0) {
    // create a new contact
    $contact_source = "payment processor transaction";

    if (strlen($msg_email) > 0) {
      $params = array(
        'version' => 3,
        'sequential' => 1,
        'first_name' => $msg_first_name,
        'last_name' => $msg_last_name,
        'email' => $msg_email,
        'source' => $contact_source,
        'contact_type' => 'Individual',
      );
    }
    else {
      $params = array(
        'version' => 3,
        'sequential' => 1,
        'first_name' => $msg_first_name,
        'last_name' => $msg_last_name,
        'source' => $contact_source,
        'contact_type' => 'Individual',
      );
    }

    $result = civicrm_api('Contact', 'create', $params);
    $tmp_values = $result['values'];

    if (count($tmp_values) == 1) {
      $contact_id = $tmp_values[0]['id'];
    }
  }

  return $contact_id;
}

function handleCancelledSubscriptions() {
  CRM_Core_Error::debug_log_message("processnewmessages: Start " . __FUNCTION__ . "().");
  // print "<h2>Section: If recurring contribution is cancelled, then update the pending contribution to cancelled status as well. </h2>";
  // If recurring subscription is cancelled, make sure the pending contribution is also cancelled.
  $cancelled_status_id = "3";

  $pending_status_id = "2";

  $tmp_sql = "
    select
      c.id as contrib_id
    FROM
      civicrm_contribution_recur r
      join civicrm_contribution c ON r.id = c.contribution_recur_id
    WHERE
      r.contribution_status_id  = $cancelled_status_id
      AND c.contribution_status_id = $pending_status_id
  ";

  $dao = & CRM_Core_DAO::executeQuery($tmp_sql, CRM_Core_DAO::$_nullArray);
  while ($dao->fetch()) {
    CRM_Core_Error::debug_log_message("processnewmessages: In " . __FUNCTION__ . "(): found pending contribution id={$dao->contrib_id} attached a canceled recurring contribution.");
    $contrib_id = $dao->contrib_id;

    if (strlen($contrib_id) > 0) {
      $params = array(
        'version' => 3,
        'sequential' => 1,
        'id' => $contrib_id,
        'contribution_status_id' => $cancelled_status_id,
      );
      $result = civicrm_api('Contribution', 'create', $params);
      CRM_Core_Error::debug_log_message("processnewmessages: In " . __FUNCTION__ . "(): updated status to {$cancelled_status_id} (canceled) for contribution id={$dao->contrib_id}.");
      //print "<br>API update contrib. status result:<br>";
      //print_r( $result);
    }
  }

  $dao->free();
  CRM_Core_Error::debug_log_message("processnewmessages: End " . __FUNCTION__ . "().");
}

/**
 * FIXME: document me!
 */
function create_needed_line_item_db_records($line_item_id, $line_item_data, $contrib_data) {
  if (strlen($contrib_data['trxn_id']) == 0) {
    CRM_Core_Error::fatal('create_needed_line_item_db_records: received an empty transaction ID.');
  }

  // select * from civicrm_entity_financial_account where financial_account_id = 1 and entity_id = 1;
  // NB: account_relationship=1 means that it's an income account.
  // +----+------------------------+-----------+----------------------+----------------------+
  // | id | entity_table           | entity_id | account_relationship | financial_account_id |
  // +----+------------------------+-----------+----------------------+----------------------+
  // |  1 | civicrm_financial_type |         1 |                    1 |                    1 |
  // +----+------------------------+-----------+----------------------+----------------------+

  $financial_account_id = CRM_Core_DAO::singleValueQuery(
      '
      SELECT financial_account_id
      FROM civicrm_entity_financial_account
      WHERE account_relationship = 1
        AND entity_table = "civicrm_financial_type"
        AND entity_id = %1
    ', array(
      1 => array($line_item_data['financial_type_id'], 'Positive'),
      )
  );

  $insert_sql_financial_item = "INSERT INTO civicrm_financial_item (created_date, transaction_date, contact_id, description, amount, currency, financial_account_id, status_id , entity_table , entity_id)
    VALUES ( '" . $contrib_data['receive_date'] . "' , '" . $contrib_data['receive_date'] . "' , %3, %4,
              " . $line_item_data['line_total'] . ", '" . $contrib_data['currency'] . "' ,
              %7, 1, 'civicrm_line_item' , " . $line_item_id . ")";

  CRM_Core_DAO::executeQuery($insert_sql_financial_item, array(
    3 => array($contrib_data['contact_id'], 'Positive'),
    4 => array($line_item_data['label'], 'String'),
    7 => array($financial_account_id, 'Positive'),
  ));

  // Now get ID from new record
  $financial_item_id = "";

  $get_id_sql = "SELECT *
     FROM civicrm_financial_item
    WHERE entity_table = 'civicrm_line_item'
      AND entity_id = " . $line_item_id;

  $dao_get_id = CRM_Core_DAO::executeQuery($get_id_sql);

  while ($dao_get_id->fetch()) {
    $financial_item_id = $dao_get_id->id;
  }

  $dao_get_id->free();

  // civicrm_financial_trxn.id is needed for financial_trxn_id field. Go get it.
  $crm_trxn_id = "";
  $get_trxn_id_sql = "SELECT id
           FROM  civicrm_financial_trxn where trxn_id = '" . $contrib_data['trxn_id'] . "'";

  $dao_get_trxn_id = CRM_Core_DAO::executeQuery($get_trxn_id_sql);
  while ($dao_get_trxn_id->fetch()) {
    $crm_trxn_id = $dao_get_trxn_id->id;
  }

  $dao_get_trxn_id->free();

  if (strlen($crm_trxn_id) > 0) {
    $insert_sql_ft = "INSERT INTO civicrm_entity_financial_trxn ( entity_table, entity_id, financial_trxn_id, amount )
           VALUES( 'civicrm_financial_item', " . $financial_item_id . ", " . $crm_trxn_id . " , " . $line_item_data['line_total'] . " )  ";

    $dao_ft = CRM_Core_DAO::executeQuery($insert_sql_ft, CRM_Core_DAO::$_nullArray);
    $dao_ft->free();
  }
}

function UpdateRecurringContributionSubscription($log_handle, &$crm_recur_id, &$trxn_id, &$trxn_receive_date, &$payment_instrument_id) {
  CRM_Core_Error::debug_log_message("processnewmessages: " . __FUNCTION__ . "(): \$crm_recur_id: $crm_recur_id; \$trxn_id: $trxn_id; \$trxn_receive_date: $trxn_receive_date; \$payment_instrument_id: $payment_instrument_id");
  $contribution_completed = FALSE;

  $params = array(
    'version' => 3,
    'sequential' => 1,
    'id' => $crm_recur_id,
  );

  $result = civicrm_api('ContributionRecur', 'get', $params);

  if ($result['is_error'] <> 0) {
    Civi::log()->warning("Error calling ContributionRecur Get API " . print_r($params, 1));
    return;
  }

  if ($result['count'] <> "1") {
    // print "<br><br>Error: Could not retrieve Recurring Contribution id: ".$crm_recur_id;
    Civi::log()->warning("Error: Could not retrieve Recurring Contribution id: " . $crm_recur_id);
    return;
  }

  $first_contrib_status = "";
  $first_contrib_id = "";

  //   print "<br>About to check for first contrib in the subscription<br>";
  //  print_r($result);
  // get contrib. id of starting contrib.
  CRM_Core_Error::debug_log_message("processnewmessages: " . __FUNCTION__ . "(): calling findFirstContributionInSubscription() for \$crm_recur_id: $crm_recur_id; \$trxn_id: $trxn_id; \$trxn_receive_date: $trxn_receive_date; \$payment_instrument_id: $payment_instrument_id");
  findFirstContributionInSubscription($log_handle, $crm_recur_id, $first_contrib_id, $first_contrib_status);
  CRM_Core_Error::debug_log_message("processnewmessages: " . __FUNCTION__ . "(): based on findFirstContributionInSubscription() for \$crm_recur_id: $crm_recur_id; first contribution id=$first_contrib_id with status of $first_contrib_status.");

  // print "<br>Already checked for first contrib in the subscription";

  if ($first_contrib_status == "1") {
    if (strlen($first_contrib_id) > 0) {
      // Create a new contribution record based on data from the first contribution record.

      CRM_Core_Error::debug_log_message("processnewmessages: " . __FUNCTION__ . "(): calling createContributionBasedOnExistingContribution() for \$first_contrib_id: $first_contrib_id; \$trxn_id: $trxn_id; \$trxn_receive_date: $trxn_receive_date; \$payment_instrument_id: $payment_instrument_id");
      $rtn_code = createContributionBasedOnExistingContribution($first_contrib_id, $trxn_id, $trxn_receive_date, $payment_instrument_id);
      CRM_Core_Error::debug_log_message("processnewmessages: " . __FUNCTION__ . "(): createContributionBasedOnExistingContribution() result was: (boolean) {$rtn_code}.");
      $contribution_completed = $rtn_code;
    }
    else {
      Civi::log()->warning("Error: For crm_recur_id: " . $crm_recur_id . " First contribution id (for completed contribution) is blank");
    }
  }
  elseif ($first_contrib_status == "2") {
    // Update existing first contribution record staus from pending to complete
    Civi::log()->warning("Need to update first contribution record (id: $first_contrib_id)");
    Civi::log()->warning("Because API issues, will create brand new contribution based on first, then will delete the first pending");

    if (strlen($first_contrib_id) > 0) {
      // Create a new contribution record based on data from the first contribution record.
      CRM_Core_Error::debug_log_message("processnewmessages: " . __FUNCTION__ . "(): calling createContributionBasedOnExistingContribution() for \$first_contrib_id: $first_contrib_id; \$trxn_id: $trxn_id; \$trxn_receive_date: $trxn_receive_date; \$payment_instrument_id: $payment_instrument_id");
      $rtn_code = createContributionBasedOnExistingContribution($first_contrib_id, $trxn_id, $trxn_receive_date, $payment_instrument_id);
      CRM_Core_Error::debug_log_message("processnewmessages: " . __FUNCTION__ . "(): createContributionBasedOnExistingContribution() result was: (boolean) {$rtn_code}.");
      $contribution_completed = $rtn_code;

      if ($rtn_code == TRUE) {
        // delete original pending contribution
        // $first_contrib_id
        $params = array(
          'version' => 3,
          'sequential' => 1,
          'id' => $first_contrib_id,
        );
        $result = civicrm_api('Contribution', 'delete', $params);
        CRM_Core_Error::debug_log_message("processnewmessages: " . __FUNCTION__ . "(): deleted first contribution id={$first_contrib_id}, for \$crm_recur_id: $crm_recur_id.");
        // print "<br>Result from deleting the pending contribution:<br>";
        // print_r($result);
      }
    }
    else {
      Civi::log()->warning("Error: For crm_recur_id: " . $crm_recur_id . " First contribution id (for pending contribution) is blank");
    }
  }
  else {
    // print "<br><br>ERROR: Unrecognized contribution status for the first contribution record in the subscription";
  }

  if ($contribution_completed) {
    CRM_Core_Error::debug_log_message("processnewmessages: " . __FUNCTION__ . "(): because createContributionBasedOnExistingContribution() returned TRUE, calling update_recurring_subscription_details() was not called, for \$trxn_receive_date: $trxn_receive_date; \$crm_recur_id: $crm_recur_id.");
    update_recurring_subscription_details($crm_recur_id, $trxn_receive_date);
  }
  else {
    CRM_Core_Error::debug_log_message("processnewmessages: " . __FUNCTION__ . "(): because createContributionBasedOnExistingContribution() returned FALSE, update_recurring_subscription_details() was not called, for \$crm_recur_id: $crm_recur_id.");
  }
}

function update_recurring_subscription_details($crm_recur_id, $trxn_receive_date) {
  CRM_Core_Error::debug_log_message("processnewmessages: starting " . __FUNCTION__ . "() for \$crm_recur_id: $crm_recur_id; \$trxn_receive_date: $trxn_receive_date; expect more log lines if this function completes propertly.");
  if (strlen($crm_recur_id) == 0) {
    // print "<br>ERROR: crm_recur_id is a required parameter";
    return;
  }

  // Figure out what new recurring status should be. Either "in progress" or "completed"
  $recur_completed_contribution_count = 0;
  $recur_expected_contribution_count = 0;
  // Step 1: Find out how many completed payments have occured.
  $params = array(
    'version' => 3,
    'sequential' => 1,
    'contribution_recur_id' => $crm_recur_id,
    'contribution_status_id' => 1,
  );
  $result = civicrm_api('Contribution', 'getcount', $params);

  if ($result['is_error'] <> 0) {
    // print "<br>ERROR: issue calling Contribution Get API";
    // print_r ( $result );
    return;
  }
  else {
    // print "<br>Successfully called Contribution...getcount API";
    //print_r($result);
    $recur_completed_contribution_count = $result;
    // print "<br>Completed Contributions for this recuring subscription: ".$recur_completed_contribution_count;
  }

  // Step 2: Find out how many payments wer expected
  $params = array(
    'version' => 3,
    'sequential' => 1,
    'id' => $crm_recur_id,
  );
  $result = civicrm_api('ContributionRecur', 'getsingle', $params);

  if ($result['is_error'] <> 0) {
    // print "<br>ERROR: issue calling ContributionRecur GetSingle API";
    // print_r ( $result );
    return;
  }
  else {
    $recur_expected_contribution_count = $result['installments'];
    // print "<br>Expected Contributions for this recuring subscription: ".$recur_expected_contribution_count;
  }

  $new_recur_status = "";

  if (is_numeric($recur_completed_contribution_count) && is_numeric($recur_expected_contribution_count)) {
    $recur_completed_num = intval($recur_completed_contribution_count);
    $recur_expected_num = intval($recur_expected_contribution_count);

    if ($recur_expected_num <> 0 && $recur_completed_num == $recur_expected_num) {
      $new_recur_status = "1"; // completed.
    }
    elseif ($recur_completed_num > 0) {
      $new_recur_status = "5"; // In progress
    }
  }
  elseif (is_numeric($recur_completed_contribution_count)) {
    $recur_completed_num = intval($recur_completed_contribution_count);
    if ($recur_completed_num > 0) {
      $new_recur_status = "5"; // In progress
    }
  }

  if (strlen($new_recur_status) > 0) {
    $status_sql = " , contribution_status_id = " . $new_recur_status;
  }
  else {
    $status_sql = "";
  }

  $update_sql = "UPDATE civicrm_contribution_recur
         SET modified_date = '" . $trxn_receive_date . "' " . $status_sql . "
          WHERE id = " . $crm_recur_id;
  // print "<br><br>Update recur sql: <br>".$update_sql;
  $dao = & CRM_Core_DAO::executeQuery($update_sql, CRM_Core_DAO::$_nullArray);
  $dao->free();
  CRM_Core_Error::debug_var("processnewmessages: starting " . __FUNCTION__ . "() for \$crm_recur_id: $crm_recur_id; \$trxn_receive_date: $trxn_receive_date; ran this update query: ", $update_sql);
}

function findFirstContributionInSubscription($log_handle, $crm_recur_id, &$first_contrib_id, &$first_contrib_status) {
  // Find the 'pending' contribution record for this subscription. (Should only be one or zero)
  $pending_status_id = "2";
  $completed_status_id = "1";
  $params = array(
    'version' => 3,
    'sequential' => 1,
    'contribution_recur_id' => $crm_recur_id,
    'contribution_status_id' => $pending_status_id,
  );

  $result = civicrm_api('Contribution', 'get', $params);

  if ($result['is_error'] <> 0) {
    // print "<br>ERROR: issue calling Contribution Get API";
  }
  else {
    if ($result['count'] == "1") {
      CRM_Core_Error::debug_log_message("processnewmessages: " . __FUNCTION__ . "(): found just 1 existing pending contribution (id={$result['values'][0]['contribution_id']}) for \$crm_recur_id: $crm_recur_id");
      $first_contrib_id = $result['id'];
      $first_contrib_status = $pending_status_id;
    }
    elseif ($result['count'] == "0") {
      CRM_Core_Error::debug_log_message("processnewmessages: " . __FUNCTION__ . "(): found 0  existing pending contributions for \$crm_recur_id: $crm_recur_id");
      $params = array(
        'version' => 3,
        'sequential' => 1,
        'contribution_recur_id' => $crm_recur_id,
        'contribution_status_id' => $completed_status_id,
      );
      $result = civicrm_api('Contribution', 'get', $params);

      // print_r( $result ) ;
      if ($result['is_error'] <> 0) {
        CRM_Core_Error::debug_log_message("processnewmessages: " . __FUNCTION__ . "(): ERROR: issue calling Contribution Get API, for \$crm_recur_id: $crm_recur_id");
      }
      else {
        if ($result['count'] <> 0) {
          CRM_Core_Error::debug_log_message("processnewmessages: " . __FUNCTION__ . "(): found an existing completed contribution (id={$result['values'][0]['contribution_id']}) for \$crm_recur_id: $crm_recur_id");
          $tmp_contrib_id = $result['values'][0]['contribution_id'];
          $first_contrib_id = $tmp_contrib_id;
        }
      }

      $first_contrib_status = $completed_status_id;
    }
    else {
      Civi::log()->warning("ProccessorMessage.Processnewmessages: Error: More than one pending contribution found. This is invalid.");
    }
  }
}

function createContributionBasedOnExistingContribution($base_contrib_id, $trxn_id, $trxn_receive_date, $payment_instrument_id) {
  $rtn_code = FALSE;

  // Get the first completed contribution from the subscription. Will use the details
  // to create the lastest contribution. Only difference should be date, and transaction ID.

  $base_result = civicrm_api('Contribution', 'get', array('version' => 3, 'sequential' => 1, 'id' => $base_contrib_id));

  //print "<br>base contrib: ";
  //print_r($base_result ) ;

  if ($base_result['is_error'] <> 0) {
    // print "<br>Error calling contribution get API:<br>";
    // print_r($base_result ) ;

    return $rtn_code;
  }

  // need to get all the line items
  $lineitem_result = civicrm_api('LineItem', 'get', array(
    'version' => 3,
    'sequential' => 1,
    'entity_table' => 'civicrm_contribution',
    'entity_id' => $base_contrib_id,
  ));

  if ($lineitem_result['is_error'] <> 0) {
    // print "<br>Error calling LineItem get API:<br>";
    // print_r( $lineitem_result ) ;
    return $rtn_code;
  }

  $new_contrib_tmp = $base_result['values'][0];

  // Need to get custom data values from contribution.
  $tmp_custom_data_api_names = getContributionAPINames();

  $source_tmp = 'automated payment';
  $skipLineItem_parm = "1";

  $new_contrib_params = array(
    'version' => 3,
    'sequential' => 1,
    'financial_type_id' => $new_contrib_tmp['financial_type_id'],
    'contact_id' => $new_contrib_tmp['contact_id'],
    'skipLineItem' => $skipLineItem_parm,
    'payment_instrument_id' => $payment_instrument_id,
    'total_amount' => $new_contrib_tmp['total_amount'],
    'trxn_id' => $trxn_id,
    'contribution_recur_id' => $new_contrib_tmp['contribution_recur_id'],
    'currency' => $new_contrib_tmp['currency'],
    //'fee_amount' => $new_contrib_tmp['fee_amount'],
    //'net_amount' => $new_contrib_tmp['net_amount'],
    'contribution_campaign_id' => $new_contrib_tmp['contribution_campaign_id'],
    'non_deductible_amount' => $new_contrib_tmp['non_deductible_amount'],
    'contribution_page_id' => $new_contrib_tmp['contribution_page_id'],
    'source' => $source_tmp,
    'honor_contact_id' => $new_contrib_tmp['honor_contact_id'],
    'honor_type_id' => $new_contrib_tmp['honor_type_id'],
    'contribution_status_id' => 1,
    'receive_date' => $trxn_receive_date,
  );

  // Deal with custom data values
  if (is_array($tmp_custom_data_api_names)) {
    foreach ($tmp_custom_data_api_names as $cur_api_name) {
      $new_contrib_params[$cur_api_name] = $new_contrib_tmp[$cur_api_name];
    }
  }

  if (strlen($new_contrib_params['non_deductible_amount']) == 0) {
    unset($new_contrib_params['non_deductible_amount']);
  }

  if (strlen($trxn_id) == 0) {
    //print "<h2>Error: trxn id CANNOT be empty, will not create contribution.</h2>";
    //print_r( $new_contrib_params );
    exit();
  }

  //$new_contrib_params['total_amount'] = $gateway_amount;
  $new_contrib_result = civicrm_api('Contribution', 'create', $new_contrib_params);

  if ($new_contrib_result['is_error'] <> 0) {
    print "<br>Error calling Contribution Create API: <br>";
    print_r($new_contrib_result);
    return $rtn_code;
  }

  //print "<hr><br>Called Contribution Create API: <br>";
  //print_r( $new_contrib_result);

  $new_contrib_id = $new_contrib_result['id'];
  //print "<br><br> new contrib id: ".$new_contrib_id;
  // process each line item

  $all_line_items = $lineitem_result['values'];
  $line_item_count = $lineitem_result['count'];
  // print "<br>all lines<br>";
  // print_r( $all_line_items ) ;
  // print "<br>line item count: ".$line_item_count;
  foreach ($all_line_items as $original_line_item) {
    //print "<hr><br><br>Original line item: ";
    //print_r( $original_line_item );
    // print "<br><br>Inside loop on line item  ";
    // create line items:
    $params = array(
      'version' => 3,
      'sequential' => 1,
      'entity_table' => 'civicrm_contribution',
      'entity_id' => $new_contrib_id,
      'price_field_id' => $original_line_item['price_field_id'],
      'label' => $original_line_item['label'],
      'qty' => $original_line_item['qty'],
      'unit_price' => $original_line_item['unit_price'],
      'line_total' => $original_line_item['line_total'],
      'participant_count' => $original_line_item['participant_count'],
      'price_field_value_id' => $original_line_item['price_field_value_id'],
      'financial_type_id' => $original_line_item['financial_type_id'],
      'deductible_amount' => $original_line_item['deductible_amount'],
    );

    //print "<br><br>New line item:<br> ";
    //print_r( $params ) ;
    $li_result = civicrm_api('LineItem', 'create', $params);
    if ($li_result['is_error'] <> 0) {
      // print "<br>Error calling Line Item API: <br>";
      // print_r( $li_result);
    }
    else {
      // print "<br>Called line item API: <br>";
      // print_r( $li_result);
      // This is needed because of bug in line item API.
      //
      // print_r( $new_contrib_params ) ;
      create_needed_line_item_db_records($li_result['id'], $li_result['values'][0], $new_contrib_params);
      $rtn_code = TRUE;
    }
  }

  return $rtn_code;
}

function getContributionAPINames() {
  $all_api_names = array();

  // get all active set IDs.
  $set_sql = "SELECT id as set_id
     FROM civicrm_custom_group
     WHERE extends = 'Contribution' AND is_active = 1";

  $all_set_ids = array();
  $dao = CRM_Core_DAO::executeQuery($set_sql);

  while ($dao->fetch()) {
    $all_set_ids[] = $dao->set_id;
  }

  $dao->free();

  // get active fields for each set.
  foreach ($all_set_ids as $cur_set_id) {
    $params = array(
      'version' => 3,
      'sequential' => 1,
      'custom_group_id' => $cur_set_id,
      'is_active' => 1,
    );

    $result = civicrm_api('CustomField', 'get', $params);

    if ($result['is_error'] == 0) {
      $tmp_values = $result['values'];
      foreach ($tmp_values as $cur) {
        $cur_id = $cur['id'];
        $cur_name = $cur['name'];
        if ($cur_name <> "Deposit_id" && $cur_name <> "Batch_id") {
          $all_api_names[] = "custom_" . $cur_id;
        }
      }
    }
  }

  return $all_api_names;
}

/**
 * Scan authorize.net messages table for unprocessed messages indicating failure
 * of the first transaction in a recurring contribution. For each one found,
 * set the status to 'Failed' for both the contribution and its corresponding
 * contribution_recur entity; also mark the message as processed.
 *
 * a.k.a. "SECOND PASS"
 *
 * @param string $timestamp A mysql datetime string. Messages may have already
 *    been processed at $timestamp by handle_the_messages(), but this function
 *    will handle them once more for its own purposes; however it will not
 *    handle any messages already processed at a time other than $timestamp.
 * @return Array of ids for messages processed in this function.
 */
function _processnewmessages_handle_authnet_first_time_recuring_failures($timestamp) {
  CRM_Core_Error::debug_log_message('processnewmessages: Beginning SECOND PASS');
  $msg_ids = array();
  $messages_table_name = 'pogstone_authnet_messages';
  // Any Authorize.net 'declined' codes (referecne http://developer.authorize.net/api/reference/dist/json/responseCodes.json):
  $declined_codes = "2, 3, 4, 27, 41, 44, 45, 65, 141, 145, 165, 191, 200, 201, 202, 203, 204, 205, 206, 207, 208, 209, 210, 211, 212, 213, 214, 215, 216, 217, 218, 219, 220, 221, 222, 223, 224, 250, 251, 254, 'E00118'";
  $sql = "
    SELECT
      ctrb.id as contribution_id,
      ctrb.contribution_recur_id,
      ctrb.contact_id,
      msgs.id
    FROM
      pogstone_authnet_messages msgs
      -- Join to the contribution record based on invoice_num/contribution.id
      INNER JOIN civicrm_contribution ctrb ON ctrb.id = msgs.x_invoice_num
      -- Join any other completed contributions (payments) having the same
      -- recuring_contribution_id; since this is a left join, we can limit to
      -- rows where this is NULL, in order to find records in ctrb that have
      -- no such matching records.
      LEFT JOIN civicrm_contribution recur_ctrb
        ON recur_ctrb.contribution_recur_id = ctrb.contribution_recur_id
        AND recur_ctrb.id <> ctrb.id
        AND recur_ctrb.contribution_status_id = 1
    WHERE
      1
      AND ctrb.contribution_recur_id IS NOT NULL -- is part of a recurring contribution.
      AND recur_ctrb.id IS NULL -- has no completed payments in same recurring contribution.
      AND msgs.x_response_code IN ($declined_codes) -- failed at Authorize.net.
      AND (msgs.processed IS NULL OR msgs.processed = %1) -- message hasn't been processed or is just recently processed.
      AND date(msgs.message_date) >= %2 -- not sure why this date check is important.
  ";
  $dao_params = array(
    1 => array($timestamp, 'String'),
    2 => array(PROCESSNEWMESSAGES_START_DATE, 'String'),
  );

  $dao = CRM_Core_DAO::executeQuery($sql, $dao_params);
  while ($dao->fetch()) {
    CRM_Core_Error::debug_log_message("processnewmessages: In SECOND PASS: found message id {$dao->id} in $messages_table_name.");
    // Mark the contribution (payment) as Failed.
    $result = civicrm_api3('Contribution', 'create', array(
      'id' => $dao->contribution_id,
      'contribution_status_id' => 'Failed',
    ));
    // Mark the recurring contribution as Failed.
    $result = civicrm_api3('ContributionRecur', 'create', array(
      'id' => $dao->contribution_recur_id,
      'contribution_status_id' => 'Failed',
    ));

    $msg_ids[] = $dao->id;

    // Mark message as processed. Reference: https://pogstone.zendesk.com/agent/tickets/11083
    $sql = "
      UPDATE $messages_table_name
      SET processed = %1
      WHERE id = %2
    ";
    $dao_params = array(
      1 => array($timestamp, 'String'),
      2 => array($dao->id, 'Int'),
    );
    CRM_Core_DAO::executeQuery($sql, $dao_params);
    CRM_Core_Error::debug_log_message("processnewmessages: In SECOND PASS: marked message id {$dao->id} in $messages_table_name, as processed at $timestamp");
  }
  CRM_Core_Error::debug_log_message('processnewmessages: End SECOND PASS.');

  return $msg_ids;
}

/**
 * Find any messages in the given message table that meet the appropriate
 * criteria, and mark them as processed by storing the current date/time in the
 * processed column. Criteria basically amount to:
 *   - Has matching contribution record by Transaction ID
 *   - Is not already marked processed.
 *
 * Rationale:
 *   Any non-recurring contribution is created at time of submission and
 *   immediately given a transaction ID and sent to authorize.net; for this
 *   one-time contribution, CiviCRM listens actively for the immediate
 *   Authorize.net response, and marks the status accordingly (usually
 *   "Completed"). This contribution record has all the data it will ever need
 *   and requires no further processing. A moment later, the payment procesor
 *   (Authorize.net/PayPal) sends a message via Silent Post / IPN. PPH
 *   intercepts this message (as it does with all messages) and logs it in
 *   the messages table.
 *   At the next cron run, PPH scans the messages tables for relevant messages,
 *   and it ignores this message, because it matches (by transaction ID) an
 *   existing contribution record (see README.md "First Pass").
 *   This means the message is never processed and thus never marked as processed.
 *   However, if the contribution is ever deleted (intentionally), PPH will
 *   later find this message, note that it's not processed, and process it, thus
 *   re-creating that contribution.
 *   To avoid this, we mark these as processed as soon as we find them, admitting
 *   that "processed" here really means "saw it and noted that nothing should
 *   be done with it."
 *
 *   Reference: https://pogstone.zendesk.com/agent/tickets/12844
 *
 * @param String $messages_table_name Name of the messages table.
 * @param string $timestamp A mysql datetime string. This timestamp will be
 *   inserted into the `processed` column for affected message rows.
 */
function _processnewmessages_messages_with_existing_contributions($messages_table_name, $timestamp) {
  switch ($messages_table_name) {
    case 'pogstone_paypal_messages':
      $sql = "
        UPDATE $messages_table_name msgs
          INNER JOIN civicrm_contribution ctrb
        SET processed = %1
        WHERE
          msgs.processed IS NULL
          AND msgs.message_date > %2
          AND length(msgs.txn_id) > ''
          AND msgs.txn_id = ctrb.trxn_id
      ";
      break;

    case 'pogstone_authnet_messages':
      $sql = "
        UPDATE $messages_table_name msgs
          INNER JOIN civicrm_contribution ctrb
        SET processed = %1
        WHERE
          msgs.processed IS NULL
          AND msgs.message_date > %2
          AND length(msgs.x_trans_id) > ''
          AND msgs.x_trans_id = ctrb.trxn_id
      ";
      break;
  }
  if (!empty($sql)) {
    $sql_params = array(
      1 => array($timestamp, 'String'),
      2 => array(PROCESSNEWMESSAGES_START_DATE, 'String'),
    );
    CRM_Core_DAO::executeQuery($sql, $sql_params);
    CRM_Core_Error::debug_log_message('processnewmessages: Ran ' . __FUNCTION__ . " on $messages_table_name with timestamp $timestamp");
  }
}
