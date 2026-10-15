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
 *  \file       antispam_log.php
 *  \ingroup    crmclientconnector
 *  \brief      Log of the anti-spam: emails rescued from junk, known senders not rescued
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

require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
dol_include_once('/crmclientconnector/class/emailaccount.class.php');
dol_include_once('/crmclientconnector/class/antispamimap.class.php');
dol_include_once('/crmclientconnector/class/antispamscanner.class.php');

$langs->loadLangs(array('crmclientconnector@crmclientconnector', 'companies'));

$readAll = $user->admin || $user->hasRight('crmclientconnector', 'antispamlog', 'readall');
$readOwn = $readAll || $user->hasRight('crmclientconnector', 'antispamlog', 'read');
if (!isModEnabled('crmclientconnector') || !CrmClientConnectorAntispamImap::isEnabled() || !$readOwn) {
	accessforbidden();
}

$limit = GETPOST('limit', 'int') ? (int) GETPOST('limit', 'int') : $conf->liste_limit;
$page = GETPOSTISSET('pageplusone') ? ((int) GETPOST('pageplusone', 'int') - 1) : (int) GETPOST('page', 'int');
if ($page < 0 || GETPOST('button_search', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
	$page = 0;
}
$offset = $limit * $page;

$search_account = (int) GETPOST('search_account', 'int');
$search_action = GETPOST('search_action', 'aZ09');
$search_from = trim(GETPOST('search_from', 'alphanohtml'));
$search_subject = trim(GETPOST('search_subject', 'alphanohtml'));
if (GETPOST('button_removefilter', 'alpha') || GETPOST('button_removefilter.x', 'alpha')) {
	$search_account = 0;
	$search_action = '';
	$search_from = '';
	$search_subject = '';
}

$actions = array(
	CrmClientConnectorAntispamScanner::ACTION_RESCUED => $langs->trans('AntispamActionRescued'),
	CrmClientConnectorAntispamScanner::ACTION_NOT_RESCUED => $langs->trans('AntispamActionNotRescued'),
);

// Mailboxes the user can see: all, or the ones of which he is the employee
$accounts = array();
$sql = "SELECT rowid, email_account, label FROM ".$db->prefix()."crmclientconnector_emailaccount";
if (!$readAll) {
	$sql .= " WHERE fk_user = ".((int) $user->id);
}
$sql .= " ORDER BY email_account";
$resql = $db->query($sql);
if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		$accounts[(int) $obj->rowid] = $obj->email_account;
	}
}
if ($search_account > 0 && !isset($accounts[$search_account])) {
	$search_account = 0;
}


/*
 * View
 */

$form = new Form($db);
$title = $langs->trans('AntispamLog');
llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-crmclientconnector page-antispam-log');

$sqlwhere = " WHERE l.entity IN (".getEntity('crmclientconnector_antispam_log').")";
$sqlwhere .= " AND l.fk_emailaccount IN (".(empty($accounts) ? '0' : implode(',', array_map('intval', array_keys($accounts)))).")";
if ($search_account > 0) {
	$sqlwhere .= " AND l.fk_emailaccount = ".((int) $search_account);
}
if ($search_action !== '' && isset($actions[$search_action])) {
	$sqlwhere .= " AND l.action = '".$db->escape($search_action)."'";
}
if ($search_from !== '') {
	$sqlwhere .= natural_search('l.from_email', $search_from);
}
if ($search_subject !== '') {
	$sqlwhere .= natural_search('l.subject', $search_subject);
}

$nbtotalofrecords = 0;
$resql = $db->query("SELECT COUNT(*) as nb FROM ".$db->prefix()."crmclientconnector_antispam_log as l".$sqlwhere);
if ($resql && ($obj = $db->fetch_object($resql))) {
	$nbtotalofrecords = (int) $obj->nb;
}

$sql = "SELECT l.rowid, l.fk_emailaccount, l.date_log, l.date_mail, l.folder, l.from_email, l.subject, l.fk_soc, l.fk_socpeople, l.action, l.auth_result, l.auth_detail";
$sql .= " FROM ".$db->prefix()."crmclientconnector_antispam_log as l";
$sql .= $sqlwhere;
$sql .= " ORDER BY l.date_log DESC, l.rowid DESC";
$sql .= $db->plimit($limit + 1, $offset);
$resql = $db->query($sql);
if (!$resql) {
	dol_print_error($db);
	llxFooter();
	exit;
}
$num = $db->num_rows($resql);

$param = '';
if ($limit != $conf->liste_limit) {
	$param .= '&limit='.((int) $limit);
}
if ($search_account > 0) {
	$param .= '&search_account='.((int) $search_account);
}
if ($search_action !== '') {
	$param .= '&search_action='.urlencode($search_action);
}
if ($search_from !== '') {
	$param .= '&search_from='.urlencode($search_from);
}
if ($search_subject !== '') {
	$param .= '&search_subject='.urlencode($search_subject);
}

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';

print_barre_liste($title, $page, $_SERVER['PHP_SELF'], $param, '', '', '', $num, $nbtotalofrecords, 'email', 0, '', '', $limit, 0, 0, 1);

print '<span class="opacitymedium">'.$langs->trans($readAll ? 'AntispamLogDescAll' : 'AntispamLogDescOwn').'</span><br><br>';

print '<div class="div-table-responsive">';
print '<table class="tagtable liste">';

// Filters
print '<tr class="liste_titre_filter">';
print '<td class="liste_titre"></td>';
print '<td class="liste_titre">'.$form->selectarray('search_account', $accounts, $search_account, 1, 0, 0, '', 0, 0, 0, '', 'maxwidth200').'</td>';
print '<td class="liste_titre"></td>';
print '<td class="liste_titre"><input type="text" class="flat maxwidth150" name="search_from" value="'.dol_escape_htmltag($search_from).'"></td>';
print '<td class="liste_titre"></td>';
print '<td class="liste_titre"><input type="text" class="flat maxwidth200" name="search_subject" value="'.dol_escape_htmltag($search_subject).'"></td>';
print '<td class="liste_titre">'.$form->selectarray('search_action', $actions, $search_action, 1, 0, 0, '', 0, 0, 0, '', 'maxwidth150').'</td>';
print '<td class="liste_titre right">'.$form->showFilterButtons().'</td>';
print '</tr>';

print '<tr class="liste_titre">';
print '<th>'.$langs->trans('Date').'</th>';
print '<th>'.$langs->trans('EmailAccount').'</th>';
print '<th>'.$langs->trans('AntispamFolder').'</th>';
print '<th>'.$langs->trans('AntispamSender').'</th>';
print '<th>'.$langs->trans('ThirdParty').' / '.$langs->trans('Contact').'</th>';
print '<th>'.$langs->trans('AntispamSubject').'</th>';
print '<th>'.$langs->trans('AntispamLogAction').'</th>';
print '<th>'.$langs->trans('AntispamAuthentication').'</th>';
print '</tr>';

$cacheSoc = array();
$cacheContact = array();

$i = 0;
while ($i < min($num, $limit)) {
	$obj = $db->fetch_object($resql);
	$i++;

	print '<tr class="oddeven">';
	print '<td class="nowraponall">'.dol_print_date($db->jdate($obj->date_log), 'dayhour', 'tzuserrel').'</td>';
	print '<td class="tdoverflowmax150">'.dol_escape_htmltag($accounts[(int) $obj->fk_emailaccount] ?? '').'</td>';
	print '<td class="tdoverflowmax100">'.dol_escape_htmltag(CrmClientConnectorAntispamImap::decodeFolderName($obj->folder)).'</td>';
	print '<td class="tdoverflowmax200">'.dol_escape_htmltag($obj->from_email).'</td>';

	print '<td class="tdoverflowmax200">';
	if ($obj->fk_soc > 0) {
		if (!isset($cacheSoc[$obj->fk_soc])) {
			$soc = new Societe($db);
			$cacheSoc[$obj->fk_soc] = $soc->fetch($obj->fk_soc) > 0 ? $soc->getNomUrl(1) : '';
		}
		print $cacheSoc[$obj->fk_soc];
	}
	if ($obj->fk_socpeople > 0) {
		if (!isset($cacheContact[$obj->fk_socpeople])) {
			$contact = new Contact($db);
			$cacheContact[$obj->fk_socpeople] = $contact->fetch($obj->fk_socpeople) > 0 ? $contact->getNomUrl(1) : '';
		}
		print ' '.$cacheContact[$obj->fk_socpeople];
	}
	print '</td>';

	print '<td class="tdoverflowmax300" title="'.dol_escape_htmltag($obj->subject).'">'.dol_escape_htmltag($obj->subject).'</td>';

	print '<td class="nowraponall">';
	if ($obj->action == CrmClientConnectorAntispamScanner::ACTION_RESCUED) {
		print '<span class="badge badge-status4">'.$langs->trans('AntispamActionRescued').'</span>';
	} else {
		print '<span class="badge badge-status1">'.$langs->trans('AntispamActionNotRescued').'</span>';
	}
	print '</td>';

	$authLabel = $langs->trans($obj->auth_result == 'pass' ? 'AntispamAuthPass' : ($obj->auth_result == 'fail' ? 'AntispamAuthFail' : 'AntispamAuthUnknown'));
	print '<td class="tdoverflowmax300" title="'.dol_escape_htmltag($obj->auth_detail).'">'.dol_escape_htmltag($authLabel).($obj->auth_detail ? ' <span class="opacitymedium small">'.dol_escape_htmltag($obj->auth_detail).'</span>' : '').'</td>';
	print '</tr>';
}
if ($num == 0) {
	print '<tr><td colspan="8"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
}

print '</table>';
print '</div>';
print '</form>';

llxFooter();
$db->close();
