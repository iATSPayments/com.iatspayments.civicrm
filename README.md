ca.civicrm.iats
===============

CiviCRM Extension for iATS Web Services Payment Processor

Requirements

CiviCRM 4.2+. These are instructions are based on 4.3, the 4.2 instructions are similar. The support for jobs in 4.2/civix is a bit iffy.

Your PHP needs to include the SOAP extension (php.net/manual/en/soap.setup.php).  

Installation 

1. create a directory for your CiviCRM Extensions (if you don't already have one).

2. Administer -> System Settings -> Directories or /civicrm/admin/setting/path?reset=1
add the CiviCRM Extensions Directory you just created

3. in a shell cd to your CiviCRM Extensions Directory and:
git clone https://github.com/adixon/ca.civicrm.iats.git ca.civicrm.iats

4. Administer -> Customize Data and Screens -> Manage Extensions
hit Refresh - you should see iATS Payments now -> hit Install in the far right column - and click through

5. Time to make a Payment Processor:
Administer -> System Settings -> Payment Processor
+ Add Payment Processor
select iATS Payments. 
To test your new processor using live workflows: use Agent Code = TEST88 and Password = TEST88 for both Live and Test.

6. Create a Contribution Page (or go to an existing one)
Under Configure -> Contribution Amounts -> select your newly installed/configured Payment Processor - hit Save

7a. Contribution Links -> online Contribution -> Live.
Use test VISA:  4222222222222220 security code = 123 and any future Expiration date - to process any $amount. 

7b. iATS has another test VISA: 41111111111111111 security code = 123 and any future Expiration date 
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

8. Visit the custom menu item under Contributions -> iATS Payments Admin. This will give you a list of recent transactions with the iATS payment processor, including deails like the Auth code and last 4 digits of the credit card that aren't stored/searchable in CiviCRM.

9. You can also visit http://home.iatspayments.com/ -> and click the Client Login button (top right)
Login with TEST88 and TEST88
hit Journal and Get Journal -> if it has been a busy day there will be lots of transactions here - so hit display all 
and scroll down to see the transaction you just processed via CiviCRM.

Once you're happy all is well - then all you need to do is update the Payment Processor data - with your own
iATS' Agent Code and Password. Please remember that iATS master accounts (ending in 01) can NOT be used to push 
monies into via web services. So when setting up your Account with iATS - ask them to create another (set of)
Agent Codes for you: e.g. 80 or 90, etc. 

ACH/EFT

The ACH/EFT testing value for the account is:

1234111111111111

So use:
1234 for the Bank Account Number
111111111111 for the Bank number + branch transit number
