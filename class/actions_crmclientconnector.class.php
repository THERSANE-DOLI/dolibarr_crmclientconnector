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
 * \file    class/actions_crmclientconnector.class.php
 * \ingroup crmclientconnector
 * \brief   Hook overload for crmclientconnector.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonhookactions.class.php';

/**
 * Class ActionsCRMClientConnector
 */
class ActionsCRMClientConnector extends CommonHookActions
{
	/**
	 * @var DoliDB Database handler.
	 */
	public $db;

	/**
	 * @var string Error code (or message)
	 */
	public $error = '';

	/**
	 * @var string[] Errors
	 */
	public $errors = array();

	/**
	 * @var mixed[] Hook results. Propagated to $hookmanager->resArray for later reuse
	 */
	public $results = array();

	/**
	 * @var ?string String displayed by executeHook() immediately after return
	 */
	public $resprints;

	/**
	 * Constructor
	 *
	 * @param	DoliDB	$db      Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Overloading the doActions function : replacing the parent's function with the one below.
	 *
	 * On the Propal/Commande/Ticket create form (context 'propalcard'/'ordercard'/'ticketcard'),
	 * the Thunderbird doliconnector extension may open the create page with
	 * ?action=create&accountEmail=...&msgId=... so the InterfaceCRMClientConnectorTriggers trigger
	 * can link the new object to the originating mail once created (see PROPAL_CREATE/ORDER_CREATE/
	 * TICKET_CREATE handling there). Those query params are only present on the initial GET though :
	 * Dolibarr's create form posts back to action=add/create without echoing them (and none of
	 * comm/propal/card.php, commande/card.php or ticket/card.php print a hook's resprints from
	 * inside the create form, so injecting hidden fields there is not an option). They are stashed
	 * in session here instead, and consumed/cleared by the trigger once the object is created.
	 *
	 * @param	array<string,mixed>	$parameters	Array of parameters
	 * @param	CommonObject		$object		The object to process (Propal, Commande or Ticket here)
	 * @param	string				$action		'create', 'add', ...
	 * @param	HookManager			$hookmanager	Hook manager
	 * @return	int								0 < on error, 0 on success (no action taken), 1 to replace standard code
	 */
	public function doActions($parameters, &$object, &$action, $hookmanager)
	{
		if (!in_array($parameters['currentcontext'], array('propalcard', 'ordercard', 'ticketcard'))) {
			return 0;
		}

		if ($action != 'create') {
			return 0;
		}

		$accountEmail = GETPOST('accountEmail', 'alpha');
		$msgId = GETPOST('msgId', 'alpha');

		if (!empty($accountEmail) && !empty($msgId)) {
			$_SESSION['crmclientconnector_pending_accountemail'] = $accountEmail;
			$_SESSION['crmclientconnector_pending_msgid'] = $msgId;
		}

		return 0;
	}
}
