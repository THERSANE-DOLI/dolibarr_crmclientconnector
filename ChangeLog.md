# CHANGELOG CRMCLIENTCONNECTOR FOR [DOLIBARR ERP CRM](https://www.dolibarr.org)

## 1.5.11

- FIX: stored XSS: linking a mail to a document through the REST API created the email account with the address
  sent by the client, without checking it, and the list of the email accounts showed it as is. The address must
  now be a valid email and the message-id must not hold spaces nor HTML characters (HTTP 400 otherwise), and the
  address, reference and label of the email accounts, links and notes are escaped in their links and tooltips.

## 1.5.10

- FIX: anti-spam: the sender of an email was read in the display name or in a comment of the From header, so an
  email of an attacker could be taken out of junk as the one of a customer (« "<client@gmail.com>"
  <attacker@gmail.com> ») and a spoofed email could escape the spoofing alert. The headers are now read with their
  quoted strings and comments:
  - the sender is only the real address of the From header; several From headers or several addresses: never
    taken out of junk, and flagged as spoofing when one of them is a known sender
  - a display name holding the address of a known sender, other than the real address, is flagged as spoofing
  - Authentication-Results: a ';' or a '(' inside quotes no longer splits the results (forged envelope sender),
    unclosed quotes or comments, a duplicate spf or dmarc result and dmarc=pass without header.from are failures
  - Received-SPF: keys read one by one (a HELO holding « envelope-from= » is ignored), identity must be mailfrom;
    hidden option CRMCLIENTCONNECTOR_ANTISPAM_DISABLE_RECEIVED_SPF to never use this header
  - same rules for the check of the DoliConnector extension (REST API antispam/check)

## 1.5.9

- FIX: the card of an email account saved the anti-spam fields posted with it (server, connection mode, owner,
  password...), although they are hidden and reserved to the « Anti-spam » tab: a user allowed to change the email
  accounts could set his own server and receive the password of the Dovecot master user when testing the
  connection. These fields are no longer saved by the card, nor searchable in the list.

## 1.5.8

- FIX: the home page of the module did not check any right: one of the rights to read the email accounts, the
  email links or the email notes is now required.

## 1.5.7

- FIX: a document created from a mail (proposal, order, ticket, event) was linked to the mail even when the user
  is not allowed to link the mails: the right « Create/Update » of the email links is now required.

## 1.5.6

- FIX: REST API of the links between mails and documents: the documents linked to a mail were listed, and a
  document could be linked, without the right to read its type (orders, invoices...) nor the restriction of a
  sales representative to his thirdparties. These documents are now left out, and linking them is refused.

## 1.5.5

- FIX: REST API of the anti-spam alerts: the alerts of any mailbox were returned to a user allowed to read the
  thirdparties. The rights of the anti-spam journal now apply (all the mailboxes, or the mailboxes of the user),
  and the name of a thirdparty is left out for a sales representative not allowed to see it.

## 1.5.4

- FIX: anti-spam: the connection of a mailbox (connection mode, own server, port, encryption) and its owner can
  only be changed by an administrator. A user allowed to change the email accounts could set his own server and
  receive the password of the Dovecot master user, or the password of the mailbox, when testing the connection.
  - REST API: when an email account is created or updated, these fields are ignored for a user who is not an
    administrator, the technical fields and the password are always ignored, and the encrypted password is no
    longer returned.

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
