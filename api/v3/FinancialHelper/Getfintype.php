<?php

/**
 * FinancialHelper.Getfintype API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRM/API+Architecture+Standards
 */
function _civicrm_api3_financial_helper_getfintype_spec(&$spec) {
  $spec['financial_type_name']['api.required'] = 1;
}

/**
 * FinancialHelper.Getfintype API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_financial_helper_getfintype($params) {
  if (array_key_exists('financial_type_name', $params) ) {
  
   $ft_name =   $params['financial_type_name'] ;
   
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
       		
   
   
    // ALTERNATIVE: $returnValues = array(); // OK, success
      $returnValues = array($id_tmp); // OK, return a single value

    // Spec: civicrm_api3_create_success($values = 1, $params = array(), $entity = NULL, $action = NULL)
    return civicrm_api3_create_success($returnValues, $params, 'NewEntity', 'NewAction');
  } else {
    throw new API_Exception(/*errorMessage*/ 'Everyone knows that the magicword is "sesame"', /*errorCode*/ 1234);
  }
}