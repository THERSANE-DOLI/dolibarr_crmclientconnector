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
 *  \file       antispam_rules.php
 *  \ingroup    crmclientconnector
 *  \brief      Manual whitelist and blacklist of the anti-spam
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
 * @var Translate $langs
 * @var User $user
 */

dol_include_once('/crmclientconnector/class/antispamimap.class.php');
dol_include_once('/crmclientconnector/class/antispamrule.class.php');

$langs->loadLangs(array('crmclientconnector@crmclientconnector', 'users'));

$permissiontoadd = $user->admin || $user->hasRight('crmclientconnector', 'antispamrule', 'write');
if (!isModEnabled('crmclientconnector') || !CrmClientConnectorAntispamImap::isEnabled() || !$permissiontoadd) {
	accessforbidden();
}
// External users: never (internal data)
if ($user->socid > 0) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');
$id = (int) GETPOST('id', 'int');
$email = trim(GETPOST('email', 'alphanohtml'));
$type = GETPOST('type', 'aZ09');
$accountId = (int) GETPOST('fk_emailaccount', 'int');
$note = trim(GETPOST('note', 'alphanohtml'));

$ruleStatic = new CrmClientConnectorAntispamRule($db);

$types = array(
	CrmClientConnectorAntispamRule::TYPE_WHITE => $langs->trans('AntispamRuleWhite'),
	CrmClientConnectorAntispamRule::TYPE_BLACK => $langs->trans('AntispamRuleBlack'),
);

$accounts = array();
$resql = $db->query("SELECT rowid, email_account FROM ".$db->prefix()."crmclientconnector_emailaccount ORDER BY email_account");
if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		$accounts[(int) $obj->rowid] = $obj->email_account;
	}
}


/*
 * Actions
 */

if ($action == 'add') {
	if (!isset($types[$type])) {
		$type = CrmClientConnectorAntispamRule::TYPE_WHITE;
	}
	if ($accountId > 0 && !isset($accounts[$accountId])) {
		$accountId = 0;
	}
	if ($ruleStatic->add($user, $email, $type, $accountId, $note) > 0) {
		setEventMessages($langs->trans('AntispamRuleAdded', CrmClientConnectorAntispamRule::normalizeEmail($email), $types[$type]), null, 'mesgs');
		header('Location: '.$_SERVER['PHP_SELF']);
		exit;
	}
	setEventMessages($ruleStatic->error, null, 'errors');
	$action = 'create';
}

if ($action == 'confirm_delete' && GETPOST('confirm', 'alpha') == 'yes' && $id > 0) {
	if ($ruleStatic->delete($id) > 0) {
		setEventMessages($langs->trans('RecordDeleted'), null, 'mesgs');
	} else {
		setEventMessages($ruleStatic->error, null, 'errors');
	}
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}


/*
 * View
 */

$form = new Form($db);
$title = $langs->trans('AntispamRules');
llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-crmclientconnector page-antispam-rules');

if ($action == 'delete' && $id > 0) {
	print $form->formconfirm($_SERVER['PHP_SELF'].'?id='.$id, $langs->trans('AntispamRuleDelete'), $langs->trans('AntispamRuleConfirmDelete'), 'confirm_delete', '', 0, 1);
}

print load_fiche_titre($title, '', 'email');
print '<span class="opacitymedium">'.$langs->trans('AntispamRulesDesc').'</span><br><br>';

// Add form (prefilled when coming from a line of the log)
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="add">';
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans('Email').'</td>';
print '<td>'.$langs->trans('AntispamRuleType').'</td>';
print '<td>'.$langs->trans('EmailAccount').'</td>';
print '<td>'.$langs->trans('Note').'</td>';
print '<td></td>';
print '</tr>';
print '<tr class="oddeven">';
print '<td><input type="text" class="flat minwidth250" name="email" value="'.dol_escape_htmltag($email).'" placeholder="contact@example.com" autofocus></td>';
print '<td>'.$form->selectarray('type', $types, isset($types[$type]) ? $type : CrmClientConnectorAntispamRule::TYPE_WHITE, 0, 0, 0, '', 0, 0, 0, '', 'minwidth150').'</td>';
print '<td>'.$form->selectarray('fk_emailaccount', array(0 => $langs->trans('AntispamAllMailboxes')) + $accounts, $accountId, 0, 0, 0, '', 0, 0, 0, '', 'minwidth200').'</td>';
print '<td><input type="text" class="flat minwidth200" name="note" value="'.dol_escape_htmltag($note).'" maxlength="255"></td>';
print '<td class="right"><input type="submit" class="button small" value="'.$langs->trans('Add').'"></td>';
print '</tr>';
print '</table>';
print '</div>';
print '</form>';
print '<br>';

// List
$rules = $ruleStatic->fetchAll();
if ($rules === null) {
	dol_print_error($db, $ruleStatic->error);
	$rules = array();
}

$userCache = array();

print '<div class="div-table-responsive">';
print '<table class="tagtable liste">';
print '<tr class="liste_titre">';
print '<th>'.$langs->trans('Email').'</th>';
print '<th>'.$langs->trans('AntispamRuleType').'</th>';
print '<th>'.$langs->trans('EmailAccount').'</th>';
print '<th>'.$langs->trans('Note').'</th>';
print '<th>'.$langs->trans('DateCreation').'</th>';
print '<th>'.$langs->trans('Author').'</th>';
print '<th></th>';
print '</tr>';
foreach ($rules as $rule) {
	print '<tr class="oddeven">';
	print '<td class="tdoverflowmax250">'.dol_escape_htmltag($rule['email']).'</td>';
	print '<td>'.($rule['type'] == CrmClientConnectorAntispamRule::TYPE_WHITE ? '<span class="badge badge-status4">'.$types[$rule['type']].'</span>' : '<span class="badge badge-status8">'.$types[$rule['type']].'</span>').'</td>';
	print '<td class="tdoverflowmax200">'.($rule['fk_emailaccount'] > 0 ? dol_escape_htmltag($accounts[$rule['fk_emailaccount']] ?? '#'.$rule['fk_emailaccount']) : '<span class="opacitymedium">'.$langs->trans('AntispamAllMailboxes').'</span>').'</td>';
	print '<td class="tdoverflowmax300">'.dol_escape_htmltag($rule['note']).'</td>';
	print '<td class="nowraponall">'.dol_print_date($rule['date_creation'], 'dayhour', 'tzuserrel').'</td>';
	print '<td class="tdoverflowmax150">';
	if ($rule['fk_user_creat'] > 0) {
		if (!isset($userCache[$rule['fk_user_creat']])) {
			$tmpuser = new User($db);
			$userCache[$rule['fk_user_creat']] = $tmpuser->fetch($rule['fk_user_creat']) > 0 ? $tmpuser->getNomUrl(-1) : '';
		}
		print $userCache[$rule['fk_user_creat']];
	}
	print '</td>';
	print '<td class="right"><a class="reposition" href="'.$_SERVER['PHP_SELF'].'?action=delete&id='.$rule['id'].'&token='.newToken().'">'.img_delete().'</a></td>';
	print '</tr>';
}
if (empty($rules)) {
	print '<tr><td colspan="7"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
}
print '</table>';
print '</div>';

llxFooter();
$db->close();
