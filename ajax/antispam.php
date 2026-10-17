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
 * \file    crmclientconnector/ajax/antispam.php
 * \ingroup crmclientconnector
 * \brief   Anti-spam switch of an email account (POST, token checked by main.inc.php), answer in JSON
 */

if (!defined('NOTOKENRENEWAL')) {
	define('NOTOKENRENEWAL', '1');	// The page keeps using its token after the action
}
if (!defined('NOREQUIREMENU')) {
	define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
	define('NOREQUIREHTML', '1');
}

// Load Dolibarr environment
$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
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

dol_include_once('/crmclientconnector/class/emailaccount.class.php');
dol_include_once('/crmclientconnector/class/antispamimap.class.php');

$langs->loadLangs(array('crmclientconnector@crmclientconnector'));

top_httphead('application/json');

/**
 * Answer in JSON and stop
 *
 * @param array<string,mixed>	$data		Data
 * @param int					$httpCode	HTTP code
 * @return never
 */
function crmclientconnectorAntispamAnswer($data, $httpCode = 200)
{
	http_response_code($httpCode);
	print json_encode($data);
	exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	crmclientconnectorAntispamAnswer(array('success' => false, 'error' => 'Bad method'), 405);
}
if ($user->socid > 0) {
	crmclientconnectorAntispamAnswer(array('success' => false, 'error' => 'Not allowed'), 403);
}
if (!CrmClientConnectorAntispamImap::isEnabled()) {
	crmclientconnectorAntispamAnswer(array('success' => false, 'error' => 'Feature disabled'), 403);
}
// Activation of the scan = the scheduled job opens the mailbox: administrators only (see emailaccount_antispam.php)
if (empty($user->admin)) {
	crmclientconnectorAntispamAnswer(array('success' => false, 'error' => $langs->transnoentitiesnoconv('NotEnoughPermissions')), 403);
}

$action = GETPOST('action', 'aZ09');
$id = (int) GETPOST('id', 'int');

$object = new EmailAccount($db);
if ($id <= 0 || $object->fetch($id) <= 0) {
	crmclientconnectorAntispamAnswer(array('success' => false, 'error' => $langs->transnoentitiesnoconv('ErrorRecordNotFound')), 404);
}

if ($action == 'setactive') {
	$value = GETPOST('value', 'int') ? 1 : 0;
	if ($object->setValueFrom('antispam_active', $value, '', null, 'int', '', $user) <= 0) {
		crmclientconnectorAntispamAnswer(array('success' => false, 'error' => $object->error), 500);
	}
	crmclientconnectorAntispamAnswer(array('success' => true, 'value' => $value));
}

crmclientconnectorAntispamAnswer(array('success' => false, 'error' => 'Bad action'), 400);
