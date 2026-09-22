<?php
/* Copyright (C) 2015   Jean-François Ferry     <jfefe@aternatik.fr>
 * Copyright (C) ---Put here your own copyright and developer email---
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

use Luracast\Restler\RestException;

dol_include_once('/crmclientconnector/class/emailaccount.class.php');
dol_include_once('/crmclientconnector/class/emaillink.class.php');
dol_include_once('/crmclientconnector/class/emailusermsg.class.php');
dol_include_once('/crmclientconnector/lib/crmclientconnector_email_link.lib.php');



/**
 * \file    htdocs/modulebuilder/template/class/api_mymodule.class.php
 * \ingroup mymodule
 * \brief   File for API management of myobject.
 */

/**
 * API class for mymodule myobject
 *
 * @access protected
 * @class  DolibarrApiAccess {@requires user,external}
 */
class CRMClientConnector extends DolibarrApi
{
	/**
	 * @var EmailAccount $emailaccount {@type EmailAccount}
	 * @var EmailLink $emaillink {@type EmailLink}
	 * @var EmailUserMsg $emailusermsg {@type EmailUserMsg}
	 */
	public $emailaccount;
	public $emaillink;
	public $emailusermsg;

	/**
	 * Constructor
	 *
	 * @url     GET /
	 *
	 */
	public function __construct()
	{
		global $db;
		$this->db = $db;
		$this->emailaccount = new EmailAccount($this->db);
		$this->emaillink = new EmailLink($this->db);
		$this->emailusermsg = new EmailUserMsg($this->db);
	}


	/**
	 * Get properties of a excluded domains dictionnary
	 *
	 * Return an array with  excluded domains dictionnary information
	 *
	 * @param	int		$id				ID of excluded domains
	 * @return  Object					Object with cleaned properties
	 *
	 * @url	GET excludeddomains/{id}
	 *
	 * @throws RestException 403 Not allowed
	 * @throws RestException 404 Not found
	 */
	public function getExcludedDomain($id)
	{
		if (!DolibarrApiAccess::$user->hasRight('crmclientconnector', 'excludeddomains', 'read')) {
			throw new RestException(403);
		}

		$obj = $this->db->getRow('SELECT id, domain, active FROM '.$this->db->prefix().'crmclientconnector_excluded_domains WHERE id = ' . (int)$id );
		if (!$obj) {
			throw new RestException(404, 'EmailAccount not found');
		}

		return $obj;
	}

	/**
	 * Get properties of a excluded domains dictionnary
	 *
	 * @param string		   $sortfield			Sort field
	 * @param string		   $sortorder			Sort order
	 * @param int			   $limit				Limit for list
	 * @param int			   $page				Page number
	 * @param string           $sqlfilters          Other criteria to filter answers separated by a comma. Syntax example "(t.domain:like:'orange.%') and (t.active:<:'20160101')"
	 * @return  array                               Array of objects
	 *
	 * @return  Object					Object with cleaned properties
	 *
	 * @url	GET excludeddomains/
	 *
	 * @throws RestException 403 Not allowed
	 * @throws RestException 404 Not found
	 */
	public function getAllExcludedDomains($sortfield = "t.domain", $sortorder = 'ASC', $limit = 0, $page = 0, $sqlfilters = '')
	{
		if (!DolibarrApiAccess::$user->hasRight('crmclientconnector', 'excludeddomains', 'read')) {
			throw new RestException(403);
		}


		$sql = 'SELECT t.id id, t.domain domain, t.active active FROM '.$this->db->prefix().'crmclientconnector_excluded_domains t ';
		$sql .= " WHERE 1 = 1 ";

		if ($sqlfilters) {
			$errormessage = '';
			$sql .= forgeSQLFromUniversalSearchCriteria($sqlfilters, $errormessage);
			if ($errormessage) {
				throw new RestException(400, 'Error when validating parameter sqlfilters -> '.$errormessage);
			}
		}

		$sql .= $this->db->order($sortfield, $sortorder);


		if ($page < 0) {
			$page = 0;
		}
		$offset = $limit * $page;


		if(empty($limit)){
			$limit = PHP_INT_MAX-10; // because getRows request a limit
			$offset = 0;
		}


		$sql .= $this->db->plimit($limit + 1, $offset);


		$obj = $this->db->getRows($sql);
		if (!$obj) {
			throw new RestException(404, 'excluded domain not found');
		}

		$TExcluded = [];
		foreach ($obj as $item){
			$TExcluded[] = $item->domain;
		}

		return $TExcluded;
	}

	/**
	 * Get properties of a emailaccount object
	 *
	 * Return an array with emailaccount information
	 *
	 * @param	int		$id				ID of emailaccount
	 * @return  Object					Object with cleaned properties
	 *
	 * @url	GET emailaccounts/{id}
	 *
	 * @throws RestException 403 Not allowed
	 * @throws RestException 404 Not found
	 */
	public function getEmailAccounts($id)
	{
		if (!DolibarrApiAccess::$user->hasRight('crmclientconnector', 'emailaccount', 'read')) {
			throw new RestException(403);
		}
		if (!DolibarrApi::_checkAccessToResource('emailaccount', $id, 'crmclientconnector_emailaccount')) {
			throw new RestException(403, 'Access to instance id='.$id.' of object not allowed for login '.DolibarrApiAccess::$user->login);
		}

		$result = $this->emailaccount->fetch($id);
		if (!$result) {
			throw new RestException(404, 'EmailAccount not found');
		}

		return $this->_cleanObjectDatas($this->emailaccount);
	}


	/**
	 * List emailaccounts
	 *
	 * Get a list of emailaccounts
	 *
	 * @param string		   $sortfield			Sort field
	 * @param string		   $sortorder			Sort order
	 * @param int			   $limit				Limit for list
	 * @param int			   $page				Page number
	 * @param string           $sqlfilters          Other criteria to filter answers separated by a comma. Syntax example "(t.ref:like:'SO-%') and (t.date_creation:<:'20160101')"
	 * @param string		   $properties			Restrict the data returned to these properties. Ignored if empty. Comma separated list of properties names
	 * @return  array                               Array of order objects
	 *
	 * @throws RestException 403 Not allowed
	 * @throws RestException 503 System error
	 *
	 * @url	GET /emailaccounts/
	 */
	public function indexEmailAccounts($sortfield = "t.rowid", $sortorder = 'ASC', $limit = 100, $page = 0, $sqlfilters = '', $properties = '')
	{
		$obj_ret = array();
		$tmpobject = new EmailAccount($this->db);

		if (!DolibarrApiAccess::$user->hasRight('crmclientconnector', 'emailaccount', 'read')) {
			throw new RestException(403);
		}

		$socid = DolibarrApiAccess::$user->socid ? DolibarrApiAccess::$user->socid : 0;

		$restrictonsocid = 0; // Set to 1 if there is a field socid in table of object

		// If the internal user must only see his customers, force searching by him
		$search_sale = 0;
		if ($restrictonsocid && !DolibarrApiAccess::$user->hasRight('societe', 'client', 'voir') && !$socid) {
			$search_sale = DolibarrApiAccess::$user->id;
		}
		if (!isModEnabled('societe')) {
			$search_sale = 0; // If module thirdparty not enabled, sale representative is something that does not exists
		}

		$sql = "SELECT t.rowid";
		$sql .= " FROM ".MAIN_DB_PREFIX.$tmpobject->table_element." AS t";
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX.$tmpobject->table_element."_extrafields AS ef ON (ef.fk_object = t.rowid)"; // Modification VMR Global Solutions to include extrafields as search parameters in the API GET call, so we will be able to filter on extrafields
		$sql .= " WHERE 1 = 1";
		if ($tmpobject->ismultientitymanaged) {
			$sql .= ' AND t.entity IN ('.getEntity($tmpobject->element).')';
		}
		if ($restrictonsocid && $socid) {
			$sql .= " AND t.fk_soc = ".((int) $socid);
		}
		// Search on sale representative
		if ($search_sale && $search_sale != '-1') {
			if ($search_sale == -2) {
				$sql .= " AND NOT EXISTS (SELECT sc.fk_soc FROM ".MAIN_DB_PREFIX."societe_commerciaux as sc WHERE sc.fk_soc = t.fk_soc)";
			} elseif ($search_sale > 0) {
				$sql .= " AND EXISTS (SELECT sc.fk_soc FROM ".MAIN_DB_PREFIX."societe_commerciaux as sc WHERE sc.fk_soc = t.fk_soc AND sc.fk_user = ".((int) $search_sale).")";
			}
		}
		if ($sqlfilters) {
			$errormessage = '';
			$sql .= forgeSQLFromUniversalSearchCriteria($sqlfilters, $errormessage);
			if ($errormessage) {
				throw new RestException(400, 'Error when validating parameter sqlfilters -> '.$errormessage);
			}
		}

		$sql .= $this->db->order($sortfield, $sortorder);
		if ($limit) {
			if ($page < 0) {
				$page = 0;
			}
			$offset = $limit * $page;

			$sql .= $this->db->plimit($limit + 1, $offset);
		}

		$result = $this->db->query($sql);
		$i = 0;
		if ($result) {
			$num = $this->db->num_rows($result);
			while ($i < $num) {
				$obj = $this->db->fetch_object($result);
				$tmp_object = new EmailAccount($this->db);
				if ($tmp_object->fetch($obj->rowid)) {
					$obj_ret[] = $this->_filterObjectProperties($this->_cleanObjectDatas($tmp_object), $properties);
				}
				$i++;
			}
		} else {
			throw new RestException(503, 'Error when retrieving emailaccount list: '.$this->db->lasterror());
		}

		return $obj_ret;
	}

	/**
	 * Create emailaccount object
	 *
	 * @param array $request_data   Request datas
	 * @return int  				ID of emailaccount
	 *
	 * @throws RestException 403 Not allowed
	 * @throws RestException 500 System error
	 *
	 * @url	POST emailaccounts/
	 */
	public function postEmailAccounts($request_data = null)
	{
		if (!DolibarrApiAccess::$user->hasRight('crmclientconnector', 'emailaccount', 'write')) {
			throw new RestException(403);
		}

		// Check mandatory fields
		$result = $this->_validateEmailAccount($request_data);

		foreach ($request_data as $field => $value) {
			if ($field === 'caller') {
				// Add a mention of caller so on trigger called after action, we can filter to avoid a loop if we try to sync back again with the caller
				$this->emailaccount->context['caller'] = sanitizeVal($request_data['caller'], 'aZ09');
				continue;
			}

			if ($field == 'array_options' && is_array($value)) {
				foreach ($value as $index => $val) {
					$this->emailaccount->array_options[$index] = $this->_checkValForAPI('extrafields', $val, $this->emailaccount);
				}
				continue;
			}

			$this->emailaccount->$field = $this->_checkValForAPI($field, $value, $this->emailaccount);
		}

		// Clean data
		// $this->emailaccount->abc = sanitizeVal($this->emailaccount->abc, 'alphanohtml');

		if ($this->emailaccount->create(DolibarrApiAccess::$user)<0) {
			throw new RestException(500, "Error creating EmailAccount", array_merge(array($this->emailaccount->error), $this->emailaccount->errors));
		}
		return $this->emailaccount->id;
	}

	/**
	 * Update emailaccount
	 *
	 * @param 	int   		$id             Id of emailaccount to update
	 * @param 	array 		$request_data   Datas
	 * @return 	Object						Object after update
	 *
	 * @throws RestException 403 Not allowed
	 * @throws RestException 404 Not found
	 * @throws RestException 500 System error
	 *
	 * @url	PUT emailaccounts/{id}
	 */
	public function putEmailAccounts($id, $request_data = null)
	{
		if (!DolibarrApiAccess::$user->hasRight('crmclientconnector', 'emailaccount', 'write')) {
			throw new RestException(403);
		}
		if (!DolibarrApi::_checkAccessToResource('emailaccount', $id, 'crmclientconnector_emailaccount')) {
			throw new RestException(403, 'Access to instance id='.$this->emailaccount->id.' of object not allowed for login '.DolibarrApiAccess::$user->login);
		}

		$result = $this->emailaccount->fetch($id);
		if (!$result) {
			throw new RestException(404, 'EmailAccount not found');
		}

		foreach ($request_data as $field => $value) {
			if ($field == 'id') {
				continue;
			}
			if ($field === 'caller') {
				// Add a mention of caller so on trigger called after action, we can filter to avoid a loop if we try to sync back again with the caller
				$this->emailaccount->context['caller'] = sanitizeVal($request_data['caller'], 'aZ09');
				continue;
			}

			if ($field == 'array_options' && is_array($value)) {
				foreach ($value as $index => $val) {
					$this->emailaccount->array_options[$index] = $this->_checkValForAPI('extrafields', $val, $this->emailaccount);
				}
				continue;
			}

			if ($field == 'array_options' && is_array($value)) {
				foreach ($value as $index => $val) {
					$this->emailaccount->array_options[$index] = $this->_checkValForAPI($field, $val, $this->emailaccount);
				}
				continue;
			}

			$this->emailaccount->$field = $this->_checkValForAPI($field, $value, $this->emailaccount);
		}

		// Clean data
		// $this->emailaccount->abc = sanitizeVal($this->emailaccount->abc, 'alphanohtml');

		if ($this->emailaccount->update(DolibarrApiAccess::$user, false) > 0) {
			return $this->get($id);
		} else {
			throw new RestException(500, $this->emailaccount->error);
		}
	}

	/**
	 * Delete emailaccount
	 *
	 * @param   int     $id   EmailAccount ID
	 * @return  array
	 *
	 * @throws RestException 403 Not allowed
	 * @throws RestException 404 Not found
	 * @throws RestException 409 Nothing to do
	 * @throws RestException 500 System error
	 *
	 * @url	DELETE emailaccounts/{id}
	 */
	public function deleteEmailAccounts($id)
	{
		if (!DolibarrApiAccess::$user->hasRight('crmclientconnector', 'emailaccount', 'delete')) {
			throw new RestException(403);
		}
		if (!DolibarrApi::_checkAccessToResource('emailaccount', $id, 'crmclientconnector_emailaccount')) {
			throw new RestException(403, 'Access to instance id='.$this->emailaccount->id.' of object not allowed for login '.DolibarrApiAccess::$user->login);
		}

		$result = $this->emailaccount->fetch($id);
		if (!$result) {
			throw new RestException(404, 'EmailAccount not found');
		}

		if ($this->emailaccount->delete(DolibarrApiAccess::$user) == 0) {
			throw new RestException(409, 'Error when deleting EmailAccount : '.$this->emailaccount->error);
		} elseif ($this->emailaccount->delete(DolibarrApiAccess::$user) < 0) {
			throw new RestException(500, 'Error when deleting EmailAccount : '.$this->emailaccount->error);
		}

		return array(
			'success' => array(
				'code' => 200,
				'message' => 'EmailAccount deleted'
			)
		);
	}


	/**
	 * Validate fields before create or update object
	 *
	 * @param	array		$data   Array of data to validate
	 * @return	array
	 *
	 * @throws	RestException
	 */
	private function _validateEmailAccount($data)
	{
		$emailaccount = array();
		foreach ($this->emailaccount->fields as $field => $propfield) {
			if (in_array($field, array('rowid', 'entity', 'date_creation', 'tms', 'fk_user_creat')) || $propfield['notnull'] != 1) {
				continue; // Not a mandatory field
			}
			if (!isset($data[$field])) {
				throw new RestException(400, "$field field missing");
			}
			$emailaccount[$field] = $data[$field];
		}
		return $emailaccount;
	}

	/* END MODULEBUILDER API EMAILACCOUNT */


	/* BEGIN MODULEBUILDER API EMAILLINK */
	/**
	 * Get properties of a emaillink object
	 *
	 * Return an array with emaillink information
	 *
	 * @param	int		$id				ID of emaillink
	 * @return  Object					Object with cleaned properties
	 *
	 * @url	GET emaillinks/{id}
	 *
	 * @throws RestException 403 Not allowed
	 * @throws RestException 404 Not found
	 */
	public function getEmailLink($id)
	{
		if (!DolibarrApiAccess::$user->hasRight('crmclientconnector', 'emaillink', 'read')) {
			throw new RestException(403);
		}
		if (!DolibarrApi::_checkAccessToResource('emaillink', $id, 'crmclientconnector_emaillink')) {
			throw new RestException(403, 'Access to instance id='.$id.' of object not allowed for login '.DolibarrApiAccess::$user->login);
		}

		$result = $this->emaillink->fetch($id);
		if (!$result) {
			throw new RestException(404, 'EmailLink not found');
		}

		return $this->_cleanObjectDatas($this->emaillink);
	}


	/**
	 * List emaillinks
	 *
	 * Get a list of emaillinks
	 *
	 * @param string		   $sortfield			Sort field
	 * @param string		   $sortorder			Sort order
	 * @param int			   $limit				Limit for list
	 * @param int			   $page				Page number
	 * @param string           $sqlfilters          Other criteria to filter answers separated by a comma. Syntax example "(t.ref:like:'SO-%') and (t.date_creation:<:'20160101')"
	 * @param string		   $properties			Restrict the data returned to these properties. Ignored if empty. Comma separated list of properties names
	 * @return  array                               Array of order objects
	 *
	 * @throws RestException 403 Not allowed
	 * @throws RestException 503 System error
	 *
	 * @url	GET /emaillinks/
	 */
	public function indexEmailLink($sortfield = "t.rowid", $sortorder = 'ASC', $limit = 100, $page = 0, $sqlfilters = '', $properties = '')
	{
		$obj_ret = array();
		$tmpobject = new EmailLink($this->db);

		if (!DolibarrApiAccess::$user->hasRight('crmclientconnector', 'emaillink', 'read')) {
			throw new RestException(403);
		}

		$socid = DolibarrApiAccess::$user->socid ? DolibarrApiAccess::$user->socid : 0;

		$restrictonsocid = 0; // Set to 1 if there is a field socid in table of object

		// If the internal user must only see his customers, force searching by him
		$search_sale = 0;
		if ($restrictonsocid && !DolibarrApiAccess::$user->hasRight('societe', 'client', 'voir') && !$socid) {
			$search_sale = DolibarrApiAccess::$user->id;
		}
		if (!isModEnabled('societe')) {
			$search_sale = 0; // If module thirdparty not enabled, sale representative is something that does not exists
		}

		$sql = "SELECT t.rowid";
		$sql .= " FROM ".MAIN_DB_PREFIX.$tmpobject->table_element." AS t";
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX.$tmpobject->table_element."_extrafields AS ef ON (ef.fk_object = t.rowid)"; // Modification VMR Global Solutions to include extrafields as search parameters in the API GET call, so we will be able to filter on extrafields
		$sql .= " WHERE 1 = 1";
		if ($tmpobject->ismultientitymanaged) {
			$sql .= ' AND t.entity IN ('.getEntity($tmpobject->element).')';
		}
		if ($restrictonsocid && $socid) {
			$sql .= " AND t.fk_soc = ".((int) $socid);
		}
		// Search on sale representative
		if ($search_sale && $search_sale != '-1') {
			if ($search_sale == -2) {
				$sql .= " AND NOT EXISTS (SELECT sc.fk_soc FROM ".MAIN_DB_PREFIX."societe_commerciaux as sc WHERE sc.fk_soc = t.fk_soc)";
			} elseif ($search_sale > 0) {
				$sql .= " AND EXISTS (SELECT sc.fk_soc FROM ".MAIN_DB_PREFIX."societe_commerciaux as sc WHERE sc.fk_soc = t.fk_soc AND sc.fk_user = ".((int) $search_sale).")";
			}
		}
		if ($sqlfilters) {
			$errormessage = '';
			$sql .= forgeSQLFromUniversalSearchCriteria($sqlfilters, $errormessage);
			if ($errormessage) {
				throw new RestException(400, 'Error when validating parameter sqlfilters -> '.$errormessage);
			}
		}

		$sql .= $this->db->order($sortfield, $sortorder);
		if ($limit) {
			if ($page < 0) {
				$page = 0;
			}
			$offset = $limit * $page;

			$sql .= $this->db->plimit($limit + 1, $offset);
		}

		$result = $this->db->query($sql);
		$i = 0;
		if ($result) {
			$num = $this->db->num_rows($result);
			while ($i < $num) {
				$obj = $this->db->fetch_object($result);
				$tmp_object = new EmailLink($this->db);
				if ($tmp_object->fetch($obj->rowid)) {
					$obj_ret[] = $this->_filterObjectProperties($this->_cleanObjectDatas($tmp_object), $properties);
				}
				$i++;
			}
		} else {
			throw new RestException(503, 'Error when retrieving emaillink list: '.$this->db->lasterror());
		}

		return $obj_ret;
	}

	/**
	 * Create emaillink object
	 *
	 * @param array $request_data   Request datas
	 * @return int  				ID of emaillink
	 *
	 * @throws RestException 403 Not allowed
	 * @throws RestException 500 System error
	 *
	 * @url	POST emaillinks/
	 */
	public function postEmailLink($request_data = null)
	{
		if (!DolibarrApiAccess::$user->hasRight('crmclientconnector', 'emaillink', 'write')) {
			throw new RestException(403);
		}

		// Check mandatory fields
		$result = $this->_validateEmailLink($request_data);

		foreach ($request_data as $field => $value) {
			if ($field === 'caller') {
				// Add a mention of caller so on trigger called after action, we can filter to avoid a loop if we try to sync back again with the caller
				$this->emaillink->context['caller'] = sanitizeVal($request_data['caller'], 'aZ09');
				continue;
			}

			if ($field == 'array_options' && is_array($value)) {
				foreach ($value as $index => $val) {
					$this->emaillink->array_options[$index] = $this->_checkValForAPI('extrafields', $val, $this->emaillink);
				}
				continue;
			}

			$this->emaillink->$field = $this->_checkValForAPI($field, $value, $this->emaillink);
		}

		// Clean data
		// $this->emaillink->abc = sanitizeVal($this->emaillink->abc, 'alphanohtml');

		if ($this->emaillink->create(DolibarrApiAccess::$user)<0) {
			throw new RestException(500, "Error creating EmailLink", array_merge(array($this->emaillink->error), $this->emaillink->errors));
		}
		return $this->emaillink->id;
	}

	/**
	 * Update emaillink
	 *
	 * @param 	int   		$id             Id of emaillink to update
	 * @param 	array 		$request_data   Datas
	 * @return 	Object						Object after update
	 *
	 * @throws RestException 403 Not allowed
	 * @throws RestException 404 Not found
	 * @throws RestException 500 System error
	 *
	 * @url	PUT emaillinks/{id}
	 */
	public function putEmailLink($id, $request_data = null)
	{
		if (!DolibarrApiAccess::$user->hasRight('crmclientconnector', 'emaillink', 'write')) {
			throw new RestException(403);
		}
		if (!DolibarrApi::_checkAccessToResource('emaillink', $id, 'crmclientconnector_emaillink')) {
			throw new RestException(403, 'Access to instance id='.$this->emaillink->id.' of object not allowed for login '.DolibarrApiAccess::$user->login);
		}

		$result = $this->emaillink->fetch($id);
		if (!$result) {
			throw new RestException(404, 'EmailLink not found');
		}

		foreach ($request_data as $field => $value) {
			if ($field == 'id') {
				continue;
			}
			if ($field === 'caller') {
				// Add a mention of caller so on trigger called after action, we can filter to avoid a loop if we try to sync back again with the caller
				$this->emaillink->context['caller'] = sanitizeVal($request_data['caller'], 'aZ09');
				continue;
			}

			if ($field == 'array_options' && is_array($value)) {
				foreach ($value as $index => $val) {
					$this->emaillink->array_options[$index] = $this->_checkValForAPI('extrafields', $val, $this->emaillink);
				}
				continue;
			}

			if ($field == 'array_options' && is_array($value)) {
				foreach ($value as $index => $val) {
					$this->emaillink->array_options[$index] = $this->_checkValForAPI($field, $val, $this->emaillink);
				}
				continue;
			}

			$this->emaillink->$field = $this->_checkValForAPI($field, $value, $this->emaillink);
		}

		// Clean data
		// $this->emaillink->abc = sanitizeVal($this->emaillink->abc, 'alphanohtml');

		if ($this->emaillink->update(DolibarrApiAccess::$user, false) > 0) {
			return $this->get($id);
		} else {
			throw new RestException(500, $this->emaillink->error);
		}
	}

	/**
	 * Delete emaillink
	 *
	 * @param   int     $id   EmailLink ID
	 * @return  array
	 *
	 * @throws RestException 403 Not allowed
	 * @throws RestException 404 Not found
	 * @throws RestException 409 Nothing to do
	 * @throws RestException 500 System error
	 *
	 * @url	DELETE emaillinks/{id}
	 */
	public function deleteEmailLink($id)
	{
		if (!DolibarrApiAccess::$user->hasRight('crmclientconnector', 'emaillink', 'delete')) {
			throw new RestException(403);
		}
		if (!DolibarrApi::_checkAccessToResource('emaillink', $id, 'crmclientconnector_emaillink')) {
			throw new RestException(403, 'Access to instance id='.$this->emaillink->id.' of object not allowed for login '.DolibarrApiAccess::$user->login);
		}

		$result = $this->emaillink->fetch($id);
		if (!$result) {
			throw new RestException(404, 'EmailLink not found');
		}

		if ($this->emaillink->delete(DolibarrApiAccess::$user) == 0) {
			throw new RestException(409, 'Error when deleting EmailLink : '.$this->emaillink->error);
		} elseif ($this->emaillink->delete(DolibarrApiAccess::$user) < 0) {
			throw new RestException(500, 'Error when deleting EmailLink : '.$this->emaillink->error);
		}

		return array(
			'success' => array(
				'code' => 200,
				'message' => 'EmailLink deleted'
			)
		);
	}


	/**
	 * Map of the short type codes used by the Thunderbird doliconnector extension
	 * (see DOLIBARR_OBJECT_TYPES in its global.lib.js) to Dolibarr's own "element" strings,
	 * as used by fetchObjectByElement()/add_object_linked()/CommonObject::$element.
	 *
	 * @var array<string,string>
	 */
	const LINKABLE_ELEMENT_TYPES = array(
		'ord'  => 'commande',
		'pro'  => 'propal',
		'inv'  => 'facture',
		'sord' => 'order_supplier',
		'sinv' => 'invoice_supplier',
		'shi'  => 'shipping',
		'con'  => 'contrat',
		'tic'  => 'ticket',
		'proj' => 'project',
		'int'  => 'fichinter',
		'mem'  => 'member',
		'act'  => 'action',
	);

	/**
	 * Fetch (read-only, does not create) the EmailLink for an accountEmail+msgId pair.
	 *
	 * @param	string	$accountEmail	Email address of the mailbox owning the message
	 * @param	string	$msgId			Message-Id of the mail
	 * @return	EmailLink|null			EmailLink object, or null if none exists yet
	 */
	private function _fetchEmailLinkReadOnly($accountEmail, $msgId)
	{
		if (empty($accountEmail) || empty($msgId) || !$this->_fetchImailLinkByMsgId($accountEmail, $msgId)) {
			return null;
		}
		return $this->emaillink;
	}

	/**
	 * List objects linked to the mail identified by accountEmail+msgId (documents already linked)
	 *
	 * @param	string	$accountEmail	Email address of the mailbox owning the message
	 * @param	string	$msgId			Message-Id of the mail
	 * @return	array					List of {type, elementtype, id, ref, refClient, refSupplier, status, statusCode, date, totalTtc}
	 *
	 * @throws RestException 403 Not allowed
	 *
	 * @url	GET emaillinks/linkedobjects
	 */
	public function getEmailLinkLinkedObjects($accountEmail, $msgId)
	{
		if (!DolibarrApiAccess::$user->hasRight('crmclientconnector', 'emaillink', 'read')) {
			throw new RestException(403);
		}

		$emaillink = $this->_fetchEmailLinkReadOnly($accountEmail, $msgId);
		if (!$emaillink) {
			return array(); // No EmailLink yet for this mail = no links yet, not an error
		}

		// Explicit sourceid/sourcetype, NOT fetchObjectLinked()'s default : with no override it
		// falls back to $emaillink->getElementType(), which prefixes the module name
		// ('crmclientconnector_emaillink') - but postEmailLinkLink() below stores the bare literal
		// 'emaillink' as sourcetype (it's passed as-is to add_object_linked(), never through
		// getElementType()). Left as the default, this search criteria never matched the row it
		// just wrote, so linkedObjects came back empty even right after a successful link.
		$emaillink->fetchObjectLinked($emaillink->id, 'emaillink', null, '', 'OR', 1, 'sourcetype', 1);

		$typeByElement = array_flip(self::LINKABLE_ELEMENT_TYPES);

		$result = array();
		foreach ($emaillink->linkedObjects as $elementtype => $objects) {
			if (!isset($typeByElement[$elementtype])) {
				continue; // Not one of the types this endpoint deals with (could be the reverse link back to 'emaillink' itself)
			}
			foreach ($objects as $linkedObject) {
				$result[] = array(
					'type' => $typeByElement[$elementtype],
					'elementtype' => $elementtype,
					'id' => $linkedObject->id,
					'ref' => $linkedObject->ref,
					// The fields below don't all apply to every document type (a ticket has no
					// total_ttc, a project has no ref_supplier, ...) - null when not applicable,
					// the Thunderbird extension only shows the ones it gets.
					'refClient' => !empty($linkedObject->ref_client) ? $linkedObject->ref_client : null,
					'refSupplier' => !empty($linkedObject->ref_supplier) ? $linkedObject->ref_supplier : null,
					'status' => method_exists($linkedObject, 'getLibStatut') ? $linkedObject->getLibStatut(0) : null,
					'statusCode' => isset($linkedObject->statut) ? (int) $linkedObject->statut : (isset($linkedObject->status) ? (int) $linkedObject->status : null),
					'date' => !empty($linkedObject->date) ? (int) $linkedObject->date : (!empty($linkedObject->date_commande) ? (int) $linkedObject->date_commande : (!empty($linkedObject->datep) ? (int) $linkedObject->datep : null)),
					'totalTtc' => isset($linkedObject->total_ttc) && $linkedObject->total_ttc !== '' ? (float) $linkedObject->total_ttc : null,
					'socid' => !empty($linkedObject->socid) ? (int) $linkedObject->socid : (!empty($linkedObject->fk_soc) ? (int) $linkedObject->fk_soc : null),
				);
			}
		}

		return $result;
	}

	/**
	 * Link a document (devis/commande/facture/...) to the mail identified by accountEmail+msgId.
	 * Creates the EmailAccount/EmailLink rows for this mail if they don't exist yet.
	 *
	 * @param	array	$request_data	{accountEmail, msgId, type: short type code from LINKABLE_ELEMENT_TYPES, elementid: int}
	 * @return	array
	 *
	 * @throws RestException 400 Bad request
	 * @throws RestException 403 Not allowed
	 * @throws RestException 404 Not found
	 * @throws RestException 500 System error
	 *
	 * @url	POST emaillinks/link
	 */
	public function postEmailLinkLink($request_data = null)
	{
		if (!DolibarrApiAccess::$user->hasRight('crmclientconnector', 'emaillink', 'write')) {
			throw new RestException(403);
		}

		$accountEmail = empty($request_data['accountEmail']) ? '' : $request_data['accountEmail'];
		$msgId = empty($request_data['msgId']) ? '' : $request_data['msgId'];
		$type = empty($request_data['type']) ? '' : $request_data['type'];
		$elementid = empty($request_data['elementid']) ? 0 : (int) $request_data['elementid'];

		if (empty($accountEmail) || empty($msgId) || empty($type) || !isset(self::LINKABLE_ELEMENT_TYPES[$type]) || $elementid <= 0) {
			throw new RestException(400, 'Missing or invalid accountEmail/msgId/type/elementid');
		}

		$emaillink = crmclientconnectorGetOrCreateEmailLink($this->db, DolibarrApiAccess::$user, $accountEmail, $msgId);
		if (!is_object($emaillink)) {
			throw new RestException(500, 'Error creating EmailLink for this mail');
		}

		$elementtype = self::LINKABLE_ELEMENT_TYPES[$type];

		$targetObject = fetchObjectByElement($elementid, $elementtype);
		if (!is_object($targetObject)) {
			throw new RestException(404, ucfirst($elementtype).' not found');
		}

		// fetchObjectByElement() sets ->module as a convenience for its own cache/isModEnabled
		// check, but CommonObject::add_object_linked() prefixes targettype with the module name
		// whenever ->module is non-empty. The rest of this install's element_element rows for
		// these types (propal->commande conversions, supplier order/proposal links, ...) never
		// carry that prefix, because the native code creating them never sets ->module - clear it
		// so we store the same bare elementtype ('commande', 'order_supplier', ...) as everyone
		// else, instead of a one-off 'commande_commande'/'fournisseur_order_supplier' only we use.
		$targetObject->module = '';

		$result = $targetObject->add_object_linked('emaillink', $emaillink->id, DolibarrApiAccess::$user);
		if ($result <= 0) {
			throw new RestException(500, 'Error linking object', array_merge(array($targetObject->error), $targetObject->errors));
		}

		return array(
			'success' => array(
				'code' => 200,
				'message' => 'Object linked'
			)
		);
	}

	/**
	 * Unlink a document from the mail identified by accountEmail+msgId
	 *
	 * @param	string	$accountEmail	Email address of the mailbox owning the message
	 * @param	string	$msgId			Message-Id of the mail
	 * @param	string	$type			Short type code from LINKABLE_ELEMENT_TYPES
	 * @param	int		$elementid		ID of the linked object
	 * @return	array
	 *
	 * @throws RestException 400 Bad request
	 * @throws RestException 403 Not allowed
	 * @throws RestException 404 Not found
	 * @throws RestException 500 System error
	 *
	 * @url	DELETE emaillinks/link
	 */
	public function deleteEmailLinkLink($accountEmail, $msgId, $type = '', $elementid = 0)
	{
		if (!DolibarrApiAccess::$user->hasRight('crmclientconnector', 'emaillink', 'write')) {
			throw new RestException(403);
		}

		$elementid = (int) $elementid;
		if (empty($type) || !isset(self::LINKABLE_ELEMENT_TYPES[$type]) || $elementid <= 0) {
			throw new RestException(400, 'Missing or invalid type/elementid');
		}

		$emaillink = $this->_fetchEmailLinkReadOnly($accountEmail, $msgId);
		if (!$emaillink) {
			throw new RestException(404, 'EmailLink not found');
		}

		$elementtype = self::LINKABLE_ELEMENT_TYPES[$type];

		$sql = "SELECT rowid FROM ".$this->db->prefix()."element_element";
		$sql .= " WHERE fk_source = ".((int) $emaillink->id)." AND sourcetype = 'emaillink'";
		$sql .= " AND fk_target = ".$elementid." AND targettype = '".$this->db->escape($elementtype)."'";
		$resql = $this->db->query($sql);
		if (!$resql || $this->db->num_rows($resql) == 0) {
			throw new RestException(404, 'Link not found');
		}
		$rowid = (int) $this->db->fetch_object($resql)->rowid;

		$result = $emaillink->deleteObjectLinked(null, '', null, '', $rowid);
		if ($result <= 0) {
			throw new RestException(500, 'Error unlinking object : '.$emaillink->error);
		}

		return array(
			'success' => array(
				'code' => 200,
				'message' => 'Object unlinked'
			)
		);
	}

	/**
	 * Map of short type code => [core/modules/<dir>, $conf->global->CONSTNAME] for the active
	 * numbering module of each document type, so Thunderbird can build a ref-detection regex from
	 * a real example. getExample() is the only method guaranteed to exist and be reliable across
	 * every ModeleNumRef* class - $prefix is not (some classes have several prefixes, some none).
	 *
	 * @var array<string,array{0:string,1:string}>
	 */
	const NUMBERING_MODULE_TYPES = array(
		'pro'  => array('propale', 'PROPALE_ADDON'),
		'ord'  => array('commande', 'COMMANDE_ADDON'),
		'inv'  => array('facture', 'FACTURE_ADDON'),
		'sord' => array('supplier_order', 'COMMANDE_SUPPLIER_ADDON_NUMBER'),
		'sinv' => array('supplier_invoice', 'INVOICE_SUPPLIER_ADDON_NUMBER'),
		'con'  => array('contract', 'CONTRACT_ADDON'),
		'shi'  => array('expedition', 'EXPEDITION_ADDON_NUMBER'),
		'tic'  => array('ticket', 'TICKET_ADDON'),
		'proj' => array('project', 'PROJECT_ADDON'),
		'int'  => array('fichinter', 'FICHEINTER_ADDON'),
		'mem'  => array('member', 'MEMBER_CODEMEMBER_ADDON'),
	);

	/**
	 * Return, for each document type, the active numbering module and an example ref, so an
	 * external tool (Thunderbird doliconnector) can build a regex to detect document references
	 * in free text (email subject/body).
	 *
	 * @return	array	{ "pro": {"addon": "mod_propale_marbre", "example": "PR0501-0001"}, ... }
	 *
	 * @throws RestException 403 Not allowed
	 *
	 * @url	GET numberingpatterns/
	 */
	public function getNumberingPatterns()
	{
		if (!DolibarrApiAccess::$user->hasRight('crmclientconnector', 'emaillink', 'read')) {
			throw new RestException(403);
		}

		$result = array();

		foreach (self::NUMBERING_MODULE_TYPES as $type => $config) {
			list($moduledir, $constname) = $config;

			try {
				$activefile = getDolGlobalString($constname);
				if (empty($activefile)) {
					continue;
				}

				$dir = dol_buildpath('/core/modules/'.$moduledir);
				$file = $dir.'/'.$activefile.'.php';
				if (!file_exists($file)) {
					continue;
				}
				require_once $file;
				if (!class_exists($activefile)) {
					continue;
				}

				$module = new $activefile();
				$example = $module->getExample();
				// getExample() can return a translation key instead of a real example (module not
				// configured, or an error) - not usable to build a detection pattern from.
				if (empty($example) || preg_match('/^Error/', $example) || $example == 'NotConfigured') {
					continue;
				}

				$result[$type] = array(
					'addon' => $activefile,
					'example' => $example,
				);
			} catch (Exception $e) {
				dol_syslog('getNumberingPatterns: failed for type '.$type.' : '.$e->getMessage(), LOG_WARNING);
				continue;
			}
		}

		return $result;
	}

	/**
	 * Map of this module's short type code (see LINKABLE_ELEMENT_TYPES above) to the Categorie
	 * class's own type string (its MAP_ID keys), for every type that has one - 'con' (contrat)
	 * and 'shi' (shipping) don't, Categorie has no category type for them at all.
	 *
	 * @var array<string,string>
	 */
	const CATEGORY_TYPES = array(
		'ord'  => 'order',
		'pro'  => 'propal',
		'inv'  => 'invoice',
		'sord' => 'supplier_order',
		'sinv' => 'supplier_invoice',
		'tic'  => 'ticket',
		'proj' => 'project',
		'int'  => 'fichinter',
		'mem'  => 'member',
		'act'  => 'actioncomm',
	);

	/**
	 * hasRight() arguments (module, permlevel1[, permlevel2]) required to read an object of each
	 * CATEGORY_TYPES entry - same checks Dolibarr's own API classes use for that object type's own
	 * get()/index() (see e.g. api_orders.class.php, api_proposals.class.php, api_supplier_orders
	 * .class.php...), or, for the five types core's own categories API already allows (ticket/
	 * project/fichinter/member/actioncomm), the exact same check api_categories.class.php's
	 * getListForObject() uses.
	 *
	 * @var array<string,string[]>
	 */
	const CATEGORY_TYPE_RIGHTS = array(
		'ord'  => array('commande', 'lire'),
		'pro'  => array('propal', 'lire'),
		'inv'  => array('facture', 'lire'),
		'sord' => array('fournisseur', 'commande', 'lire'),
		'sinv' => array('fournisseur', 'facture', 'lire'),
		'tic'  => array('ticket', 'read'),
		'proj' => array('projet', 'lire'),
		'int'  => array('ficheinter', 'lire'),
		'mem'  => array('adherent', 'lire'),
		'act'  => array('agenda', 'allactions', 'read'),
	);

	/**
	 * List the categories/tags assigned to an object of any CATEGORY_TYPES type.
	 *
	 * Dolibarr core's own GET categories/object/{type}/{id} (api_categories.class.php's
	 * getListForObject()) only allows a specific whitelist of types : product, contact, customer,
	 * supplier, member, project, knowledgemanagement, actioncomm, user, warehouse, ticket,
	 * fichinter - order/invoice/propal/supplier_order/supplier_invoice are rejected with a 403
	 * even though Categorie's own data model (see its MAP_ID property) fully supports categories
	 * for them too, and Categorie::getListForItem() (the method that whitelist gates) works fine
	 * for any of them when called directly, which is what this does - the Thunderbird
	 * doliconnector extension's document cards need tags for quotations/orders/invoices too, not
	 * just the five types core's endpoint happens to allow.
	 *
	 * @param	string	$type	Short type code, see CATEGORY_TYPES
	 * @param	int		$id		Object id
	 * @return	array
	 *
	 * @throws RestException 400 Bad request
	 * @throws RestException 403 Not allowed
	 * @throws RestException 500 System error
	 *
	 * @url GET objectcategories/{type}/{id}
	 */
	public function getObjectCategories($type, $id)
	{
		if (!isset(self::CATEGORY_TYPES[$type])) {
			throw new RestException(400, 'Unknown or unsupported type');
		}

		if (!DolibarrApiAccess::$user->hasRight('categorie', 'lire')) {
			throw new RestException(403);
		}

		list($module, $permlevel1, $permlevel2) = array_pad(self::CATEGORY_TYPE_RIGHTS[$type], 3, '');
		if (!DolibarrApiAccess::$user->hasRight($module, $permlevel1, $permlevel2)) {
			throw new RestException(403);
		}

		require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';
		$categorie = new Categorie($this->db);

		$result = $categorie->getListForItem((int) $id, self::CATEGORY_TYPES[$type]);
		if (!is_array($result)) {
			throw new RestException(500, 'Error fetching categories : '.$categorie->error);
		}

		return $result;
	}

	/**
	 * Validate fields before create or update object
	 *
	 * @param	array		$data   Array of data to validate
	 * @return	array
	 *
	 * @throws	RestException
	 */
	private function _validateEmailLink($data)
	{
		$emaillink = array();
		foreach ($this->emaillink->fields as $field => $propfield) {
			if (in_array($field, array('rowid', 'entity', 'date_creation', 'tms', 'fk_user_creat')) || $propfield['notnull'] != 1) {
				continue; // Not a mandatory field
			}
			if (!isset($data[$field])) {
				throw new RestException(400, "$field field missing");
			}
			$emaillink[$field] = $data[$field];
		}
		return $emaillink;
	}

	/* END MODULEBUILDER API EMAILLINK */


	/* BEGIN MODULEBUILDER API EMAILUSERMSG */
	/**
	 * Get properties of a emailusermsg object
	 *
	 * Return an array with emailusermsg information
	 *
	 * @param	int		$id				ID of emailusermsg
	 * @return  Object					Object with cleaned properties
	 *
	 * @url	GET emailusermsgs/{id}
	 *
	 * @throws RestException 403 Not allowed
	 * @throws RestException 404 Not found
	 */
	public function getEmailUserMsg($id)
	{
		if (!DolibarrApiAccess::$user->hasRight('crmclientconnector', 'emailusermsg', 'read')) {
			throw new RestException(403);
		}
		if (!DolibarrApi::_checkAccessToResource('emailusermsg', $id, 'crmclientconnector_emailusermsg')) {
			throw new RestException(403, 'Access to instance id='.$id.' of object not allowed for login '.DolibarrApiAccess::$user->login);
		}

		$result = $this->emailusermsg->fetch($id);
		if (!$result) {
			throw new RestException(404, 'EmailUserMsg not found');
		}

		return $this->_cleanObjectDatas($this->emailusermsg);
	}

	/**
	 * List emailusermsgs
	 *
	 * Get a list of emailusermsgs
	 *
	 * @param string		   $sortfield			Sort field
	 * @param string		   $sortorder			Sort order
	 * @param int			   $limit				Limit for list
	 * @param int			   $page				Page number
	 * @param string           $sqlfilters          Other criteria to filter answers separated by a comma. Syntax example "(t.ref:like:'SO-%') and (t.date_creation:<:'20160101')"
	 * @param string		   $properties			Restrict the data returned to these properties. Ignored if empty. Comma separated list of properties names
	 * @return  array                               Array of order objects
	 *
	 * @throws RestException 403 Not allowed
	 * @throws RestException 503 System error
	 *
	 * @url	GET /emailusermsgs/
	 */
	public function indexEmailUserMsgFromAccountAndMsId($accountEmail, $msgId, $sortfield = "t.rowid", $sortorder = 'ASC', $limit = 100, $page = 0, $sqlfilters = '', $properties = '')
	{

		if (!DolibarrApiAccess::$user->hasRight('crmclientconnector', 'emaillink', 'read')) {
			throw new RestException(403);
		}

		if (!$this->_fetchImailLinkByMsgId($accountEmail, $msgId)){
			throw new RestException(404, 'EmailLink not found');
		}

		return $this->indexEmailUserMsg($sortfield, $sortorder, $limit, 0,'',  '');
	}

	/**
	 * List emailusermsgs
	 *
	 * Get a list of emailusermsgs
	 *
	 * @param string		   $sortfield			Sort field
	 * @param string		   $sortorder			Sort order
	 * @param int			   $limit				Limit for list
	 * @param int			   $page				Page number
	 * @param string           $sqlfilters          Other criteria to filter answers separated by a comma. Syntax example "(t.ref:like:'SO-%') and (t.date_creation:<:'20160101')"
	 * @param string		   $properties			Restrict the data returned to these properties. Ignored if empty. Comma separated list of properties names
	 * @return  array                               Array of order objects
	 *
	 * @throws RestException 403 Not allowed
	 * @throws RestException 503 System error
	 *
	 * @url	GET /emailusermsgs/
	 */
	public function indexEmailUserMsg($sortfield = "t.rowid", $sortorder = 'ASC', $limit = 100, $page = 0, $sqlfilters = '', $properties = '')
	{
		$obj_ret = array();
		$tmpobject = new EmailUserMsg($this->db);

		if (!DolibarrApiAccess::$user->hasRight('crmclientconnector', 'emailusermsg', 'read')) {
			throw new RestException(403);
		}

		$socid = DolibarrApiAccess::$user->socid ? DolibarrApiAccess::$user->socid : 0;

		$restrictonsocid = 0; // Set to 1 if there is a field socid in table of object

		// If the internal user must only see his customers, force searching by him
		$search_sale = 0;
		if ($restrictonsocid && !DolibarrApiAccess::$user->hasRight('societe', 'client', 'voir') && !$socid) {
			$search_sale = DolibarrApiAccess::$user->id;
		}
		if (!isModEnabled('societe')) {
			$search_sale = 0; // If module thirdparty not enabled, sale representative is something that does not exists
		}

		$sql = "SELECT t.rowid";
		$sql .= " FROM ".MAIN_DB_PREFIX.$tmpobject->table_element." AS t";
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX.$tmpobject->table_element."_extrafields AS ef ON (ef.fk_object = t.rowid)"; // Modification VMR Global Solutions to include extrafields as search parameters in the API GET call, so we will be able to filter on extrafields
		$sql .= " WHERE 1 = 1";
		if ($tmpobject->ismultientitymanaged) {
			$sql .= ' AND t.entity IN ('.getEntity($tmpobject->element).')';
		}
		if ($restrictonsocid && $socid) {
			$sql .= " AND t.fk_soc = ".((int) $socid);
		}
		// Search on sale representative
		if ($search_sale && $search_sale != '-1') {
			if ($search_sale == -2) {
				$sql .= " AND NOT EXISTS (SELECT sc.fk_soc FROM ".MAIN_DB_PREFIX."societe_commerciaux as sc WHERE sc.fk_soc = t.fk_soc)";
			} elseif ($search_sale > 0) {
				$sql .= " AND EXISTS (SELECT sc.fk_soc FROM ".MAIN_DB_PREFIX."societe_commerciaux as sc WHERE sc.fk_soc = t.fk_soc AND sc.fk_user = ".((int) $search_sale).")";
			}
		}
		if ($sqlfilters) {
			$errormessage = '';
			$sql .= forgeSQLFromUniversalSearchCriteria($sqlfilters, $errormessage);
			if ($errormessage) {
				throw new RestException(400, 'Error when validating parameter sqlfilters -> '.$errormessage);
			}
		}

		$sql .= $this->db->order($sortfield, $sortorder);
		if ($limit) {
			if ($page < 0) {
				$page = 0;
			}
			$offset = $limit * $page;

			$sql .= $this->db->plimit($limit + 1, $offset);
		}

		$result = $this->db->query($sql);
		$i = 0;
		if ($result) {
			$num = $this->db->num_rows($result);
			while ($i < $num) {
				$obj = $this->db->fetch_object($result);
				$tmp_object = new EmailUserMsg($this->db);
				if ($tmp_object->fetch($obj->rowid)) {

					$tmp_object->fetchHelpingApiRestData();

					// Backup special property
					$backupProperty = new stdClass();
					$backupProperty->user_full_name = $tmp_object->user_full_name;
					$backupProperty->user_img = $tmp_object->user_img;
					$backupProperty->user_mail_hash = $tmp_object->user_mail_hash;

					$outPutObj = $this->_filterObjectProperties($this->_cleanObjectDatas($tmp_object), $properties);

					// restore special property
					$tmp_object->user_full_name = $backupProperty->user_full_name;
					$tmp_object->user_img = $backupProperty->user_img;
					$tmp_object->user_mail_hash = $backupProperty->user_mail_hash;


					$obj_ret[] = $outPutObj;
				}
				$i++;
			}
		} else {
			throw new RestException(503, 'Error when retrieving emailusermsg list: '.$this->db->lasterror());
		}

		return $obj_ret;
	}

	/**
	 * Create emailusermsg object
	 *
	 * @param array $request_data   Request datas
	 * @return int  				ID of emailusermsg
	 *
	 * @throws RestException 403 Not allowed
	 * @throws RestException 500 System error
	 *
	 * @url	POST emailusermsgs/
	 */
	public function postEmailUserMsg($request_data = null)
	{
		if (!DolibarrApiAccess::$user->hasRight('crmclientconnector', 'emailusermsg', 'write')) {
			throw new RestException(403);
		}



		// use for quick add
		$emailAccountId = 0;
		if(isset($request_data['emailAccount'])) {
			$emailAccountStatic = new EmailAccount($this->db);

			$obj = $this->db->getRow('SELECT rowid as id FROM '.$this->db->prefix().$emailAccountStatic->table_element.' WHERE email_account = \''.$this->db->escape($request_data['emailAccount']).'\' ');
			if(!$obj){
				throw new RestException(404, 'EmailAccount not found');
			}

			$emailAccountId = $obj->id;
		}

		if(isset($request_data['emailMsgId'])) {
			if(empty($emailAccountId)) {
				throw new RestException(404, 'EmailAccount not found');
			}

			$emailLink = new EmailLink($this->db);
			$obj = $this->db->getRow('SELECT rowid as id
											FROM '.$this->db->prefix().$emailLink->table_element.'
											WHERE fk_email_account = \''.(int)$emailAccountId.'\'
											AND email_msgid = \''.$this->db->escape($request_data['emailMsgId']).'\' '
			);

			if(!$obj){
				$emailLink->fk_email_account = $emailAccountId;
				$emailLink->email_msgid = $request_data['emailMsgId'];
				$emailLinkId = $emailLink->create(DolibarrApiAccess::$user);
				if($emailLinkId<=0){
					throw new RestException(500, "Error creating EmailLink", array_merge(array($emailLink->error), $emailLink->errors));
				}

				$request_data['fk_email_link'] = $emailLinkId;
			}else{
				$request_data['fk_email_link'] = $obj->id;
			}
		}


		// Check mandatory fields
		$result = $this->_validateEmailUserMsg($request_data);
		if(!$result){
			throw new RestException(500, 'Data send invalid');
		}

		foreach ($request_data as $field => $value) {
			if ($field === 'caller') {
				// Add a mention of caller so on trigger called after action, we can filter to avoid a loop if we try to sync back again with the caller
				$this->emailusermsg->context['caller'] = sanitizeVal($request_data['caller'], 'aZ09');
				continue;
			}

			if ($field == 'array_options' && is_array($value)) {
				foreach ($value as $index => $val) {
					$this->emailusermsg->array_options[$index] = $this->_checkValForAPI('extrafields', $val, $this->emailusermsg);
				}
				continue;
			}

			$this->emailusermsg->$field = $this->_checkValForAPI($field, $value, $this->emailusermsg);
		}

		// Clean data
		// $this->emailusermsg->abc = sanitizeVal($this->emailusermsg->abc, 'alphanohtml');

		if ($this->emailusermsg->create(DolibarrApiAccess::$user)<0) {
			throw new RestException(500, "Error creating EmailUserMsg", array_merge(array($this->emailusermsg->error), $this->emailusermsg->errors));
		}
		return $this->emailusermsg->id;
	}



	/**
	 * Update emailusermsg
	 *
	 * @param 	int   		$id             Id of emailusermsg to update
	 * @param 	array 		$request_data   Datas
	 * @return 	Object						Object after update
	 *
	 * @throws RestException 403 Not allowed
	 * @throws RestException 404 Not found
	 * @throws RestException 500 System error
	 *
	 * @url	PUT emailusermsgs/{id}
	 */
	public function putEmailUserMsg($id, $request_data = null)
	{
		if (!DolibarrApiAccess::$user->hasRight('crmclientconnector', 'emailusermsg', 'write')) {
			throw new RestException(403);
		}
		if (!DolibarrApi::_checkAccessToResource('emailusermsg', $id, 'crmclientconnector_emailusermsg')) {
			throw new RestException(403, 'Access to instance id='.$this->emailusermsg->id.' of object not allowed for login '.DolibarrApiAccess::$user->login);
		}

		$result = $this->emailusermsg->fetch($id);
		if (!$result) {
			throw new RestException(404, 'EmailUserMsg not found');
		}

		foreach ($request_data as $field => $value) {
			if ($field == 'id') {
				continue;
			}
			if ($field === 'caller') {
				// Add a mention of caller so on trigger called after action, we can filter to avoid a loop if we try to sync back again with the caller
				$this->emailusermsg->context['caller'] = sanitizeVal($request_data['caller'], 'aZ09');
				continue;
			}

			if ($field == 'array_options' && is_array($value)) {
				foreach ($value as $index => $val) {
					$this->emailusermsg->array_options[$index] = $this->_checkValForAPI('extrafields', $val, $this->emailusermsg);
				}
				continue;
			}

			if ($field == 'array_options' && is_array($value)) {
				foreach ($value as $index => $val) {
					$this->emailusermsg->array_options[$index] = $this->_checkValForAPI($field, $val, $this->emailusermsg);
				}
				continue;
			}

			$this->emailusermsg->$field = $this->_checkValForAPI($field, $value, $this->emailusermsg);
		}

		// Clean data
		// $this->emailusermsg->abc = sanitizeVal($this->emailusermsg->abc, 'alphanohtml');

		if ($this->emailusermsg->update(DolibarrApiAccess::$user, false) > 0) {
			return $this->getEmailUserMsg($id);
		} else {
			throw new RestException(500, $this->emailusermsg->error);
		}
	}

	/**
	 * Delete emailusermsg
	 *
	 * @param   int     $id   EmailUserMsg ID
	 * @return  array
	 *
	 * @throws RestException 403 Not allowed
	 * @throws RestException 404 Not found
	 * @throws RestException 409 Nothing to do
	 * @throws RestException 500 System error
	 *
	 * @url	DELETE emailusermsgs/{id}
	 */
	public function deleteEmailUserMsg($id)
	{
		if (!DolibarrApiAccess::$user->hasRight('crmclientconnector', 'emailusermsg', 'delete')) {
			throw new RestException(403);
		}
		if (!DolibarrApi::_checkAccessToResource('emailusermsg', $id, 'crmclientconnector_emailusermsg')) {
			throw new RestException(403, 'Access to instance id='.$this->emailusermsg->id.' of object not allowed for login '.DolibarrApiAccess::$user->login);
		}

		$result = $this->emailusermsg->fetch($id);
		if (!$result) {
			throw new RestException(404, 'EmailUserMsg not found');
		}

		if ($this->emailusermsg->delete(DolibarrApiAccess::$user) == 0) {
			throw new RestException(409, 'Error when deleting EmailUserMsg : '.$this->emailusermsg->error);
		} elseif ($this->emailusermsg->delete(DolibarrApiAccess::$user) < 0) {
			throw new RestException(500, 'Error when deleting EmailUserMsg : '.$this->emailusermsg->error);
		}

		return array(
			'success' => array(
				'code' => 200,
				'message' => 'EmailUserMsg deleted'
			)
		);
	}

	/**
	 * Get properties of a emaillink object
	 *
	 * Return an array with emaillink information
	 *
	 * @param	string		$accountEmail imap account mail
	 * @param	string		$msgId imap mail ID
	 * @return  Object					Object with cleaned properties
	 *
	 * @url	GET emaillinks/quicksearch
	 *
	 * @throws RestException 403 Not allowed
	 * @throws RestException 404 Not found
	 */
	public function getEmailLinkFromAccountAndMsId($accountEmail, $msgId)
	{
		if (!DolibarrApiAccess::$user->hasRight('crmclientconnector', 'emaillink', 'read')) {
			throw new RestException(403);
		}

		if (!$this->_fetchImailLinkByMsgId($accountEmail, $msgId)){
			throw new RestException(404, 'EmailLink not found');
		}

		return $this->_cleanObjectDatas($this->emaillink);
	}

	/**
	 * @param $accountEmail
	 * @param $msgId
	 *
	 * @return bool
	 */
	private function _fetchImailLinkByMsgId($accountEmail, $msgId){

		$this->emaillink = new EmailLink($this->db);

		$sql = /** @lang MySQL */
			'SELECT emailLink.rowid id '
			.' FROM '.$this->db->prefix().$this->emaillink->table_element.' emailLink '
			.' JOIN '.$this->db->prefix().$this->emailaccount->table_element.' emailAccount ON (emailLink.fk_email_account = emailAccount.rowid ) '
			.' WHERE 	emailLink.email_msgid = \''.$this->db->escape($msgId).'\' '
			.' 		AND emailAccount.email_account = \''.$this->db->escape($accountEmail).'\' ';


		$obj = $this->db->getRow($sql);
		if (!$obj) {
			return false;
		}

		$result = $this->emaillink->fetch($obj->id);
		if ($result <= 0) {
			return false;
		}

		return true;
	}

	/**
	 * Validate fields before create or update object
	 *
	 * @param	array		$data   Array of data to validate
	 * @return	array
	 *
	 * @throws	RestException
	 */
	private function _validateEmailUserMsg($data)
	{
		$emailusermsg = array();
		foreach ($this->emailusermsg->fields as $field => $propfield) {
			if (in_array($field, array('rowid', 'entity', 'date_creation', 'tms', 'fk_user_creat')) || $propfield['notnull'] != 1) {
				continue; // Not a mandatory field
			}
			if (!isset($data[$field])) {
				throw new RestException(400, "$field field missing");
			}
			$emailusermsg[$field] = $data[$field];
		}
		return $emailusermsg;
	}

	/* END MODULEBUILDER API EMAILUSERMSG */


	/* BEGIN MODULEBUILDER API MYOBJECT */
	/* END MODULEBUILDER API MYOBJECT */



	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
	/**
	 * Clean sensible object datas
	 *
	 * @param   Object  $object     Object to clean
	 * @return  Object              Object with cleaned properties
	 */
	protected function _cleanObjectDatas($object)
	{
		// phpcs:enable
		$object = parent::_cleanObjectDatas($object);

		unset($object->rowid);
		unset($object->canvas);

		// If object has lines, remove $db property
		if (isset($object->lines) && is_array($object->lines) && count($object->lines) > 0) {
			$nboflines = count($object->lines);
			for ($i = 0; $i < $nboflines; $i++) {
				$this->_cleanObjectDatas($object->lines[$i]);

				unset($object->lines[$i]->lines);
				unset($object->lines[$i]->note);
			}
		}

		return $object;
	}
}
