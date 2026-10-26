# CHANGELOG CRMCLIENTCONNECTOR FOR [DOLIBARR ERP CRM](https://www.dolibarr.org)

## 1.8.0

- NEW: a project created from DoliConnector (« New project » action of the popup) is linked to the email it was
  created from, like proposals, orders, tickets and agenda events
  - trigger PROJECT_CREATE and hook context projectcard: disable then enable the module to register the new
    hook context

## 1.7.0

- NEW: German, Italian and Spanish translations, Dutch translation completed: every key of the module (anti-spam
  included) in the 6 languages en_US, fr_FR, de_DE, it_IT, es_ES and nl_NL
  - same vocabulary everywhere: « notes » on emails (instead of « email user messages »), « comptes mail » in
    French, links between emails and documents

## 1.6.0

- NEW: Dutch translation (langs/nl_NL)

## 1.5.20

- FIX: translations: the status of the email accounts was written in French (Brouillon / Actif / Désactivé), it now
  follows the language of the user (core keys Draft / Enabled / Disabled); the titles of the left menus and the
  description of the module are translated, and the labels of the rights of the module are added

## 1.5.19

- FIX: REST API: a sales representative restricted to his thirdparties got an HTTP 403 when reading, changing or
  deleting an email account, a link or a note by its id (even his own note, from DoliConnector): the check of the
  thirdparty was made on tables that have no thirdparty. The rights of the module (and the author of a note) are
  the access rule of these objects.

## 1.5.18

- FIX: hardening of the REST API:
  - documents linked to a mail: a project is only listed, linked or unlinked when the user is allowed on it, a
    link can only be removed for a document the user can see, and the categories of a document need the access to
    the document itself
  - GET emailusermsgs with accountEmail and msgId returned the notes of all the mails, it now returns the notes of
    this mail only
  - email accounts: the anti-spam connection (login, server, last error) is only returned to administrators, the
    encrypted password never, and the list can no longer be filtered on the anti-spam fields nor the owner
  - the SQL errors are written in the log instead of being returned to the caller

## 1.5.17

- FIX: hardening of the pages and of the anti-spam:
  - external users (users of a thirdparty) can no longer open the email accounts, notes, anti-spam pages and the
    home page of the module (only the two lists refused them)
  - sending an email from the card of an email account or of a note needs the right to change it, and a copy of an
    email account no longer copies its anti-spam settings (connection, password, owner)
  - anti-spam tab: IMAP folders whose path holds quotes or angle brackets are left out of the pick list and never
    saved, and the IMAP error messages are escaped
  - DKIM / SPF alignment: a top level domain alone (ex: « fr ») is never aligned with the sender domain

## 1.5.16

- FIX: the mail to link, kept when a create page is opened from DoliConnector (accountEmail, msgId), was used by the
  next creation of any proposal, order, ticket or event, even much later: a link to a create page sent to a user
  attached his next document to a mail chosen by the sender. It is now used only by the creation of the same type
  of document, within one hour, and an invalid address or message-id no longer prevents the creation of the
  document.

## 1.5.15

- FIX: CSRF on the « Anti-spam » tab of an email account with Dolibarr 18: the scan now, connection test and edition
  could be run by a link or an image without the security token (the default CSRF level of Dolibarr 18 only checks
  the token of GET actions with some names). Every action of the tab now needs the token.

## 1.5.14

- FIX: REST API antispam/check: the name of the contact whose address is spoofed was returned without checking the
  right to read the contacts, the restriction of a sales representative to his thirdparties nor the private contacts
  of the other users. The spoofing alert is still given, without the name of a contact the user can't see.

## 1.5.13

- FIX: notes on mails and links between mails and documents: the REST API copied every posted field into the object,
  so a note could be given another author or date, or moved to another mail, and a link to another mailbox. Any user
  allowed to change or delete the notes could also change or delete the notes of the other users.
  - REST API: only the text and the mail of a note, the mailbox and message-id of a link are read (checked), the
    other posted fields are ignored
  - a note can only be changed or deleted by its author or an administrator (REST API, card, mass delete of the
    list), like in the DoliConnector extension
  - the update of an email account or of a link no longer ends with an HTTP 500 although it was saved, and deleting
    an email account, a link or a note no longer runs the deletion twice

## 1.5.12

- FIX: anti-spam: with the Dovecot master user, the login of a mailbox is its address, so a user allowed to change
  the email accounts could have any mailbox of the server opened (folders, scan, log) by changing the address.
  Opening the mailbox (connection test, scan now, folders read on the server, activation) is now reserved to
  administrators, a new address set by another user stops the scan of the mailbox until an administrator enables it
  again, and the IMAP login (which holds the name of the master user) is only shown to administrators.

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
