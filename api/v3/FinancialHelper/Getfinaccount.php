<?php

/**
 * FinancialHelper.Getfinaccount API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRM/API+Architecture+Standards
 */
function _civicrm_api3_financial_helper_getfinaccount_spec(&$spec) {
  $spec['financial_account_name']['api.required'] = 1;
}

/**
 * FinancialHelper.Getfinaccount API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_financial_helper_getfinaccount($params) {
  if (array_key_exists('financial_account_name', $params)) {

    $ft_name = $params['financial_account_name'];

    // Lookup contact_id of primary organization for this CiviCRM install.
    $params = array(
      'version' => 3,
      'sequential' => 1,
    );
    $result_cid = civicrm_api('Domain', 'getsingle', $params);

    $contact_id = $result_cid['contact_id'];

    $ft_is_deductible = "0";  // Default to non-deductible tax status.
    // $financial_account_type_id = "3";
    // $account_type_code = "INC";

    $id_tmp = "";
    $ft_name = str_replace("'", "''", $ft_name);
    $sql = "select id from civicrm_financial_account
       		where name = '" . $ft_name . "' AND contact_id = '" . $contact_id . "' ";

    $empty_arr = array();

    $dao = CRM_Core_DAO::executeQuery($sql, $empty_arr);

    if ($dao->fetch()) {
      $id_tmp = $dao->id;
    }

    $dao->free();

    // ALTERNATIVE: $returnValues = array(); // OK, success
    $returnValues = array($id_tmp); // OK, return a single value
    // Spec: civicrm_api3_create_success($values = 1, $params = array(), $entity = NULL, $action = NULL)
    return civicrm_api3_create_success($returnValues, $params, 'NewEntity', 'NewAction');
  }
  else {
    throw new API_Exception(/* errorMessage */ 'Everyone knows financial_account_name is required', /* errorCode */ 1234);
  }
}
