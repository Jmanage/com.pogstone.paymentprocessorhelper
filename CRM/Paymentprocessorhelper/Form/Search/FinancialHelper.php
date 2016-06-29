<?php

  class FinancialHelper{
  
  function create_financial_type_and_account(&$ft_name, &$ft_description, &$ft_accounting_code, &$ft_is_deductible_parm  ){
	
	
	}
	
	
	function getFinancialTypeId($ft_name){
    	
        $id_tmp = ""; 	
        	
        	
        $ft_name_formatted_for_sql = str_replace("'", "''", $ft_name); 
       $sql = "select id from civicrm_financial_type 
       		where name = '".$ft_name_formatted_for_sql."' " ;
       		
	$empty_arr = array();

	$dao = CRM_Core_DAO::executeQuery( $sql, $empty_arr );
	
	if( $dao->fetch() ){
		$id_tmp = $dao->id ; 
	
	}
	
	$dao->free();
       		
    	return $id_tmp ;
    
    }
    
  
  
  
  
  
  }




?>