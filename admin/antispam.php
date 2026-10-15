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
 * \file    crmclientconnector/admin/antispam.php
 * \ingroup crmclientconnector
 * \brief   Setup of the anti-spam IMAP feature
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
 * @var Translate $langs
 * @var User $user
 */

require_once DOL_DOCUMENT_ROOT."/core/lib/admin.lib.php";
require_once DOL_DOCUMENT_ROOT."/core/lib/ajax.lib.php";
require_once '../lib/crmclientconnector.lib.php';
require_once '../class/antispamimap.class.php';

$langs->loadLangs(array("admin", "crmclientconnector@crmclientconnector"));

if (!$user->admin) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');

$encryptions = array(
	'ssl' => $langs->trans('AntispamEncryptionSsl'),
	'tls' => $langs->trans('AntispamEncryptionStartTls'),
	'none' => $langs->trans('AntispamEncryptionNone'),
);


/*
 * Actions
 */

if ($action == 'update') {
	$error = 0;

	$host = trim(GETPOST('CRMCLIENTCONNECTOR_ANTISPAM_IMAP_HOST', 'alphanohtml'));
	$port = (int) GETPOST('CRMCLIENTCONNECTOR_ANTISPAM_IMAP_PORT', 'int');
	$encryption = GETPOST('CRMCLIENTCONNECTOR_ANTISPAM_IMAP_ENCRYPTION', 'aZ09');
	$validateCert = GETPOST('CRMCLIENTCONNECTOR_ANTISPAM_IMAP_VALIDATE_CERT', 'int') ? 1 : 0;
	$masterUser = trim(GETPOST('CRMCLIENTCONNECTOR_ANTISPAM_MASTER_USER', 'alphanohtml'));
	$separator = trim(GETPOST('CRMCLIENTCONNECTOR_ANTISPAM_MASTER_SEPARATOR', 'alphanohtml'));
	// 'none': a password may contain any character ('password' filter does not exist in Dolibarr 18)
	$password = GETPOST('CRMCLIENTCONNECTOR_ANTISPAM_MASTER_PASSWORD', 'none');

	if ($port <= 0 || $port > 65535) {
		$port = 993;
	}
	if (!array_key_exists($encryption, $encryptions)) {
		$encryption = 'ssl';
	}
	if ($separator === '') {
		$separator = '*';
	}
	if ($masterUser !== '' && strpos($masterUser, $separator) !== false) {
		setEventMessages($langs->trans('AntispamErrorSeparatorInMasterUser', $separator), null, 'errors');
		$error++;
	}

	if (!$error) {
		$db->begin();
		$values = array(
			'CRMCLIENTCONNECTOR_ANTISPAM_IMAP_HOST' => $host,
			'CRMCLIENTCONNECTOR_ANTISPAM_IMAP_PORT' => (string) $port,
			'CRMCLIENTCONNECTOR_ANTISPAM_IMAP_ENCRYPTION' => $encryption,
			'CRMCLIENTCONNECTOR_ANTISPAM_IMAP_VALIDATE_CERT' => (string) $validateCert,
			'CRMCLIENTCONNECTOR_ANTISPAM_MASTER_USER' => $masterUser,
			'CRMCLIENTCONNECTOR_ANTISPAM_MASTER_SEPARATOR' => $separator,
		);
		// Empty password field = keep the current one (it is never sent back to the browser)
		if ($password !== '') {
			// Encrypted by dolibarr_set_const() because the name ends with _PASSWORD
			$values['CRMCLIENTCONNECTOR_ANTISPAM_MASTER_PASSWORD'] = $password;
		}
		foreach ($values as $name => $value) {
			if (dolibarr_set_const($db, $name, $value, 'chaine', 0, '', $conf->entity) < 0) {
				$error++;
			}
		}
		if (!$error) {
			$db->commit();
			setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
		} else {
			$db->rollback();
			setEventMessages($langs->trans('Error'), null, 'errors');
		}
	}

	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}

if ($action == 'confirm_deletepassword' && GETPOST('confirm', 'alpha') == 'yes') {
	dolibarr_del_const($db, 'CRMCLIENTCONNECTOR_ANTISPAM_MASTER_PASSWORD', $conf->entity);
	setEventMessages($langs->trans('AntispamPasswordDeleted'), null, 'mesgs');
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}


/*
 * View
 */

$form = new Form($db);

$title = 'CRMClientConnectorSetup';
llxHeader('', $langs->trans($title), '', '', 0, 0, '', '', '', 'mod-crmclientconnector page-admin-antispam');

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans($title), $linkback, 'title_setup');

$head = crmclientconnectorAdminPrepareHead();
print dol_get_fiche_head($head, 'antispam', $langs->trans($title), -1, "crmclientconnector@crmclientconnector");

if ($action == 'deletepassword') {
	print $form->formconfirm($_SERVER['PHP_SELF'], $langs->trans('AntispamDeletePassword'), $langs->trans('AntispamConfirmDeletePassword'), 'confirm_deletepassword', '', 0, 1);
}

print '<span class="opacitymedium">'.$langs->trans('AntispamSetupDesc').'</span><br><br>';

// Enable / disable
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans('Parameter').'</td><td></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('AntispamEnable').'</td><td class="right">';
print ajax_constantonoff('CRMCLIENTCONNECTOR_ANTISPAM_ENABLED');
print '</td></tr>';
print '</table>';
print '</div>';
print '<br>';

// Tasks: default of every mailbox, each mailbox can force a task on or off in its "Anti-spam" tab
foreach (CrmClientConnectorAntispamImap::getTasks() as $task => $taskInfo) {
	// A task never saved is on: save it so that the switch shows it
	if (!isset($conf->global->{CrmClientConnectorAntispamImap::getTaskConstName($task)})) {
		dolibarr_set_const($db, CrmClientConnectorAntispamImap::getTaskConstName($task), '1', 'chaine', 0, '', $conf->entity);
	}
}
print load_fiche_titre($langs->trans('AntispamTasks'), '', '');
print '<span class="opacitymedium">'.$langs->trans('AntispamTasksDesc').'</span><br><br>';
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
$previousGroup = '';
foreach (CrmClientConnectorAntispamImap::getTasks() as $task => $taskInfo) {
	if ($taskInfo['group'] != $previousGroup) {
		print '<tr class="liste_titre"><td>'.$langs->trans($taskInfo['group'] == 'rescue' ? 'AntispamTaskGroupRescue' : 'AntispamTaskGroupSpoof').'</td><td></td></tr>';
		$previousGroup = $taskInfo['group'];
	}
	print '<tr class="oddeven"><td>'.$form->textwithpicto($langs->trans($taskInfo['label']), $langs->trans($taskInfo['help'])).'</td><td class="right">';
	// setzeroinsteadofdel: off is saved as 0 (a deleted constant would mean "on")
	print ajax_constantonoff(CrmClientConnectorAntispamImap::getTaskConstName($task), array(), null, 0, 0, 0, 2, 0, 1);
	print '</td></tr>';
}
print '</table>';
print '</div>';
print '<br>';

// IMAP connection
$hasPassword = getDolGlobalString('CRMCLIENTCONNECTOR_ANTISPAM_MASTER_PASSWORD') !== '';

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'" autocomplete="off">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="update">';

print load_fiche_titre($langs->trans('AntispamImapConnection'), '', '');
print '<span class="opacitymedium">'.$langs->trans('AntispamImapConnectionDesc').'</span><br><br>';
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td class="titlefieldcreate">'.$langs->trans('Parameter').'</td><td>'.$langs->trans('Value').'</td></tr>';

print '<tr class="oddeven"><td>'.$form->textwithpicto($langs->trans('AntispamImapHost'), $langs->trans('AntispamImapHostHelp')).'</td><td>';
print '<input type="text" class="minwidth300" name="CRMCLIENTCONNECTOR_ANTISPAM_IMAP_HOST" value="'.dol_escape_htmltag(getDolGlobalString('CRMCLIENTCONNECTOR_ANTISPAM_IMAP_HOST')).'" placeholder="mail.example.com">';
print '</td></tr>';

print '<tr class="oddeven"><td>'.$langs->trans('AntispamImapPort').'</td><td>';
print '<input type="number" class="width75" min="1" max="65535" name="CRMCLIENTCONNECTOR_ANTISPAM_IMAP_PORT" value="'.getDolGlobalInt('CRMCLIENTCONNECTOR_ANTISPAM_IMAP_PORT', 993).'">';
print '</td></tr>';

print '<tr class="oddeven"><td>'.$langs->trans('AntispamImapEncryption').'</td><td>';
print $form->selectarray('CRMCLIENTCONNECTOR_ANTISPAM_IMAP_ENCRYPTION', $encryptions, getDolGlobalString('CRMCLIENTCONNECTOR_ANTISPAM_IMAP_ENCRYPTION', 'ssl'), 0, 0, 0, '', 0, 0, 0, '', 'minwidth200');
print '</td></tr>';

print '<tr class="oddeven"><td>'.$form->textwithpicto($langs->trans('AntispamImapValidateCert'), $langs->trans('AntispamImapValidateCertHelp')).'</td><td>';
print $form->selectyesno('CRMCLIENTCONNECTOR_ANTISPAM_IMAP_VALIDATE_CERT', getDolGlobalInt('CRMCLIENTCONNECTOR_ANTISPAM_IMAP_VALIDATE_CERT', 1), 1);
print '</td></tr>';

print '<tr class="liste_titre"><td colspan="2">'.$form->textwithpicto($langs->trans('AntispamMasterSection'), $langs->trans('AntispamMasterSectionHelp')).'</td></tr>';

print '<tr class="oddeven"><td>'.$form->textwithpicto($langs->trans('AntispamMasterUser'), $langs->trans('AntispamMasterUserHelp')).'</td><td>';
print '<input type="text" class="minwidth200" name="CRMCLIENTCONNECTOR_ANTISPAM_MASTER_USER" value="'.dol_escape_htmltag(getDolGlobalString('CRMCLIENTCONNECTOR_ANTISPAM_MASTER_USER')).'" placeholder="doliconnector" autocomplete="off">';
print '</td></tr>';

print '<tr class="oddeven"><td>'.$langs->trans('AntispamMasterPassword').'</td><td>';
print '<input type="password" class="minwidth300" name="CRMCLIENTCONNECTOR_ANTISPAM_MASTER_PASSWORD" value="" autocomplete="new-password"';
print ' placeholder="'.dol_escape_htmltag($hasPassword ? $langs->trans('AntispamPasswordKeep') : '').'">';
if ($hasPassword) {
	print ' <span class="opacitymedium">'.img_picto('', 'tick', 'class="pictofixedwidth"').$langs->trans('AntispamPasswordSaved').'</span>';
	print ' <a class="reposition marginleftonly" href="'.$_SERVER['PHP_SELF'].'?action=deletepassword&token='.newToken().'">'.img_delete($langs->trans('AntispamDeletePassword')).'</a>';
}
print '</td></tr>';

print '<tr class="oddeven"><td>'.$form->textwithpicto($langs->trans('AntispamMasterSeparator'), $langs->trans('AntispamMasterSeparatorHelp')).'</td><td>';
print '<input type="text" class="width50" maxlength="5" name="CRMCLIENTCONNECTOR_ANTISPAM_MASTER_SEPARATOR" value="'.dol_escape_htmltag(getDolGlobalString('CRMCLIENTCONNECTOR_ANTISPAM_MASTER_SEPARATOR', '*')).'">';
print '</td></tr>';

print '<tr class="oddeven"><td>'.$langs->trans('AntispamLoginExample').'</td><td>';
print '<span class="opacitymedium">'.dol_escape_htmltag(CrmClientConnectorAntispamImap::getMasterLogin('contact@example.com')).'</span>';
print '</td></tr>';

print '</table>';
print '</div>';

print '<div class="center"><input type="submit" class="button button-save" value="'.$langs->trans('Save').'"></div>';
print '</form>';

// Server prerequisites
print '<br>';
print load_fiche_titre($langs->trans('AntispamServerPrerequisites'), '', '');
print '<span class="opacitymedium">'.$langs->trans('AntispamServerPrerequisitesOnlyMaster').'</span><br><br>';
$masterUser = getDolGlobalString('CRMCLIENTCONNECTOR_ANTISPAM_MASTER_USER') !== '' ? getDolGlobalString('CRMCLIENTCONNECTOR_ANTISPAM_MASTER_USER') : 'doliconnector';
$separator = getDolGlobalString('CRMCLIENTCONNECTOR_ANTISPAM_MASTER_SEPARATOR', '*');
$help = '<p>'.$langs->trans('AntispamServerPrerequisitesDesc').'</p>';
$help .= '<ol>';
$help .= '<li>'.$langs->trans('AntispamServerStepHash').'<pre>doveadm pw -s SHA512-CRYPT</pre></li>';
$help .= '<li>'.$langs->trans('AntispamServerStepFile').'<pre>'.dol_escape_htmltag($masterUser).':{SHA512-CRYPT}&lt;hash&gt;::::::allow_nets=&lt;'.$langs->trans('AntispamDolibarrServerIp').'&gt;/32</pre></li>';
$help .= '<li>'.$langs->trans('AntispamServerStepConf').'<pre>auth_master_user_separator = '.dol_escape_htmltag($separator)."\npassdb {\n  driver = passwd-file\n  args = /etc/dovecot/master-users\n  master = yes\n  pass = yes\n}</pre></li>";
$help .= '<li>'.$langs->trans('AntispamServerStepReload').'<pre>systemctl reload dovecot</pre></li>';
$help .= '</ol>';
$help .= '<p>'.$langs->trans('AntispamServerSecurity').'</p>';
print '<div class="info">'.$help.'</div>';

print dol_get_fiche_end();

llxFooter();
$db->close();
