<?php
/* Copyright (C) 2004-2017 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2025 Administrateur SuperAdmin <admin@thersane.fr>
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
 * \file    crmclientconnector/admin/setup.php
 * \ingroup crmclientconnector
 * \brief   CRMClientConnector setup page.
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

global $langs, $user;

// Libraries
require_once DOL_DOCUMENT_ROOT."/core/lib/admin.lib.php";
require_once '../lib/crmclientconnector.lib.php';
//require_once "../class/myclass.class.php";

// Translations
$langs->loadLangs(array("admin", "crmclientconnector@crmclientconnector"));

// Initialize technical object to manage hooks of page. Note that conf->hooks_modules contains array of hook context
$hookmanager->initHooks(array('crmclientconnectorsetup', 'globalsetup'));

// Parameters
$action = GETPOST('action', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');
$modulepart = GETPOST('modulepart', 'aZ09');	// Used by actions_setmoduleoptions.inc.php

$value = GETPOST('value', 'alpha');
$label = GETPOST('label', 'alpha');
$scandir = GETPOST('scan_dir', 'alpha');
$type = 'myobject';

$error = 0;
$setupnotempty = 0;

// Access control
if (!$user->admin) {
	accessforbidden();
}


// Set this to 1 to use the factory to manage constants. Warning, the generated module will be compatible with version v15+ only
$useFormSetup = 1;

if (!class_exists('FormSetup')) {
	require_once DOL_DOCUMENT_ROOT.'/core/class/html.formsetup.class.php';
}
$formSetup = new FormSetup($db);

// Access control
if (!$user->admin) {
	accessforbidden();
}


// Enter here all parameters in your setup page

// Setup conf for selection of an URL
//$item = $formSetup->newItem('CRMCLIENTCONNECTOR_MYPARAM1');
//$item->fieldOverride = (empty($_SERVER['HTTPS']) ? 'http://' : 'https://') . $_SERVER['HTTP_HOST'];
//$item->cssClass = 'minwidth500';
//
//// Setup conf for selection of a simple string input
//$item = $formSetup->newItem('CRMCLIENTCONNECTOR_MYPARAM2');
//$item->defaultFieldValue = 'default value';
//$item->fieldAttr['placeholder'] = 'A placeholder here';
//
//// Setup conf for selection of a simple textarea input but we replace the text of field title
//$item = $formSetup->newItem('CRMCLIENTCONNECTOR_MYPARAM3');
//$item->nameText = $item->getNameText().' more html text ';
//
//// Setup conf for a selection of a thirdparty
//$item = $formSetup->newItem('CRMCLIENTCONNECTOR_MYPARAM4');
//$item->setAsThirdpartyType();
//
//// Setup conf for a selection of a boolean
//$formSetup->newItem('CRMCLIENTCONNECTOR_MYPARAM5')->setAsYesNo();
//
//// Setup conf for a selection of an email template of type thirdparty
//$formSetup->newItem('CRMCLIENTCONNECTOR_MYPARAM6')->setAsEmailTemplate('thirdparty');
//
//// Setup conf for a selection of a secured key
////$formSetup->newItem('CRMCLIENTCONNECTOR_MYPARAM7')->setAsSecureKey();
//
//// Setup conf for a selection of a product
//$formSetup->newItem('CRMCLIENTCONNECTOR_MYPARAM8')->setAsProduct();
//
//// Add a title for a new section
//$formSetup->newItem('NewSection')->setAsTitle();
//
//$TField = array(
//	'test01' => $langs->trans('test01'),
//	'test02' => $langs->trans('test02'),
//	'test03' => $langs->trans('test03'),
//	'test04' => $langs->trans('test04'),
//	'test05' => $langs->trans('test05'),
//	'test06' => $langs->trans('test06'),
//);
//
//// Setup conf for a simple combo list
//$formSetup->newItem('CRMCLIENTCONNECTOR_MYPARAM9')->setAsSelect($TField);
//
//// Setup conf for a multiselect combo list
//$item = $formSetup->newItem('CRMCLIENTCONNECTOR_MYPARAM10');
//$item->setAsMultiSelect($TField);
//$item->helpText = $langs->transnoentities('CRMCLIENTCONNECTOR_MYPARAM10');
//
//// Setup conf for a category selection
//$formSetup->newItem('CRMCLIENTCONNECTOR_CATEGORY_ID_XXX')->setAsCategory('product');
//
//// Setup conf CRMCLIENTCONNECTOR_MYPARAM10
//$item = $formSetup->newItem('CRMCLIENTCONNECTOR_MYPARAM10');
//$item->setAsColor();
//$item->defaultFieldValue = '#FF0000';
//$item->nameText = $item->getNameText().' more html text ';
//$item->fieldInputOverride = '';
//$item->helpText = $langs->transnoentities('AnHelpMessage');
//$item->fieldValue = '';
//$item->fieldAttr = array() ; // fields attribute only for compatible fields like input text
//$item->fieldOverride = false; // set this var to override field output will override $fieldInputOverride and $fieldOutputOverride too
//$item->fieldInputOverride = false; // set this var to override field input
//$item->fieldOutputOverride = false; // set this var to override field output


$setupnotempty += count($formSetup->items);


$dirmodels = array_merge(array('/'), (array) $conf->modules_parts['models']);

$moduledir = 'crmclientconnector';
$myTmpObjects = array();
// TODO Scan list of objects to fill this array
$myTmpObjects['myobject'] = array('label'=>'MyObject', 'includerefgeneration'=>0, 'includedocgeneration'=>0, 'class'=>'MyObject');

$tmpobjectkey = GETPOST('object', 'aZ09');
if ($tmpobjectkey && !array_key_exists($tmpobjectkey, $myTmpObjects)) {
	accessforbidden('Bad value for object. Hack attempt ?');
}


/*
 * Actions
 */

// For retrocompatibility Dolibarr < 15.0
if (versioncompare(explode('.', DOL_VERSION), array(15)) < 0 && $action == 'update' && !empty($user->admin)) {
	$formSetup->saveConfFromPost();
}

include DOL_DOCUMENT_ROOT.'/core/actions_setmoduleoptions.inc.php';

if ($action == 'updateMask') {
	$maskconst = GETPOST('maskconst', 'aZ09');
	$maskvalue = GETPOST('maskvalue', 'alpha');

	if ($maskconst && preg_match('/_MASK$/', $maskconst)) {
		$res = dolibarr_set_const($db, $maskconst, $maskvalue, 'chaine', 0, '', $conf->entity);
		if (!($res > 0)) {
			$error++;
		}
	}

	if (!$error) {
		setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
	} else {
		setEventMessages($langs->trans("Error"), null, 'errors');
	}
} elseif ($action == 'specimen' && $tmpobjectkey) {
	$modele = GETPOST('module', 'alpha');

	$className = $myTmpObjects[$tmpobjectkey]['class'];
	$tmpobject = new $className($db);
	$tmpobject->initAsSpecimen();

	// Search template files
	$file = '';
	$className = '';
	$filefound = 0;
	$dirmodels = array_merge(array('/'), (array) $conf->modules_parts['models']);
	foreach ($dirmodels as $reldir) {
		$file = dol_buildpath($reldir."core/modules/crmclientconnector/doc/pdf_".$modele."_".strtolower($tmpobjectkey).".modules.php", 0);
		if (file_exists($file)) {
			$filefound = 1;
			$className = "pdf_".$modele."_".strtolower($tmpobjectkey);
			break;
		}
	}

	if ($filefound) {
		require_once $file;

		$module = new $className($db);

		if ($module->write_file($tmpobject, $langs) > 0) {
			header("Location: ".DOL_URL_ROOT."/document.php?modulepart=crmclientconnector-".strtolower($tmpobjectkey)."&file=SPECIMEN.pdf");
			return;
		} else {
			setEventMessages($module->error, null, 'errors');
			dol_syslog($module->error, LOG_ERR);
		}
	} else {
		setEventMessages($langs->trans("ErrorModuleNotFound"), null, 'errors');
		dol_syslog($langs->trans("ErrorModuleNotFound"), LOG_ERR);
	}
} elseif ($action == 'setmod') {
	// TODO Check if numbering module chosen can be activated by calling method canBeActivated
	if (!empty($tmpobjectkey)) {
		$constforval = 'CRMCLIENTCONNECTOR_'.strtoupper($tmpobjectkey)."_ADDON";
		dolibarr_set_const($db, $constforval, $value, 'chaine', 0, '', $conf->entity);
	}
} elseif ($action == 'set') {
	// Activate a model
	$ret = addDocumentModel($value, $type, $label, $scandir);
} elseif ($action == 'del') {
	$ret = delDocumentModel($value, $type);
	if ($ret > 0) {
		if (!empty($tmpobjectkey)) {
			$constforval = 'CRMCLIENTCONNECTOR_'.strtoupper($tmpobjectkey).'_ADDON_PDF';
			if (getDolGlobalString($constforval) == "$value") {
				dolibarr_del_const($db, $constforval, $conf->entity);
			}
		}
	}
} elseif ($action == 'setdoc') {
	// Set or unset default model
	if (!empty($tmpobjectkey)) {
		$constforval = 'CRMCLIENTCONNECTOR_'.strtoupper($tmpobjectkey).'_ADDON_PDF';
		if (dolibarr_set_const($db, $constforval, $value, 'chaine', 0, '', $conf->entity)) {
			// The constant that was read before the new set
			// We therefore requires a variable to have a coherent view
			$conf->global->{$constforval} = $value;
		}

		// We disable/enable the document template (into llx_document_model table)
		$ret = delDocumentModel($value, $type);
		if ($ret > 0) {
			$ret = addDocumentModel($value, $type, $label, $scandir);
		}
	}
} elseif ($action == 'unsetdoc') {
	if (!empty($tmpobjectkey)) {
		$constforval = 'CRMCLIENTCONNECTOR_'.strtoupper($tmpobjectkey).'_ADDON_PDF';
		dolibarr_del_const($db, $constforval, $conf->entity);
	}
}

$action = 'edit';


/*
 * View
 */

$form = new Form($db);

$help_url = '';
$title = "CRMClientConnectorSetup";

llxHeader('', $langs->trans($title), $help_url, '', 0, 0, '', '', '', 'mod-crmclientconnector page-admin');

// Subheader
$linkback = '<a href="'.($backtopage ? $backtopage : DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';

print load_fiche_titre($langs->trans($title), $linkback, 'title_setup');

// Configuration header
$head = crmclientconnectorAdminPrepareHead();
print dol_get_fiche_head($head, 'settings', $langs->trans($title), -1, "crmclientconnector@crmclientconnector");

// Setup page goes here
echo '<span class="opacitymedium">'.$langs->trans("CRMClientConnectorSetupPage").'</span><br><br>';



print '<h4>' . $langs->trans('ThisModuleAddNewDictionary') . '</h4>';
print '<ul><li>' . $langs->trans('ExcludedMailDomains') . '</li></ul>';
print '<a href="'. DOL_URL_ROOT .'/admin/dict.php?mainmenu=home">' . $langs->trans('ThisModuleAddNewDictionaryDesc') . '</a>';

print '<hr/>';

print '<h4>' . $langs->trans('ThisModuleAddObjectEmailAccount') . '</h4>';
print '<p>' . $langs->trans('ThisModuleAddObjectEmailAccountDesc') . '</p>';
print '<p><a href="'. dol_buildpath('crmclientconnector/emailaccount_list.php', 1) .'">' . $langs->trans('ThisModuleAddObjectEmailAccountLink') . '</a></p>';

print '<hr/>';

print '<h4>' . $langs->trans('NewUsersRightsForCRMClientConnector') . '</h4>';
print '<p>' . $langs->trans('NewUsersRightsForCRMClientConnectorDesc') . '</p>';

print '<hr/>';


/*if ($action == 'edit') {
 print $formSetup->generateOutput(true);
 print '<br>';
 } elseif (!empty($formSetup->items)) {
 print $formSetup->generateOutput();
 print '<div class="tabsAction">';
 print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?action=edit&token='.newToken().'">'.$langs->trans("Modify").'</a>';
 print '</div>';
 }
 */
if (!empty($formSetup->items)) {
	print $formSetup->generateOutput(true);
	print '<br>';
} else {
	print '<br>'.$langs->trans("NothingToSetup");
}


// Page end
print dol_get_fiche_end();

llxFooter();
$db->close();
