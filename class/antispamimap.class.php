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
 * \file        crmclientconnector/class/antispamimap.class.php
 * \ingroup     crmclientconnector
 * \brief       IMAP access of the anti-spam feature (Dovecot master user)
 */

/**
 * IMAP access of the anti-spam feature.
 *
 * A mailbox is opened either with the Dovecot master user (login "<mailbox><separator><master user>" and the master
 * password, no mailbox password stored in Dolibarr) or, without master user, with its own login and password (stored
 * encrypted with dolEncrypt()) and optionally its own server. Uses the Webklex php-imap library shipped with Dolibarr
 * (same version 2.7.2 from Dolibarr 18 to 23).
 */
class CrmClientConnectorAntispamImap
{
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
	 * @var string Connection mode of the last connect() call ('master' or 'login'), for the error message
	 */
	protected $lastAuthMode = 'master';

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
	 * Is the anti-spam feature enabled in the module setup
	 *
	 * @return bool
	 */
	public static function isEnabled()
	{
		return isModEnabled('crmclientconnector') && getDolGlobalInt('CRMCLIENTCONNECTOR_ANTISPAM_ENABLED') > 0;
	}

	/**
	 * Tasks of the anti-spam that can be enabled one by one: default in the setup (constant
	 * CRMCLIENTCONNECTOR_ANTISPAM_TASK_<TASK>, on when not set), forced on or off per mailbox.
	 *
	 * @return array<string,array{group:string,label:string,help:string}>	Task => group and translation keys
	 */
	public static function getTasks()
	{
		return array(
			'rescue_customers' => array('group' => 'rescue', 'label' => 'AntispamTaskRescueCustomers', 'help' => 'AntispamTaskRescueCustomersHelp'),
			'rescue_suppliers' => array('group' => 'rescue', 'label' => 'AntispamTaskRescueSuppliers', 'help' => 'AntispamTaskRescueSuppliersHelp'),
			'rescue_contacts' => array('group' => 'rescue', 'label' => 'AntispamTaskRescueContacts', 'help' => 'AntispamTaskRescueContactsHelp'),
			'rescue_manual' => array('group' => 'rescue', 'label' => 'AntispamTaskRescueManual', 'help' => 'AntispamTaskRescueManualHelp'),
			'mark_notjunk' => array('group' => 'rescue', 'label' => 'AntispamTaskMarkNotJunk', 'help' => 'AntispamTaskMarkNotJunkHelp'),
			'spoof_junk' => array('group' => 'spoof', 'label' => 'AntispamTaskSpoofJunk', 'help' => 'AntispamTaskSpoofJunkHelp'),
			'spoof_inbox' => array('group' => 'spoof', 'label' => 'AntispamTaskSpoofInbox', 'help' => 'AntispamTaskSpoofInboxHelp'),
		);
	}

	/**
	 * Name of the setup constant of a task
	 *
	 * @param	string	$task	Task (key of getTasks())
	 * @return	string
	 */
	public static function getTaskConstName($task)
	{
		return 'CRMCLIENTCONNECTOR_ANTISPAM_TASK_'.strtoupper($task);
	}

	/**
	 * Is a task on by default (module setup)
	 *
	 * @param	string	$task	Task (key of getTasks())
	 * @return	bool
	 */
	public static function isTaskEnabledByDefault($task)
	{
		// On when the constant was never saved
		return (bool) getDolGlobalInt(self::getTaskConstName($task), 1);
	}

	/**
	 * Is a task on for a mailbox: forced value of the mailbox, else the default of the setup
	 *
	 * @param	EmailAccount	$account	Email account
	 * @param	string			$task		Task (key of getTasks())
	 * @return	bool
	 */
	public static function isTaskEnabled($account, $task)
	{
		$overrides = $account->getAntispamTaskOverrides();
		if (isset($overrides[$task])) {
			return (bool) $overrides[$task];
		}
		return self::isTaskEnabledByDefault($task);
	}

	/**
	 * Is the Dovecot master user set up (name and password)
	 *
	 * @return bool
	 */
	public static function isMasterConfigured()
	{
		return getDolGlobalString('CRMCLIENTCONNECTOR_ANTISPAM_MASTER_USER') !== ''
			&& getDolGlobalString('CRMCLIENTCONNECTOR_ANTISPAM_MASTER_PASSWORD') !== '';
	}

	/**
	 * Connection mode of a mailbox
	 *
	 * @param	EmailAccount	$account	Email account
	 * @return	string						'master' (Dovecot master user) or 'login' (own login and password)
	 */
	public static function getAuthMode($account)
	{
		return $account->antispam_auth_mode === 'login' ? 'login' : 'master';
	}

	/**
	 * IMAP login of the master user for a mailbox
	 *
	 * @param	string	$mailbox	Mailbox address (ex: contact@example.com)
	 * @return	string				Ex: contact@example.com*doliconnector
	 */
	public static function getMasterLogin($mailbox)
	{
		$separator = getDolGlobalString('CRMCLIENTCONNECTOR_ANTISPAM_MASTER_SEPARATOR', '*');
		return trim($mailbox).$separator.getDolGlobalString('CRMCLIENTCONNECTOR_ANTISPAM_MASTER_USER');
	}

	/**
	 * IMAP login used for a mailbox
	 *
	 * @param	EmailAccount	$account	Email account
	 * @return	string
	 */
	public static function getLogin($account)
	{
		if (self::getAuthMode($account) == 'login') {
			return trim((string) $account->antispam_login) !== '' ? trim($account->antispam_login) : trim($account->email_account);
		}
		return self::getMasterLogin($account->email_account);
	}

	/**
	 * IMAP server of a mailbox: its own server if set, else the default server of the setup
	 *
	 * @param	EmailAccount	$account	Email account
	 * @return	array{host:string,port:int,encryption:string}
	 */
	public static function getServer($account)
	{
		if (trim((string) $account->antispam_imap_host) !== '') {
			$encryption = (string) $account->antispam_imap_encryption;
			return array(
				'host' => trim($account->antispam_imap_host),
				'port' => $account->antispam_imap_port > 0 ? (int) $account->antispam_imap_port : ($encryption == 'ssl' || $encryption == '' ? 993 : 143),
				'encryption' => $encryption !== '' ? $encryption : 'ssl',
			);
		}
		return array(
			'host' => getDolGlobalString('CRMCLIENTCONNECTOR_ANTISPAM_IMAP_HOST'),
			'port' => getDolGlobalInt('CRMCLIENTCONNECTOR_ANTISPAM_IMAP_PORT', 993),
			'encryption' => getDolGlobalString('CRMCLIENTCONNECTOR_ANTISPAM_IMAP_ENCRYPTION', 'ssl'),
		);
	}

	/**
	 * Can the mailbox be opened: server known and credentials of its connection mode set
	 *
	 * @param	EmailAccount	$account	Email account
	 * @return	string						'' if OK, else the reason (plain text)
	 */
	public static function getConfigurationError($account)
	{
		global $langs;

		$server = self::getServer($account);
		if ($server['host'] === '') {
			return $langs->transnoentities('AntispamErrorNoServer');
		}
		if (self::getAuthMode($account) == 'login') {
			if ((string) $account->antispam_password === '') {
				return $langs->transnoentities('AntispamErrorNoPassword');
			}
		} elseif (!self::isMasterConfigured()) {
			return $langs->transnoentities('AntispamErrorNoMaster');
		}
		return '';
	}

	/**
	 * Can an IMAP folder path be stored and shown in a form: no quote, angle bracket nor line break (a folder name is
	 * chosen by the owner of the mailbox)
	 *
	 * @param	string	$path	IMAP folder path
	 * @return	bool
	 */
	public static function isSafeFolderPath($path)
	{
		$path = (string) $path;
		return $path !== '' && strlen($path) <= 255 && !preg_match('/["\'<>\r\n\x00]/', $path);
	}

	/**
	 * Readable name of an IMAP folder path (paths are encoded in modified UTF-7)
	 *
	 * @param	string	$path	IMAP folder path (ex: INBOX/Ind&AOk-sirables)
	 * @return	string			Ex: INBOX/Indésirables
	 */
	public static function decodeFolderName($path)
	{
		return mb_convert_encoding((string) $path, 'UTF-8', 'UTF7-IMAP');
	}

	/**
	 * Open a mailbox
	 *
	 * @param	EmailAccount	$account	Email account
	 * @return	\Webklex\PHPIMAP\Client|null	Connected client, null on error (see $this->error)
	 */
	public function connect($account)
	{
		global $langs;

		$this->error = '';
		$this->errors = array();

		$mailbox = trim((string) $account->email_account);
		if (!isValidEmail($mailbox)) {
			$this->setError($langs->transnoentities('AntispamErrorBadMailbox', $mailbox));
			return null;
		}
		$configurationError = self::getConfigurationError($account);
		if ($configurationError !== '') {
			$this->setError($configurationError);
			return null;
		}

		require_once DOL_DOCUMENT_ROOT.'/core/lib/security.lib.php';
		require_once DOL_DOCUMENT_ROOT.'/includes/webklex/php-imap/vendor/autoload.php';

		$server = self::getServer($account);
		$this->lastAuthMode = self::getAuthMode($account);
		$encryption = in_array($server['encryption'], array('ssl', 'tls')) ? $server['encryption'] : false;
		if (self::getAuthMode($account) == 'login') {
			$password = dolDecrypt((string) $account->antispam_password);
		} else {
			$password = getDolGlobalString('CRMCLIENTCONNECTOR_ANTISPAM_MASTER_PASSWORD');
		}

		// The socket errors (refused connection, rejected certificate...) are PHP warnings, the exception only says
		// "connection failed": keep them for the message instead of printing them in the page
		$warnings = array();
		set_error_handler(function ($errno, $errstr) use (&$warnings) {
			$warnings[] = preg_replace('/^[a-z_]+\(\):\s*/', '', $errstr);
			return true;
		}, E_WARNING | E_NOTICE);

		try {
			$cm = new \Webklex\PHPIMAP\ClientManager();
			$client = $cm->make(array(
				'host'           => $server['host'],
				'port'           => $server['port'],
				'encryption'     => $encryption,
				'validate_cert'  => (bool) getDolGlobalInt('CRMCLIENTCONNECTOR_ANTISPAM_IMAP_VALIDATE_CERT', 1),
				'protocol'       => 'imap',
				'username'       => self::getLogin($account),
				'password'       => $password,
				'authentication' => 'login',
				'timeout'        => getDolGlobalInt('CRMCLIENTCONNECTOR_ANTISPAM_IMAP_TIMEOUT', 20),
			));
			$client->connect();
		} catch (Exception $e) {
			restore_error_handler();
			$this->setError($this->explainException($e, $warnings));
			dol_syslog(__METHOD__.' '.$mailbox.' '.$this->error, LOG_WARNING);
			return null;
		}
		restore_error_handler();

		return $client;
	}

	/**
	 * List the folders of a connected mailbox
	 *
	 * @param	\Webklex\PHPIMAP\Client	$client		Connected client
	 * @return	array<int,array{path:string,name:string,delimiter:string,selectable:bool,junk:bool}>|null	Sorted by name, null on error
	 */
	public function listFolders($client)
	{
		try {
			$items = $client->getConnection()->folders('', '*');
		} catch (Exception $e) {
			$this->setError($this->explainException($e));
			return null;
		}

		$folders = array();
		foreach ((array) $items as $path => $item) {
			$flags = array_map('strtolower', (array) $item['flags']);
			$folders[] = array(
				'path' => (string) $path,
				'name' => self::decodeFolderName($path),
				'delimiter' => (string) $item['delimiter'],
				'selectable' => !in_array('\\noselect', $flags) && !in_array('\\nonexistent', $flags),
				'junk' => in_array('\\junk', $flags),
			);
		}
		usort($folders, function ($a, $b) {
			// INBOX first, then alphabetical
			if (strtoupper($a['path']) === 'INBOX') {
				return -1;
			}
			if (strtoupper($b['path']) === 'INBOX') {
				return 1;
			}
			return strcasecmp($a['name'], $b['name']);
		});

		return $folders;
	}

	/**
	 * Number of messages of a folder (read only EXAMINE, the folder is not modified)
	 *
	 * @param	\Webklex\PHPIMAP\Client	$client		Connected client
	 * @param	string					$path		IMAP folder path
	 * @return	int								Number of messages, -1 on error
	 */
	public function countMessages($client, $path)
	{
		try {
			$status = $client->getConnection()->examineFolder($path);
		} catch (Exception $e) {
			$this->setError($this->explainException($e));
			return -1;
		}
		return (is_array($status) && isset($status['exists'])) ? (int) $status['exists'] : -1;
	}

	/**
	 * Test the connection to a mailbox: login, folders and their number of messages
	 *
	 * @param	EmailAccount	$account	Email account
	 * @return	array{duration:float,folders:array<int,array{path:string,name:string,delimiter:string,selectable:bool,junk:bool,count:int}>}|null	Null on error
	 */
	public function testConnection($account)
	{
		$start = microtime(true);

		$client = $this->connect($account);
		if (!$client) {
			return null;
		}

		$folders = $this->listFolders($client);
		if ($folders === null) {
			$client->disconnect();
			return null;
		}
		foreach ($folders as $i => $folder) {
			$folders[$i]['count'] = $folder['selectable'] ? $this->countMessages($client, $folder['path']) : -1;
		}

		$client->disconnect();

		return array('duration' => round(microtime(true) - $start, 2), 'folders' => $folders);
	}

	/**
	 * Human readable message of an IMAP exception (Webklex wraps the real cause in the previous exception)
	 *
	 * @param	Exception	$e			Exception
	 * @param	string[]	$warnings	PHP warnings raised during the connection
	 * @return	string					Plain text (not HTML, also stored as last error of the mailbox)
	 */
	protected function explainException($e, $warnings = array())
	{
		global $langs;

		foreach ($warnings as $warning) {
			if (stripos($warning, 'certificate verify failed') !== false) {
				return $langs->transnoentities('AntispamErrorCertificate');
			}
		}

		$messages = array();
		$authFailed = false;
		for ($ex = $e; $ex; $ex = $ex->getPrevious()) {
			if ($ex instanceof \Webklex\PHPIMAP\Exceptions\AuthFailedException) {
				$authFailed = true;
			}
			if ($ex->getMessage() !== '' && !in_array($ex->getMessage(), $messages)) {
				$messages[] = $ex->getMessage();
			}
		}

		if ($authFailed) {
			return $langs->transnoentities($this->lastAuthMode == 'login' ? 'AntispamErrorAuthFailedLogin' : 'AntispamErrorAuthFailed');
		}
		// Most precise cause last: the socket warning (ex: "Unable to connect to ssl://host:993 (Connection refused)")
		$lastWarning = end($warnings);
		if ($lastWarning) {
			$messages[] = $lastWarning;
		}
		return $langs->transnoentities('AntispamErrorConnection', implode(' - ', $messages));
	}

	/**
	 * Store an error
	 *
	 * @param	string	$message	Error message
	 * @return	void
	 */
	protected function setError($message)
	{
		$this->error = $message;
		$this->errors[] = $message;
	}
}
