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
 *  \file       emailaccount_antispam.php
 *  \ingroup    crmclientconnector
 *  \brief      Tab "Anti-spam" of an email account: activation, owner, junk folders, connection test
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; $tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
// Try main.inc.php using relative path
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Translate $langs
 * @var User $user
 */

dol_include_once('/crmclientconnector/class/emailaccount.class.php');
dol_include_once('/crmclientconnector/class/antispamimap.class.php');
dol_include_once('/crmclientconnector/class/antispamscanner.class.php');
dol_include_once('/crmclientconnector/lib/crmclientconnector_email_account.lib.php');
require_once DOL_DOCUMENT_ROOT.'/core/lib/security.lib.php';

$langs->loadLangs(array("crmclientconnector@crmclientconnector", "users"));

$id = GETPOST('id', 'int');
$ref = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'aZ09');
$cancel = GETPOST('cancel', 'aZ09');

$object = new EmailAccount($db);
$extrafields = new ExtraFields($db);
$hookmanager->initHooks(array($object->element.'antispam', 'globalcard'));
$extrafields->fetch_name_optionals_label($object->table_element);

include DOL_DOCUMENT_ROOT.'/core/actions_fetchobject.inc.php'; // Must be include, not include_once

$permissiontoread = $user->admin || $user->hasRight('crmclientconnector', 'emailaccount', 'read');
$permissiontoadd = $user->admin || $user->hasRight('crmclientconnector', 'emailaccount', 'write');
// The connection (server, connection mode, owner) is set by an administrator : the master password of the setup
// or the password of the mailbox would be sent to the server set here
$canSetConnection = !empty($user->admin);

if (!isModEnabled('crmclientconnector') || !CrmClientConnectorAntispamImap::isEnabled()) {
	accessforbidden();
}
if (!$permissiontoread || empty($object->id)) {
	accessforbidden();
}

$antispamImap = new CrmClientConnectorAntispamImap($db);
$testResult = null;
$serverFolders = null;


/*
 * Actions
 */

$parameters = array();
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action);
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($reshook)) {
	if ($cancel) {
		$action = '';
	}

	if ($action == 'update_antispam' && $permissiontoadd) {
		if ($canSetConnection) {
			$fkUser = (int) GETPOST('fk_user', 'int');
			$object->fk_user = $fkUser > 0 ? $fkUser : null;
		}
		// Tasks: '' = follow the setup, '1' / '0' = forced for this mailbox
		$overrides = array();
		foreach (array_keys(CrmClientConnectorAntispamImap::getTasks()) as $task) {
			$value = GETPOST('antispam_task_'.$task, 'alpha');
			$overrides[$task] = in_array($value, array('0', '1'), true) ? (int) $value : null;
		}
		$object->setAntispamTaskOverrides($overrides);
		$folders = GETPOST('antispam_junk_folders', 'array');
		$object->setAntispamJunkFolders(is_array($folders) ? $folders : array());

		// Connection: Dovecot master user or own login / password, default server or own server
		if ($canSetConnection) {
			$object->antispam_auth_mode = GETPOST('antispam_auth_mode', 'aZ09') == 'login' ? 'login' : 'master';
		}
		if (CrmClientConnectorAntispamImap::getAuthMode($object) == 'login') {
			$object->antispam_login = trim(GETPOST('antispam_login', 'alphanohtml'));
			// 'none': a password may contain any character ('password' filter does not exist in Dolibarr 18)
			$password = GETPOST('antispam_password', 'none');
			if ($password !== '') {
				$object->antispam_password = dolEncrypt($password);
			}
		} else {
			// No password kept for a mailbox opened with the master user
			$object->antispam_login = null;
			$object->antispam_password = null;
		}
		if ($canSetConnection) {
			$object->antispam_imap_host = trim(GETPOST('antispam_imap_host', 'alphanohtml'));
			$port = (int) GETPOST('antispam_imap_port', 'int');
			$object->antispam_imap_port = ($object->antispam_imap_host !== '' && $port > 0 && $port <= 65535) ? $port : null;
			$encryption = GETPOST('antispam_imap_encryption', 'aZ09');
			$object->antispam_imap_encryption = ($object->antispam_imap_host !== '' && in_array($encryption, array('ssl', 'tls', 'none'))) ? $encryption : null;
			if ($object->antispam_imap_host === '') {
				$object->antispam_imap_host = null;
			}
		}

		if ($object->update($user) > 0) {
			setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
			header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
			exit;
		}
		setEventMessages($object->error, $object->errors, 'errors');
		$action = 'edit_antispam';
	}

	if ($action == 'test' && $permissiontoadd) {
		$testResult = $antispamImap->testConnection($object);
		if ($testResult === null) {
			setEventMessages($antispamImap->error, null, 'errors');
		} else {
			setEventMessages($langs->trans('AntispamConnectionOk', $testResult['duration']), null, 'mesgs');
		}
	}

	if ($action == 'scan' && $permissiontoadd) {
		// Same scan as the scheduled job, for this mailbox only (even if its anti-spam is off, to try it)
		$scanner = new CrmClientConnectorAntispamScanner($db);
		$stats = $scanner->scanAccount($object);
		if ($stats === null) {
			setEventMessages($scanner->error, null, 'errors');
		} else {
			setEventMessages(CrmClientConnectorAntispamScanner::getSummary($stats), null, 'mesgs');
		}
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
		exit;
	}

	if ($action == 'edit_antispam' && $permissiontoadd) {
		// Folders read on the server for the pick list, the form stays usable without them
		$client = $antispamImap->connect($object);
		if ($client) {
			$serverFolders = $antispamImap->listFolders($client);
			$client->disconnect();
		}
		if ($serverFolders === null) {
			setEventMessages($langs->trans('AntispamFoldersNotRead').' '.$antispamImap->error, null, 'warnings');
		}
	}
}


/*
 * View
 */

$form = new Form($db);

$title = $langs->trans('EmailAccount').' - '.$langs->trans('Antispam');
llxHeader('', $title, '', '', 0, 0, array('/crmclientconnector/js/antispam.js'), array(), '', 'mod-crmclientconnector page-card_antispam');

$head = emailaccountPrepareHead($object);
print dol_get_fiche_head($head, 'antispam', $langs->trans('EmailAccount'), -1, $object->picto);

$linkback = '<a href="'.dol_buildpath('/crmclientconnector/emailaccount_list.php', 1).'?restore_lastsearch_values=1">'.$langs->trans("BackToList").'</a>';
$morehtmlref = '<div class="refidno"></div>';
dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);

print '<div class="fichecenter">';
print '<div class="underbanner clearboth"></div>';

$configurationError = CrmClientConnectorAntispamImap::getConfigurationError($object);
if ($configurationError !== '' && $action != 'edit_antispam') {
	$msg = dol_escape_htmltag($configurationError);
	if ($user->admin) {
		$msg .= ' <a href="'.dol_buildpath('/crmclientconnector/admin/antispam.php', 1).'">'.$langs->trans('AntispamGoToSetup').'</a>';
	}
	print '<div class="warning">'.$msg.'</div>';
}

if ($action != 'edit_antispam' && empty(CrmClientConnectorAntispamScanner::getAuthservIds())) {
	$msg = $langs->trans('AntispamWarningNoAuthservIds');
	if ($user->admin) {
		$msg .= ' <a href="'.dol_buildpath('/crmclientconnector/admin/antispam.php', 1).'">'.$langs->trans('AntispamGoToSetup').'</a>';
	}
	print '<div class="warning">'.$msg.'</div>';
}

$selectedFolders = $object->getAntispamJunkFolders();

if ($action == 'edit_antispam' && $permissiontoadd) {
	// Edit form
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="update_antispam">';

	print '<table class="border centpercent tableforfield">';

	// Connection mode: the master user is proposed only when it is set up
	$authMode = CrmClientConnectorAntispamImap::getAuthMode($object);
	if ($authMode == 'master' && !CrmClientConnectorAntispamImap::isMasterConfigured()) {
		$authMode = 'login';
	}
	print '<tr><td class="titlefield">'.$langs->trans('AntispamAuthMode').'</td><td>';
	print '<select name="antispam_auth_mode" class="flat minwidth300 crmcc-antispam-authmode"'.($canSetConnection ? '' : ' disabled').'>';
	print '<option value="master"'.($authMode == 'master' ? ' selected' : '').(CrmClientConnectorAntispamImap::isMasterConfigured() ? '' : ' disabled').'>';
	print dol_escape_htmltag($langs->trans('AntispamAuthModeMaster').(CrmClientConnectorAntispamImap::isMasterConfigured() ? '' : ' ('.$langs->trans('AntispamMasterNotConfigured').')')).'</option>';
	print '<option value="login"'.($authMode == 'login' ? ' selected' : '').'>'.dol_escape_htmltag($langs->trans('AntispamAuthModeLogin')).'</option>';
	print '</select>';
	if (!$canSetConnection) {
		print ' <span class="opacitymedium">'.$langs->trans('AntispamConnectionAdminOnly').'</span>';
	}
	print '</td></tr>';

	$loginRowClass = 'crmcc-antispam-login-row'.($authMode == 'login' ? '' : ' hidden');
	print '<tr class="'.$loginRowClass.'"><td>'.$langs->trans('AntispamLogin').'</td><td>';
	print '<input type="text" class="flat minwidth300" name="antispam_login" value="'.dol_escape_htmltag((string) $object->antispam_login).'" placeholder="'.dol_escape_htmltag($object->email_account).'" autocomplete="off">';
	print '</td></tr>';
	print '<tr class="'.$loginRowClass.'"><td>'.$langs->trans('AntispamPassword').'</td><td>';
	$hasPassword = (string) $object->antispam_password !== '';
	print '<input type="password" class="flat minwidth300" name="antispam_password" value="" autocomplete="new-password" placeholder="'.dol_escape_htmltag($hasPassword ? $langs->trans('AntispamPasswordKeep') : '').'">';
	if ($hasPassword) {
		print ' <span class="opacitymedium">'.img_picto('', 'tick', 'class="pictofixedwidth"').$langs->trans('AntispamPasswordSaved').'</span>';
	}
	print '</td></tr>';

	$defaultServer = getDolGlobalString('CRMCLIENTCONNECTOR_ANTISPAM_IMAP_HOST');
	print '<tr><td>'.$form->textwithpicto($langs->trans('AntispamOwnServer'), $langs->trans('AntispamOwnServerHelp')).'</td><td>';
	print '<input type="text" class="flat minwidth300" name="antispam_imap_host"'.($canSetConnection ? '' : ' disabled').' value="'.dol_escape_htmltag((string) $object->antispam_imap_host).'" placeholder="'.dol_escape_htmltag($defaultServer !== '' ? $langs->trans('AntispamDefaultServer', $defaultServer) : 'mail.example.com').'">';
	print ' <input type="number" class="flat width75" min="1" max="65535" name="antispam_imap_port"'.($canSetConnection ? '' : ' disabled').' value="'.($object->antispam_imap_port > 0 ? (int) $object->antispam_imap_port : '').'" placeholder="993">';
	$encryptions = array('ssl' => $langs->trans('AntispamEncryptionSsl'), 'tls' => $langs->trans('AntispamEncryptionStartTls'), 'none' => $langs->trans('AntispamEncryptionNone'));
	print ' '.$form->selectarray('antispam_imap_encryption', $encryptions, $object->antispam_imap_encryption ? $object->antispam_imap_encryption : 'ssl', 0, 0, 0, '', 0, 0, ($canSetConnection ? 0 : 1), '', 'minwidth150');
	print '</td></tr>';

	print '<tr><td>'.$form->textwithpicto($langs->trans('AntispamMailboxOwner'), $langs->trans('AntispamMailboxOwnerHelp')).'</td><td>';
	print img_picto('', 'user', 'class="pictofixedwidth"').$form->select_dolusers($object->fk_user, 'fk_user', 1, null, ($canSetConnection ? 0 : 1), '', '', '0', 0, 0, '', 0, '', 'minwidth300');
	print '</td></tr>';

	print '<tr><td>'.$form->textwithpicto($langs->trans('AntispamJunkFolders'), $langs->trans('AntispamJunkFoldersHelp')).'</td><td>';
	$options = array();
	if (is_array($serverFolders)) {
		$suggested = array();
		foreach ($serverFolders as $folder) {
			if (!$folder['selectable'] || strtoupper($folder['path']) === 'INBOX') {
				continue;
			}
			$options[$folder['path']] = $folder['name'];
			if ($folder['junk'] || preg_match('/(spam|junk|ind[ée]sirable)/iu', $folder['name'])) {
				$suggested[] = $folder['path'];
			}
		}
		// Nothing chosen yet: preselect the folders that look like junk folders
		if (empty($selectedFolders)) {
			$selectedFolders = $suggested;
		}
	}
	// Keep the chosen folders even if they are not found (any more) on the server
	foreach ($selectedFolders as $path) {
		if (!isset($options[$path])) {
			$options[$path] = CrmClientConnectorAntispamImap::decodeFolderName($path).(is_array($serverFolders) ? ' ('.$langs->trans('AntispamFolderNotFound').')' : '');
		}
	}
	print $form->multiselectarray('antispam_junk_folders', $options, $selectedFolders, 0, 0, 'minwidth300 maxwidth500', 0, 0, '', '', $langs->trans('AntispamChooseFolders'));
	print '</td></tr>';

	print '</table>';

	// Tasks: follow the setup or forced on / off for this mailbox
	print '<br>';
	print load_fiche_titre($langs->trans('AntispamTasks'), '', '');
	$overrides = $object->getAntispamTaskOverrides();
	print '<table class="border centpercent tableforfield">';
	$previousGroup = '';
	foreach (CrmClientConnectorAntispamImap::getTasks() as $task => $taskInfo) {
		if ($taskInfo['group'] != $previousGroup) {
			print '<tr class="liste_titre"><td colspan="2">'.$langs->trans($taskInfo['group'] == 'rescue' ? 'AntispamTaskGroupRescue' : 'AntispamTaskGroupSpoof').'</td></tr>';
			$previousGroup = $taskInfo['group'];
		}
		$current = isset($overrides[$task]) ? (string) $overrides[$task] : '';
		$choices = array(
			'' => $langs->trans('AntispamTaskFollowSetup', yn(CrmClientConnectorAntispamImap::isTaskEnabledByDefault($task))),
			'1' => $langs->trans('AntispamTaskForcedOn'),
			'0' => $langs->trans('AntispamTaskForcedOff'),
		);
		print '<tr><td class="titlefield">'.$form->textwithpicto($langs->trans($taskInfo['label']), $langs->trans($taskInfo['help'])).'</td><td>';
		print '<select name="antispam_task_'.$task.'" class="flat minwidth300">';
		foreach ($choices as $value => $label) {
			print '<option value="'.$value.'"'.($current === (string) $value ? ' selected' : '').'>'.dol_escape_htmltag($label).'</option>';
		}
		print '</select>';
		print '</td></tr>';
	}
	print '</table>';

	print $form->buttonsSaveCancel('Save', 'Cancel');
	print '</form>';
} else {
	// View
	print '<table class="border centpercent tableforfield">';

	print '<tr><td class="titlefield">'.$langs->trans('AntispamActive').'</td><td>';
	if ($permissiontoadd) {
		print '<span class="crmcc-antispam-switch cursorpointer"';
		print ' data-url="'.dol_escape_htmltag(dol_buildpath('/crmclientconnector/ajax/antispam.php', 1)).'"';
		print ' data-id="'.((int) $object->id).'" data-token="'.currentToken().'" data-value="'.(empty($object->antispam_active) ? 0 : 1).'"';
		print ' data-error="'.dol_escape_htmltag($langs->trans('AntispamErrorToggle')).'">';
		print '<span class="crmcc-antispam-switch-on'.(empty($object->antispam_active) ? ' hidden' : '').'">'.img_picto($langs->trans('Enabled'), 'switch_on').'</span>';
		print '<span class="crmcc-antispam-switch-off'.(empty($object->antispam_active) ? '' : ' hidden').'">'.img_picto($langs->trans('Disabled'), 'switch_off').'</span>';
		print '</span>';
	} else {
		print yn($object->antispam_active);
	}
	print '</td></tr>';

	print '<tr><td>'.$langs->trans('AntispamAuthMode').'</td><td>';
	print $langs->trans(CrmClientConnectorAntispamImap::getAuthMode($object) == 'login' ? 'AntispamAuthModeLogin' : 'AntispamAuthModeMaster');
	print '</td></tr>';

	print '<tr><td>'.$langs->trans('AntispamImapLogin').'</td><td>';
	print '<span class="opacitymedium">'.dol_escape_htmltag(CrmClientConnectorAntispamImap::getLogin($object)).'</span>';
	print '</td></tr>';

	$server = CrmClientConnectorAntispamImap::getServer($object);
	print '<tr><td>'.$langs->trans('AntispamImapServer').'</td><td>';
	if ($server['host'] !== '') {
		print '<span class="opacitymedium">'.dol_escape_htmltag($server['host'].':'.$server['port']).'</span>';
		if (trim((string) $object->antispam_imap_host) === '') {
			print ' <span class="opacitymedium small">('.$langs->trans('AntispamServerFromSetup').')</span>';
		}
	}
	print '</td></tr>';

	print '<tr><td>'.$form->textwithpicto($langs->trans('AntispamMailboxOwner'), $langs->trans('AntispamMailboxOwnerHelp')).'</td><td>';
	if ($object->fk_user > 0) {
		$owner = new User($db);
		if ($owner->fetch($object->fk_user) > 0) {
			print $owner->getNomUrl(-1);
		}
	}
	print '</td></tr>';

	print '<tr><td>'.$form->textwithpicto($langs->trans('AntispamJunkFolders'), $langs->trans('AntispamJunkFoldersHelp')).'</td><td>';
	if (empty($selectedFolders)) {
		print '<span class="opacitymedium">'.$langs->trans('AntispamNoJunkFolder').'</span>';
	}
	foreach ($selectedFolders as $path) {
		print '<span class="badge badge-secondary marginrightonly">'.dol_escape_htmltag(CrmClientConnectorAntispamImap::decodeFolderName($path)).'</span>';
	}
	print '</td></tr>';

	print '<tr><td>'.$langs->trans('AntispamDateLastScan').'</td><td>';
	print $object->antispam_date_last_scan ? dol_print_date($object->antispam_date_last_scan, 'dayhour', 'tzuserrel') : '<span class="opacitymedium">'.$langs->trans('AntispamNeverScanned').'</span>';
	print '</td></tr>';

	if ($user->admin || $user->hasRight('crmclientconnector', 'antispamlog', 'readall') || ($user->hasRight('crmclientconnector', 'antispamlog', 'read') && $object->fk_user == $user->id)) {
		$nbLog = 0;
		$resql = $db->query("SELECT COUNT(*) as nb FROM ".$db->prefix()."crmclientconnector_antispam_log WHERE fk_emailaccount = ".((int) $object->id));
		if ($resql && ($obj = $db->fetch_object($resql))) {
			$nbLog = (int) $obj->nb;
		}
		print '<tr><td>'.$langs->trans('AntispamLog').'</td><td>';
		print '<a href="'.dol_buildpath('/crmclientconnector/antispam_log.php', 1).'?search_account='.((int) $object->id).'">'.$langs->trans('AntispamSeeLog').'</a>';
		print ' <span class="badge marginleftonlyshort">'.$nbLog.'</span>';
		print '</td></tr>';
	}

	if (!empty($object->antispam_last_error)) {
		print '<tr><td>'.$langs->trans('AntispamLastError').'</td><td><span class="error">'.dol_escape_htmltag($object->antispam_last_error).'</span></td></tr>';
	}

	print '</table>';

	// Effective tasks of the mailbox
	print '<br>';
	print load_fiche_titre($langs->trans('AntispamTasks'), '', '');
	$overrides = $object->getAntispamTaskOverrides();
	print '<table class="border centpercent tableforfield">';
	$previousGroup = '';
	foreach (CrmClientConnectorAntispamImap::getTasks() as $task => $taskInfo) {
		if ($taskInfo['group'] != $previousGroup) {
			print '<tr class="liste_titre"><td colspan="2">'.$langs->trans($taskInfo['group'] == 'rescue' ? 'AntispamTaskGroupRescue' : 'AntispamTaskGroupSpoof').'</td></tr>';
			$previousGroup = $taskInfo['group'];
		}
		print '<tr><td class="titlefield">'.$form->textwithpicto($langs->trans($taskInfo['label']), $langs->trans($taskInfo['help'])).'</td><td>';
		print yn(CrmClientConnectorAntispamImap::isTaskEnabled($object, $task));
		print ' <span class="opacitymedium small">('.$langs->trans(isset($overrides[$task]) ? 'AntispamTaskForcedForMailbox' : 'AntispamTaskFromSetup').')</span>';
		print '</td></tr>';
	}
	print '</table>';
}

print '</div>';

print dol_get_fiche_end();

if ($action != 'edit_antispam') {
	print '<div class="tabsAction">';
	print dolGetButtonAction('', $langs->trans('Modify'), 'default', $_SERVER['PHP_SELF'].'?id='.$object->id.'&action=edit_antispam&token='.newToken(), '', $permissiontoadd);
	print dolGetButtonAction('', $langs->trans('AntispamScanNow'), 'default', $_SERVER['PHP_SELF'].'?id='.$object->id.'&action=scan&token='.newToken(), '', $permissiontoadd && $configurationError === '');
	print dolGetButtonAction('', $langs->trans('AntispamTestConnection'), 'default', $_SERVER['PHP_SELF'].'?id='.$object->id.'&action=test&token='.newToken(), '', $permissiontoadd && $configurationError === '');
	print '</div>';
}

// Result of the connection test
if (is_array($testResult)) {
	print load_fiche_titre($langs->trans('AntispamFoldersOnServer'), '', '');
	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans('AntispamFolder').'</td>';
	print '<td class="right">'.$langs->trans('AntispamNbMessages').'</td>';
	print '<td>'.$langs->trans('AntispamFolderRole').'</td>';
	print '</tr>';
	foreach ($testResult['folders'] as $folder) {
		$role = '';
		if (in_array($folder['path'], $selectedFolders)) {
			$role = '<span class="badge badge-status4">'.$langs->trans('AntispamRoleJunkChosen').'</span>';
		} elseif (strtoupper($folder['path']) === 'INBOX') {
			$role = '<span class="badge badge-status1">'.$langs->trans('AntispamRoleInbox').'</span>';
		} elseif ($folder['junk']) {
			$role = '<span class="opacitymedium">'.$langs->trans('AntispamRoleJunkServer').'</span>';
		}
		print '<tr class="oddeven">';
		print '<td>'.img_picto('', 'folder', 'class="pictofixedwidth"').dol_escape_htmltag($folder['name']).'</td>';
		print '<td class="right">'.($folder['count'] >= 0 ? $folder['count'] : '<span class="opacitymedium">-</span>').'</td>';
		print '<td>'.$role.'</td>';
		print '</tr>';
	}
	print '</table>';
	print '</div>';
}

llxFooter();
$db->close();
