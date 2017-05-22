<?php

/**
 * FinancialHelper.Createfintype API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRM/API+Architecture+Standards
 */
function _civicrm_api3_financial_helper_createfintype_spec(&$spec) {
  $spec['financial_type_name']['api.required'] = 1;
}

/**
 * FinancialHelper.Createfintype API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_financial_helper_createfintype($params) {
  if (array_key_exists('financial_type_name', $params)) {

    // ALTERNATIVE: $returnValues = array(); // OK, success

    $ft_name = $params['financial_type_name'];
    $tmp_rtn = createFinTypeAndAccount($ft_name);

    if ($tmp_rtn) {
      $returnValues = array("everything is cool"); // OK, return a single value
      // Spec: civicrm_api3_create_success($values = 1, $params = array(), $entity = NULL, $action = NULL)
      return civicrm_api3_create_success($returnValues, $params, 'FinancialHelper', 'createfintype');
    }
    else {
      throw new API_Exception(/* errorMessage */ 'call to createFinTypeAndAccount failed', /* errorCode */ 1234);
    }
  }
  else {
    throw new API_Exception(/* errorMessage */ 'financial_type_name is required', /* errorCode */ 1234);
  }
}

function createFinTypeAndAccount(&$ft_name) {

  if (strlen($ft_name) == 0) {
    return FALSE;
  }

  // Lookup contact_id of primary organization for this CiviCRM install.
  $params = array(
    'version' => 3,
    'sequential' => 1,
  );
  $result = civicrm_api('Domain', 'getsingle', $params);

  $contact_id = $result['contact_id'];

  $ft_is_deductible = $ft_is_deductible_parm;  // 0 = non-deductible tax status.
  if (strlen($ft_is_deductible) == 0) {
    $ft_is_deductible = "0";
  }
  $financial_account_type_id = "3";  // CiviCRM ID for this account type.
  $account_type_code = "INC";

  $params = array(
    'version' => 3,
    'sequential' => 1,
    'financial_account_name' => $ft_name,
  );
  $result = civicrm_api('FinancialHelper', 'getfinaccount', $params);
  //print "<br>Just called get fin account: ";
  //print_r( $result);
  //$fa_id = $this->getFinancialAccountId($ft_name, $contact_id) ;
  $fa_id = $result['values'][0];

  $ft_name_formatted_for_sql = str_replace("'", "''", $ft_name);
  $ft_description_formatted_for_sql = str_replace("'", "''", $ft_description);

  if (strlen($fa_id) == 0) {

    $sql = "INSERT INTO civicrm_financial_account (
	name , contact_id, financial_account_type_id, description, accounting_code, account_type_code, is_deductible,  is_active )
	VALUES ( '$ft_name_formatted_for_sql', $contact_id, $financial_account_type_id, '$ft_description_formatted_for_sql',  '$ft_accounting_code',
	'$account_type_code', '$ft_is_deductible' , '1' ) ";

    //print "<br>financial account insert sql: ".$sql."<br>";
    $params = array();
    $dao = CRM_Core_DAO::executeQuery($sql, $params);
    $dao->free();

    $params = array(
      'version' => 3,
      'sequential' => 1,
      'financial_account_name' => $ft_name,
    );
    $result = civicrm_api('FinancialHelper', 'getfinaccount', $params);
    $fa_id = $result['values'][0];

    $expense_label = "Banking Fees";
    $params = array(
      'version' => 3,
      'sequential' => 1,
      'financial_account_name' => $expense_label,
    );
    $result = civicrm_api('FinancialHelper', 'getfinaccount', $params);

    $financial_account_id_expense = $result['values'][0];

    $ar_label = "Accounts Receivable";
    $params = array(
      'version' => 3,
      'sequential' => 1,
      'financial_account_name' => $ar_label,
    );
    $result = civicrm_api('FinancialHelper', 'getfinaccount', $params);
    $financial_account_id_accounts_receivable = $result['values'][0];

    $premium_label = "Premiums";
    $params = array(
      'version' => 3,
      'sequential' => 1,
      'financial_account_name' => $premium_label,
    );
    $result = civicrm_api('FinancialHelper', 'getfinaccount', $params);

    $financial_account_id_premium = $result['values'][0];
    ;
  }

  print "<br>We should have financial account id by now: " . $fa_id;
  if (strlen($fa_id) == 0) {
    print "<Br>ERROR: Could not find or create the financial account.";
    return FALSE;
  }
  // We should have the financial account id by now. So create the financial type.
  $sql = "INSERT INTO civicrm_financial_type (
			name  , description , is_deductible , is_reserved ,is_active )
			VALUES ( '$ft_name_formatted_for_sql', '$ft_description_formatted_for_sql', '$ft_is_deductible', NULL , '1' ) ";

  print "<br> financial type insert sql: " . $sql . "<br>";
  $params = array();
  $dao = CRM_Core_DAO::executeQuery($sql, $params);
  $dao->free();

  //$financial_type_id = $this->getFinancialTypeId($ft_name);
  $params = array(
    'version' => 3,
    'sequential' => 1,
    'financial_type_name' => $ft_name,
  );
  $result = civicrm_api('FinancialHelper', 'getfintype', $params);
  //print "<Br>get fin type id: ";

  $financial_type_id = $result['values'][0];

  if (strlen($financial_type_id) == 0) {
    print "<Br>ERROR: Could not find id for financial type: " . $ft_name;
    return FALSE;
  }

  // Now create the 3 needed relationships connecting financial type to the financial accounts.
  $financial_relationship_sql_1 = "INSERT INTO civicrm_entity_financial_account (
		 	entity_table, entity_id, account_relationship, financial_account_id
		 ) VALUES ( 'civicrm_financial_type', $financial_type_id, 1,   $fa_id   ) ";
  $params = array();

  // print "<br>SQL part 1: ".$financial_relationship_sql_1;
  $dao = CRM_Core_DAO::executeQuery($financial_relationship_sql_1, $params);
  $dao->free();

  $financial_relationship_sql_2 = "INSERT INTO civicrm_entity_financial_account (
		 	entity_table, entity_id, account_relationship, financial_account_id
		 ) VALUES ( 'civicrm_financial_type', $financial_type_id, 5,  $financial_account_id_expense   ) ";
  $params = array();
  // print "<br>SQL part 2: ".$financial_relationship_sql_2;
  $dao = CRM_Core_DAO::executeQuery($financial_relationship_sql_2, $params);
  $dao->free();

  $financial_relationship_sql_3 = "INSERT INTO civicrm_entity_financial_account (
		 	entity_table, entity_id, account_relationship, financial_account_id
		 ) VALUES ( 'civicrm_financial_type', $financial_type_id, 3,   $financial_account_id_accounts_receivable  ) ";
  $params = array();
  // print "<br>SQL part 3: ".$financial_relationship_sql_3;
  $dao = CRM_Core_DAO::executeQuery($financial_relationship_sql_3, $params);
  $dao->free();

  // Handle 7  ie Premium account.
  $financial_relationship_sql_4 = "INSERT INTO civicrm_entity_financial_account (
		 	entity_table, entity_id, account_relationship, financial_account_id
		 ) VALUES ( 'civicrm_financial_type', $financial_type_id, 7,   $financial_account_id_premium ) ";
  $params = array();

  $dao = CRM_Core_DAO::executeQuery($financial_relationship_sql_4, $params);
  $dao->free();

  return TRUE;
}
