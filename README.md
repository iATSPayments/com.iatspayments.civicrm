com.iatspayments.civicrm
===============

CiviCRM Extension for iATS Web Services Payment Processor

Date: Apr 3, 2014, version 1.2


Requirements
------------

CiviCRM 4.2+. These are instructions are based on 4.3, the 4.2 instructions are similar. The support for jobs in 4.2/civix is a bit iffy.

Your PHP needs to include the SOAP extension (php.net/manual/en/soap.setup.php).  


Installation 
------------

1. create a directory for your CiviCRM Extensions (if you don't already have one).

2. Administer -> System Settings -> Directories or /civicrm/admin/setting/path?reset=1
add the CiviCRM Extensions Directory you just created

[optional: if you want to run a version different from one of the standard releases]
3. in a shell cd to your CiviCRM Extensions Directory and:
git clone https://github.com/adixon/com.iatspayments.civicrm.git com.iatspayments.civicrm

4. Administer -> Customize Data and Screens -> Manage Extensions
hit Refresh - you should see iATS Payments now -> hit Install in the far right column - and click through

5. Time to make a Payment Processor: 
 Administer -> System Settings -> Payment Processor + Add Payment Processor
 Select iATS Payments Credit Card
 The "Name" is what your visitors will see when they select a payment method, so typically use "Credit Card" here, or "Credit Card, C$" (or US$) if there's any doubt about the currency. Your iATS account is configured for a single currency, so when you set up the payment page, you'll have to manually ensure you set the right currency (not an issue if you're only handling one currency).
 Use the url https://www.iatspayments.com/NetGate/, or https://www.uk.iatspayments.com/NetGate/ for the Site URL and Recurring payments URL. Only the domain is actually used, but it's important to let the payment processor plugin know whether your account is on the UK or NA server.
 To test your new processor using live workflows: use Agent Code = TEST88 and Password = TEST88 for both Live and Test.

6. Create a Contribution Page (or go to an existing one)
 Under Configure -> Contribution Amounts -> select your newly installed/configured Payment Processor - hit Save


Testing
-------

1. If you're using Drupal: turn on CiviCRM debug and Drupal Watchdog logging in the Settings - Debugging and Error Handling. This will add some extra verbose SOAP logging to the Drupal log.

2. Contribution Links -> online Contribution -> Live.
Use test VISA:  4222222222222220 security code = 123 and any future Expiration date - to process any $amount. 
 iATS has another test VISA: 41111111111111111 security code = 123 and any future Expiration date 

 Reponses depend on the $amount processed - as follows

 1.00 OK: 678594;
 2.00 REJ: 15;
 3.00 OK: 678594;
 4.00 REJ: 15;
 5.00 REJ: 15;
 6.00 OK: 678594:X;
 7.00 OK: 678594:y;
 8.00 OK: 678594:A;
 9.00 OK: 678594:Z;
 10.00 OK: 678594:N;
 15.00, if CVV2=1234 OK: 678594:Y; if there is no CVV2: REJ: 19
 16.00 REJ: 2;
 Other Amount REJ: 15

3. Visit the custom menu item under Contributions -> iATS Payments Admin. This will give you a list of recent transactions with the iATS payment processor, including details like the Auth code and last 4 digits of the credit card that aren't stored/searchable in CiviCRM.

4. You can also visit http://home.iatspayments.com/ -> and click the Client Login button (top right)
 Login with TEST88 and TEST88
 hit Journal and Get Journal -> if it has been a busy day there will be lots of transactions here - so hit display all 
 and scroll down to see the transaction you just processed via CiviCRM.

5. If things don't look right, you can open the Drupal log and see some detailed logging of the SOAP exchanges for more hints about where it might have gone wrong.

6. Also test recurring contributions - try creating a recurring contribution for every day and then go back the next day and manually trigger the corresponding Scheduled Job.

 Once you're happy all is well - then all you need to do is update the Payment Processor data - with your own iATS' Agent Code and Password. 

 Remember that iATS master accounts (ending in 01) can NOT be used to push monies into via web services. So when setting up your Account with iATS - ask them to create another (set of) Agent Codes for you: e.g. 80 or 90, etc. 

 Also remember to turn off debugging/logging on any production environment!


ACH/EFT
-------

ACH/EFT is pretty new, suggestions to improve this function welcome! It is currently only implemented for North American accounts and recurring contributions.

The ACH/EFT testing value for the TEST88 account is:

1234111111111111

So use:
1234 for the Bank Account Number
111111111111 for the Bank number + branch transit number

ACH/EFT contributions are forced by this extension to be recurring only. Support for the UK server has been excluded due to different legal issues with EU direct payment. The initial contribution goes in with a pending status until a process at iATS confirms the payment went through (or not). There's a Scheduled Job that must be enabled that checks iATS daily for approvals/rejections. Unfortunately, all test contributions are rejected, so we have no way of testing approvals yet.

'Backend' ACH/EFT is not supported by CiviCRM core. Having an enabled ACH/EFT payment processor actually breaks the backend live credit card payment page in core, so this module fixes that and instead provides links to easily allow administrators to input ACH/EFT on behalf of constituents.

Please post an issue to the github repository if you have any questions.
