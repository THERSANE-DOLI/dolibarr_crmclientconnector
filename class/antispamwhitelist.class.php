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
 * \file        crmclientconnector/class/antispamwhitelist.class.php
 * \ingroup     crmclientconnector
 * \brief       Known senders of the anti-spam: email addresses of the customers, suppliers and their contacts
 */

dol_include_once('/crmclientconnector/class/antispamimap.class.php');
dol_include_once('/crmclientconnector/class/antispamrule.class.php');

/**
 * Known senders of the anti-spam, read once per scan.
 *
 * Exact addresses only (no domain): email of the active customer (client 1 or 3, prospects only are excluded) or
 * supplier thirdparties, and of the active contacts of these thirdparties, plus the manual whitelist. Which ones are
 * used for a mailbox depends on its tasks rescue_customers, rescue_suppliers, rescue_contacts and rescue_manual. An
 * address of the manual blacklist is never a known sender.
 */
class CrmClientConnectorAntispamWhitelist
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
	 * @var array<string,array<int,array{socid:int,contactid:int,customer:bool,supplier:bool}>> Address => entries
	 */
	protected $senders = array();

	/**
	 * @var array<string,array<int,array{type:string,accountId:int}>> Address => manual rules (accountId 0 = all mailboxes)
	 */
	protected $rules = array();

	/**
	 * @var bool
	 */
	protected $loaded = false;

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
	 * Load the known senders
	 *
	 * @param	string	$onlyEmail	Load only this address (check of one email, faster than the whole list)
	 * @return	int					Number of addresses, -1 on error
	 */
	public function load($onlyEmail = '')
	{
		$this->senders = array();
		$onlyEmail = strtolower(trim($onlyEmail));
		// An email field may hold several addresses: rough filter in SQL, exact match done by add() / find()
		$filterSoc = $onlyEmail !== '' ? " AND s.email LIKE '%".$this->db->escape($this->db->escapeforlike($onlyEmail))."%'" : '';
		$filterContact = $onlyEmail !== '' ? " AND c.email LIKE '%".$this->db->escape($this->db->escapeforlike($onlyEmail))."%'" : '';

		$sql = "SELECT s.rowid, s.email, s.client, s.fournisseur";
		$sql .= " FROM ".$this->db->prefix()."societe as s";
		$sql .= " WHERE s.entity IN (".getEntity('societe').")";
		$sql .= " AND s.status = 1 AND s.email IS NOT NULL AND s.email <> ''";
		$sql .= " AND (s.client IN (1, 3) OR s.fournisseur = 1)".$filterSoc;
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		while ($obj = $this->db->fetch_object($resql)) {
			$this->add($obj->email, (int) $obj->rowid, 0, (int) $obj->client, (int) $obj->fournisseur);
		}
		$this->db->free($resql);

		$sql = "SELECT c.rowid, c.email, c.fk_soc, s.client, s.fournisseur";
		$sql .= " FROM ".$this->db->prefix()."socpeople as c";
		$sql .= " INNER JOIN ".$this->db->prefix()."societe as s ON s.rowid = c.fk_soc";
		$sql .= " WHERE c.entity IN (".getEntity('contact').")";
		$sql .= " AND c.statut = 1 AND c.email IS NOT NULL AND c.email <> ''";
		$sql .= " AND s.status = 1 AND (s.client IN (1, 3) OR s.fournisseur = 1)".$filterContact;
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		while ($obj = $this->db->fetch_object($resql)) {
			$this->add($obj->email, (int) $obj->fk_soc, (int) $obj->rowid, (int) $obj->client, (int) $obj->fournisseur);
		}
		$this->db->free($resql);

		$this->rules = array();
		$ruleStatic = new CrmClientConnectorAntispamRule($this->db);
		$rules = $ruleStatic->fetchAll();
		if ($rules === null) {
			$this->error = $ruleStatic->error;
			return -1;
		}
		foreach ($rules as $rule) {
			if ($onlyEmail !== '' && $rule['email'] !== $onlyEmail) {
				continue;
			}
			$this->rules[$rule['email']][] = array('type' => $rule['type'], 'accountId' => $rule['fk_emailaccount']);
		}

		$this->loaded = true;
		return count($this->senders);
	}

	/**
	 * Add the addresses of an email field (it may hold several addresses)
	 *
	 * @param	string	$emails		Email field
	 * @param	int		$socid		Thirdparty id
	 * @param	int		$contactid	Contact id, 0 for the thirdparty itself
	 * @param	int		$client		Field client of the thirdparty
	 * @param	int		$fournisseur	Field fournisseur of the thirdparty
	 * @return	void
	 */
	protected function add($emails, $socid, $contactid, $client, $fournisseur)
	{
		foreach (preg_split('/[\s,;]+/', strtolower((string) $emails)) as $email) {
			$email = trim($email, " \t<>\"'");
			if ($email === '' || strpos($email, '@') === false) {
				continue;
			}
			$this->senders[$email][] = array(
				'socid' => $socid,
				'contactid' => $contactid,
				'customer' => in_array($client, array(1, 3)),
				'supplier' => $fournisseur == 1,
			);
		}
	}

	/**
	 * Known sender matching an address, with the tasks of a mailbox
	 *
	 * @param	string			$email		Sender address
	 * @param	EmailAccount	$account	Mailbox (its tasks choose the sources)
	 * @return	array{socid:int,contactid:int,customer:bool,supplier:bool}|null	The thirdparty is preferred to a contact
	 */
	public function find($email, $account)
	{
		$email = strtolower(trim($email));
		if ($this->hasRule($email, $account, CrmClientConnectorAntispamRule::TYPE_BLACK)) {
			return null;
		}
		$manual = null;
		if (CrmClientConnectorAntispamImap::isTaskEnabled($account, 'rescue_manual') && $this->hasRule($email, $account, CrmClientConnectorAntispamRule::TYPE_WHITE)) {
			$manual = array('socid' => 0, 'contactid' => 0, 'customer' => false, 'supplier' => false);
		}
		if (!isset($this->senders[$email])) {
			return $manual;
		}

		$customers = CrmClientConnectorAntispamImap::isTaskEnabled($account, 'rescue_customers');
		$suppliers = CrmClientConnectorAntispamImap::isTaskEnabled($account, 'rescue_suppliers');
		$contacts = CrmClientConnectorAntispamImap::isTaskEnabled($account, 'rescue_contacts');

		$found = null;
		foreach ($this->senders[$email] as $entry) {
			if (!(($entry['customer'] && $customers) || ($entry['supplier'] && $suppliers))) {
				continue;
			}
			if ($entry['contactid'] > 0 && !$contacts) {
				continue;
			}
			if ($entry['contactid'] == 0) {
				return $entry;
			}
			if ($found === null) {
				$found = $entry;
			}
		}
		return $found !== null ? $found : $manual;
	}

	/**
	 * Customer, supplier or contact of Dolibarr with an address, whatever the tasks of the mailbox
	 *
	 * @param	string	$email	Address
	 * @return	array{socid:int,contactid:int,customer:bool,supplier:bool}|null	The thirdparty is preferred to a contact
	 */
	public function getDolibarrSender($email)
	{
		$email = strtolower(trim($email));
		if (empty($this->senders[$email])) {
			return null;
		}
		foreach ($this->senders[$email] as $entry) {
			if ($entry['contactid'] == 0) {
				return $entry;
			}
		}
		return $this->senders[$email][0];
	}

	/**
	 * Known sender for the spoofing check: any active customer, supplier or contact of Dolibarr (whatever the rescue
	 * tasks), or an address of the manual whitelist
	 *
	 * @param	string			$email		Address
	 * @param	EmailAccount	$account	Mailbox
	 * @return	array{socid:int,contactid:int,customer:bool,supplier:bool}|null
	 */
	public function getSpoofSender($email, $account)
	{
		$sender = $this->getDolibarrSender($email);
		if ($sender) {
			return $sender;
		}
		if ($this->hasRule($email, $account, CrmClientConnectorAntispamRule::TYPE_WHITE)) {
			return array('socid' => 0, 'contactid' => 0, 'customer' => false, 'supplier' => false);
		}
		return null;
	}

	/**
	 * Has an address a manual rule for a mailbox (rule of this mailbox or of all the mailboxes)
	 *
	 * @param	string			$email		Address
	 * @param	EmailAccount	$account	Mailbox
	 * @param	string			$type		CrmClientConnectorAntispamRule::TYPE_*
	 * @return	bool
	 */
	public function hasRule($email, $account, $type)
	{
		$email = strtolower(trim($email));
		if (!isset($this->rules[$email])) {
			return false;
		}
		foreach ($this->rules[$email] as $rule) {
			if ($rule['type'] === $type && ($rule['accountId'] == 0 || $rule['accountId'] == $account->id)) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Does a mailbox use the known senders at all (one of its rescue tasks is on)
	 *
	 * @param	EmailAccount	$account	Mailbox
	 * @return	bool
	 */
	public static function isUsedBy($account)
	{
		return (CrmClientConnectorAntispamImap::isTaskEnabled($account, 'rescue_customers')
			|| CrmClientConnectorAntispamImap::isTaskEnabled($account, 'rescue_suppliers')
			|| CrmClientConnectorAntispamImap::isTaskEnabled($account, 'rescue_manual'));
	}
}
