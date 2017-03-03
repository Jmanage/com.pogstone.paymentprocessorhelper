<?php

/**
 * ProccessorMessage.Simulateauthnetipn API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 *
 * If a $params['contribution_id'] is present, then we will send an IPN for that
 * contribution. Otherwise, a new contribution is created and an IPN will be sent for it.
 * FIXME: This test hardcodes a few things specific to a dev environment.
 * It also assumes Drupal (see the getUrl call).
 */
function civicrm_api3_proccessor_message_simulateauthnetipn($params) {
  $returnValues = array('ok' => 'Now run ProccessorMessage.Processnewmessages');

  $contrib = $recur = NULL;

  if (empty($params['contribution_id'])) {
    $amount = '20' + rand(0,50);
    $now = date('Y-m-d H:i:s');

    $recur = civicrm_api3('ContributionRecur', 'create', [
      'contact_id' => 1029, // FIXME
      'amount' => $amount,
      'currency' => 'USD',
      'frequency_unit' => 'month',
      'frequency_interval' => 1,
      'installments' => 3,
      'start_date' => $now,
      'create_date' => $now,
      'processor_id' => '99' . date('Ymdhis'),
      'contribution_status_id' => 5, // FIXME?
      'payment_processor_id' => 31, // FIXME
      'financial_type_id' => 2,
      'payment_instrument_id' => 1,
      'is_email_receipt' => 1,
      'contribution_type_id' => 2,
    ]);

    $contrib = civicrm_api3('Contribution', 'create', [
      'contact_id' => 1029, // FIXME: hardcoded to Mathieu's contact record
      'currency' => 'USD',
      'total_amount' => $amount,
      'contribution_source' => 'Automated Authnet Test',
      'contribution_status_id' => 2, // pending
      'financial_type_id' => 2,
      'financial_account_id' => 2,
      'contribution_recur_id' => $recur['id'],
    ]);

    $params['contribution_id'] = $contrib['id'];
  }

  $contrib = civicrm_api3('Contribution', 'getsingle', [
    'id' => $params['contribution_id'],
  ]);
  $recur = civicrm_api3('ContributionRecur', 'getsingle', [
    'id' => $contrib['contribution_recur_id'],
  ]);

  $url = Civi::paths()->getUrl('sites/all/modules/civicrm/extern/authorizeIPN.php', 'absolute', TRUE);

  $post = [
    'x_response_code' => 1,
    'x_response_reason_code' => 1,
    'x_response_reason_text' => "This transaction has been approved. (PP simulator)",
    'x_avs_code' => 'Y',
    'x_auth_code' => 'D9Y6TD',
    'x_trans_id' => '999' . date('Ymdhis'),
    'x_method' => 'CC',
    'x_card_type' => 'Visa',
    'x_account_number' => 'XXXX0027',
    'x_first_name' => 'Sample',
    'x_last_name' => 'Member',
    'x_company' => '',
    'x_address' => '"123 Pleasant Way"',
    'x_city' => 'Chicago',
    'x_state' => 'IL',
    'x_zip' => '60606',
    'x_country' => 'US',
    'x_phone' => '',
    'x_fax' => '',
    'x_email' => '',
    'x_invoice_num' => '126',
    'x_description' => '',
    'x_type' => 'auth_capture',
    'x_cust_id' => '',
    'x_amount' => $contrib['total_amount'],
    'x_tax_exempt' => 'FALSE',
    'x_MD5_Hash' => '3DF686F103827BB5EFEC7670AE2D634A',
    'x_cvv2_resp_code' => '',
    'x_cavv_response' => '2',
    'x_test_request' => 'false',
    'x_subscription_id' => $recur['processor_id'],
    'x_subscription_paynum' => '1',
  ];

  $http = CRM_Utils_HttpClient::singleton();
  $result = $http->post($url, $post);

  drush_log(print_r($url, 1), 'ok');
  drush_log(print_r($result, 1), 'ok');

  return civicrm_api3_create_success($returnValues, $params, 'ProcessorMessage', 'simulateauthnetipn');
}
