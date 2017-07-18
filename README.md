# com.pogstone.paymentprocessorhelper

The basic structure of this CiviCRM native extension:

1. Intercept all messages/notifications/data from a payment processor and
immediately insert it into a "messages" database table, without attempting to
make sense of it and/or create a contribution, etc. This bypasses the core
logic of trying to create a contribution immediately when an IPN notification
is received.

2. This extension creates a new scheduled job named "Process data from payment
processors" that is, by default, set to run every hour. This job queries the
"messages" database table mentioned above, looking for data that has not been
processed yet. For any new data that represents a successful transaction, it
will create a new contribution. This includes handling multiple line items,
custom data, campaign ID, etc., so that all the new contributions match the
first contribution in the recurring schedule. It also handles some minor
housekeeping like marking the first "pending" contribution as cancelled if the
user cancels the subscription before the first installment.

This extension also provides a custom search, "Payment Processor Messages",
which facilitates examination of the “messages” database table.

## Supported payment processors

* PayPal Pro (CRM_Core_Payment_PayPalProIPN)

* Authorize.net (CRM_Core_Payment_AuthorizeNetIPN)

## Implementation details

### PHP file overrides

The following CiviCRM core files are overridden by this extension. **Care must
be taken during CiviCRM upgrades to update these files accordingly.**

* CRM/Core/Payment/PayPalProIPN.php

* CRM/Core/Payment/AuthorizeNetIPN.php

The general design of these overridden PHP files is to leave all code in place,
with the exception of altering the main() method such that it calls a custom
method pogstone_log_details() and then returns TRUE without any further
processing. See inline documentation in the main() and pogstone_log_details()
methods, and see **CRM_Core_Payment_AuthorizeNetIPN::pogstone_log_details()**
and **CRM_Core_Payment_PayPalProIPN::pogstone_log_details()** elsewhere in this
README.

## APIs

This extension provides the following APIs:

### Financial_helper

Utility class providing helper methods for Financial Types.

#### createfintype

Create a financial type with a given name. Probably redundant to
FinancialAccount. Probably redundant do FinancialType.create().

#### getfinaccount

Get the ID of a financial account with a given name. Probably redundant to
FinancialAccount.get().

#### getfintype

Get the ID of a financial type with a given name. Probably redundant to
FinancialType.get().

### PaymentProcessorTypeHelper

#### get

Checks whether payment processors of a given vendor type (e.g., 'PayPal',
'Authorize.net') exist. Probably redundant to Paymentprocessor.get().

### ProccessorMessage

#### Processnewmessages

Search message tables for unprocessed messages and process them. See "Business
rules: Periodic message processing".

## Business rules

### Incoming messages (PayPal's IPN and Authorize.net's "Silent Post")

1. All messages are intercepted. Messages are logged for later processing and
no other action is taken.

### Periodic message processing

1. Messages are processed on a schedule by the "Process data from payment
processors" scheduled job, which searches message tables for unprocessed
messages and then processes them according to these rules.

2. Check whether any payment processors of type "PayPal" or "Authorize.net"
exist, and if so, be sure to check the relevant message tables.

3. Perform a **first pass** over the relevant message table to check for
relevant messages representing **a payment in a Recurring Contribution series**:

  **pogstone_paypal_messages**

  Relevant messages meet these criteria:

  1. Payment status (as received via IPN) is 'Completed';
  2. Recurring payment ID (as received via IPN) is not an empty string;
  3. Transaction ID (as received via IPN) is not an empty string;
  4. Transaction ID (as received via IPN) does not match the Transaction
ID of any existing Contribution record;
  5. The message_date column in the messages table has a value >=
'2013-03-01';
  6. The processed column in the messages table is NULL.

  **pogstone_authnet_messages**

  Relevant messages meet these criteria:

  1. Response code (as received via Silent Post) is 1;
  2. Subscription ID (as received via Silent Post) is not an empty string;
  3. The transaction amount (as received via Silent Post) is equal to the
amount of the matching existing Recurring Contribution record;
  4. Transaction ID (as received via Silent Post) is not an empty string;
    5. Transaction ID (as received via Silent Post) does not match the
Transaction ID of any existing Contribution record;
  6. The message_date column in the messages table has a value >=
'2013-03-01';
  7. The processed column in the messages table is NULL.

4. For each message found in this **first pass**:

    1. Determine whether an **existing Recurring Contribution record** can be
found, by matching the Recurring Contribution’s "processor ID" value to the
Subscription ID (as received via Authorize.net Silent Post) or the Recurring
payment ID (as received via PayPal IPN).

    2. Only if an **existing Recurring Contribution record** can be found:

        * Find the first Contribution record in the Recurring Contribution
series;

        * Create a new Contribution record in the Recurring Contribution series:

            1. copying all values (line items, custom field values, etc.) from
this first Contribution record, and

            2. giving it a status of 'Completed'.

        * If this first Contribution record was still in a 'Pending' state,
delete it.

        * Determine whether this new Contribution constitutes the last of the
expected number of payments in the Recurring Contribution series; if so, set
the Recurring Contribution status to 'Completed'; if not, set the Recurring
Contribution status to 'In Progress'.

    3. Mark this message as *processed* by storing the current date/time in the
processed column.

5. Perform a **second pass** over the pogstone_authnet_messages table *only*,
to check for unprocessed messages indicating **failure of the first transaction
in a recurring contribution**. Relevant messages meet these criteria:

    1. Authorized.net response code (as received via Silent Post) is one of the
various 'declined' status codes from Authorize.net;

    2. Subscription ID (as received via Silent Post) matches the "Processor ID"
of an existing Recurring Contribution record;

    3. Invoice Number (as received via Silent Post) matches the ID of an
existing Contribution record;

    4. The matching existing Recurring Contribution record has no completed
payments;

    5. The message has never been processed, or was processed in the
above-mentioned **first pass**.

    6. The message_date column in the messages table has a value >=
'2015-01-15';

6. For each message found in this **second pass**:

    1. Set the status of the matching existing Contribution record to 'Failed'.

    2. Set the status of the matching existing Recurring Contribution record to
'Failed'.

    3. Add the ID of the message to a list of **second-pass processed message
IDs**.

    4. Mark this message as *processed* by storing the current date/time in the
processed column.

7. Perform a **third pass** over the pogstone_authnet_messages table *only*, to
check for unprocessed messages that are **not linked by Transaction ID to any
existing Contribution**. Relevant message meet these criteria:

    1. ID is not in the list of **second-pass processed message IDs**;

    2. Transaction ID (as received via Silent Post) is not 0;

    3. Transaction ID (as received via Silent Post) does not match the
Transaction ID of any existing Contribution record;

    4. The message type (as received via Silent Post) is either 'auth_capture',
'capture_only', or 'credit';

    5. The message_date column in the messages table has a value >=
'2013-03-01';

    6. The message has never been processed, or was processed in the
above-mentioned **first pass**.

8. For each message found in this **third pass**:

    1. Determine the **Contact ID** of the donor based on the first available
value among these options:

        * The Contact ID from an existing Recurring Contribution record having
a "Processor ID" value matching the message's Subscription ID value (as
received via Silent Post), if one is found; or

        * The Customer ID (as received via Silent Post), if one is available; or

        * The First Name, Last Name, and Email components of the message (as
received via Silent Post).

    2. Create a Contribution record with the appropriate properties:

        * The **Contact ID** as determined above;

        * Payment Instrument ID: "1" (credit card);

        * Financial Type: "Unknown Financial";

        * Received Date: copied from the message_date column;

        * Contribution Status:

        * 'Completed' if Response Code (as received via Silent Post) is 1;

        * 'Failed' if Response Code (as received via Silent Post) is 2 or 3;

        * Amount: copied from Amount (as received via Silent Post);

        * Associated Recurring Contribution record: the existing Recurring
Contribution record having a "Processor ID" value matching the message's
Subscription ID value (as received via Silent Post), if one is found;

        * Transaction ID: copied from the Trans ID (as received via Silent
Post);

    3. Mark this message as *processed* by storing the current date/time in the
processed column.

9. Perform a **fourth pass** over the pogstone_authnet_messages table *only*,
to check for messages representing **a "void" action on Authorize.net**.
Relevant messages meet these criteria:

    1. The message type (as received via Silent Post) is ‘void’;

    2. Transaction ID (as received via Silent Post) matches that of another
message in which

        * Message type (as received via Silent Post) is either 'auth_capture',*
*'capture_only', or 'credit'; and

        * Response code (as received via Silent Post) is ‘1’; and

        * The message_date column in the messages table has a value >=
'2015-01-15'; and

        * The message has never been processed, or was processed in the
above-mentioned **first pass**.

    3. Transaction ID (as received via Silent Post) matches the transaction ID
of an existing Contribution record;

    4. The status of the matching existing Contribution record is either '1',
'2', '5', or '6';

10. For each message found in this **fourth pass**:

    1. Mark this message as *processed* by storing the current date/time in the
processed column.
*NOTE: a bug in this part of the code actually prevents this update from
happening.*

11. Perform a **database query** for existing Contributions meeting these
criteria:

    1. Contribution is part of a Recurring Contribution series;

    35. The Recurring Contribution series has a status of ‘3’ (canceled);

    36. The Contribution has a status of ‘2’ (pending).

12. For each Contribution found in this **database query**:

    1. Update the status of the Contribution to ‘3’ (canceled).

13. Return an API success message containing the string "record count: X",
where X is the number of messages processed in the above-mentioned **first
pass**.

## CRM_Core_Payment_AuthorizeNetIPN::pogstone_log_details()

This method appears in the overridden file CRM/Core/Payment/AuthorizeNetIPN.php
(see **PHP file overrides elsewhere** in this README). It is called once for
each incoming Authorize.net Silent Post and inserts a single row into the table
pogstone_authnet_messages. This row contain these values:

1. civicrm_contribution_id: [empty string]

2. civicrm_recur_id: [empty string]

3. rec_type: 'authorize.net'

4. message_date: [CURRENT_TIMESTAMP]

5. x_response_code: x_response_code (as received in the $_REQUEST global)

6. x_response_reason_code: x_response_reason_code (as received in the $_REQUEST
global)

7. x_response_reason_text: x_response_reason_text (as received in the $_REQUEST
global)

8. x_avs_code: x_avs_code (as received in the $_REQUEST global)

9. x_auth_code: x_auth_code (as received in the $_REQUEST global)

10. x_trans_id: x_trans_id (as received in the $_REQUEST global)

11. x_method: x_method (as received in the $_REQUEST global)

12. x_card_type: x_card_type (as received in the $_REQUEST global)

13. x_account_number: x_account_number (as received in the $_REQUEST global)

14. x_first_name: x_first_name (as received in the $_REQUEST global)

15. x_last_name: x_last_name (as received in the $_REQUEST global)

16. x_company: x_company (as received in the $_REQUEST global)

17. x_address: x_address (as received in the $_REQUEST global)

18. x_city: x_city (as received in the $_REQUEST global)

19. x_state: x_state (as received in the $_REQUEST global)

20. x_zip: x_zip (as received in the $_REQUEST global)

21. x_country: x_country (as received in the $_REQUEST global)

22. x_phone: x_phone (as received in the $_REQUEST global)

23. x_fax: x_fax (as received in the $_REQUEST global)

24. x_email: x_email (as received in the $_REQUEST global)

25. x_invoice_num: x_invoice_num (as received in the $_REQUEST global)

26. x_description: x_description (as received in the $_REQUEST global)

27. x_type: x_type (as received in the $_REQUEST global)

28. x_cust_id: x_cust_id (as received in the $_REQUEST global)

29. x_ship_to_first_name: x_ship_to_first_name (as received in the $_REQUEST
global)

30. x_ship_to_last_name: x_ship_to_last_name (as received in the $_REQUEST
global)

31. x_ship_to_company: x_ship_to_company (as received in the $_REQUEST global)

32. x_ship_to_address: x_ship_to_address (as received in the $_REQUEST global)

33. x_ship_to_city: x_ship_to_city (as received in the $_REQUEST global)

34. x_ship_to_state: x_ship_to_state (as received in the $_REQUEST global)

35. x_ship_to_zip: x_ship_to_zip (as received in the $_REQUEST global)

36. x_ship_to_country: x_ship_to_country (as received in the $_REQUEST global)

37. x_amount: x_amount (as received in the $_REQUEST global)

38. x_tax: x_tax (as received in the $_REQUEST global)

39. x_duty: x_duty (as received in the $_REQUEST global)

40. x_freight: x_freight (as received in the $_REQUEST global)

41. x_tax_exempt: x_tax_exempt (as received in the $_REQUEST global)

42. x_po_num: x_po_num (as received in the $_REQUEST global)

43. x_MD5_Hash: x_MD5_Hash (as received in the $_REQUEST global)

44. x_cvv2_resp_code: x_cvv2_resp_code (as received in the $_REQUEST global)

45. x_cavv_response: x_cavv_response (as received in the $_REQUEST global)

46. x_test_request: x_test_request (as received in the $_REQUEST global)

47. x_subscription_id: x_subscription_id (as received in the $_REQUEST global)

48. x_subscription_paynum: x_subscription_paynum (as received in the $_REQUEST
global)

49. message_raw: [A string compiled of all variables from the $_REQUEST global]

NOTE: This insert leaves the following columns empty or at their default values:

1. id (auto-increment)

2. civicrm_contribution_id (empty)

3. civicrm_recur_id: (empty)

4. processed (NULL)

## CRM_Core_Payment_PayPalProIPN::pogstone_log_details()

This method appears in the overridden file CRM/Core/Payment/PayPalProIPN.php
(see **PHP file overrides elsewhere** in this README). It is called once for
each incoming PayPal IPN notification and performs these tasks:

1. Attempt to create a **log file** in the directory just above the server
document root, with the name "[X]__pogstone_pay_pal_log.txt", where [X] is the
current date in the form 'Y-m-d'.

2. Compile all variables from the $_REQUEST global into a string we’ll call
the **raw message string**.

3. Append this **raw message string** to the above-mentioned **log file**.

4. Insert a single row into the table pogstone_paypal_messages, with these
values:

    1. rec_type: 'paypal'

    2. message_date: (CURRENT_TIMESTAMP)

    3. amount: amount (as received in the $_REQUEST global)

    4. txn_id: txn_id (as received in the $_REQUEST global)

    5. recurring_payment_id: recurring_payment_id (as received in the $_REQUEST
global)

    6. payment_date: payment_date (as received in the $_REQUEST global)

    7. payment_status: payment_status (as received in the $_REQUEST global)

    8. mc_gross: mc_gross (as received in the $_REQUEST global)

    9. mc_fee: mc_fee (as received in the $_REQUEST global)

    10. first_name: first_name (as received in the $_REQUEST global)

    11. last_name: last_name (as received in the $_REQUEST global)

    12. payer_email: payer_email (as received in the $_REQUEST global)

    13. txn_type: txn_type (as received in the $_REQUEST global)

    14. period_type: period_type (as received in the $_REQUEST global)

    15. payment_fee: payment_fee (as received in the $_REQUEST global)

    16. payment_gross: payment_gross (as received in the $_REQUEST global)

    17. currency_code: currency_code (as received in the $_REQUEST global)

    18. mc_currency: mc_currency (as received in the $_REQUEST global)

    19. outstanding_balance: outstanding_balance (as received in the $_REQUEST
global)

    20. next_payment_date: next_payment_date (as received in the $_REQUEST
global)

    21. protection_eligibility: protection_eligibility (as received in the
$_REQUEST global)

    22. payment_cycle: payment_cycle (as received in the $_REQUEST global)

    23. tax: tax (as received in the $_REQUEST global)

    24. payer_id: payer_id (as received in the $_REQUEST global)

    25. product_name: product_name (as received in the $_REQUEST global)

    26. charset: charset (as received in the $_REQUEST global)

    27. rp_invoice_id: rp_invoice_id (as received in the $_REQUEST global)

    28. notify_version: notify_version (as received in the $_REQUEST global)

    29. amount_per_cycle: amount_per_cycle (as received in the $_REQUEST global)

    30. payer_status: payer_status (as received in the $_REQUEST global)

    31. business: business (as received in the $_REQUEST global)

    32. verify_sign: verify_sign (as received in the $_REQUEST global)

    33. initial_payment_amount: initial_payment_amount (as received in the
$_REQUEST global)

    34. profile_status: profile_status (as received in the $_REQUEST global)

    35. payment_type: payment_type (as received in the $_REQUEST global)

    36. receiver_email: receiver_email (as received in the $_REQUEST global)

    37. receiver_id: receiver_id (as received in the $_REQUEST global)

    38. residence_country: residence_country (as received in the $_REQUEST
global)

    39. receipt_id: receipt_id (as received in the $_REQUEST global)

    40. transaction_subject: transaction_subject (as received in the $_REQUEST
global)

    41. shipping: shipping (as received in the $_REQUEST global)

    42. product_type: product_type (as received in the $_REQUEST global)

    43. time_created: time_created (as received in the $_REQUEST global)

    44. ipn_track_id: ipn_track_id (as received in the $_REQUEST global)

NOTE: This insert leaves the following columns empty or at their default values:

1. id (auto-increment)

2. civicrm_contribution_id (empty)

3. civicrm_processed (empty)

4. civicrm_recur_id (empty)

5. message_raw (empty)

6. processed (NULL)

# Known issues, concerns, and surprises:

1. Potentially unintended behavior:

    1. The table pogstone_paypal_messages is only processed in the **first
pass**, i.e., only Recurring Contributions are updated based on the content of
this table.

    2. Some steps filter for messages with a message_date >= ‘2013-03-01’
while others filter for a message_date >= '2015-01-15'.  The reason for this
difference is unclear.

    3. In several places, hard-coded integer values are used within the code;
for example, see criteria in the **fourth pass**: *The status of the matching
existing Contribution record is either '1', '2', '5', or '6';* It’s unclear
what these integers should represent, and it’s unknown whether they have the
same meaning on every site.

2. Unadvisable behavior:

    4. Creation of "[X]__pogstone_pay_pal_log.txt" log files in the directory
just above the server document root (see **log file** under
CRM_Core_Payment_PayPalProIPN::pogstone_log_details() elsewhere in this README)
seems ill-advised and probably is not even possible, as that directory is
equivalent to /var/aegir/platforms which is probably not writable by the web
server. If it were possible, it would create a growing collection of log files
with no process in place for garbage collection / log rotation to prevent disk
overuse.

    5. The function create_needed_line_item_db_records() makes several direct
SQL INSERT queries to financial tables; this will lead to breakage if the
schema of these tables changes between versions and this code is not modified
accordingly.

3. Known bugs:

    6. A bug in the **fourth pass** prevents messages affected by that pass
from being marked as processed, which most likely will lead to repeat
processing of those messages.

4. Maintenance and performance issues:

    7. Multiple passes over the pogstone_authnet_messages table could probably
be avoided with better planning, with the aim to make the code logic easier to
follow by future developers.

    8. Future development and bug-fixing would be greatly eased by the addition
of more verbose in-line documentation of the code.

    9. The code in general would greatly benefit from cleanup of unused
variables, unnecessarily long and complex SQL queries, unused functions and
methods, and hard-to-read whitespace formatting.

    10. Large blocks of code exist in the
ProccessorMessage.Processnewmessages() API for processing messages from iATS
and eWay, but no code exists for capturing  messages for these processors, so
this code is unused. I recommend removing it.

    11. The file CRM/Paymentprocessorhelper/Form/Search/FinancialHelper.php
appears to be completely unused.

    12. Code that could be run only once per run is being called once per
message; for example, retrieval of the "Unknown Financial" financial type ID in
the function getFinancialTypeID_forMessage().

    13. All APIs other than ProccessorMessage.Processnewmessages() appear to be
redundant to existing CiviCRM native APIs.

    14. Columns in the following SQL tables are unused and always empty; they
should be removed to ease maintenance:

        1. pogstone_authnet_messages:

            1. civicrm_contribution_id

            2. civicrm_recur_id

        2. pogstone_paypal_messages:

            3. civicrm_contribution_id

            4. civicrm_processed

            5. civicrm_recur_id

            6. message_raw


## License

Distributed under the terms of the GNU Affero General public license (AGPL).
See LICENSE.txt for details.

(C) 2014 Sarah Gladstone (C) 2016 Jvillage Networks

This extension was originally written by Sarah Gladstone (2014) and is
currently maintained by Jvillage Network (https://jvillagenetwork.com).
