<?php

/**
 * ProccessorMessage.Processnewmessages API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRM/API+Architecture+Standards
 */
function _civicrm_api3_proccessor_message_processnewmessages_spec(&$spec) {
  
}

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
 
 /*
  if (array_key_exists('magicword', $params) && $params['magicword'] == 'sesame') {
    $returnValues = array( // OK, return several data rows
      12 => array('id' => 12, 'name' => 'Twelve'),
      34 => array('id' => 34, 'name' => 'Thirty four'),
      56 => array('id' => 56, 'name' => 'Fifty six'),
    );
    */
    // ALTERNATIVE: $returnValues = array(); // OK, success
   
	$rec_count = handle_the_messages(); 
	  $returnValues = array("record count:".$rec_count ); // OK, return a single value
    // Spec: civicrm_api3_create_success($values = 1, $params = array(), $entity = NULL, $action = NULL)
    return civicrm_api3_create_success($returnValues, $params, 'ProcessorMessage', 'processnewmessages');
 // } else {
  //  throw new API_Exception(/*errorMessage*/ 'Everyone knows that the magicword is "sesame"', /*errorCode*/ 1234);
 // }
}



 function handle_the_messages(){
   
   	//init();

	$all_message_types_tocheck = array();
	
	 
	$ewayemailrecur_type = "eWay_Recurring";
	
	$pay_pal_type = "PayPal"; 
	$params = array(
	  'version' => 3,
	  'sequential' => 1,
	  'vendor_type' => $pay_pal_type,
	);
	$result = civicrm_api('PaymentProcessorTypeHelper', 'get', $params);
	
	$tmp  = $result['values'][0];
	if($tmp['id'] == $pay_pal_type){
		$bool_str = $tmp['name'];
		$pay_pal_enabled = $bool_str === 'true'? true: false;
	
	}
	if( $pay_pal_enabled){
		$all_message_types_tocheck[] = "PayPal"; 
	}
	//  ( [is_error] => 0 [version] => 3 [count] => 1 [id] => PayPal [values] => Array ( [0] => Array ( [id] => PayPal [name] => false ) ) )
	//print "<br><br>result:";
	//print_r( $result); 
	$authnet_type = "AuthNet";
	$params = array(
	  'version' => 3,
	  'sequential' => 1,
	  'vendor_type' => $authnet_type,
	);
	$result = civicrm_api('PaymentProcessorTypeHelper', 'get', $params);
	
	$tmp  = $result['values'][0];
	if($tmp['id'] == $authnet_type){
		$bool_str = $tmp['name'];
		$authnet_enabled = $bool_str === 'true'? true: false;
	
	}
	
	if( $authnet_enabled){
		$all_message_types_tocheck[] = "AuthNet"; 
	}
	
	
	// Now check for eWay recurring (email notifications)
	$params = array(
	  'version' => 3,
	  'sequential' => 1,
	  'vendor_type' => $ewayemailrecur_type ,
	);
	$result = civicrm_api('PaymentProcessorTypeHelper', 'get', $params);
	
	$tmp  = $result['values'][0];
	if($tmp['id'] == $ewayemailrecur_type ){
		$bool_str = $tmp['name'];
		$ewayrecur_enabled = $bool_str === 'true'? true: false;
	
	}
	
	if( $ewayrecur_enabled){
		$all_message_types_tocheck[] = "eWay"; 
	}
	
	
	
	
	
	/*
	iATS Payments Credit Card
iATS Payments ACH\/EFT
iATS Payments SWIPE
   */
   
   /*
      $iats_types = array(); 
      $iats_types[] = "iATS Payments Credit Card";
      $iats_types[] = "iATS Payments ACH\/EFT";
      $iats_types[] = "iATS Payments SWIPE";
      
      $iats_enabled = false; 
      foreach(  $iats_types as $cur){
   
		
		$params = array(
		  'version' => 3,
		  'sequential' => 1,
		  'vendor_type' => $cur,
		);
		$result = civicrm_api('PaymentProcessorTypeHelper', 'get', $params);
		
		$tmp  = $result['values'][0];
		if($tmp['id'] == $cur){
			$bool_str = $tmp['name'];
			if( $bool_str === 'true' ){
				$iats_enabled = true; 
			}
		
		}
		
	}
	if( $iats_enabled ){
		$all_message_types_tocheck[] = "iATS"; 
	}
	
	*/
	//fixRecurringWithNoContribs();
	
	
	
	// For each processor type in use, process related messages 
	 $rec_count = 0;
	foreach( $all_message_types_tocheck as $cur_type){
	
	
	if( $cur_type == "iATS" ){
	
	
		$sql = " SELECT    msg.transaction_id as transaction_id , 
		 msg.trans_date, 
		 msg.recur_id as crm_recur_id  , msg.payment_instrument_id 
		FROM `pogstone_iats_messages` msg LEFT JOIN
		civicrm_contribution c ON msg.transaction_id = c.trxn_id where msg.payment_instrument_id IN ( '1', '2') AND c.id is NULL "; 
        
        
        
        
	
	        
	
	
	
	
	}else if( $cur_type == "PayPal"  ){
   //   print "<br><br><br> PayPal enabled"; 
	    
		$sql = 	"SELECT msgs.amount, msgs.txn_id, substr( msgs.payment_date, 10, 3 ) as payment_date_month , 
		substr( msgs.payment_date, 14, 2 ) as payment_date_day ,
		substr( msgs.payment_date, 18, 4 ) as payment_date_year ,
		concat(msgs.last_name, ',' , msgs.first_name) as sort_name , `civicrm_recur_id` , c.id as crm_contrib_id, c.contact_id as crm_contact_id, con.sort_name as crm_contact_name, recur.id as crm_recur_id, ct.name as contrib_type_name, recur_ct.id as recur_contribution_type , recur_ct.name as recur_contrib_type_name, recur.contact_id as recur_contact_id, recur_contact.id as recur_contact_id, recur_contact.sort_name as recur_contact_name, `rec_type` , date_format(message_date, '%Y%m%d'  ) as message_date , `payment_status` ,
			rp_invoice_id, recur.amount  as crm_amount
      FROM pogstone_paypal_messages as msgs LEFT JOIN civicrm_contribution c ON msgs.txn_id = c.trxn_id LEFT JOIN civicrm_contact con ON c.contact_id = con.id LEFT JOIN civicrm_contribution_recur recur ON recur.id = (  substr( rp_invoice_id, LOCATE( '&r=' , rp_invoice_id) + 3,   ( LOCATE( 
    '&b=', rp_invoice_id ) - 3 -  (LOCATE( '&r=' , rp_invoice_id)  ) ) ) )  LEFT JOIN civicrm_financial_type recur_ct ON recur.financial_type_id = recur_ct.id LEFT JOIN civicrm_contact recur_contact ON recur.contact_id = recur_contact.id LEFT JOIN civicrm_financial_type ct ON c.financial_type_id = ct.id WHERE msgs.payment_status = 'Completed' AND length(msgs.recurring_payment_id) > 0 AND c.id IS NULL
       AND msgs.message_date >= '2013-03-01'
       GROUP by msgs.ipn_track_id ";
		
       
       	    }else if(  $cur_type == "AuthNet" ){
       	    // print "<br><br><br> Authorize.net enabled"; 
       		
       		$sql = "SELECT concat(x_last_name, ',' , x_first_name) as sort_name , `civicrm_recur_id` , c.id as crm_contrib_id, c.contact_id as crm_contact_id, con.sort_name as crm_contact_name, recur.id as crm_recur_id, ct.name as contrib_type_name, recur_ct.id as recur_contribution_type , recur_ct.name as recur_contrib_type_name, recur.contact_id as recur_contact_id, recur_contact.id as recur_contact_id, recur_contact.sort_name as recur_contact_name, `rec_type` , date_format(message_date, '%Y%m%d'  ) as message_date ,
       		x_amount as message_amount, 
       		`x_response_code` , `x_response_reason_code` , `x_response_reason_text` , `x_avs_code` , `x_auth_code` , `x_trans_id` , 
     `x_method` , `x_card_type` , `x_account_number` , `x_first_name` , `x_last_name` , `x_company` , `x_address` , `x_city` , `x_state` , `x_zip` ,
      `x_country` , `x_phone` , `x_fax` , `x_email` , `x_invoice_num` , `x_description` , `x_type` , `x_cust_id` , `x_ship_to_first_name` , `x_ship_to_last_name` , `x_ship_to_company` , `x_ship_to_address` , `x_ship_to_city` , `x_ship_to_state` , `x_ship_to_zip` , `x_ship_to_country` , `x_amount` , `x_tax` , `x_duty` , `x_freight` , `x_tax_exempt` , `x_po_num` , `x_MD5_Hash` , `x_cvv2_resp_code` , `x_cavv_response` , `x_test_request` , `x_subscription_id` , `x_subscription_paynum` , recur.amount  as crm_amount
      FROM pogstone_authnet_messages as msgs LEFT JOIN civicrm_contribution c ON msgs.x_trans_id = c.trxn_id LEFT JOIN civicrm_contact con ON c.contact_id = con.id LEFT JOIN civicrm_contribution_recur recur ON recur.processor_id = msgs.x_subscription_id LEFT JOIN civicrm_financial_type recur_ct ON recur.financial_type_id = recur_ct.id LEFT JOIN civicrm_contact recur_contact ON recur.contact_id = recur_contact.id LEFT JOIN civicrm_financial_type ct ON c.financial_type_id = ct.id WHERE msgs.x_response_code = '1' AND length(msgs.x_subscription_id) > 0 AND c.id IS NULL
       AND msgs.message_date >= '2013-03-01' 
        " ; 
       		
       		
      }else if(  $cur_type == "eWay"){
       		   // Currently only completed eWay transactions have an amount > 0. 
       		   // Raw 'eway_email_date' is always in America/New York time zone. Need to adjust to the time zone of the client, such as Sydney in Australia
       		   $hours_to_add = ""; 
				
				$org_timezone = variable_get('pogstone_local_timezone', NULL);
				if( $org_timezone == 'Australia/Sydney'){
					$num_sec  = 60 * 60 * 14; 
					$hours_to_add = '14 HOUR' ;
				}else if( $org_timezone ==   'Australia/Melbourne'){ 
					$num_sec  = 60 * 60 * 14; 
					$hours_to_add = '14 HOUR' ;
				
				}else if( $org_timezone ==  'Australia/Perth' ){
					$num_sec  = 60 * 60 * 12;  
					$hours_to_add = '12 HOUR' ;
				}else{
					print "<br>org time zone not recognized: ".$org_timezone;
					$num_sec  = 60 * 60 * 14; 
					$hours_to_add = '14 HOUR' ;	
				}
				
				// field 'eway_email_date' is of type datetime
				
				
				//$tmp_a =  $email_timestamp + $num_sec; 	
				// 	print "<br><br>email timestamp: ".$email_timestamp."<br> Adjusted ts: ".$tmp_a;
				// $paymentDate = date('Ymd H:i:s', $tmp_a) ;
       		
       		    $sql = 	"SELECT recur.id as crm_recur_id,  msgs.eway_transaction_id,  
       		    DATE_ADD( `eway_email_date`,  INTERVAL ".$hours_to_add." ) as adj_eway_email_date , `eway_currency`, `eway_amount`,
       		     `eway_transaction_id`, `eway_name`, `eway_address`,
       		     `eway_invoice_reference_number`, `eway_email_subject`, `eway_email_body`, 
       		     c.id as crm_contrib_id, c.contact_id as contact_id, contact_a.sort_name as crm_contact_name, ct.name as contrib_type_name,
       		     recur.id as crm_recur_id, recur_ct.name as recur_contrib_type_name, recur.contact_id as recur_contact_id,
       		      recur_contact.id as recur_contact_id,
       		     recur_contact.sort_name as recur_contact_name , recur.amount  as crm_amount
       		    FROM pogstone_eway_messages as msgs LEFT JOIN civicrm_contribution c ON msgs.eway_transaction_id = c.trxn_id 
       		    LEFT JOIN civicrm_contribution_recur recur ON recur.processor_id = msgs.eway_invoice_reference_number 
       		    LEFT JOIN civicrm_financial_type recur_ct ON recur.financial_type_id = recur_ct.id 
       		    LEFT JOIN civicrm_contact recur_contact ON recur.contact_id = recur_contact.id 
       		    LEFT JOIN civicrm_contact contact_a ON c.contact_id = contact_a.id 
       		    LEFT JOIN civicrm_financial_type ct ON c.financial_type_id = ct.id WHERE msgs.eway_amount > 0 
       		    AND msgs.eway_invoice_reference_number LIKE '%(r)' AND c.id IS NULL
       		    AND msgs.eway_trans_type = 'payment'  ";
       		
       		
       		
       		
       		}
	
	
     
       $tmp_server_path =   realpath($_SERVER['DOCUMENT_ROOT'].'/../') ;   
    	  
    	  $filename_prefix = date('Y-m-d');
    	  
    	  
	 // $logfile = $tmp_server_path."/".$filename_prefix."__pogstone_createContributionsFromMessgae_log.txt";
	 // print "<br>Log file path: ".$logfile;
	  
	//     $log_handle =   fopen(  $logfile , "a+") ;
	$logfile = 0; 
	     
	     $now = date('Y-m-d  H:i:s');
	     
	     // Store the posted values in an associative array
		$fields = array();
		
	     if($log_handle){
	     
		  fwrite($log_handle, "\n Special Debug version -------------------------------------------------------\n\n");
		  fwrite ( $log_handle, "\n Now it is: ".$now); 
		  fwrite(  $log_handle , '  ');
	     	  fwrite(  $log_handle , '\n');
	     	  
	     	  fwrite(  $log_handle , $sql);
	     	  fwrite(  $log_handle , '\n');
     	  }
     	
     	 
   //	print "<br><hr>";
 //  print "<h1>Pogstone script for recurring contributions</h1>";
   
   
   
  // print "<h2>Section: Find new payment processor messages and attempt to create contribution records</h2>"; 
  // print "<br><br>SQL: ".$sql;
   if(strlen( $sql) > 0 ){
	 $dao  =  & CRM_Core_DAO::executeQuery( $sql,   CRM_Core_DAO::$_nullArray ) ;
       
	while ( $dao->fetch( ) ) {
	
		$message_valid_to_process = true; 
		
		$cid = $dao->recur_contact_id; 
		$contrib_type_id = $dao->recur_contribution_type ; 
		$recur_id = $dao->crm_recur_id;
		$crm_contact_name = $dao->crm_contact_name; 
		$card_billingname = $dao->sort_name;
		$crm_amount = $dao->crm_amount;
			
		
		if(  $cur_type == "iATS"  ){
			 $receive_date = $dao->trans_date;
			
			$trxn_id = $dao->transaction_id ; 
			
			$payment_instrument_id = $dao->payment_instrument_id;
		
		}else if( $cur_type == "PayPal"  ){
		       $date_raw_year = $dao->payment_date_year;
		        $date_raw_month = $dao->payment_date_month;
		         $date_raw_day = $dao->payment_date_day;
		       
		       $tmp_sql_date = $date_raw_year."-".$date_raw_month."-".$date_raw_day;
		       
		        $receive_date = $tmp_sql_date;
			$amount = $dao->amount;
			$trxn_id = $dao->txn_id ; 
			$processor_subscription_id = $dao->recurring_payment_id;
			$payment_instrument_id = "1" ;  // Assume Credit Card
			// print "<br>Inside paypal section: amt: ".$amount; 
		
		}else if( $cur_type == "AuthNet"  ){
		
		        $receive_date = $dao->message_date;
			$amount = $dao->x_amount;
			$trxn_id = $dao->x_trans_id ; 
			$processor_subscription_id = $dao->x_subscription_id;
			$payment_instrument_id = "1" ;  // Assume Credit Card
			
			$tmp_trans_amount = number_format($amount, 2); 
			$tmp_crm_amount = number_format($crm_amount, 2); 
			if( $tmp_crm_amount  <> $tmp_trans_amount ){
				$message_valid_to_process = false; 
				$message_error_text = "Transaction amount ($tmp_trans_amount) does NOT match CRM amount ($tmp_crm_amount) for this subscription";
			
			}
		
		}else if($cur_type == "eWay" ){
			// recur.id as crm_recur_id,  msgs.eway_transaction_id,   `eway_email_date`, 
			
			$receive_date = $dao->adj_eway_email_date;  
			$amount = $dao->eway_amount;
			 
			$trxn_id = $dao->eway_transaction_id ; 
			$processor_subscription_id = $dao->crm_recur_id; 
			$payment_instrument_id = "1" ;  // Assume Credit Card
		       
		}
		
		
		
		// this is the fancy new way introduced for version 4.3.x or better
		if(strlen( $recur_id) > 0 ){
		     if( strlen( $trxn_id ) == 0 ){
		       //    print "<br><br>Cannot process this message, trxn_id is empty. "; 
		       //    print "<br> \n Error on contact id: ".$cid." -- Name on Card: ".$card_billingname." -- CRM Name: ".$crm_contact_name." crm_recur_id: ".$recur_id; 
		     
		     }else if($message_valid_to_process <> true ){
		     	// print "<br><br>Cannot process this message: ".$message_error_text; 
		     	//  print "<br> \n Error on contact id: ".$cid." -- Name on Card: ".$card_billingname." -- CRM Name: ".$crm_contact_name." crm_recur_id: ".$recur_id; 
		     
		     
		     }else{
		  //	print "<h2>Process for contact id: ".$cid." -- Name on Card: ".$card_billingname." -- CRM Name: ".$crm_contact_name." crm_recur_id: ".$recur_id."</h2>";
		  if( $log_handle){
			   fwrite($log_handle, "\n Process for contact id: ".$cid." -- Name on Card: ".$card_billingname." -- CRM Name: ".$crm_contact_name." crm_recur_id: ".$recur_id." trxn id: ".$trxn_id."  ----------------------------------------------------\n\n");
			   }
			$rtn_code = UpdateRecurringContributionSubscription($log_handle, $recur_id , $trxn_id, $receive_date, $payment_instrument_id  ); 
			
			
		
			// TODO: Check rtn_code to see if there was an error. 
			$rec_count++;
			
		      }
		}else{
			// print "<br>Error: Could not find crm_recur_id for x_subscription_id: ".$processor_subscription_id;
		
		}
		
		
		
		
	}

	$dao->free();
	
	handle_messges_with_no_contrib($cur_type); 
	
	}
	
	}
	
	
	handleCancelledSubscriptions(); 
	
	
	
	 
	 
	
	 return $rec_count; 
	 
	 
		
	}
	
	
	  //  run();
	   
				
	  
	  
	    // Test with hard-coded message
	     // Next 3 values should come from the payment processor message. 
	     /*
	        $crm_recur_id = "3";
	 	$trxn_id = "41122";
		$trxn_receive_date = "20130703"; 
		
		UpdateRecurringContributionSubscription($log_handle, $crm_recur_id , $trxn_id, $trxn_receive_date  ); 
		*/
	
	
	function handle_messges_with_no_contrib($cur_type){
	
	$start_date =  '2015-01-15';
	
	   if(  $cur_type == "AuthNet" ){
       	    // print "<br><br><br> Authorize.net enabled"; 
       		
       		$sql = " SELECT concat(x_last_name, ',' , x_first_name) as sort_name , `civicrm_recur_id` , c.id as crm_contrib_id, c.contact_id as crm_contact_id, con.sort_name as crm_contact_name, recur.id as crm_recur_id, ct.name as contrib_type_name, recur_ct.id as recur_contribution_type , recur_ct.name as recur_contrib_type_name, recur.contact_id as recur_contact_id, recur_contact.id as recur_contact_id, recur_contact.sort_name as recur_contact_name, `rec_type` ,
       		 date_format(message_date, '%Y-%m-%d'  ) as message_date , `x_type` as trans_type ,
       		x_amount as message_amount, 
       		`x_response_code` , `x_response_reason_code` , `x_response_reason_text` , `x_avs_code` , `x_auth_code` , `x_trans_id` , 
     `x_method` , `x_card_type` , `x_account_number` , `x_first_name` , `x_last_name` , `x_company` , `x_address` , `x_city` , `x_state` , `x_zip` ,
      `x_country` , `x_phone` , `x_fax` , `x_email` , `x_invoice_num` , `x_description` ,  `x_cust_id` , `x_ship_to_first_name` , `x_ship_to_last_name` , `x_ship_to_company` , `x_ship_to_address` , `x_ship_to_city` , `x_ship_to_state` , `x_ship_to_zip` , `x_ship_to_country` , `x_amount` , `x_tax` , `x_duty` , `x_freight` , `x_tax_exempt` , `x_po_num` , `x_MD5_Hash` , `x_cvv2_resp_code` , `x_cavv_response` , `x_test_request` , `x_subscription_id` , `x_subscription_paynum` , recur.amount  as crm_amount
      FROM pogstone_authnet_messages as msgs LEFT JOIN civicrm_contribution c ON msgs.x_trans_id = c.trxn_id LEFT JOIN civicrm_contact con ON c.contact_id = con.id LEFT JOIN civicrm_contribution_recur recur ON recur.processor_id = msgs.x_subscription_id LEFT JOIN civicrm_financial_type recur_ct ON recur.financial_type_id = recur_ct.id LEFT JOIN civicrm_contact recur_contact ON recur.contact_id = recur_contact.id LEFT JOIN civicrm_financial_type ct ON c.financial_type_id = ct.id
       WHERE c.id IS NULL
       AND date(msgs.message_date) >= '$start_date'
       AND x_trans_id <> '0'
       AND x_type IN ( 'auth_capture', 'capture_only',  'credit' ) 
        " ; 
        
      //  print "<br><br>sql for messages missing a contribution: <br> ".$sql."<br>"; 
         $dao  =  & CRM_Core_DAO::executeQuery( $sql,   CRM_Core_DAO::$_nullArray ) ;
	 while($dao->fetch()){
	  
	    $trans_id = $dao->x_trans_id;
	    $trans_type = $dao->trans_type;
	    $message_date = $dao->message_date; 
	    $response_code = $dao->x_response_code; 
	    $response_reason_code = $dao->x_response_reason_code;
	    $response_reason_text =  $dao->x_response_reason_text;
	    $crm_recur_id = $dao->crm_recur_id; 
	    $recur_ct = $dao->recur_ct; 
	    $recur_contact_id = $dao->recur_contact_id;
	    $x_cust_id = $dao->x_cust_id;
	    $recur_contribution_type = $dao->recur_contribution_type; 
	    $trans_description = $dao->x_description;
	    
	    
	    //
	    $msg_email = $dao->x_email; 
	    $msg_first_name = $dao->x_first_name; 
	    $msg_last_name = $dao->x_last_name;
	    $message_amount = $dao->message_amount;
	   // print "<br><Hr> trans type: ".$trans_type; 
	    
	    
	    
	    if( $trans_type == "auth_capture" || $trans_type == "capture_only" || $trans_type == "credit" ){
	       if( strlen($recur_contact_id) == 0){
	          if( strlen( $x_cust_id) == 0  ||  ( is_int($x_cust_id ) == false)){
	       
	         	$contact_id_tmp = get_contact_from_msg( $msg_first_name , $msg_last_name, $msg_email  ); 
	         }else{
	         	$contact_id_tmp  = $x_cust_id; 
	         
	         }
	       
	       }else{
	       	$contact_id_tmp  = $recur_contact_id;
	       }
	    
	    //print "<br>At this point we have a contact id:".$contact_id_tmp; 
		    if( $response_code == "1"){
		    // completed
		 //   print "<br>completed transaction"; 
		    $contribution_status_id = "1"; // CiviCRM Completed status
		    if( $trans_type == "auth_capture" || $trans_type == "capture_only"  ){
		    	$tmp_source = "automated record-($trans_description)" ; 
		    }else if($trans_type == "credit"){
		    	$tmp_source = "automated record-(Auth.net credit) ($trans_description)" ; 
		    
		    }
		    
		  
		    }else if( $response_code == "2" || $response_code == "3"){
		      // failed
		   //   print "<br>failed transaction"; 
		      $contribution_status_id = "4"; // CiviCRM Failed status
		      $tmp_source = "automated record-".$response_code."-".$response_reason_code."-".$response_reason_text." ($trans_description)"; 
		    
		    }
		    
		    $tmp_payment_instrument_id = "1"; // assume credit card for now
		    
		    
		    
		    
		    if( strlen( $recur_contribution_type) == 0 ){
		       // Get financial type id for "Unknown Financial"; 
		       $tmp_financial_type_id = getFinancialTypeID_forMessage();
		    
		    }else{
		   	$tmp_financial_type_id = $recur_contribution_type; 
		    
		    }
		    if( strlen( $tmp_financial_type_id) > 0){
		    if( strlen( $crm_recur_id) > 0 ){
		    	$contrib_params = array(
			  'version' => 3,
			  'sequential' => 1,
			  'financial_type_id' => $tmp_financial_type_id,
			  'contribution_payment_instrument_id' =>  $tmp_payment_instrument_id,
			  'receive_date' => $message_date,
			  'contribution_status_id' => $contribution_status_id,
			  'contribution_source' => $tmp_source,
			  'total_amount' => $message_amount,
			  'contribution_recur_id' => $crm_recur_id, 
			  'contact_id' => $contact_id_tmp,
			  'trxn_id' => $trans_id, 
			);
		    
		    }else{
		    
		        $contrib_params = array(
			  'version' => 3,
			  'sequential' => 1,
			  'financial_type_id' => $tmp_financial_type_id,
			  'contribution_payment_instrument_id' => $tmp_payment_instrument_id,
			  'receive_date' => $message_date,
			  'contribution_status_id' => $contribution_status_id,
			  'contribution_source' => $tmp_source,
			  'total_amount' => $message_amount,
			  'contact_id' => $contact_id_tmp,
			   'trxn_id' => $trans_id, 
			);
		    
		   		    
		    }
		    
		    
		    $result = civicrm_api('Contribution', 'create',  $contrib_params);
		    if( $result[is_error] == 1 ){
		    
		    	//print "<br>ERROR creating contribution: ";
		    	//print "<br>params to api: ";
		    	//print_r( $contrib_params);
		    	//print "<br>API result: "; 
		    	//print_r( $result); 
		    }
		  }
	    }
	 
	 }
	 $dao->free();
          
          // Now handle VOIDs
          $sql = "SELECT c.id as contribution_id, con.id as contact_id,  c.contribution_status_id,   concat( m.x_last_name, ',' , m.x_first_name) as sort_name , m.civicrm_recur_id , c.id as crm_contrib_id, c.contact_id as crm_contact_id, con.sort_name as crm_contact_name, recur.id as crm_recur_id, ct.name as contrib_type_name, recur_ct.id as recur_contribution_type , recur_ct.name as recur_contrib_type_name, recur.contact_id as recur_contact_id, recur_contact.id as recur_contact_id, recur_contact.sort_name as recur_contact_name, m.rec_type , date_format(m.message_date, '%Y-%m-%d'  ) as message_date , m.x_type as trans_type , 
       		m.x_amount as message_amount, 
       		m.x_response_code , m.x_response_reason_code , m.x_response_reason_text , m.x_avs_code , m.x_auth_code , m.x_trans_id , 
     m.x_method , m.x_card_type , m.x_account_number , m.x_first_name, m.x_last_name , m.x_company , m.x_address, m.x_city , m.x_state , m.x_zip,
      m.x_country , m.x_phone , m.x_fax , m.x_email , m.x_invoice_num , m.x_description , m.x_cust_id, m.x_ship_to_first_name ,
 m.x_ship_to_last_name , m.x_ship_to_company , m.x_ship_to_address , m.x_ship_to_city , m.x_ship_to_state , m.x_ship_to_zip , m.x_ship_to_country , 
 m.x_amount , m.x_tax , m.x_duty , m.x_freight , m.x_tax_exempt, m.x_po_num , m.x_MD5_Hash , m.x_cvv2_resp_code , m.x_cavv_response, m.x_test_request , 
  m.x_subscription_id , m.x_subscription_paynum , recur.amount  as crm_amount   FROM `pogstone_authnet_messages` v  
join pogstone_authnet_messages m ON v.x_trans_id = m.x_trans_id AND m.x_type IN ('auth_capture', 'credit', 'capture_only' ) 
LEFT JOIN civicrm_contribution c ON m.x_trans_id = c.trxn_id LEFT JOIN civicrm_contact con ON c.contact_id = con.id 
LEFT JOIN civicrm_contribution_recur recur ON recur.processor_id = m.x_subscription_id 
LEFT JOIN civicrm_financial_type recur_ct ON recur.financial_type_id = recur_ct.id 
LEFT JOIN civicrm_contact recur_contact ON recur.contact_id = recur_contact.id LEFT JOIN civicrm_financial_type ct ON c.financial_type_id = ct.id
where v.x_type = 'void' and v.x_response_code = '1'
AND m.x_response_code = '1'
AND c.contribution_status_id IN  ( '1', '2', '5', '6') 
AND m.message_date >= '$start_date'";

   // The user voided (ie cancelled) the transaction at Authorize.net on the same business day as the original transaction. 
   // This means the original transaction is never settled. The original transaction could be 'auth_capture', 'credit' or 'capture_only'
	      
	//      print "<hr><br><br>sql for messages that were voided: <br>".$sql."<br>"; 
         $dao  =  & CRM_Core_DAO::executeQuery( $sql,   CRM_Core_DAO::$_nullArray ) ;
         
          
	 while($dao->fetch()){
	 	$contribution_id = $dao->contribution_id ;
	 	$contact_id = $dao->contact_id;  
	    //  print "<Br><br>Have a VOID for contrib id: ".$contribution_id." contact id: ".$contact_id; 
	      // Update the existing contribution to have a "cancelled" status 
	 
	 }
	 
	 $dao->free();
       		
       		
      }
	
	
	}
	
	
	function getFinancialTypeID_forMessage(){
	
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
	
	 if( strlen( $tmp_ft_id ) == 0){
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
	
	
	 if(1==1  ){
	    //$tmp_ft_id = $tmpfinhelper->getFinancialTypeId( $tmp_name) ;
	   // print "<br>Created financial type with id: ".$tmp_ft_id; 
	    }else{
	    //  print "<br>Unable to create financial type: ".$tmp_name; 
	    
	    }
	     
	}
	
	return $tmp_ft_id ; 
	}
	
	
	
	
	
	function get_contact_from_msg( $msg_first_name , $msg_last_name, $msg_email  ){
	     
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
  // If there is no email, do not attempt to find contact based only on first name and last name.
  	 if( strlen( $msg_email) > 0 ){
  	 
  	      $first_name_formatted_for_sql = mysql_real_escape_string(  $msg_first_name); 
  	      $last_name_formatted_for_sql = mysql_real_escape_string(  $msg_last_name); 
  	      $email_formatted_for_sql = mysql_real_escape_string(  $msg_email); 
  	      
		$sql  = "select c.id as contact_id from civicrm_contact c LEFT JOIN civicrm_email e ON c.id = e.contact_id
			WHERE lower(c.first_name) = lower('$first_name_formatted_for_sql') 
			AND lower(c.last_name) = lower('$last_name_formatted_for_sql') 
			AND lower(e.email) = lower('$email_formatted_for_sql')
			AND c.is_deleted <> 1
			LIMIT 1  ";
		 $dao  =  & CRM_Core_DAO::executeQuery( $sql,   CRM_Core_DAO::$_nullArray ) ;	
		 if($dao->fetch()){
		 	$contact_id = $dao->contact_id; 
		 
		 }
		 $dao->free();
		 
	 }
		 
		 if( strlen($contact_id ) == 0){
		 	// create a new contact
		 	
		 	$contact_source = "payment processor transaction";
		 	if( strlen( $msg_email) > 0 ){
			 	$params = array(
				  'version' => 3,
				  'sequential' => 1,
				  'first_name' => $msg_first_name,
				  'last_name' => $msg_last_name,
				  'email' => $msg_email,
				  'source' => $contact_source,
				  'contact_type' => 'Individual',
				);
			}else{
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
			if( count( $tmp_values)  == 1)
			  $contact_id = $tmp_values[0]['id'];
			//  print "<br><br>created new contact with id: ".$contact_id ; 
			} 
			
			
		 
		 
	
	   return $contact_id ; 
	}
		
	function handleCancelledSubscriptions(){
		// print "<h2>Section: If recurring contribution is cancelled, then update the pending contribution to cancelled status as well. </h2>"; 
	// If recurring subscription is cancelled, make sure the pending contribution is also cancelled. 
	$cancelled_status_id = "3"; 
	
	$pending_status_id = "2"; 
	
	$tmp_sql = "select c.id as contrib_id 
	FROM civicrm_contribution_recur r join civicrm_contribution c ON r.id = c.contribution_recur_id 
	WHERE  r.contribution_status_id  = $cancelled_status_id
	AND c.contribution_status_id = $pending_status_id "; 
	
	 $dao  =  & CRM_Core_DAO::executeQuery( $tmp_sql,   CRM_Core_DAO::$_nullArray ) ;
	 while($dao->fetch()){
	 	$contrib_id = $dao->contrib_id; 
	 
	 	if( strlen( $contrib_id ) > 0){
		 	$params = array(
			  'version' => 3,
			  'sequential' => 1,
			  'id' => $contrib_id ,
			  'contribution_status_id' => $cancelled_status_id,
			);
			$result = civicrm_api('Contribution', 'create', $params);
			//print "<br>API update contrib. status result:<br>";
			//print_r( $result); 
		}
	 
	 }
	 
	 
	 $dao->free();
	
	}	
		
	function OLDfixRecurringWithNoContribs(){
	
		 // Check for recurring contributions with NO associated contributions. 
	// print "<h2>Section: Look for contribution_recur records with NO associated contributions, as this prevents messages from being processed. </h2>";
	 
	 // check payment processor type. This is only needed for Auth.net, PayPalPro, and eWAY
	  // Ignore subscriptions that are cancelled(3) or completed(1).
	 $tmp_sql = "select r.id, r.contact_id,  r.amount, r.financial_type_id as financial_type_id ,
	  r.contribution_status_id , r.campaign_id, r.start_date
	FROM civicrm_contribution_recur r 
	JOIN civicrm_payment_processor p ON r.payment_processor_id = p.id
	JOIN civicrm_payment_processor_type pt ON pt.id = p.payment_processor_type_id 
	LEFT JOIN civicrm_contribution c ON r.id = c.contribution_recur_id 
	WHERE c.id IS NULL
	AND r.start_date > '2014-01-01'
	AND pt.name IN ('PayPal', 'AuthNet', 'eWay_recurring') 
	AND r.contribution_status_id NOT IN ( 1, 3)
	GROUP BY r.id "; 
	
	 $dao  =  & CRM_Core_DAO::executeQuery( $tmp_sql,   CRM_Core_DAO::$_nullArray ) ;
	  while($dao->fetch()){
	  	
	  	$recur_id = $dao->id;
	  	$contact_id = $dao->contact_id ; 
	  	$amount = $dao->amount;
	  	$financial_type_id= $dao->financial_type_id; 
	  //	$contribution_status_id = $dao->contribution_status_id; 
	  	$start_date = $dao->start_date; 
	  	$campaign_id = $dao->campaign_id; 
	  	
	  	
	  		//$params = array(
			//  'version' => 3,
			//  'sequential' => 1,
			//  'contribution_status_id' => $cancelled_status_id,
		//	);
			//$result = civicrm_api('Contribution', 'create', $params);
			//print "<br>API update contrib. status result:<br>";
			//print_r( $result); 
	  
	  
	  
	  }
	   $dao->free();
	
	}	
	     
	 function create_needed_line_item_db_records( $line_item_id, $line_item_data, $contrib_data ){
	 
	 
	      if( strlen($contrib_data['trxn_id']) == 0 ){
	    //       print "<h2>Error: Transaction ID cannot be empty!</h2>";
	           
	           exit(); 
	      }
	  //    print "<br>About to create needed line item records for one line item <br>";
	  //    print_r(  $line_item_data) ; 
	      
	      $description_cleaned = str_replace( "'", "\'", $line_item_data['label'] ); 
	      
	      	$insert_sql_financial_item = "INSERT INTO 
	      					civicrm_financial_item (  created_date, transaction_date, contact_id, description, 
	      					amount, 
	      					currency, 
	      					financial_account_id, status_id , entity_table , entity_id ) 
	      					VALUES ( '".$contrib_data['receive_date']."' , '".$contrib_data['receive_date']."' , ".$contrib_data['contact_id'].",
	      					 '".$description_cleaned."' , ".$line_item_data['line_total'].
	      					", '".$contrib_data['currency'] ."' , 
	      					".$line_item_data['financial_type_id'].", '1' , 'civicrm_line_item' , ".$line_item_id."  ) "; 
	      					
	 //     	print "<br>Part 1: Insert SQL: ".$insert_sql_financial_item; 				
	       	$dao_fi  =  & CRM_Core_DAO::executeQuery($insert_sql_financial_item,   CRM_Core_DAO::$_nullArray ) ;
	 	$dao_fi->free();
	 	
	 	// Now get ID from new record
	 	$financial_item_id = "";
	 	$get_id_sql = "SELECT * FROM civicrm_financial_item WHERE 
	 			entity_table = 'civicrm_line_item' AND  entity_id = ".$line_item_id;
	 	
	 	$dao_get_id =  & CRM_Core_DAO::executeQuery($get_id_sql,   CRM_Core_DAO::$_nullArray ) ;
	 	while ( $dao_get_id->fetch( ) ) {
	 	   $financial_item_id = $dao_get_id->id ; 	 	
	 	}
	 	
	 	$dao_get_id->free(); 
	 	
	 	
	 	// civicrm_financial_trxn.id is needed for financial_trxn_id field. Go get it. 
	 	$crm_trxn_id = ""; 
	 	$get_trxn_id_sql = "SELECT id 
				   FROM  civicrm_financial_trxn where trxn_id = '".$contrib_data['trxn_id']."'"; 
				   
		
				
		$dao_get_trxn_id =  & CRM_Core_DAO::executeQuery($get_trxn_id_sql,   CRM_Core_DAO::$_nullArray ) ;
	 	while ( $dao_get_trxn_id->fetch( ) ) {
	 	   $crm_trxn_id = $dao_get_trxn_id->id ; 	 	
	 	}
	 	
	 	$dao_get_trxn_id->free(); 
		if( strlen( $crm_trxn_id) > 0 ){		   
				   
	 	
	 	$insert_sql_ft = "INSERT INTO civicrm_entity_financial_trxn ( entity_table, entity_id, financial_trxn_id, amount ) 
	 			  VALUES( 'civicrm_financial_item', ".$financial_item_id.", ".$crm_trxn_id." , ".$line_item_data['line_total']." )  ";
	 			  
	 	// print "<br>Part 2: Insert SQL: ".$insert_sql_ft; 
	 	$dao_ft  =  & CRM_Core_DAO::executeQuery($insert_sql_ft,   CRM_Core_DAO::$_nullArray ) ;
	 	$dao_ft->free();
	 	}else{
	 	
	 	
	 	}
	 
	 
	//  print "<br>Done with db records for one line item";
	 
	 }   
	    
	 function UpdateRecurringContributionSubscription($log_handle, &$crm_recur_id , &$trxn_id, &$trxn_receive_date, &$payment_instrument_id  ){
	 
	   $contribution_completed = false; 
	   
	    
	    $params = array(
		  'version' => 3,
		  'sequential' => 1,
		  'id' => $crm_recur_id,
		);
	   $result = civicrm_api('ContributionRecur', 'get', $params);
	   if($result['is_error'] <> 0 ){
	   	//print "<br><br>Error calling ContributionRecur Get API: <br>";
	   	//print_r( $result) ;
	   	 fwrite(  $log_handle , '\n');
	   	 fwrite( $log_handle, "Error calling ContributionRecur Get API: \n") ; 
	   	return; 
	   } 
	    
	    	if($result['count'] <> "1" ){
	    		// print "<br><br>Error: Could not retrieve Recurring Contribution id: ".$crm_recur_id; 
	    		 fwrite(  $log_handle , '\n');
	   	 fwrite( $log_handle, "\nError: Could not retrieve Recurring Contribution id: ".$crm_recur_id) ; 
	    		return; 
	    	
	    	}
	    $first_contrib_status = "";
	    $first_contrib_id = "";
	    
	  //   print "<br>About to check for first contrib in the subscription<br>"; 
	   //  print_r($result); 	
	    // get contrib. id of starting contrib.
	    findFirstContributionInSubscription($log_handle,  $crm_recur_id,  $first_contrib_id,  $first_contrib_status);
	    
	   // print "<br>Already checked for first contrib in the subscription"; 
		
	
	 if(  $first_contrib_status == "1" ){
	 	if( strlen( $first_contrib_id ) > 0 ){
			// Create a new contribution record based on data from the first contribution record. 
			
	  		$rtn_code = createContributionBasedOnExistingContribution($first_contrib_id,  $trxn_id, $trxn_receive_date, $payment_instrument_id );
	  		$contribution_completed = $rtn_code; 
	  		
	  	}else{
	  		// print "<Br><br>Error: For crm_recur_id: ".$crm_recur_id."   First contribution id (for completed contribution) is blank"; 
	  		 fwrite(  $log_handle , '\n');
	   		 fwrite( $log_handle, "Error: For crm_recur_id: ".$crm_recur_id." First contribution id (for completed contribution) is blank \n") ; 
	  	
	  	}
	  }else if( $first_contrib_status == "2" ){
	  	// update existing first contribution record staus from pending to complete
	  	// print "<br><br>Need to update first contribution record (id: ".$first_contrib_id.") ."; 
	  	// print "<br>Because API issues, will create brand new contribution based on first, then will delete the first pending";
	  	 fwrite(  $log_handle , '\n');
	   		 fwrite( $log_handle, "\nNeed to update first contribution record (id: ".$first_contrib_id.") . \n") ;
	   		  fwrite( $log_handle, "\nBecause API issues, will create brand new contribution based on first, then will delete the first pending \n") ;
	  	
	  	if( strlen( $first_contrib_id ) > 0 ){
			// Create a new contribution record based on data from the first contribution record. 
	  		$rtn_code = createContributionBasedOnExistingContribution($first_contrib_id,  $trxn_id, $trxn_receive_date, $payment_instrument_id );
	  		$contribution_completed = $rtn_code; 
	  		
	  		if($rtn_code == true ){
	  			// delete original pending contribution
	  			// $first_contrib_id
	  			 $params = array(
				  'version' => 3,
				  'sequential' => 1,
				  'id' => $first_contrib_id,
				);
				$result = civicrm_api('Contribution', 'delete', $params ) ; 
				// print "<br>Result from deleting the pending contribution:<br>";
				// print_r($result); 
	  		
	  		}
	  		
	  	}else{
	  	//	print "<Br><br>Error: For crm_recur_id: ".$crm_recur_id." First contribution id (for pending contribution) is blank"; 
	  		 fwrite(  $log_handle , '\n');
	   		 fwrite( $log_handle, "<Br><br>Error: For crm_recur_id: ".$crm_recur_id." First contribution id (for pending contribution) is blank\n") ;
	   		 
	  	
	  	}
	         
	        
	         
	  	
	  }else{
	  	// print "<br><br>ERROR: Unrecognized contribution status for the first contribution record in the subscription"; 
	  
	  }
	  
	 
	 if( $contribution_completed){
	      update_recurring_subscription_details( $crm_recur_id ,  $trxn_receive_date  );
	      
	 
	 }
	 
	 }   
	    
	    
	    
	 function update_recurring_subscription_details( $crm_recur_id ,  $trxn_receive_date  ){
	 	if(strlen( $crm_recur_id) == 0){
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
		
		if($result['is_error'] <> 0 ){
			// print "<br>ERROR: issue calling Contribution Get API";
			// print_r ( $result );
			return;
		
		}else{
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
		
		if($result['is_error'] <> 0 ){
			// print "<br>ERROR: issue calling ContributionRecur GetSingle API";
			// print_r ( $result );
			return;
		
		}else{
		   $recur_expected_contribution_count  = $result['installments'];
		     // print "<br>Expected Contributions for this recuring subscription: ".$recur_expected_contribution_count;
		   
		
		}


		$new_recur_status = "";
		//if( is_numeric( $recur_completed_contribution_count )) {
		if( is_numeric( $recur_completed_contribution_count ) && is_numeric( $recur_expected_contribution_count) ){
			$recur_completed_num  = intval( $recur_completed_contribution_count) ;
	 		$recur_expected_num = intval( $recur_expected_contribution_count );
	 		
	 		if(  $recur_expected_num <> 0 && $recur_completed_num == $recur_expected_num ){
	 			$new_recur_status = "1" ; // completed.
	 		}else if( $recur_completed_num > 0 ){
	 			$new_recur_status = "5" ; // In progress
	 		
	 		}
		
		}else if( is_numeric($recur_completed_contribution_count) ) {
			$recur_completed_num  = intval ( $recur_completed_contribution_count ) ;
			if( $recur_completed_num > 0 ){
	 			$new_recur_status = "5" ; // In progress
	 		
	 		}
		
		
		}
		if( strlen( $new_recur_status) > 0 ){
			$status_sql = " , contribution_status_id = ".$new_recur_status;
		}else{
			$status_sql = "";
		}	
	 
	 	$update_sql = "UPDATE civicrm_contribution_recur 
	 			SET modified_date = '".$trxn_receive_date."' ".$status_sql."
	 			 WHERE id = ".$crm_recur_id ; 
	 	 // print "<br><br>Update recur sql: <br>".$update_sql; 
	 	$dao  =  & CRM_Core_DAO::executeQuery($update_sql,   CRM_Core_DAO::$_nullArray ) ;
	 	$dao->free();
	 
	 
	 }
	    
	 function findFirstContributionInSubscription( $log_handle,  $crm_recur_id,  &$first_contrib_id,  &$first_contrib_status){
	 	
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
		if( $result['is_error'] <> 0 ){
			// print "<br>ERROR: issue calling Contribution Get API";
			// print_r ( $result );
		}else{
			fwrite(  $log_handle , '\n');
	   		 fwrite( $log_handle, "Inside FindFirst: For crm_recur_id: ".$crm_recur_id." first contrib array :") ; 
	  		foreach( $result as $key => $cur_tmp){
	  			fwrite( $log_handle, "\n".$key." : ".$cur_tmp);
	  			
	  			if($key == 'values' ){
	  				foreach( $cur_tmp as $key_j => $cur_j){
	  					foreach($cur_j as $key_k => $cur_k){
	  						fwrite( $log_handle, "\n".$key_k." : ".$cur_k);
	  					}
	  				}
	  			
	  			}	
	  		
	  		}
		  // print "<Br><br>for crm_recur_id: ".$crm_recur_id." first contrib:<br> ";
		  // print_r( $result  ); 
		    if( $result['count'] == "1" ){
		    	 $first_contrib_id = $result['id'] ; 
		    	 $first_contrib_status = $pending_status_id ; 
		    
		    }else if(  $result['count'] == "0" ){
		       //  print "<br><br>There is no pending contribution. So create so get the oldest contribution on this subscription: ".$crm_recur_id; 
		        
		        fwrite(  $log_handle , '\n');
	   		 fwrite( $log_handle, "There is no pending contribution. So create so get the oldest contribution on this subscription: ".$crm_recur_id) ; 
		        $params = array(
			  'version' => 3,
			  'sequential' => 1,
			  'contribution_recur_id' => $crm_recur_id,
			  'contribution_status_id' => $completed_status_id ,
			);
			$result = civicrm_api('Contribution', 'get', $params);
		
		       // print_r( $result ) ;
		        if($result['is_error'] <> 0 ){
		        	// print "<br>ERROR: issue calling Contribution Get API";
				// print_r ( $result );
				 fwrite(  $log_handle , "\n");
	   			 fwrite( $log_handle, "ERROR: issue calling Contribution Get API: \n") ; 
	   			 foreach($result as $key => $cur){
	  					fwrite( $log_handle, "\n".$key." : ".$cur);
	  			}
		        
		        }else{
		        	fwrite( $log_handle, "Call to contrib API was successful.");
		        	foreach($result as $key => $cur){
	  					fwrite( $log_handle, "\n".$key." : ".$cur);
	  			}
		           // print_r( $result ) ;
		        	if( $result['count'] <> 0){
		        		$tmp_contrib_id = $result['values'][0]['contribution_id'] ;
		        		$first_contrib_id = $tmp_contrib_id; 
		        	}
		        
		        
		        }
		        
		        
		        
		        $first_contrib_status = $completed_status_id ; 
		    }else{
		    	// print "<br><br>Error: More than one pending contribution found. This is invalid. ";
		    
		    }
			
			
		
		}
			
	 	
	 
	 }  
	    
	 function createContributionBasedOnExistingContribution($base_contrib_id, $trxn_id, $trxn_receive_date, $payment_instrument_id  ){
	 	
	 	$rtn_code = false; 
	 	
		
		// Get the first completed contribution ID from the subscription. Will use the details
		// to create the lastest contribution. Only difference should be date, and transaction ID. 
		
		$base_result = civicrm_api('Contribution', 'get', array( 'version' => 3, 'sequential' => 1, 'id' => $base_contrib_id ) );
		
		//print "<br>base contrib: ";
		//print_r($base_result ) ;
		
		if($base_result['is_error'] <> 0){
			// print "<br>Error calling contribution get API:<br>";
			// print_r($base_result ) ;
			
			 return $rtn_code; 
			
		}
		
		
		
		
		// need to get all the line items
		$lineitem_result = civicrm_api('LineItem', 'get', array( 'version' => 3, 'sequential' => 1, 
		'entity_table' => 'civicrm_contribution',   'entity_id' => $base_contrib_id ) );
		
		
		if($lineitem_result['is_error'] <> 0){
			// print "<br>Error calling LineItem get API:<br>";
			// print_r( $lineitem_result ) ;
			 return $rtn_code; 
		
		}
				
		$new_contrib_tmp = $base_result['values'][0]; 
		
		// Need to get custom data values from contribution.	
		$tmp_custom_data_api_names = getContributionAPINames();
		/*
		//  get the first contribution in this series to help with line items and some other values
		 $initial_contribution = array();
		    $line_items = array();
		    $get = array('version'  => 3, 'contribution_recur_id' => $new_contrib_tmp['contribution_recur_id'], 'options'  => array('sort'  => ' id' , 'limit'  => 1));
		    $result = civicrm_api('contribution', 'get', $get);
		    if (!empty($result['values'])) {
		      $contribution_ids = array_keys($result['values']);
		      $get = array('version'  => 3, 'entity_table' => 'civicrm_contribution', 'entity_id' => $contribution_ids[0]);
		      $result = civicrm_api('LineItem', 'get', $get);
		      if (!empty($result['values'])) {
		        foreach($result['values'] as $initial_line_item) {
		          $line_item = array();
		          foreach(array('price_field_id','qty','line_total','unit_price','label','price_field_value_id','financial_type_id') as $key) {
		            $line_item[$key] = $initial_line_item[$key];
		          }
		          $line_items[] = $line_item;
		        }
		      }
		    }
		
		// end of new code
		*/
		
	//	print "<br>Contribution parms from Base:<br>";
	//	print_r( $new_contrib_tmp ) ;
		$source_tmp = 'automated payment'; 
		 $skipLineItem_parm = "1"; 
		 
		 //  new line item parm:
		 // 'api.line_item.create' => $line_items, 
		 
		 // TODO: Get payment instrument ID from payment processor type
		// $payment_instrument_id = "1";  // 1 = credit card, 2 = debit card (used by iATS for ACH/DirectDebit)
		
		$new_contrib_params = array( 'version' => 3,
			  'sequential' => 1,
			  'financial_type_id' =>  $new_contrib_tmp['financial_type_id'],
			  'contact_id' => $new_contrib_tmp['contact_id'], 
			  'skipLineItem' => $skipLineItem_parm, 
			  'payment_instrument_id' => $payment_instrument_id ,
			  'total_amount' => $new_contrib_tmp['total_amount'] ,
			  'trxn_id' => $trxn_id ,
			  'contribution_recur_id' => $new_contrib_tmp['contribution_recur_id'] ,
			  'currency' => $new_contrib_tmp['currency'] , 
			  //'fee_amount' => $new_contrib_tmp['fee_amount'],
			  //'net_amount' => $new_contrib_tmp['net_amount'],
			  'contribution_campaign_id' => $new_contrib_tmp['contribution_campaign_id'], 
			  'non_deductible_amount' => $new_contrib_tmp['non_deductible_amount'],
			  'contribution_page_id' => $new_contrib_tmp['contribution_page_id'],
			  'source' => $source_tmp ,  
			  'honor_contact_id' => $new_contrib_tmp['honor_contact_id'],
			  'honor_type_id' => $new_contrib_tmp['honor_type_id'], 
			  'contribution_status_id' => 1,
			  'receive_date' => $trxn_receive_date  ) ; 
			  
			  // Deal with custom data values
			  if(is_array($tmp_custom_data_api_names ) ){
			  	foreach($tmp_custom_data_api_names as $cur_api_name){
			  		$new_contrib_params[$cur_api_name] = $new_contrib_tmp[$cur_api_name] ;
			  	
			  	}
			  
			  }
		
		if( strlen( $new_contrib_params['non_deductible_amount'])  == 0  ){
		
			unset( $new_contrib_params['non_deductible_amount'] );
		
		}
		
		 if( strlen($trxn_id ) == 0){
		 	//print "<h2>Error: trxn id CANNOT be empty, will not create contribution.</h2>";
		 	//print_r( $new_contrib_params ); 
		 	exit();
		 	
		 }	  
			  
		//$new_contrib_params['total_amount'] = $gateway_amount; 
			$new_contrib_result = civicrm_api('Contribution', 'create', $new_contrib_params ) ; 
			if($new_contrib_result['is_error'] <> 0 ){
					print "<br>Error calling Contribution Create API: <br>";
					print_r( $new_contrib_result); 
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
			foreach( $all_line_items as $original_line_item){
						//print "<hr><br><br>Original line item: ";
						//print_r( $original_line_item ); 
				// print "<br><br>Inside loop on line item	"; 	
						
						// create line items:
						$params = array(
						  'version' => 3,
						  'sequential' => 1,
						  'entity_table' => 'civicrm_contribution',
						  'entity_id' => $new_contrib_id , 
						  'price_field_id' => $original_line_item['price_field_id'],
						  'label' => $original_line_item['label'],
						  'qty' => $original_line_item['qty'],
						  'unit_price' =>  $original_line_item['unit_price'],
						  'line_total' => $original_line_item['line_total'],
						  'participant_count' => $original_line_item['participant_count'],
						  'price_field_value_id' => $original_line_item['price_field_value_id'],
						  'financial_type_id' => $original_line_item['financial_type_id'],
						  'deductible_amount' => $original_line_item['deductible_amount'],
						  
						);
						
						//print "<br><br>New line item:<br> ";
						//print_r( $params ) ; 
						$li_result = civicrm_api('LineItem', 'create', $params);
						if($li_result['is_error'] <> 0 ){
							// print "<br>Error calling Line Item API: <br>";
							// print_r( $li_result); 
					
						}else{
							// print "<br>Called line item API: <br>";
							// print_r( $li_result); 
							// This is needed because of bug in line item API. 
							// 
							// print_r( $new_contrib_params ) ; 
							create_needed_line_item_db_records($li_result['id'] , $li_result['values'][0], $new_contrib_params );
							$rtn_code = true; 
						}
				// print "<br> End of loop iteration on line item"; 					
					
			}  // end of loop on each line item.
					
				  
			
			

		
		
		
				
			
	  return $rtn_code; 
	 
	 
	 }
	 
	 function getContributionAPINames(){
 
 	$all_api_names = array();
 	
 	
 	// get all active set IDs.
 	$set_sql = "SELECT id as set_id FROM civicrm_custom_group
 			WHERE extends = 'Contribution' AND is_active =1 "; 
 	
 	$all_set_ids = array();
 	$dao =& CRM_Core_DAO::executeQuery($set_sql );
 	while( $dao->fetch() ) {
 		$all_set_ids[] = $dao->set_id; 
 	
 	}
 	
 	$dao->free();
 	
 	// get active fields for each set.
 	foreach( $all_set_ids as $cur_set_id){
 		$params = array(
		  'version' => 3,
		  'sequential' => 1,
		  'custom_group_id' => $cur_set_id,
		  'is_active' => 1,
		);
		$result = civicrm_api('CustomField', 'get', $params);
		if( $result['is_error'] == 0){
			$tmp_values = $result['values'];
			foreach($tmp_values as $cur){
				$cur_id = $cur['id']; 
				$cur_name = $cur['name'];
				if( $cur_name <> "Deposit_id" &&  $cur_name <> "Batch_id" ){
					$all_api_names[] = "custom_".$cur_id;
				}
			}
		}
 	}
 	
 	return $all_api_names;
 	
 
 }
	 