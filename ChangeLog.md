# CHANGELOG CRMCLIENTCONNECTOR FOR [DOLIBARR ERP CRM](https://www.dolibarr.org)

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
