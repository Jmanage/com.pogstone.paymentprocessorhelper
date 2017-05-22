<?php

/**
 * PaymentProcessorTypeHelper.Get API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRM/API+Architecture+Standards
 */
function _civicrm_api3_payment_processor_type_helper_get_spec(&$spec) {
  $spec['vendor_type']['api.required'] = 1;
}

/**
 * PaymentProcessorTypeHelper.Get API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_payment_processor_type_helper_get($params) {

  if (array_key_exists('vendor_type', $params)) {

    $params_pp = array(
      'version' => 3,
      'sequential' => 1,
      'is_active' => 1,
      'is_test' => 0,
    );
    $result = civicrm_api('PaymentProcessor', 'get', $params_pp);

    $tmp_values = $result['values'];

    $found_match = "false";
    $wanted_vendor_type = $params['vendor_type'];
    foreach ($tmp_values as $cur) {
      $cur_type_id = $cur['payment_processor_type_id'];
      $cur_pp_user = $cur['user_name'];
      if (strlen($cur_pp_user) > 1) {
        $params_type = array(
          'version' => 3,
          'sequential' => 1,
          'id' => $cur_type_id,
        );
        $result_type = civicrm_api('PaymentProcessorType', 'getsingle', $params_type);

        $tmp_name = $result_type['name'];
        if ($tmp_name == $wanted_vendor_type) {

          $found_match = "true";
        }
      }
    }

    $returnValues = array(// OK, return several data rows
      $wanted_vendor_type => array('id' => $wanted_vendor_type, 'name' => $found_match),
    );
    // ALTERNATIVE: $returnValues = array(); // OK, success
    // ALTERNATIVE: $returnValues = array("Some value"); // OK, return a single value
    // Spec: civicrm_api3_create_success($values = 1, $params = array(), $entity = NULL, $action = NULL)
    return civicrm_api3_create_success($returnValues, $params, 'PaymentProcessorTypeHelper', 'get');
  }
  else {
    throw new API_Exception(/* errorMessage */ 'vendor_type is a required parm', /* errorCode */ 1234);
  }
}
