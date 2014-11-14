com.iatspayments.civicrm
===============

CiviCRM Extension for iATS Web Services Payment Processor
Date: May 5, 2014, version 1.2


Requirements
------------

CiviCRM 4.3+. These are instructions are based on 4.3, instructions for other releases are similar. 

4.2 support was dropped in the 1.2.11 release, but it was okay up to that point except for the error in creating the recurring jobs (which you have to do manually).

Your PHP needs to include the SOAP extension (php.net/manual/en/soap.setup.php).

You must have an iATS Payments account - and have configured it to accept payment though WebServices. Our recommended configuration would look like this:

2ABC01 account: configured to access via iATSpayments.com only (no WebServices) - full refund/journal capability - the works. Password is unique to the 01 account.

2ABC80 account: subaccount configured to connected with iATS WebServices - can also access iATSpayments.com - but journal functionality only (no refund capability). The 80 account has a different password than the 01 account - use the 80(+) accounts to configure your Payment Processor(s) in CiviCRM.

2ABC81 etc - same as 2ABC80. If this was a school; a payment processor connecting to subaccount 80 could be cafeteria monies; 81 could be tuition deposits; 82 could be special event tickets, etc. Passwords for the 80, 81, 82 etc accounts can all be the same. Essentially they are deposit only accounts.

Installation
------------

This extension follows the standard installation method - if you've got the right CiviCRM version and you've set up your extensions directory, it'll appear in the extensions list as 'iATS Payments'.

For details, try: https://wiki.civicrm.org/confluence/display/CRMDOC/Extensions

If you want to try out a particular version directly from github, you probably already know how to do that.

Once the extension is installed, you need to add the payment processor(s) and input your iATS credentials

1. Administer -> System Settings -> Payment Processor + Add Payment Processor

2. Select iATS Payments Credit Card, (or iATS Payment ACH/EFT, both are provided by this extension and modify the instructions below appropriately).

3. The "Name" of the payment processor is what your visitors will see when they select a payment method, so typically use "Credit Card" here, or "Credit Card, C$" (or US$) if there's any doubt about the currency. Your iATS account is configured for a single currency, so when you set up the payment page, you'll have to manually ensure you set the right currency (not an issue if you're only handling one currency).

4. Use the url https://www.iatspayments.com/NetGate/, or https://www.uk.iatspayments.com/NetGate/ for the Site URL and Recurring payments URL. Only the domain is actually used, but it's important to let the payment processor plugin know whether your account is on the UK or NA server.

5. To test your new processor using live workflows: use Agent Code = TEST88 and Password = TEST88 for both Live and Test.

6. Create a Contribution Page (or go to an existing one)

7. Under Configure -> Contribution Amounts -> select your newly installed/configured Payment Processor - hit Save

Testing
-------

1. If you're using Drupal: turn on CiviCRM debug and Drupal Watchdog logging in the Settings - Debugging and Error Handling. This will add some extra verbose SOAP logging to the Drupal log.

2. Contribution Links -> online Contribution -> Live.

3. Use test VISA:  4222222222222220 security code = 123 and any future Expiration date - to process any $amount.

4. iATS has another test VISA: 41111111111111111 security code = 123 and any future Expiration date

5. Reponses depend on the $amount processed - as follows
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

6. Visit the custom menu item under Contributions -> iATS Payments Admin. This will give you a list of recent transactions with the iATS payment processor, including details like the Auth code and last 4 digits of the credit card that aren't stored/searchable in CiviCRM.

7. You can also visit http://home.iatspayments.com/ -> and click the Client Login button (top right)
  * Login with TEST88 and TEST88
  * hit Journal and Get Journal -> if it has been a busy day there will be lots of transactions here - so hit display all and scroll down to see the transaction you just processed via CiviCRM.

8. If things don't look right, you can open the Drupal log and see some detailed logging of the SOAP exchanges for more hints about where it might have gone wrong.

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

Support for the UK server has been excluded due to special legal requirements with UK direct payment. We expect to have a solution to this sometime in 2014.

Issues
------

The best source for understanding current issues with the most recent release is the github issue queue:
https://github.com/iATSPayments/com.iatspayments.civicrm/issues

Most of the outstanding issues are related in some way to core CiviCRM issues, and may not have an immediate solution, but we'll endeavour to help you understand, work-around, and/or fix whatever concerns you raise on the issue queue.

Below is a list of some of the most common issues:

ACH/EFT contributions go in with a pending status until a process at iATS confirms the payment went through (or not). There's a Scheduled Job that must be enabled that checks iATS daily for approvals/rejections. Unfortunately, all test contributions are rejected, so we have no way of testing approvals except with a live transaction. In addition, the approvals on the iATS website may show up a couple of days before they do on your CiviCRM site. This issue is being worked on.

'Backend' ACH/EFT is not supported by CiviCRM core. Having an enabled ACH/EFT payment processor broke the backend live credit card payment page in core (until it was fixed here https://issues.civicrm.org/jira/browse/CRM-14442), so this module fixes that if it's an issue, and also provides links to easily allow administrators to input ACH/EFT on behalf of constituents. A similar problem existings for backend membership and event payments, and this has not been fixed in core.

9002 Error - if you get this when trying to make a contribution, then you're getting that error back from the iATS server due to an account misconfiguration. One source is due to some special characters in your passwd.

Please post an issue to the github repository if you have any questions.
