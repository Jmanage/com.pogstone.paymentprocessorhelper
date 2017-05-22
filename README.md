# com.pogstone.paymentprocessorhelper

The basic structure of this CiviCRM native extension:

1) Intercept all messages/notifications/data from a payment processor and
immediately insert it into a “messages” database table, without attempting to make sense of
it and/or create a contribution, etc. This bypasses the core logic of trying to
create a contribution immediately when an IPN notification is received. 

2) This extension creates a new scheduled job named "Process data from payment
processors" that is, by default, set to run every hour. This job queries the “messages” database table mentioned above, looking for data that has not been processed yet. For any new data
that represents a successful transaction, it will create a new contribution.
This includes handling multiple line items, custom data, campaign ID, etc., so
that all the new contributions match the first contribution in the recurring
schedule. It also handles some minor housekeeping like marking the first
"pending" contribution as cancelled if the user cancels the subscription before
the first installment.

This extension also provides a custom search, “Payment Processor Messages”, which facilitates examination of the “messages” database table.

## Supported payment processors

* PayPal Pro (CRM_Core_Payment_PayPalProIPN)
* Authorize.net (CRM_Core_Payment_AuthorizeNetIPN)

## Implementation details
### PHP file overrides
The following CiviCRM core files are overridden by this extension. **Care must be taken during CiviCRM upgrades to update these files accordingly.**

* CRM_Core_Payment_PayPalProIPN
* CRM_Core_Payment_AuthorizeNetIPN

The general design of these overridden PHP files is to leave all code in place, with the exception of altering the `main()` method such that it calls a custom method `pogstone_log_details()` and then returns TRUE without any further processing. See inline documentation in the `main()` and `pogstone_log_details()` methods, and see **pogstone\_log\_details()** elsewhere in this README.

## APIs
This extension provides the following APIs
### Financial_helper
Utility class providing helper methods for Financial Types.
#### createfintype

#### getfinaccount
#### getfintype
### PaymentProcessorTypeHelper
#### get
### ProccessorMessage
#### Processnewmessages

## Business rules

### Incoming messages (PayPal's IPN and Authorize.net's "Silent Post") 
1. All messages are intercepted. Messages are logged for later processing and no other action is taken.

### Periodic message processing
1. Messages are processed on a schedule by the "Process data from payment processors" scheduled job, which calls the 


## pogstone\_log\_details()

## Future plans
When there is a native extension for Authorize.net and PayPal,
the creation of tables "pogstone_authnet_messages" and
"pogstone_paypal_messages" should move to those extensions.  Also create
"failed" contributions for messages that indicate a failed/voided transaction.

License
-------

Distributed under the terms of the GNU Affero General public license (AGPL).
See LICENSE.txt for details.

(C) 2014 Sarah Gladstone
(C) 2016 Jvillage Networks

This extension was originally written by Sarah Gladstone (2014) and is
currently maintained by Jvillage Network (https://jvillagenetwork.com).

