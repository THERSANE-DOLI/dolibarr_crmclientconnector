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

/**
 * Known senders of the anti-spam, read once per scan.
 *
 * Exact addresses only (no domain): email of the active customer (client 1 or 3, prospects only are excluded) or
 * supplier thirdparties, and of the active contacts of these thirdparties. Which ones are used for a mailbox depends
 * on its tasks rescue_customers, rescue_suppliers and rescue_contacts.
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
	 * @return int	Number of addresses, -1 on error
	 */
	public function load()
	{
		$this->senders = array();

		$sql = "SELECT s.rowid, s.email, s.client, s.fournisseur";
		$sql .= " FROM ".$this->db->prefix()."societe as s";
		$sql .= " WHERE s.entity IN (".getEntity('societe').")";
		$sql .= " AND s.status = 1 AND s.email IS NOT NULL AND s.email <> ''";
		$sql .= " AND (s.client IN (1, 3) OR s.fournisseur = 1)";
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
		$sql .= " AND s.status = 1 AND (s.client IN (1, 3) OR s.fournisseur = 1)";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		while ($obj = $this->db->fetch_object($resql)) {
			$this->add($obj->email, (int) $obj->fk_soc, (int) $obj->rowid, (int) $obj->client, (int) $obj->fournisseur);
		}
		$this->db->free($resql);

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
		if (!isset($this->senders[$email])) {
			return null;
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
		return $found;
	}

	/**
	 * Does a mailbox use the known senders at all (one of its rescue tasks is on)
	 *
	 * @param	EmailAccount	$account	Mailbox
	 * @return	bool
	 */
	public static function isUsedBy($account)
	{
		return (CrmClientConnectorAntispamImap::isTaskEnabled($account, 'rescue_customers') || CrmClientConnectorAntispamImap::isTaskEnabled($account, 'rescue_suppliers'));
	}
}
