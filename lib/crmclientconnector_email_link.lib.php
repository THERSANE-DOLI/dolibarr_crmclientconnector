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
 * \file    lib/crmclientconnector_emaillink.lib.php
 * \ingroup crmclientconnector
 * \brief   Library files with common functions for EmailLink
 */

/**
 * Find the EmailLink matching an account email + a message-id, creating the EmailAccount
 * and/or the EmailLink if they don't exist yet. Shared between the PROPAL_CREATE/ORDER_CREATE
 * trigger (auto-link on creation from the Thunderbird popup) and the manual link/unlink API
 * endpoints, so the account/link lookup-or-create logic (mirrored from the private
 * CRMClientConnector::_fetchImailLinkByMsgId()) only lives in one place.
 *
 * @param	DoliDB	$db				Database handler
 * @param	User	$user			User doing the create (used as fk_user_creat if a row must be created)
 * @param	string	$accountEmail	Email address of the mailbox owning the message
 * @param	string	$msgId			Message-Id of the mail
 * @return	EmailLink|int			EmailLink object, or <0 if KO
 */
function crmclientconnectorGetOrCreateEmailLink($db, $user, $accountEmail, $msgId)
{
	dol_include_once('/crmclientconnector/class/emailaccount.class.php');
	dol_include_once('/crmclientconnector/class/emaillink.class.php');

	if (empty($accountEmail) || empty($msgId)) {
		return -1;
	}

	$emailaccount = new EmailAccount($db);
	$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX.$emailaccount->table_element;
	$sql .= " WHERE email_account = '".$db->escape($accountEmail)."'";
	$resql = $db->query($sql);
	if (!$resql) {
		return -1;
	}
	$obj = $db->fetch_object($resql);
	if ($obj) {
		if ($emailaccount->fetch($obj->rowid) <= 0) {
			return -1;
		}
	} else {
		$emailaccount->email_account = $accountEmail;
		$emailaccount->status = 1;
		if ($emailaccount->create($user) <= 0) {
			return -1;
		}
	}

	$emaillink = new EmailLink($db);
	$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX.$emaillink->table_element;
	$sql .= " WHERE fk_email_account = ".((int) $emailaccount->id);
	$sql .= " AND email_msgid = '".$db->escape($msgId)."'";
	$resql = $db->query($sql);
	if (!$resql) {
		return -1;
	}
	$obj = $db->fetch_object($resql);
	if ($obj) {
		if ($emaillink->fetch($obj->rowid) <= 0) {
			return -1;
		}
		return $emaillink;
	}

	$emaillink->fk_email_account = $emailaccount->id;
	$emaillink->email_msgid = $msgId;
	if ($emaillink->create($user) <= 0) {
		return -1;
	}

	return $emaillink;
}

/**
 * Prepare array of tabs for EmailLink
 *
 * @param	EmailLink	$object		EmailLink
 * @return 	array					Array of tabs
 */
function emaillinkPrepareHead($object)
{
	global $db, $langs, $conf;

	$langs->load("crmclientconnector@crmclientconnector");

	$showtabofpagecontact = 1;
	$showtabofpagenote = 1;
	$showtabofpagedocument = 1;
	$showtabofpageagenda = 1;

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath("/crmclientconnector/emaillink_card.php", 1).'?id='.$object->id;
	$head[$h][1] = $langs->trans("EmailLink");
	$head[$h][2] = 'card';
	$h++;

	if ($showtabofpagecontact) {
		$head[$h][0] = dol_buildpath("/crmclientconnector/emaillink_contact.php", 1).'?id='.$object->id;
		$head[$h][1] = $langs->trans("Contacts");
		$head[$h][2] = 'contact';
		$h++;
	}

	if ($showtabofpagenote) {
		if (isset($object->fields['note_public']) || isset($object->fields['note_private'])) {
			$nbNote = 0;
			if (!empty($object->note_private)) {
				$nbNote++;
			}
			if (!empty($object->note_public)) {
				$nbNote++;
			}
			$head[$h][0] = dol_buildpath('/crmclientconnector/emaillink_note.php', 1).'?id='.$object->id;
			$head[$h][1] = $langs->trans('Notes');
			if ($nbNote > 0) {
				$head[$h][1] .= (!getDolGlobalInt('MAIN_OPTIMIZEFORTEXTBROWSER') ? '<span class="badge marginleftonlyshort">'.$nbNote.'</span>' : '');
			}
			$head[$h][2] = 'note';
			$h++;
		}
	}

	if ($showtabofpagedocument) {
		require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
		require_once DOL_DOCUMENT_ROOT.'/core/class/link.class.php';
		$upload_dir = $conf->crmclientconnector->dir_output."/emaillink/".dol_sanitizeFileName($object->ref);
		$nbFiles = count(dol_dir_list($upload_dir, 'files', 0, '', '(\.meta|_preview.*\.png)$'));
		$nbLinks = Link::count($db, $object->element, $object->id);
		$head[$h][0] = dol_buildpath("/crmclientconnector/emaillink_document.php", 1).'?id='.$object->id;
		$head[$h][1] = $langs->trans('Documents');
		if (($nbFiles + $nbLinks) > 0) {
			$head[$h][1] .= '<span class="badge marginleftonlyshort">'.($nbFiles + $nbLinks).'</span>';
		}
		$head[$h][2] = 'document';
		$h++;
	}

	if ($showtabofpageagenda) {
		$head[$h][0] = dol_buildpath("/crmclientconnector/emaillink_agenda.php", 1).'?id='.$object->id;
		$head[$h][1] = $langs->trans("Events");
		$head[$h][2] = 'agenda';
		$h++;
	}

	// Show more tabs from modules
	// Entries must be declared in modules descriptor with line
	//$this->tabs = array(
	//	'entity:+tabname:Title:@crmclientconnector:/crmclientconnector/mypage.php?id=__ID__'
	//); // to add new tab
	//$this->tabs = array(
	//	'entity:-tabname:Title:@crmclientconnector:/crmclientconnector/mypage.php?id=__ID__'
	//); // to remove a tab
	complete_head_from_modules($conf, $langs, $object, $head, $h, 'emaillink@crmclientconnector');

	complete_head_from_modules($conf, $langs, $object, $head, $h, 'emaillink@crmclientconnector', 'remove');

	return $head;
}
