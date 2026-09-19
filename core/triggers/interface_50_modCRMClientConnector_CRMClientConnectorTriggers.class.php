<?php
/* Copyright (C) 2025 Administrateur SuperAdmin <admin@thersane.fr>
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
 * \file    core/triggers/interface_50_modCRMClientConnector_CRMClientConnectorTriggers.class.php
 * \ingroup crmclientconnector
 * \brief   Trigger file for crmclientconnector : auto-link a Propal/Commande to the mail it was
 *          created from (Thunderbird doliconnector extension "Create quotation" button).
 */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';
dol_include_once('/crmclientconnector/lib/crmclientconnector_email_link.lib.php');

/**
 *  Class of triggers for CRMClientConnector module
 */
class InterfaceCRMClientConnectorTriggers extends DolibarrTriggers
{
	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		parent::__construct($db);
		$this->family = "crm";
		$this->description = "CRMClientConnector triggers.";
		$this->version = self::VERSIONS['dev'];
		$this->picto = 'crmclientconnector@crmclientconnector';
	}

	/**
	 * Function called when a Dolibarr business event is done.
	 *
	 * @param string 		$action 	Event action code
	 * @param CommonObject 	$object 	Object
	 * @param User 			$user 		Object user
	 * @param Translate 	$langs 		Object langs
	 * @param Conf 			$conf 		Object conf
	 * @return int              		Return integer <0 if KO, 0 if no triggered ran, >0 if OK
	 */
	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
	{
		if (!isModEnabled('crmclientconnector')) {
			return 0;
		}

		switch ($action) {
			case 'PROPAL_CREATE':
			case 'ORDER_CREATE':
				return $this->linkObjectToOriginatingMail($object, $user);

			default:
				break;
		}

		return 0;
	}

	/**
	 * Link a just-created Propal/Commande to the EmailLink of the mail it was created from, if
	 * the create request carried accountEmail/msgId (either directly on this request - a future
	 * API-driven creation - or stashed in session by ActionsCRMClientConnector::doActions() during
	 * the create-form GET, since the create form's own POST does not resubmit them).
	 *
	 * @param	CommonObject	$object	The just-created Propal or Commande
	 * @param	User			$user	User doing the create
	 * @return	int						0 if nothing to do, >0 on success, <0 on error
	 */
	private function linkObjectToOriginatingMail($object, User $user)
	{
		$accountEmail = GETPOST('accountEmail', 'alpha');
		$msgId = GETPOST('msgId', 'alpha');

		if (empty($accountEmail) || empty($msgId)) {
			$accountEmail = empty($_SESSION['crmclientconnector_pending_accountemail']) ? '' : $_SESSION['crmclientconnector_pending_accountemail'];
			$msgId = empty($_SESSION['crmclientconnector_pending_msgid']) ? '' : $_SESSION['crmclientconnector_pending_msgid'];
		}

		unset($_SESSION['crmclientconnector_pending_accountemail']);
		unset($_SESSION['crmclientconnector_pending_msgid']);

		if (empty($accountEmail) || empty($msgId)) {
			return 0;
		}

		$emaillink = crmclientconnectorGetOrCreateEmailLink($this->db, $user, $accountEmail, $msgId);
		if (!is_object($emaillink)) {
			$this->errors[] = 'Failed to get or create EmailLink for accountEmail='.$accountEmail.' msgId='.$msgId;
			return -1;
		}

		$result = $object->add_object_linked('emaillink', $emaillink->id, $user);
		if ($result <= 0) {
			$this->errors = array_merge($this->errors, $object->errors);
			return -1;
		}

		return 1;
	}
}
