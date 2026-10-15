<?php
/* Copyright (C) 2026 THERSANE
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file        crmclientconnector/class/antispamscanner.class.php
 * \ingroup     crmclientconnector
 * \brief       Scan of the junk folders of the mailboxes (scheduled job)
 */

dol_include_once('/crmclientconnector/class/emailaccount.class.php');
dol_include_once('/crmclientconnector/class/antispamimap.class.php');
dol_include_once('/crmclientconnector/class/antispamauth.class.php');
dol_include_once('/crmclientconnector/class/antispamwhitelist.class.php');

/**
 * Scan of the junk folders of the mailboxes.
 *
 * For each mailbox with the anti-spam on, each junk folder is read from the last message seen (UIDVALIDITY + last
 * UID kept in llx_crmclientconnector_antispam_folder, so a message is read only once). Only the headers are read and
 * the messages stay unread. An email of a known sender (see CrmClientConnectorAntispamWhitelist) authenticated by our
 * receiving server (see CrmClientConnectorAntispamAuth) is moved back to the inbox, with the keyword $NotJunk when the
 * task mark_notjunk is on. A known sender not authenticated is only written in the log.
 */
class CrmClientConnectorAntispamScanner
{
	const ACTION_RESCUED = 'rescued';
	const ACTION_NOT_RESCUED = 'notrescued';
	const ACTION_BLOCKED = 'blocked';

	/**
	 * @var DoliDB Database handler
	 */
	public $db;

	/**
	 * @var string Last error
	 */
	public $error = '';

	/**
	 * @var string[] Errors
	 */
	public $errors = array();

	/**
	 * @var string Output of the scheduled job
	 */
	public $output = '';

	/**
	 * @var CrmClientConnectorAntispamWhitelist|null Known senders, loaded once per scan
	 */
	protected $whitelist = null;

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Scheduled job: scan all the mailboxes with the anti-spam on
	 *
	 * @return int	0 if OK, 1 if a mailbox could not be scanned (see the output and the mailbox)
	 */
	public function runScheduledScan()
	{
		global $langs;

		$langs->loadLangs(array('crmclientconnector@crmclientconnector'));
		$this->output = '';

		if (!CrmClientConnectorAntispamImap::isEnabled()) {
			$this->output = $langs->transnoentities('AntispamScanDisabled');
			return 0;
		}

		$sql = "SELECT rowid FROM ".$this->db->prefix()."crmclientconnector_emailaccount";
		$sql .= " WHERE antispam_active = 1 AND status = 1";
		$sql .= " ORDER BY rowid";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$this->output = $this->error;
			return 1;
		}
		$ids = array();
		while ($obj = $this->db->fetch_object($resql)) {
			$ids[] = (int) $obj->rowid;
		}
		$this->db->free($resql);

		$nbErrors = 0;
		$lines = array();
		foreach ($ids as $id) {
			$account = new EmailAccount($this->db);
			if ($account->fetch($id) <= 0) {
				continue;
			}
			$stats = $this->scanAccount($account);
			if ($stats === null) {
				$nbErrors++;
				$lines[] = $account->email_account.': '.$this->error;
			} else {
				$lines[] = $account->email_account.': '.$langs->transnoentities('AntispamScanSummary', $stats['read'], $stats['rescued'], $stats['notrescued'], $stats['blocked']);
			}
		}

		$this->output = empty($lines) ? $langs->transnoentities('AntispamScanNoMailbox') : implode("\n", $lines);

		$purged = $this->purgeLog();
		if ($purged > 0) {
			$this->output .= "\n".$langs->transnoentities('AntispamLogPurged', $purged);
		}

		return $nbErrors > 0 ? 1 : 0;
	}

	/**
	 * Scan the junk folders of a mailbox
	 *
	 * @param	EmailAccount	$account	Mailbox
	 * @return	array{read:int,rescued:int,notrescued:int,blocked:int}|null		Counters, null on error (see $this->error)
	 */
	public function scanAccount($account)
	{
		global $langs;

		$this->error = '';
		$stats = array('read' => 0, 'rescued' => 0, 'notrescued' => 0, 'blocked' => 0);

		$folders = array_values(array_filter($account->getAntispamJunkFolders(), function ($folder) {
			return strtoupper($folder) !== 'INBOX';
		}));
		if (empty($folders) || !CrmClientConnectorAntispamWhitelist::isUsedBy($account)) {
			// Nothing to do for this mailbox (no junk folder chosen or no rescue task on)
			$this->saveAccountScan($account, '');
			return $stats;
		}

		if ($this->whitelist === null) {
			$this->whitelist = new CrmClientConnectorAntispamWhitelist($this->db);
			if ($this->whitelist->load() < 0) {
				$this->error = $this->whitelist->error;
				$this->whitelist = null;
				return null;
			}
		}

		$imap = new CrmClientConnectorAntispamImap($this->db);
		$client = $imap->connect($account);
		if (!$client) {
			$this->error = $imap->error;
			$this->saveAccountScan($account, $this->error);
			return null;
		}

		$errors = array();
		foreach ($folders as $folder) {
			try {
				$folderStats = $this->scanJunkFolder($client, $account, $folder);
			} catch (Exception $e) {
				$folderStats = null;
				$this->error = $langs->transnoentities('AntispamErrorFolder', CrmClientConnectorAntispamImap::decodeFolderName($folder), $e->getMessage());
			}
			if ($folderStats === null) {
				$errors[] = $this->error;
				continue;
			}
			foreach ($folderStats as $key => $value) {
				$stats[$key] += $value;
			}
		}

		try {
			$client->disconnect();
		} catch (Exception $e) {
			// Already disconnected
		}

		$this->error = implode(' / ', $errors);
		$this->saveAccountScan($account, $this->error);

		return empty($errors) ? $stats : null;
	}

	/**
	 * Read the new messages of a junk folder and move back to the inbox the ones of authenticated known senders
	 *
	 * @param	\Webklex\PHPIMAP\Client	$client		Connected client
	 * @param	EmailAccount			$account	Mailbox
	 * @param	string					$folder		IMAP path of the junk folder
	 * @return	array{read:int,rescued:int,notrescued:int,blocked:int}|null	Null on error (see $this->error)
	 */
	protected function scanJunkFolder($client, $account, $folder)
	{
		global $langs;

		$stats = array('read' => 0, 'rescued' => 0, 'notrescued' => 0, 'blocked' => 0);
		$connection = $client->getConnection();

		$status = $connection->selectFolder($folder);
		if (!is_array($status) || empty($status['uidvalidity'])) {
			$this->error = $langs->transnoentities('AntispamErrorFolderNotFound', CrmClientConnectorAntispamImap::decodeFolderName($folder));
			return null;
		}

		// Messages not read yet: after the last UID seen, or the last days on the first scan (or when the folder was
		// recreated on the server, then its UIDs are new)
		$state = $this->getFolderState($account->id, $folder);
		if ($state && (int) $state['uidvalidity'] === (int) $status['uidvalidity']) {
			$lastUid = (int) $state['last_uid'];
			$uids = $connection->search(array('UID', ($lastUid + 1).':*'), true);
		} else {
			$lastUid = 0;
			$since = dol_now() - getDolGlobalInt('CRMCLIENTCONNECTOR_ANTISPAM_FIRST_SCAN_DAYS', 7) * 86400;
			$uids = $connection->search(array('SINCE', self::formatImapDate($since)), true);
		}
		$uids = array_values(array_filter(array_map('intval', (array) $uids), function ($uid) use ($lastUid) {
			// "UID n:*" always returns the last message, even if its UID is lower than n
			return $uid > $lastUid;
		}));
		sort($uids);
		$uids = array_slice($uids, 0, max(1, getDolGlobalInt('CRMCLIENTCONNECTOR_ANTISPAM_MAX_PER_RUN', 200)));

		$targetFolder = getDolGlobalString('CRMCLIENTCONNECTOR_ANTISPAM_TARGET_FOLDER', 'INBOX');
		$authservIds = self::getAuthservIds();
		$markNotJunk = CrmClientConnectorAntispamImap::isTaskEnabled($account, 'mark_notjunk');
		$maxUid = $lastUid;

		foreach (array_chunk($uids, 50) as $chunk) {
			$headersByUid = $connection->headers($chunk, 'RFC822', true);
			foreach ($chunk as $uid) {
				$maxUid = max($maxUid, $uid);
				if (!isset($headersByUid[$uid]) || !is_string($headersByUid[$uid])) {
					continue;
				}
				$stats['read']++;

				$headers = CrmClientConnectorAntispamAuth::parseHeaders($headersByUid[$uid]);
				$fromAddress = CrmClientConnectorAntispamAuth::extractAddress(CrmClientConnectorAntispamAuth::getHeader($headers, 'from'));
				if ($fromAddress === '') {
					continue;
				}
				// Blacklisted customer or supplier: left in junk, written in the log so that nobody wonders why
				// (a blacklisted unknown address is not logged, junk folders are full of them)
				if ($this->whitelist->hasRule($fromAddress, $account, CrmClientConnectorAntispamRule::TYPE_BLACK)) {
					$dolibarrSender = $this->whitelist->getDolibarrSender($fromAddress);
					if ($dolibarrSender) {
						$stats['blocked']++;
						$this->addLog($account, $folder, $headers, $fromAddress, $dolibarrSender, self::ACTION_BLOCKED, array('result' => '', 'detail' => ''));
					}
					continue;
				}
				$sender = $this->whitelist->find($fromAddress, $account);
				if (!$sender) {
					continue;
				}

				$auth = CrmClientConnectorAntispamAuth::check($headers, $fromAddress, $authservIds);
				$action = self::ACTION_NOT_RESCUED;
				if ($auth['result'] === CrmClientConnectorAntispamAuth::RESULT_PASS) {
					if ($this->moveToInbox($connection, $uid, $targetFolder, $markNotJunk)) {
						$action = self::ACTION_RESCUED;
					} else {
						$auth['detail'] .= ' / '.$langs->transnoentities('AntispamErrorMove');
					}
				}
				$stats[$action]++;
				$this->addLog($account, $folder, $headers, $fromAddress, $sender, $action, $auth);
			}
		}

		$this->saveFolderState($account->id, $folder, (int) $status['uidvalidity'], $maxUid);

		return $stats;
	}

	/**
	 * Move a message of the selected folder to the inbox
	 *
	 * @param	\Webklex\PHPIMAP\Connection\Protocols\ImapProtocol	$connection		Connection, junk folder selected
	 * @param	int		$uid			UID of the message
	 * @param	string	$targetFolder	Destination
	 * @param	bool	$markNotJunk	Add the keywords $NotJunk / NonJunk and remove $Junk / Junk
	 * @return	bool
	 */
	protected function moveToInbox($connection, $uid, $targetFolder, $markNotJunk)
	{
		if ($markNotJunk) {
			// Keywords set before the move, so the moved message keeps them. Names used by Thunderbird and most clients.
			$connection->store(array('$NotJunk', 'NonJunk'), $uid, null, '+', true, true);
			$connection->store(array('$Junk', 'Junk'), $uid, null, '-', true, true);
		}

		if ($connection->moveMessage($targetFolder, $uid, null, true)) {
			return true;
		}

		// Server without the MOVE extension: copy, then delete from the junk folder
		if (!$connection->copyMessage($targetFolder, $uid, null, true)) {
			return false;
		}
		$connection->store(array('\Deleted'), $uid, null, '+', true, true);
		$connection->expunge();
		return true;
	}

	/**
	 * Delete the lines of the log older than the retention of the setup (0 = keep everything)
	 *
	 * @return	int		Number of lines deleted, -1 on error
	 */
	public function purgeLog()
	{
		global $conf;

		$days = getDolGlobalInt('CRMCLIENTCONNECTOR_ANTISPAM_LOG_RETENTION_DAYS', 90);
		if ($days <= 0) {
			return 0;
		}
		$sql = "DELETE FROM ".$this->db->prefix()."crmclientconnector_antispam_log";
		$sql .= " WHERE entity = ".((int) $conf->entity)." AND date_log < '".$this->db->idate(dol_now() - $days * 86400)."'";
		$resql = $this->db->query($sql);
		if (!$resql) {
			dol_syslog(__METHOD__.' '.$this->db->lasterror(), LOG_ERR);
			return -1;
		}
		return (int) $this->db->affected_rows($resql);
	}

	/**
	 * Date for an IMAP search (ex: 8-Oct-2026), month always in English
	 *
	 * @param	int		$timestamp	Date
	 * @return	string
	 */
	public static function formatImapDate($timestamp)
	{
		$months = array('Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec');
		$date = dol_getdate($timestamp, true);
		return $date['mday'].'-'.$months[$date['mon'] - 1].'-'.$date['year'];
	}

	/**
	 * Ids of our receiving servers whose Authentication-Results are trusted (setup, comma separated)
	 *
	 * @return string[]
	 */
	public static function getAuthservIds()
	{
		return array_values(array_filter(array_map('trim', explode(',', getDolGlobalString('CRMCLIENTCONNECTOR_ANTISPAM_AUTHSERV_IDS'))), 'strlen'));
	}

	/**
	 * Last UID seen in a folder
	 *
	 * @param	int		$accountId	Mailbox id
	 * @param	string	$folder		IMAP path
	 * @return	array{uidvalidity:int,last_uid:int}|null
	 */
	protected function getFolderState($accountId, $folder)
	{
		$sql = "SELECT uidvalidity, last_uid FROM ".$this->db->prefix()."crmclientconnector_antispam_folder";
		$sql .= " WHERE fk_emailaccount = ".((int) $accountId)." AND folder = '".$this->db->escape($folder)."'";
		$resql = $this->db->query($sql);
		if (!$resql) {
			return null;
		}
		$obj = $this->db->fetch_object($resql);
		$this->db->free($resql);
		return $obj ? array('uidvalidity' => (int) $obj->uidvalidity, 'last_uid' => (int) $obj->last_uid) : null;
	}

	/**
	 * Save the last UID seen in a folder
	 *
	 * @param	int		$accountId		Mailbox id
	 * @param	string	$folder			IMAP path
	 * @param	int		$uidvalidity	UIDVALIDITY of the folder
	 * @param	int		$lastUid		Last UID read
	 * @return	void
	 */
	protected function saveFolderState($accountId, $folder, $uidvalidity, $lastUid)
	{
		$sql = "UPDATE ".$this->db->prefix()."crmclientconnector_antispam_folder";
		$sql .= " SET uidvalidity = ".((int) $uidvalidity).", last_uid = ".((int) $lastUid);
		$sql .= " WHERE fk_emailaccount = ".((int) $accountId)." AND folder = '".$this->db->escape($folder)."'";
		$resql = $this->db->query($sql);
		if ($resql && $this->db->affected_rows($resql) > 0) {
			return;
		}
		if ($this->getFolderState($accountId, $folder) !== null) {
			// Same values as before (no row changed)
			return;
		}
		$sql = "INSERT INTO ".$this->db->prefix()."crmclientconnector_antispam_folder (fk_emailaccount, folder, uidvalidity, last_uid)";
		$sql .= " VALUES (".((int) $accountId).", '".$this->db->escape($folder)."', ".((int) $uidvalidity).", ".((int) $lastUid).")";
		$this->db->query($sql);
	}

	/**
	 * Save the date of the scan and the last error of a mailbox
	 *
	 * @param	EmailAccount	$account	Mailbox
	 * @param	string			$error		Error, '' if OK
	 * @return	void
	 */
	protected function saveAccountScan($account, $error)
	{
		$now = dol_now();
		$sql = "UPDATE ".$this->db->prefix()."crmclientconnector_emailaccount";
		$sql .= " SET antispam_date_last_scan = '".$this->db->idate($now)."'";
		$sql .= ", antispam_last_error = ".($error !== '' ? "'".$this->db->escape(dol_trunc($error, 1000, 'right', 'UTF-8', 1))."'" : "NULL");
		$sql .= " WHERE rowid = ".((int) $account->id);
		$this->db->query($sql);
		$account->antispam_date_last_scan = $now;
		$account->antispam_last_error = $error !== '' ? $error : null;
	}

	/**
	 * Write a line in the log
	 *
	 * @param	EmailAccount	$account		Mailbox
	 * @param	string			$folder			IMAP path of the folder
	 * @param	array<int,array{0:string,1:string}>	$headers	Headers of the email
	 * @param	string			$fromAddress	Sender address
	 * @param	array{socid:int,contactid:int,customer:bool,supplier:bool}	$sender	Known sender
	 * @param	string			$action			self::ACTION_*
	 * @param	array{result:string,detail:string}	$auth	Authentication of the sender
	 * @return	void
	 */
	protected function addLog($account, $folder, $headers, $fromAddress, $sender, $action, $auth)
	{
		global $conf;

		$subject = self::cleanText(CrmClientConnectorAntispamAuth::decodeMime(CrmClientConnectorAntispamAuth::getHeader($headers, 'subject')), 255);
		$messageId = self::cleanText(trim(CrmClientConnectorAntispamAuth::getHeader($headers, 'message-id'), " <>"), 255);
		$dateMail = strtotime(CrmClientConnectorAntispamAuth::getHeader($headers, 'date'));

		$sql = "INSERT INTO ".$this->db->prefix()."crmclientconnector_antispam_log";
		$sql .= " (entity, fk_emailaccount, date_log, folder, message_id, from_email, subject, date_mail, fk_soc, fk_socpeople, action, auth_result, auth_detail)";
		$sql .= " VALUES (".((int) $conf->entity).", ".((int) $account->id).", '".$this->db->idate(dol_now())."'";
		$sql .= ", '".$this->db->escape(self::cleanText($folder, 255))."'";
		$sql .= ", '".$this->db->escape($messageId)."'";
		$sql .= ", '".$this->db->escape(self::cleanText($fromAddress, 255))."'";
		$sql .= ", '".$this->db->escape($subject)."'";
		$sql .= ", ".($dateMail ? "'".$this->db->idate($dateMail)."'" : "NULL");
		$sql .= ", ".($sender['socid'] > 0 ? (int) $sender['socid'] : "NULL");
		$sql .= ", ".($sender['contactid'] > 0 ? (int) $sender['contactid'] : "NULL");
		$sql .= ", '".$this->db->escape($action)."'";
		$sql .= ", '".$this->db->escape($auth['result'])."'";
		$sql .= ", '".$this->db->escape(self::cleanText($auth['detail'], 255))."'";
		$sql .= ")";
		if (!$this->db->query($sql)) {
			dol_syslog(__METHOD__.' '.$this->db->lasterror(), LOG_ERR);
		}
	}

	/**
	 * Text storable in the database: valid UTF-8, without 4 bytes characters (emojis, the tables may be utf8 and not
	 * utf8mb4) and cut to a length
	 *
	 * @param	string	$text		Text
	 * @param	int		$length		Max length
	 * @return	string
	 */
	public static function cleanText($text, $length)
	{
		$text = mb_convert_encoding((string) $text, 'UTF-8', 'UTF-8');
		$text = (string) preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $text);
		$text = trim((string) preg_replace('/\s+/u', ' ', $text));
		return dol_trunc($text, $length, 'right', 'UTF-8', 1);
	}
}
