# CHANGELOG CRMCLIENTCONNECTOR FOR [DOLIBARR ERP CRM](https://www.dolibarr.org)

## 1.5.3

- FIX: the unique index on the address of the email accounts was declared on a column that does not exist
  (emailaccount instead of email_account), so it was never created and the same address could be added twice.
  Created when the module is enabled again (not created if the table already holds the same address twice).

## 1.5.2

- FIX: the pages of the email accounts and of the email notes (list, card, notes, documents, contacts, events)
  did not check the rights of the module: any user could see, create, change or delete them. The rights
  « Read / Create/Update / Delete » of these objects are now required.

## 1.5.1

- FIX: anti-spam REST API: a mailbox not in the list of the email accounts answers 404 instead of 403 (a 403 is
  shown by DoliConnector as a missing Dolibarr right).

## 1.5.0

- NEW: Anti-spam IMAP, part 4: spoofing alerts
  - an email with the address of a known customer, supplier, contact or of the whitelist, whose authentication by our receiving server fails, gets the IMAP keyword doliconnector_spoof (never moved), in the junk folders (task « In the junk folders ») and in the inbox (task « In the inbox », the inbox is read too)
  - written in the log as « Usurpation possible »
  - REST API for the DoliConnector Thunderbird extension: POST crmclientconnector/antispam/check (check of the headers of an email when it is opened) and GET crmclientconnector/antispam/alerts (alerts written by the scan for a Message-Id), right to read thirdparties and mailbox in the list of the email accounts needed

## 1.4.0

- NEW: Anti-spam IMAP, part 3: manual whitelist and blacklist, purge of the log
  - page « Liste blanche / noire anti-spam » (new right): exact addresses, for all the mailboxes or one mailbox, with a note
  - whitelist: address treated as a known sender (task « Manual whitelist »), still only when the sender is authenticated
  - blacklist: never taken out of junk, even for a customer or a supplier (written in the log as « Liste noire » for known senders only)
  - quick add of a sender from a line of the log (form prefilled, saved after confirmation)
  - log lines older than the retention of the setup (90 days by default, 0 to keep everything) deleted by the scheduled job

## 1.3.0

- NEW: Anti-spam IMAP, part 2: emails of known senders taken out of junk
  - scheduled job « Anti-spam IMAP : analyse des indésirables » every 5 minutes (created disabled, state shown in the setup), « Scan now » button on each mailbox
  - known senders: exact email of the active customers (prospects only excluded) and suppliers, and of the active contacts of these thirdparties, following the tasks of the mailbox
  - the sender must be authenticated by our receiving server: first « Authentication-Results » of the server ids set in the setup (DMARC, else DKIM or SPF aligned on the From domain), else « Received-SPF »
  - only the headers are read, the messages stay unread, each message is read once (last UID kept per folder), first scan on the last 7 days
  - email moved to the inbox with the keywords $NotJunk / NonJunk (task « Mark not junk »), MOVE or COPY + delete when the server has no MOVE
  - log « Journal anti-spam » (rescued, left in junk with the reason), visible for the employee of the mailbox (new right) or for all the mailboxes (new right)

## 1.2.0

- NEW: Anti-spam IMAP, first part (off by default): setup tab « Anti-spam IMAP » (default IMAP server, optional Dovecot master user with encrypted password, server prerequisites) and « Anti-spam » tab on each email account
  - connection of each mailbox with the master user, or without master user with its own login and password (stored encrypted) and optionally its own IMAP server
  - switch to enable the anti-spam of the mailbox (ajax), employee owning the mailbox, junk folders picked from the folders read on the server
  - tasks enabled one by one: take out of junk the known senders by source (customers, suppliers, their contacts, manual whitelist), mark them « not junk », flag spoofing in junk folders and in the inbox; defaults in the setup (switches), each mailbox follows the setup or forces a task on / off
  - « Test connection » button: login with the master user, list of the folders with their number of messages
  - no email is read nor moved yet (automatic rescue and spoofing alerts come in the next versions)

## 1.1.0

- Add supplier proposal support (type 'spro') to LINKABLE_ELEMENT_TYPES, NUMBERING_MODULE_TYPES, CATEGORY_TYPES and CATEGORY_TYPE_RIGHTS, so the Thunderbird extension's linked-documents, ref-detection and tags features cover supplier proposals like every other document type

## 

- Add whoami endpoint (GET crmclientconnector/whoami) to identify the current API user without requiring users/info's rights
- Fix compatibility V18 V0.1.1
Initial version V0.1.0
