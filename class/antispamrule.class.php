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
 * \file        crmclientconnector/class/antispamrule.class.php
 * \ingroup     crmclientconnector
 * \brief       Manual whitelist and blacklist of the anti-spam
 */

/**
 * Manual whitelist and blacklist of the anti-spam: exact addresses, for all the mailboxes or for one mailbox.
 *
 * - white: the address is a known sender (task rescue_manual), its emails are taken out of junk when the sender is
 *   authenticated, like the customers and suppliers of Dolibarr;
 * - black: the emails of the address are never taken out of junk, even when it is a known customer or supplier.
 */
class CrmClientConnectorAntispamRule
{
	const TYPE_WHITE = 'white';
	const TYPE_BLACK = 'black';

	/**
	 * @var DoliDB Database handler
	 */
	public $db;

	/**
	 * @var string Last error
	 */
	public $error = '';

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
	 * Normalize an address: lower case, without the name ("Name <a@b.fr>" gives "a@b.fr")
	 *
	 * @param	string	$email	Address
	 * @return	string			'' if not a valid address
	 */
	public static function normalizeEmail($email)
	{
		$email = trim((string) $email);
		if (preg_match('/<\s*([^<>\s]+)\s*>/', $email, $reg)) {
			$email = $reg[1];
		}
		$email = strtolower(trim($email, " \t\"'"));
		return isValidEmail($email) ? $email : '';
	}

	/**
	 * Add a rule (an existing rule of the same address and scope is replaced)
	 *
	 * @param	User	$user		User adding the rule
	 * @param	string	$email		Address
	 * @param	string	$type		self::TYPE_WHITE or self::TYPE_BLACK
	 * @param	int		$accountId	Mailbox, 0 for all the mailboxes
	 * @param	string	$note		Note
	 * @return	int					Id of the rule, <0 on error
	 */
	public function add($user, $email, $type, $accountId = 0, $note = '')
	{
		global $conf, $langs;

		$typedEmail = $email;
		$email = self::normalizeEmail($email);
		if ($email === '') {
			$langs->load('errors');
			$this->error = $langs->transnoentities('ErrorBadEMail', $typedEmail);
			return -1;
		}
		if (!in_array($type, array(self::TYPE_WHITE, self::TYPE_BLACK), true)) {
			$this->error = 'Bad type';
			return -1;
		}

		$this->db->begin();

		// One rule per address and scope: the new one replaces the old one (ex: white becomes black)
		$sql = "DELETE FROM ".$this->db->prefix()."crmclientconnector_antispam_rule";
		$sql .= " WHERE entity = ".((int) $conf->entity)." AND email = '".$this->db->escape($email)."'";
		$sql .= " AND ".($accountId > 0 ? "fk_emailaccount = ".((int) $accountId) : "fk_emailaccount IS NULL");
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}

		$sql = "INSERT INTO ".$this->db->prefix()."crmclientconnector_antispam_rule (entity, email, type, fk_emailaccount, note, date_creation, fk_user_creat)";
		$sql .= " VALUES (".((int) $conf->entity).", '".$this->db->escape($email)."', '".$this->db->escape($type)."'";
		$sql .= ", ".($accountId > 0 ? (int) $accountId : "NULL");
		$sql .= ", ".(trim($note) !== '' ? "'".$this->db->escape(dol_trunc(trim($note), 255, 'right', 'UTF-8', 1))."'" : "NULL");
		$sql .= ", '".$this->db->idate(dol_now())."', ".((int) $user->id).")";
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}
		$id = (int) $this->db->last_insert_id($this->db->prefix()."crmclientconnector_antispam_rule");

		$this->db->commit();
		return $id;
	}

	/**
	 * Delete a rule
	 *
	 * @param	int		$id		Rule id
	 * @return	int				>0 if OK, <0 on error
	 */
	public function delete($id)
	{
		global $conf;

		$sql = "DELETE FROM ".$this->db->prefix()."crmclientconnector_antispam_rule";
		$sql .= " WHERE rowid = ".((int) $id)." AND entity = ".((int) $conf->entity);
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		return 1;
	}

	/**
	 * All the rules of the current entity
	 *
	 * @param	string	$type	Only this type ('' for both)
	 * @return	array<int,array{id:int,email:string,type:string,fk_emailaccount:int,note:string,date_creation:int,fk_user_creat:int}>|null	Null on error
	 */
	public function fetchAll($type = '')
	{
		global $conf;

		$sql = "SELECT rowid, email, type, fk_emailaccount, note, date_creation, fk_user_creat";
		$sql .= " FROM ".$this->db->prefix()."crmclientconnector_antispam_rule";
		$sql .= " WHERE entity = ".((int) $conf->entity);
		if ($type !== '') {
			$sql .= " AND type = '".$this->db->escape($type)."'";
		}
		$sql .= " ORDER BY email, fk_emailaccount";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return null;
		}
		$rules = array();
		while ($obj = $this->db->fetch_object($resql)) {
			$rules[] = array(
				'id' => (int) $obj->rowid,
				'email' => (string) $obj->email,
				'type' => (string) $obj->type,
				'fk_emailaccount' => (int) $obj->fk_emailaccount,
				'note' => (string) $obj->note,
				'date_creation' => (int) $this->db->jdate($obj->date_creation),
				'fk_user_creat' => (int) $obj->fk_user_creat,
			);
		}
		$this->db->free($resql);
		return $rules;
	}
}
