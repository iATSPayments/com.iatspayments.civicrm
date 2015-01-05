com.iatspayments.civicrm
===============

CiviCRM Extension for iATS Web Services Payment Processor
Date: January 5, 2015, version 1.3.1

This README.md contains information specific to system administrators/developers. Information for users/implementors can be found in the Documentation Wiki: https://github.com/iATSPayments/com.iatspayments.civicrm/wiki/Documentation

Requirements
------------

1. CiviCRM 4.4.x or 4.5.x

2. Your PHP needs to include the SOAP extension (php.net/manual/en/soap.setup.php).

3. NOTE: to ensure all different types of transactions are working across all CiviCRM pathways [our test matrix includes 20 type of transactions at the moment] - a small patch to CiviCRM core is required. You can find iATS_4.4.10.diff and iATS_4.5.4.diff in the repository. If you use another version of CiviCRM you may have to adjust the line numbers in these patches. The patches have been submitted to be included into CiviCRM Core - but until they are you need to include these yourself. 

4. You must have an iATS Payments Account - and have configured it to accept payment though WebServices. For details please see the Documentation Wiki: https://github.com/iATSPayments/com.iatspayments.civicrm/wiki/Documentation

Installation
------------

This extension follows the standard installation method - if you've got the right CiviCRM version and you've set up your extensions directory, it'll appear in the Manage Extensions list as 'iATS Payments (com.iatspayments.civicrm)'. Hit Install.

If you need help with installing extensions, try: https://wiki.civicrm.org/confluence/display/CRMDOC/Extensions - If you want to try out a particular version directly from github, you probably already know how to do that.

Once the extension is installed, you need to add the payment processor(s) and input your iATS credentials:

1. Administer -> System Settings -> Payment Processors -> + Add Payment Processor

2. Select iATS Payments Credit Card, (iATS Payments ACH/EFT, iATS Payments SWIPE or iATS Payments UK Direct Debit) provided by this extension and modify the instructions below appropriately).

3. The "Name" of the payment processor is what your visitors will see when they select a payment method, so typically use "Credit Card" here, or "Credit Card, C$" (or US$) if there's any doubt about the currency. Your iATS Payments Account is configured for a single currency, so when you set up the payment page, you'll have to manually ensure you set the right currency (not an issue if you're only handling one currency).

4. To test your new processor using live workflows: 
For iATS Payments Credit Card, iATS Payments ACH/EFT or iATS Payments SWIPE:
use Agent Code = TEST88 and Password = TEST88 for both Live and Test
For iATS Payments UK Direct Debit:
use Agent Code = UDDD88, Password = UDDD888 and Service User Number = 123456789

5. Create a Contribution Page (or go to an existing one)

6. Under Configure -> Contribution Amounts -> select your newly installed/configured Payment Processor - hit Save

Testing
-------

0. Our test matrix includes 20 type of transactions at the moment -> view a summary of the results here: https://cloud.githubusercontent.com/assets/5340555/5616064/2459a9b8-94be-11e4-84c7-2ef0c83cc744.png

1. Contribution Links -> online Contribution -> Live.

2a. - iATS Payments Credit Card: use test VISA: 4222222222222220 security code = 123 and any future Expiration date - to process any $amount.
2b. - iATS Payments ACH/EFT: use 1234 for the Bank Account Number and 111111111111 for the Bank Identification Number (Bank number + branch transit number)
2c - iATS Payments SWIPE: not easy to test - even if you have an Encrypted USB Card Reader (sourced by iATS Payments) you will need a physical fake credit card with: 4222222222222220 security code = 123 and any future Expiration date - to process any $amount.
2d - iATS Payments UK Direct Debit: use


3. iATS has another test VISA: 41111111111111111 security code = 123 and any future Expiration date

4. Reponses depend on the $amount processed - as follows
  * 1.00 OK: 678594;
  * 2.00 REJ: 15;
  * 3.00 OK: 678594;
  * 4.00 REJ: 15;
  * 5.00 REJ: 15;
  * 6.00 OK: 678594:X;
  * 7.00 OK: 678594:y;
  * 8.00 OK: 678594:A;
  * 9.00 OK: 678594:Z;
  * 10.00 OK: 678594:N;
  * 15.00, if CVV2=1234 OK: 678594:Y; if there is no CVV2: REJ: 19
  * 16.00 REJ: 2;
  * Other Amount REJ: 15

5. Visit the custom menu item under Contributions -> iATS Payments Admin. This will give you a list of recent transactions with the iATS payment processor, including details like the Auth code and last 4 digits of the credit card that aren't stored/searchable in CiviCRM.

6. You can also visit http://home.iatspayments.com/ -> and click the Client Login button (top right)
  * Login with TEST88 and TEST88
  * hit Journal and Get Journal -> if it has been a busy day there will be lots of transactions here - so hit display all and scroll down to see the transaction you just processed via CiviCRM.

7. If things don't look right, you can open the Drupal log and see some detailed logging of the SOAP exchanges for more hints about where it might have gone wrong.

9. Also test recurring contributions - try creating a recurring contribution for every day and then go back the next day and manually trigger the corresponding Scheduled Job.

Once you're happy all is well - then all you need to do is update the Payment Processor data - with your own iATS' Agent Code and Password.

Remember that iATS master accounts (ending in 01) can NOT be used to push monies into via web services. So when setting up your Account with iATS - ask them to create another (set of) Agent Codes for you: e.g. 80 or 90, etc.

Also remember to turn off debugging/logging on any production environment!


ACH/EFT
-------

ACH/EFT is pretty new, suggestions to improve this function welcome! It is currently only implemented for North American accounts.

The ACH/EFT testing value for the TEST88 account is: 1234111111111111 - iATS Payments is working to improve ACH/EFT testing as currently all direct debits in TEST88 get rejected.

So use:
  * 1234 for the Bank Account Number
  * 111111111111 for the Bank Identification Number (Bank number + branch transit number)

When you enable this payment processor for a contribution page, it modifies the form to set recurring contributions as the default, but no longer forces recurring contributions as it did up until version 1.2.3.

Please note that ACH Returns require manually processing. iATS Payments will notify an organization by Email in case such ACH Returns occur - the reason (e.g. NSF) is included. It is up to CiviCRM administrators to handle this in CiviCRM according to your organization's procedures (e.g. if these were monies re: Event registration -> should that registration be canceled as well or will you ask participant to bring cash; if and the amount of NSF fees should be charged to the participant etc).

A beta release for the UK direct debit support will be out soon. Notes specfic to that:
- Each charity needs to have a BACS accredited supplier confirm their CiviCRM Direct Debit - Contribution Pages
- Administer -> System Settings -> Payment Processors -> processors of type: iATS Payments UK Direct Debit require a Service User Number (SUN) in addition to the iATS Agent Code and Password
- UK TEST purposes: https://www.uk.iatspayments.com/login/login.html Client Code: UDDD88 Password: UDDD888 SUN: 123456789

SWIPE

SWIPE on backend:
- Create and set your Payment Processor of type iATSpayments SWIPE to default (Administer -> System Settings -> Payment Processor)
- Search for a Contact (or add a new one) and in their Contact Summary screen hit the Contributions Tab -> then hit: + Submit Credit Card Contribution
- Click in Encrypted -> SWIPE card -> add Expiration Date -> Save [transaction completed - confirmed monies are in iATSpayments.com]

SWIPE on public contribution pages:
To get SWIPE working on public contribution pages disable three lines in CiviCRM Core code:
CRM -> Core -> Payment -> Form.php

in 4.5.4
lines (367 - 369)

//elseif (!empty($values['credit_card_number'])) {
// $errors['credit_card_number'] = ts('Please enter a valid Card Number');
//}

Working on something permanent (adding some parameter to say don't invoke credit card validation if expecting a long encrypted string) - but this will work - till then. 

Issues
------

The best source for understanding current issues with the most recent release is the github issue queue:
https://github.com/iATSPayments/com.iatspayments.civicrm/issues

Most of the outstanding issues are related in some way to core CiviCRM issues, and may not have an immediate solution, but we'll endeavour to help you understand, work-around, and/or fix whatever concerns you raise on the issue queue.

Below is a list of some of the most common issues:

ACH/EFT contributions go in with a pending status until a process at iATS confirms the payment went through (or not). There's a Scheduled Job that must be enabled that checks iATS daily for approvals/rejections. Unfortunately, all test contributions are rejected, so we have no way of testing approvals except with a live transaction. In addition, the approvals on the iATS website may show up a couple of days before they do on your CiviCRM site. This issue is being worked on.

'Backend' ACH/EFT is not supported by CiviCRM core. Having an enabled ACH/EFT payment processor broke the backend live credit card payment page in core (until it was fixed here https://issues.civicrm.org/jira/browse/CRM-14442), so this module fixes that if it's an issue, and also provides links to easily allow administrators to input ACH/EFT on behalf of constituents. A similar problem existings for backend membership and event payments, and this has not been fixed in core.

9002 Error - if you get this when trying to make a contribution, then you're getting that error back from the iATS server due to an account misconfiguration. One source is due to some special characters in your passwd.

CiviCRM core assigns Membership status (=new) and extends Membership End date as well as Event status (=registered) as soon as ACH/EFT is submitted (so while payment is still pending - this could be up to 3-5 days for ACH/EFT). If the contribution receives a Ok:BankAccept -> the extension will mark the contribution in CiviCRM as completed. If the contribution does NOT receive a Ok:BankAccept -> the extension will mark the contribution in CiviCRM as rejected - however - associated existing Membership and Event records may need to be updated manually.

Please post an issue to the github repository if you have any questions.
