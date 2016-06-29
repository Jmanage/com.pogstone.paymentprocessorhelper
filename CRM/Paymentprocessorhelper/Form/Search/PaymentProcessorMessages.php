<?php

/**
 * A custom contact search
 */
class CRM_Paymentprocessorhelper_Form_Search_PaymentProcessorMessages extends CRM_Contact_Form_Search_Custom_Base implements CRM_Contact_Form_Search_Interface {
  protected $_statusChoices = null;  
  protected $_userChoices = null; 

  function __construct(&$formValues) {
    parent::__construct($formValues);

    $this->setColumns();
    $this->_statusChoices = CRM_Utils_Array::value( 'status_id', $this->_formValues);
  }
    
  function buildForm(&$form) {
    $this->setTitle('Payment Processor Messages');

    $config = CRM_Core_Config::singleton();

    if ($config->userSystem->is_drupal) {
      // This is a Drupal install, check user authority. 
      if (user_access('access CiviContribute') == false) {
        $this->setTitle('Not Authorized');
        return;
      }
    }

	$status_choices = array();
	$status_choices[''] = " -- select --"; 
	$status_choices['not_1'] = "Only Non-completed Transactions";
	$status_choices['only_1'] = "Only Completed Transactions";  
	
	
	$form->add( 'select',
                    'status_choices',
                    ts( 'Transaction Status' ),
                    $status_choices,
                    false );
                    
          
         $recur_choice_opts = array();
         $recur_choice_opts[''] = " -- select --" ;
         $recur_choice_opts['recur_only'] = "Only Recurring Transactions" ;            
         $recur_choice_opts['nonrecur_only'] = "Only One-time Transactions" ;  
			
	$form->add( 'select',
                    'recur_choice',
                    ts( 'Only Recurring or Not' ),
                    $recur_choice_opts,
                    false );	
                    
                    
        $tran_type_choices = array();
	$tran_type_choices[''] = " -- select --"; 
	$tran_type_choices['credit'] = "Only Refund(credit) Transactions";
	$tran_type_choices['auth_capture'] = "Only Non-Refund(regular) Transactions";  
	
	$form->add( 'select',
                    'tran_type_choices',
                    ts( 'Transaction Types(Auth.net only)' ),
                    $tran_type_choices,
                    false );
        
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
	//
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
	
	//
	 $eway_type = "eWay_Recurring"; 
       $params = array(
	  'version' => 3,
	  'sequential' => 1,
	  'vendor_type' =>$eway_type,
	);
	$result = civicrm_api('PaymentProcessorTypeHelper', 'get', $params);
	
	$tmp  = $result['values'][0];
	if($tmp['id'] == $eway_type){
		$bool_str = $tmp['name'];
		$eway_enabled = $bool_str === 'true'? true: false;
	
	}
	
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
          
              
        $processor_choices = array();
       // $processor_choices[''] = " -- select --"; 
        if($authnet_enabled ){
		$processor_choices['authorize.net'] = "Authorize.net";
	}
	if( $pay_pal_enabled ){
		$processor_choices['paypal'] = "PayPal"; 
	} 
	
	if($eway_enabled ){
		$processor_choices['eway'] = "eWay";  
	}
	
	if( $iats_enabled){
		$processor_choices['iats'] = "iATS";  
	
	}
	
	$form->add( 'select',
                    'payment_processor_type_choices',
                    ts( 'Payment Processor Type' ),
                    $processor_choices,
                    false );
        
	
	/*
	
	$contrib_id_choice = $this->_formValues['contrib_id_choice'];
	if( $contrib_id_choice == 'has_contrib_id'){
		$clauses[] = "c.id IS NOT NULL"; 
	}else if($contrib_id_choice == 'missing_contrib_id'){
		$clauses[] = "c.id IS NULL";
	}
	
	*/
	
	$contrib_id_choice = array();
	$contrib_id_choice[''] = " -- select --"; 
	$contrib_id_choice['has_contrib_id'] = "Only Messages associated with a Contribution Record";
	$contrib_id_choice['missing_contrib_id'] = "Messages missing a Contribution Record";
	
	
	
	$form->add( 'select',
                    'contrib_id_choice',
                    ts( 'Association with Contribution' ),
                    $contrib_id_choice,
                    false );
         
         $layout_choice = array();
	$layout_choice[''] = " -- select --"; 
	$layout_choice['details'] = "Standard layout - one row per transaction";
	$layout_choice['combine_possible_duplicates'] = "Help spot doubled completed submissions";
	
	
	
	$form->add( 'select',
                    'layout_choice',
                    ts( 'Layout Choice' ),
                    $layout_choice,
                    false );                       	
			
	
	 $form->add('text',
	      'transaction_id',
     	      ts('Transaction ID'),
    		  FALSE
   		 );

	 $form->add('text',
	      'subscription_id',
     	      ts('Processor Subscription ID'),
    		  FALSE
   		 );

	 $form->add('text',
	      'amount',
     	      ts('Amount'),
    		  FALSE
   		 );
   		 
   	$form->add('text',
	      'first_name',
     	      ts('Contact First Name'),
    		  FALSE
   		 );
   		 
   	$form->add('text',
	      'last_name',
     	      ts('Contact Last Name'),
    		  FALSE
   		 );	 	 
   		 
		
	 $form->addDate('start_date', ts('Message Date From'), false, array( 'formatType' => 'custom' ) );
        
         $form->addDate('end_date', ts('...through'), false, array( 'formatType' => 'custom' ) );            
                    
        
        if($authnet_enabled ){
     $form->assign( 'elements', array( 'payment_processor_type_choices', 'start_date', 'end_date' , 'transaction_id', 'subscription_id',  'status_choices', 'tran_type_choices', 'contrib_id_choice',  'recur_choice', 'amount', 'first_name', 'last_name', 'layout_choice' ) );
     
     
     }else{
     	 $form->assign( 'elements', array( 'payment_processor_type_choices', 'start_date', 'end_date' , 'transaction_id',  'status_choices',
     	   'contrib_id_choice',  'recur_choice',  'amount', 'first_name', 'last_name' ) );
     
     }
     
     }
     
     

    function __destruct( ) {
        /*
        if ( $this->_eventID ) {
            $sql = "DROP TEMPORARY TABLE {$this->_tableName}";
            CRM_Core_DAO::executeQuery( $sql );
        }
        */
    }


/***********************************************************************************************/

    function setColumns( ) {
    /*  PayPal message table:
        `rp_invoice_id`, `notify_version`, `amount_per_cycle`, `payer_status`,
         `business`, `verify_sign`, 
        `initial_payment_amount`, `profile_status`, `payment_type`, `receiver_email`, `receiver_id`, `residence_country`,
         `receipt_id`, `transaction_subject`, `shipping`, `product_type`, `time_created`, `ipn_track_id`, 
         `civicrm_contribution_id`, `civicrm_recur_id`, `civicrm_processed`
    */
   
        $processor_type = $this->_formValues['payment_processor_type_choices']; 

       if( $processor_type == "paypal"){
       	  $this->_columns = array( 
				 ts('message_date') => 'message_date',        			
        			 ts('rec_type') => 'rec_type' ,
        			  ts('first_name') => 'first_name' ,
        			 ts('last_name') => 'last_name' ,
        			 ts('payer_email') => 'payer_email' ,
        			 ts('mc_gross') => 'mc_gross' ,
        			 ts('mc_fee') => 'mc_fee' ,
        			 ts('txn_id') => 'txn_id' ,
        			 ts('rp_invoice_id') => 'rp_invoice_id', 
        			  ts('Contrib. ID') => 'crm_contrib_id', 
        			 ts('Contact Name') => 'crm_contact_name', 
        			 ts('Contact ID') => 'contact_id',
        			 ts('Contribution Type') => 'contrib_type_name', 
        			 ts('Internal Recuring ID') => 'crm_recur_id',
        			 ts('Recurring Contrib. Financial Type') => 'recur_contrib_type_name', 
        			 ts('Recurring Contrib. Contact ID') => 'recur_contact_id',
        			 ts('Recurring Contrib. Contact Name') => 'recur_contact_name',  
        			 ts('recurring_payment_id') => 'recurring_payment_id' ,
        			 ts('amount') => 'amount' ,
        			  ts('payment_date') => 'payment_date' ,
        			 ts('payment_status') => 'payment_status' ,
        			  ts('txn_type') => 'txn_type' ,
        			 ts('period_type') => 'period_type' ,
        			 ts('payment_fee') => 'payment_fee' ,
        			 ts('payment_gross') => 'payment_gross' ,
        			 ts('currency_code') => 'currency_code' ,
        			  ts('mc_currency') => 'mc_currency' ,
        			 ts('outstanding_balance') => 'outstanding_balance' ,
        			 ts('next_payment_date') => 'next_payment_date' ,
        			 ts('protection_eligibility') => 'protection_eligibility' ,
        			   ts('payment_cycle') => 'payment_cycle' ,
        			 ts('tax') => 'tax' ,
        			 ts('payer_id') => 'payer_id' ,
        			 ts('product_name') => 'product_name' ,
        			   ts('charset') => 'charset' ,
        			 ); 
       
       }else if( $processor_type == "authorize.net" ){
    
        $tmp_array =  array( 
				  ts('message_date') => 'message_date',        			
        			 ts('x_first_name') => 'x_first_name' ,
        			 ts('x_last_name')  => 'x_last_name',
        			 ts('x_email')  => 'x_email', 
        			 ts('x_amount') => 'x_amount',
        			 ts('x_trans_id') => 'x_trans_id',
        			 ts('Contrib. ID') => 'crm_contrib_id', 
        			 ts('Contact Name') => 'crm_contact_name', 
        			 ts('Contact ID') => 'contact_id',
        			 ts('Contribution Type') => 'contrib_type_name', 
        			 ts('Internal Recuring ID') => 'crm_recur_id',
        			 ts('Recurring Contrib. Financial Type') => 'recur_contrib_type_name', 
        			 ts('Recurring Contrib. Contact ID') => 'recur_contact_id',
        			 ts('Recurring Contrib. Contact Name') => 'recur_contact_name',  
        			 ts('x_subscription_id') => 'x_subscription_id',
        			 ts('Installment Num.') => 'x_subscription_paynum',
        			 ts('x_response_code') =>'x_response_code',
        			 ts('x_response_reason_code') => 'x_response_reason_code',
        			 ts('x_response_reason_text') => 'x_response_reason_text',
        			 ts('x_avs_code') => 'x_avs_code',
        			 ts('x_auth_code') => 'x_auth_code', 
        			 ts('x_method') => 'x_method',
        			 ts('x_card_type') => 'x_card_type', 
        			 ts('x_account_number') => 'x_account_number',
        			 ts('x_company') => 'x_company',
        			 ts('x_address') => 'x_address', 
        			 ts('x_city') => 'x_city',
        			 ts('x_state') => 'x_state',
        			 ts('x_zip') => 'x_zip',
        			 ts('x_country') => 'x_country',
        			 ts('x_phone') => 'x_phone',
        			 ts('x_fax') => 'x_fax',
        			 ts('x_invoice_num') => 'x_invoice_num',
        			 ts('x_description') => 'x_description',
        			 ts('x_type') => 'x_type',
        			 ts('x_cust_id') => 'x_cust_id',
        			 ts('x_ship_to_first_name') => 'x_ship_to_first_name',
        			 ts('x_ship_to_last_name') => 'x_ship_to_last_name',
        			 ts('x_ship_to_company') => 'x_ship_to_company',
        			 ts('x_ship_to_address') => 'x_ship_to_address',
        			 ts('x_ship_to_city') => 'x_ship_to_city',
        			 ts('x_ship_to_state') => 'x_ship_to_state',
        			 ts('x_ship_to_zip') => 'x_ship_to_zip',
        			 ts('x_ship_to_country') => 'x_ship_to_country',
        			 
        			 ts('x_tax') => 'x_tax',
        			 ts('x_duty') => 'x_duty',
        			 ts('x_freight') => 'x_freight',
        			 ts('x_tax_exempt') => 'x_tax_exempt',
        			 ts('x_po_num') => 'x_po_num',
        			 ts('x_MD5_Hash') => 'x_MD5_Hash',
        			 ts('x_cvv2_resp_code') => 'x_cvv2_resp_code',
        			 ts('x_cavv_response') => 'x_cavv_response',
        			 ts('x_test_request') => 'x_test_request',
        			 
        			
        			 
        			 //ts('Contribution ID') => 'civicrm_contribution_id',
        			 ts('civicrm_recur_id') => 'civicrm_recur_id',
        			 );
        			
        			 
        			 
        			$tmp_array['Message Count'] = 'msg_count'; 
        			$tmp_array['Contrib. Count'] = 'contrib_count'; 
        			 
        			 
        	  $this->_columns = $tmp_array ; 		 
        			 
        	}else if( $processor_type == "eway" ){
       
    
        $this->_columns = array( 
				  ts('eWay Email Date') => 'adj_eway_email_date',  
				   ts('Transaction Type') => 'eway_trans_type', 
				  ts('eway_name') => 'eway_name',  
				  ts('eWay transaction Type') => 'eway_trans_type',      			
        			  ts('Contrib. ID') => 'crm_contrib_id', 
        			  ts('Financial Type') => 'contrib_type_name' , 
        			 ts('Contact Name') => 'crm_contact_name', 
        			 ts('Contact ID') => 'contact_id', 
        			   ts('Internal Recuring ID') => 'crm_recur_id',  
        			 ts('Recurring Contrib. Financial Type') => 'recur_contrib_type_name', 
        			 ts('Recurring Contrib. Contact ID') => 'recur_contact_id',
        			 ts('Recurring Contrib. Contact Name') => 'recur_contact_name',
        			  ts('eway_amount') => 'eway_amount', 
        			  ts('eway_transaction_id') => 'eway_transaction_id', 
        			  ts('eway_invoice_reference_number') => 'eway_invoice_reference_number', 
        			  ts('eway_email_subject') => 'eway_email_subject', 
        			  ts('eway_email_body') => 'eway_email_body', 
        			  
        			  ts('eway_address') => 'eway_address' , 
        			  ts('eway_currency') => 'eway_currency' ,
        			
        			   );
        			 
        	
        	}else if(  $processor_type == "iats" ){
        	
        	/*
        	  $selectClause = " msgs.transaction_id, msgs.invoice_id, msgs.trans_date, msgs.recur_id, msgs.payment_instrument_id,
           c.contact_id as contact_id, contact_a.sort_name as crm_contact_name, recur.id  as crm_recur_id ";
       
               */
        		  $this->_columns = array( 
				  ts('Date') => 'trans_date', 
				  ts('Transaction ID') => 'transaction_id',  
				  ts('Contact') => 'crm_contact_name',
				  ts('CRM recur id') => 'crm_recur_id',  
				  ts('Payment Instrument ID') => 'payment_instrument_id', 
				  );
        	}		
    }

    function all( $offset = 0, $rowcount = 0, $sort = null,
                  $includeContactIDs = false,  $onlyIDs = false ) {
                  
          // check authority of end-user
       $config = CRM_Core_Config::singleton();
	if ($config->userSystem->is_drupal){
	      // This is a Drupal install, check user authority. 
	        if( user_access('access CiviContribute') == false){	
	      		 
	       		return "select 'You are not authorized to this area' as total_amount from  civicrm_contact where 1=0 limit 1"; ; 
	       
	       }
	}

         if ( $onlyIDs ) {
       $selectClause  = "contact_a.id as contact_id, contact_a.id as id ";
       }else{
	
        $processor_type = $this->_formValues['payment_processor_type_choices']; 
        
      //  print "<br><br> processor_type: ".$processor_type; 
        
       if( $processor_type == "paypal"){
       	 $selectClause = "concat(msgs.last_name, ',' , msgs.first_name) as sort_name  , 
       	  `civicrm_recur_id` , c.id as crm_contrib_id, c.contact_id as contact_id, contact_a.sort_name as crm_contact_name,  
       	  recur.id as crm_recur_id, ct.name as contrib_type_name, recur_ct.name as recur_contrib_type_name, recur.contact_id as recur_contact_id, recur_contact.id as recur_contact_id,
        	recur_contact.sort_name as recur_contact_name, msgs.rp_invoice_id , 
       	  msgs.rec_type, msgs.message_date, msgs.mc_gross,
       	 msgs.mc_fee, msgs.txn_id, msgs.recurring_payment_id, msgs.amount, msgs.payment_date, msgs.payment_status, msgs.first_name, msgs.last_name, 
       	 msgs.payer_email, msgs.txn_type, msgs.period_type, msgs.payment_fee, msgs.payment_gross, msgs.currency_code, msgs.mc_currency, 
       	 msgs.outstanding_balance, msgs.next_payment_date, msgs.protection_eligibility, msgs.payment_cycle, msgs.tax, msgs.payer_id, 
       	 msgs.product_name, msgs.charset, msgs.rp_invoice_id, msgs.notify_version, msgs.amount_per_cycle, msgs.payer_status, msgs.business,
       	 msgs.verify_sign, msgs.initial_payment_amount, msgs.profile_status, msgs.payment_type, msgs.receiver_email , msgs.receiver_id,
       	 msgs.residence_country, msgs.receipt_id, msgs.transaction_subject, msgs.shipping, msgs.product_type, msgs.time_created, msgs.ipn_track_id,
       	 msgs.civicrm_contribution_id, msgs.civicrm_recur_id, msgs.civicrm_processed"; 
       
       }else if( $processor_type == "authorize.net" ){
          $tmp_layout_choice = $this->_formValues['layout_choice']; 
          if( $tmp_layout_choice == 'combine_possible_duplicates'){
          
          	 $selectClause = " concat(x_last_name, ',' , x_first_name) as sort_name ,   `civicrm_recur_id` , 
          	 group_concat(c.id) as crm_contrib_id , group_concat( distinct c.contact_id ) as contact_id, group_concat(distinct contact_a.sort_name) as crm_contact_name,  
        	recur.id as crm_recur_id, ct.name as contrib_type_name, recur_ct.name as recur_contrib_type_name,
        	 recur.contact_id as recur_contact_id, recur_contact.id as recur_contact_id,
        	recur_contact.sort_name as recur_contact_name, 
         `rec_type` ,   date(`message_date`)  as message_date ,  `x_response_code` ,  `x_response_reason_code` , count(*) as  `msg_count` , count(c.id) as contrib_count, 
          `x_avs_code` ,  `x_auth_code` ,   group_concat(`x_trans_id`) as x_trans_id ,  `x_method` ,  `x_card_type` ,  `x_account_number` , `x_first_name` ,  `x_last_name` ,  `x_company` ,  `x_address` ,  `x_city` ,  `x_state` ,  `x_zip` ,  `x_country` ,  `x_phone` ,  `x_fax` , group_concat( distinct(`x_email`)) as x_email ,  `x_invoice_num` ,  `x_description` ,  `x_type` ,  `x_cust_id` ,  `x_ship_to_first_name` ,  `x_ship_to_last_name` ,  `x_ship_to_company` , `x_ship_to_address` ,  `x_ship_to_city` ,  `x_ship_to_state` ,  `x_ship_to_zip` ,  `x_ship_to_country` ,  `x_amount` ,  `x_tax` ,  `x_duty` ,  `x_freight` ,  `x_tax_exempt` ,  `x_po_num` ,  `x_MD5_Hash` ,  `x_cvv2_resp_code` ,  `x_cavv_response` ,  `x_test_request` , `x_subscription_id` ,  `x_subscription_paynum`  "; 
          
          }else{
       	 $selectClause = " concat(x_last_name, ',' , x_first_name) as sort_name ,   `civicrm_recur_id` , c.id as crm_contrib_id, c.contact_id as contact_id, contact_a.sort_name as crm_contact_name,  
        	recur.id as crm_recur_id, ct.name as contrib_type_name, recur_ct.name as recur_contrib_type_name, recur.contact_id as recur_contact_id, recur_contact.id as recur_contact_id,
        	recur_contact.sort_name as recur_contact_name, 
         `rec_type` ,  `message_date` ,  `x_response_code` ,  `x_response_reason_code` ,  `x_response_reason_text` ,  `x_avs_code` ,  `x_auth_code` ,  `x_trans_id` ,  `x_method` ,  `x_card_type` ,  `x_account_number` , `x_first_name` ,  `x_last_name` ,  `x_company` ,  `x_address` ,  `x_city` ,  `x_state` ,  `x_zip` ,  `x_country` ,  `x_phone` ,  `x_fax` ,  `x_email` ,  `x_invoice_num` ,  `x_description` ,  `x_type` ,  `x_cust_id` ,  `x_ship_to_first_name` ,  `x_ship_to_last_name` ,  `x_ship_to_company` , `x_ship_to_address` ,  `x_ship_to_city` ,  `x_ship_to_state` ,  `x_ship_to_zip` ,  `x_ship_to_country` ,  `x_amount` ,  `x_tax` ,  `x_duty` ,  `x_freight` ,  `x_tax_exempt` ,  `x_po_num` ,  `x_MD5_Hash` ,  `x_cvv2_resp_code` ,  `x_cavv_response` ,  `x_test_request` , `x_subscription_id` ,  `x_subscription_paynum`  "; 
         
         }
       }else if( $processor_type == "eway"){
       
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
				
       
       		$selectClause = " `rec_type`, DATE_ADD( `eway_email_date`,  INTERVAL ".$hours_to_add." ) as adj_eway_email_date,  `eway_currency`, `eway_amount`, `eway_transaction_id`, `eway_name`, eway_trans_type, `eway_address`,  `eway_invoice_reference_number`, `eway_email_subject`, `eway_email_body`, c.id as crm_contrib_id, c.contact_id as contact_id, contact_a.sort_name as crm_contact_name, ct.name as contrib_type_name, recur.id  as crm_recur_id, recur_ct.name as recur_contrib_type_name, recur.contact_id as recur_contact_id, recur_contact.id as recur_contact_id,
        	recur_contact.sort_name as recur_contact_name ";
       
       }else if(  $processor_type == "iats" ){
           $selectClause = " msgs.transaction_id, msgs.invoice_id, msgs.trans_date, msgs.recur_id, msgs.payment_instrument_id,
           c.contact_id as contact_id, contact_a.sort_name as crm_contact_name ";
       
       }else{
       
       
       }
       
       /*
       
       // PayPal: 
       SELECT `id`, `rec_type`, `message_date`, `mc_gross`, `mc_fee`, `txn_id`, `recurring_payment_id`, `amount`, `payment_date`, `payment_status`, `first_name`, `last_name`, `payer_email`, `txn_type`, `period_type`, `payment_fee`, `payment_gross`, `currency_code`, `mc_currency`, `outstanding_balance`, `next_payment_date`, `protection_eligibility`, `payment_cycle`, `tax`, `payer_id`, `product_name`, `charset`, `rp_invoice_id`, `notify_version`, `amount_per_cycle`, `payer_status`, `business`, `verify_sign`, `initial_payment_amount`, `profile_status`, `payment_type`, `receiver_email`, `receiver_id`, `residence_country`, `receipt_id`, `transaction_subject`, `shipping`, `product_type`, `time_created`, `ipn_track_id`, `civicrm_contribution_id`, `civicrm_recur_id`, `civicrm_processed` FROM `pogstone_paypal_messages` WHERE 1
       */
       
       

        
        }
          if( $processor_type == 'paypal' ){
          	$groupBy = " GROUP by msgs.ipn_track_id ";
          
          }else if( $processor_type == 'authorize.net' ){
          	// 'layout_choice'
          	$tmp_layout_choice = $this->_formValues['layout_choice']; 
          	if( $tmp_layout_choice == 'combine_possible_duplicates'){
          		$groupBy = " GROUP by x_first_name, x_last_name, x_amount, DATE( message_date)  ,  x_response_code , x_description
          		HAVING count(*) > 1";
          	
          	}
          	
          
          
          }
        
        if( strlen( $sort) == 0){
           $sort = "1";
        }
        // for PayPal: need to add:  GROUP by msgs.ipn_track_id
       $tmp_full_sql =  $this->sql( $selectClause,
                           $offset, $rowcount, $sort,
                           $includeContactIDs, $groupBy );
                           
    //print "<br><br><br>full sql: ". $tmp_full_sql;    	
                           
         return $tmp_full_sql; 
         
    
    /*************************************************************************/
         
   

    }
    


    
    function from( ) {
    
    	 $processor_type = $this->_formValues['payment_processor_type_choices']; 
    
   
       if( $processor_type == 'eway' ){
       		

	
	$tmp_from = " FROM pogstone_eway_messages as msgs LEFT JOIN civicrm_contribution c ON msgs.eway_transaction_id = c.trxn_id 
	  LEFT JOIN civicrm_contribution_recur recur ON recur.processor_id = msgs.eway_invoice_reference_number
	   LEFT JOIN civicrm_financial_type recur_ct ON recur.financial_type_id = recur_ct.id
	  LEFT JOIN civicrm_contact recur_contact ON recur.contact_id = recur_contact.id 
	  LEFT JOIN civicrm_contact contact_a ON c.contact_id = contact_a.id
	  LEFT JOIN civicrm_financial_type ct ON c.financial_type_id = ct.id";
	
	
	
       
       }else if( $processor_type == 'paypal' ){
       	

	
	$tmp_from = " FROM pogstone_paypal_messages as msgs LEFT JOIN civicrm_contribution c ON msgs.txn_id = c.trxn_id 
	  LEFT JOIN civicrm_contact contact_a ON c.contact_id = contact_a.id
	  LEFT JOIN civicrm_contribution_recur recur ON recur.processor_id = msgs.recurring_payment_id
	  LEFT JOIN civicrm_financial_type recur_ct ON recur.financial_type_id = recur_ct.id
	  LEFT JOIN civicrm_contact recur_contact ON recur.contact_id = recur_contact.id
	  LEFT JOIN civicrm_financial_type ct ON c.financial_type_id = ct.id";
	
	
       
       }else if( $processor_type == 'authorize.net'){
    	
		$tmp_from = " FROM pogstone_authnet_messages as msgs LEFT JOIN civicrm_contribution c ON msgs.x_trans_id = c.trxn_id 
        	  LEFT JOIN civicrm_contact contact_a ON c.contact_id = contact_a.id
        	  LEFT JOIN civicrm_contribution_recur recur ON recur.processor_id = msgs.x_subscription_id
        	  LEFT JOIN civicrm_financial_type recur_ct ON recur.financial_type_id = recur_ct.id
        	  LEFT JOIN civicrm_contact recur_contact ON recur.contact_id = recur_contact.id
        	  LEFT JOIN civicrm_financial_type ct ON c.financial_type_id = ct.id";
	
	
    	}else if(  $processor_type == 'iats' ){
    		$tmp_from = " FROM pogstone_iats_messages as msgs
    		 LEFT JOIN civicrm_contribution c ON msgs.transaction_id = c.trxn_id
    		 LEFT JOIN civicrm_contact contact_a ON c.contact_id = contact_a.id " ; 
    	
    	}
        return $tmp_from;



    }

    function where( $includeContactIDs = false ) {
       // print "<hr><br>Inside where function.";
       $clauses = array();
       $tmp_where = '';
       
       $processor_type = $this->_formValues['payment_processor_type_choices']; 
       
       
       if( $processor_type == 'eway' ){
       
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
       
      	  $startDate = CRM_Utils_Date::processDate( $this->_formValues['start_date'] );
     if ( $startDate ) {
        $clauses[] = " date(DATE_ADD( msgs.eway_email_date,  INTERVAL ".$hours_to_add." ) ) >= date($startDate)";
     }

     $endDate = CRM_Utils_Date::processDate( $this->_formValues['end_date'] );
     if ( $endDate ) {
         $clauses[]  = " date(DATE_ADD( msgs.eway_email_date,  INTERVAL ".$hours_to_add." ) ) <= date($endDate)";
     }
      
      
       }else if( $processor_type == 'iats' ){
       	 $startDate = CRM_Utils_Date::processDate( $this->_formValues['start_date'] );
	     if ( $startDate ) {
	        $clauses[] = " date(msgs.trans_date) >= date($startDate)";
	     }
	
	     $endDate = CRM_Utils_Date::processDate( $this->_formValues['end_date'] );
	     if ( $endDate ) {
	         $clauses[]  = " date(msgs.trans_date) <= date($endDate)";
	     }
	       
       
       
       }else{
      
      
      $startDate = CRM_Utils_Date::processDate( $this->_formValues['start_date'] );
     if ( $startDate ) {
        $clauses[] = " date(msgs.message_date) >= date($startDate)";
     }

     $endDate = CRM_Utils_Date::processDate( $this->_formValues['end_date'] );
     if ( $endDate ) {
         $clauses[]  = " date(msgs.message_date) <= date($endDate)";
     }
     
     
     }
     $status_choices =  $this->_formValues['status_choices'];
     
      if( $processor_type == 'eway' ){
      		     if( $status_choices == 'not_1' ){
	     		$clauses[] = " msgs.eway_amount = 0 ";
	     
	     }else if($status_choices == 'only_1' ){
	     		$clauses[] = " msgs.eway_amount > 0 ";
	     }
      
      }else if( $processor_type == 'paypal' ){
      	  if( $status_choices == 'not_1' ){
	     		$clauses[] = " msgs.payment_status != 'Completed' ";
	     
	     }else if($status_choices == 'only_1' ){
	     		$clauses[] = " msgs.payment_status = 'Completed' ";
	     }
      
      }else{
      $tmp_layout_choice = $this->_formValues['layout_choice']; 
       if( $tmp_layout_choice == 'combine_possible_duplicates'){
       		$clauses[] = " msgs.x_response_code = '1' ";
       }
	     if( $status_choices == 'not_1' ){
	     		$clauses[] = " msgs.x_response_code <> '1' ";
	     
	     }else if($status_choices == 'only_1' ){
	     		$clauses[] = " msgs.x_response_code = '1' ";
	     }
     
     }
     
     $tran_type_choices =  $this->_formValues['tran_type_choices'];
	if( $processor_type == 'eway' ){
	
	
	
	
	}else{	
	     
	     if( $tran_type_choices == 'credit' ){
	     		$clauses[] = " msgs.x_type = 'credit' ";
	     
	     }else if($tran_type_choices == 'auth_capture' ){
	     		$clauses[] = " msgs.x_type = 'auth_capture' ";
	     }
	     
	      $tmp_layout_choice = $this->_formValues['layout_choice']; 
       		if( $tmp_layout_choice == 'combine_possible_duplicates'){
       			$clauses[] = " msgs.x_type = 'auth_capture' ";
       			$clauses[] = " length(msgs.x_subscription_id) = 0 "; 
       		}
     
     }
     
     
          
     $recur_choice = $this->_formValues['recur_choice'];
     if( $processor_type == 'eway' ){
	   if( $recur_choice == 'recur_only'){
     		$clauses[] = " msgs.eway_invoice_reference_number LIKE '%(r)' "; 
     
	     }else if($recur_choice == 'nonrecur_only'){
	     		$clauses[] = " msgs.eway_invoice_reference_number NOT LIKE '%(r)' "; 
	     }
	
	
	
	}else if($processor_type == 'paypal'  ){
		if( $recur_choice == 'recur_only'){
			$clauses[] = " length(msgs.recurring_payment_id) > 0 "; 
		}else if($recur_choice == 'nonrecur_only'){
			$clauses[] = " length(msgs.recurring_payment_id) = 0 "; 
		}
	
	
	}else{	
     if( $recur_choice == 'recur_only'){
     		$clauses[] = " length(msgs.x_subscription_id) > 0 "; 
     
     }else if($recur_choice == 'nonrecur_only'){
     		$clauses[] = " length(msgs.x_subscription_id) = 0 "; 
     }
     
     }
     
     $transaction_id = $this->_formValues['transaction_id'];
      if( $processor_type == 'eway' ){
      	 if( strlen($transaction_id) > 0 ){
	     		$clauses[] = " msgs.eway_transaction_id = '".$transaction_id."'";
	     }
      }else if( $processor_type == 'paypal' ){
      	 if( strlen($transaction_id) > 0 ){
	     		$clauses[] = " msgs.txn_id = '".$transaction_id."'";
	     }
      
      }else{
	     if( strlen($transaction_id) > 0 ){
	     		$clauses[] = " msgs.x_trans_id = '".$transaction_id."'";
	     }
     
     }
     

     $subscription_id = $this->_formValues['subscription_id'];
       if( $processor_type == 'eway' ){
       
       }else{
	     if( strlen($subscription_id) > 0 ){
	     		$clauses[] = " msgs.x_subscription_id = '".$subscription_id."'";
	     }
	}     
     
     $amount = $this->_formValues['amount'];
      if( $processor_type == 'eway' ){
      		 if( strlen($amount) > 0 ){
     		$clauses[] = " msgs.eway_amount = ".$amount ;
     }
      
      }else if( $processor_type == 'paypal' ){
      	if( strlen($amount) > 0 ){
     		$clauses[] = " msgs.amount = ".$amount ;
     		
     		}
       
      	}else{
	     if( strlen($amount) > 0 ){
	     		$clauses[] = " msgs.x_amount = ".$amount ;
	     }
     
     }
     
     $tmp_first_name =  $this->_formValues['first_name'];
     if(strlen($tmp_first_name) > 0 ){
     	$clauses[] = " contact_a.first_name like '%".$tmp_first_name."%' "; 
     
     }
     
     $tmp_last_name =  $this->_formValues['last_name'];
     if(strlen($tmp_last_name) > 0 ){
     	$clauses[] = " contact_a.last_name like '%".$tmp_last_name."%' "; 
     
     }
     // contact_a
     
	$contrib_id_choice = $this->_formValues['contrib_id_choice'];
	if( $contrib_id_choice == 'has_contrib_id'){
		$clauses[] = "c.id IS NOT NULL"; 
	}else if($contrib_id_choice == 'missing_contrib_id'){
		$clauses[] = "c.id IS NULL";
	}
	
   // c.id
     
 
       
        if(count($clauses) > 0){
       		 $partial_where_clause = implode( ' AND ', $clauses );
       		 $tmp_where = $partial_where_clause; 
       
       
       }else{
       	   $tmp_where = "";
       }

  //     print "<Hr><br>About to return where clause: ".$tmp_where; 
       return $tmp_where;
    }

    function templateFile( ) {
        return 'CRM/Contact/Form/Search/Custom.tpl';
    }

    function setDefaultValues( ) {
        return array( );
    } 

    function alterRow( &$row ) {
		// URL to view contrib. detail: /civicrm/contact/view/contribution?reset=1&id=19617&cid=1297&action=view&context=contribution&selectedChild=contribute
		// display hyperlink if contribution id is available. 
		 $row['crm_contrib_id'] = "<a href='/civicrm/contact/view/contribution?reset=1&id=".$row['crm_contrib_id']."&cid=".$row['contact_id']."&action=view&context=contribution&selectedChild=contribute'>".$row['crm_contrib_id']."</a>";
		 
   
		$row['crm_contact_name'] = "<a href='/civicrm/contact/view?reset=1&force=1&cid=".$row['contact_id']."&selectedChild=contribute'>".$row['crm_contact_name']."</a>";
	
    		$row['crm_recur_id']  = "<a href='/civicrm/contact/view/contributionrecur?reset=1&id=".$row['crm_recur_id']."&cid=".$row['recur_contact_id']."&context=contribution'>".$row['crm_recur_id']."</a>";
    
   // /civicrm/contact/view/contributionrecur?reset=1&id=338&cid=742&context=contribution
    }

    /* 
     * Functions below generally don't need to be modified
     */
    function count( ) {
           $sql = $this->all( );
           
           $dao = CRM_Core_DAO::executeQuery( $sql,
                                             CRM_Core_DAO::$_nullArray );
           return $dao->N;
    }
       
    function contactIDs( $offset = 0, $rowcount = 0, $sort = null) { 
        return $this->all( $offset, $rowcount, $sort, false, true );
    }
       
    function &columns( ) {
        return $this->_columns;
    }

   function setTitle( $title ) {
       if ( $title ) {
           CRM_Utils_System::setTitle( $title );
       } else {
           CRM_Utils_System::setTitle(ts('Search'));
       }
   }

   function summary() {
       return null;
   }

}
