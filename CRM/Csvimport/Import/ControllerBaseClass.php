<?php
/*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.4                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2013                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
*/

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2013
 * $Id$
 *
 */
// all the stuff in this folder is just a copy of the event version because the functionality that
// should be in the base class seems to be copied & pasted into the event class.

// so I have copied & pasted to this 'base' form & will put the non-generic parts in the
// non - b form ie. this is working as the base import class that doesn't seem to exist
class CRM_Csvimport_Import_ControllerBaseClass extends CRM_Core_Controller {

  public static $specialCaseFields = array(
    'MembershipType' => array(
      'membership_type_id' => 'name',
    ),
    'Address' => array(
      'master_id' => array(
        'contact_id',
        'external_identifier', // special case; handled in import task
      ),
    ),
    'County' => array(
      'county_id' => 'name',
    ),
    'StateProvince' => array(
      'state_province_id' => 'name',
    ),
  );

  /**
   * class constructor
   */
  function __construct($title = NULL, $action = CRM_Core_Action::NONE, $modal = TRUE) {
    parent::__construct($title, $modal);

    // lets get around the time limit issue if possible, CRM-2113
    if (!ini_get('safe_mode')) {
      set_time_limit(0);
    }

    $this->_stateMachine = new CRM_Import_StateMachine($this, $action);

    // create and instantiate the pages
    $this->addPages($this->_stateMachine, $action);

    // add all the actions
    $config = CRM_Core_Config::singleton();
    $this->addActions($config->uploadDir, array('uploadFile'));
  }

  /**
   * Finds all reference fields for a given entity
   *
   * @param $entity
   * @param $params
   * @return array
   */
  function findAllReferenceFields($entity, $params = NULL) {
    $referenceFields = array();
    $allEntities = civicrm_api3('Entity', 'get', array(
      'sequential' => 1,
    ))['values'];
    if (!in_array($entity, $allEntities)) {
      return $referenceFields;
    }

    // Get all fields for this entity type
    $entityFields = civicrm_api3($entity, 'getfields', array(
      'api_action' => "",
    ));
    if ($entityFields['count'] > 0) {
      foreach ($entityFields['values'] as $k => $val) {
        //todo: Improve logic to find reference fields
        if (isset($val['FKApiName']) && $entity != 'Note') {
          // this is a reference field
          $referenceFields[$k]['label'] = $val['title'];
          $referenceFields[$k]['name'] = $val['name'];
          $referenceFields[$k]['entity'] = $val['FKApiName'];
        }
        // spl handling for 'Note' as it can reference multiple entity types
        else if ($entity == 'Note' && $val['name'] == 'entity_id' && isset($params[$entity])) {
          // this is a reference field
          $referenceFields[$k]['label'] = $val['title'];
          $referenceFields[$k]['name'] = $val['name'];
          $referenceFields[$k]['entity'] = $params[$entity];
        }
      }
    }

    // Get all custom fields for this entity of type, contactReference
    $customFields = civicrm_api3('CustomField', 'get', array(
      'sequential' => 1,
      'custom_group_id.extends' => $entity,
      'data_type' => "ContactReference",
    ));
    if ($customFields['count'] > 0) {
      $referenceFields['custom_fields'] = $customFields['values'];
    }

    return $referenceFields;
  }

  function getSpecialCaseFields($entity) {
    if(isset(self::$specialCaseFields[$entity])) {
      return self::$specialCaseFields[$entity];
    }
    return null;
  }

  /**
   * Returns all unique fields of given entity
   * (this is added to core as an api 'getuique' but not available in a stable release)
   * @param $entity
   * @return array
   */
  public static function findAllUniqueFields($entity) {
    $uniqueFields = array();

    $dao = _civicrm_api3_get_DAO($entity);
    $uFields = $dao::indices();

    foreach($uFields as $fieldKey => $field) {
      if(!isset($field['unique']) || !$field['unique']) {
        continue;
      }
      $uniqueFields[$fieldKey] = $field['field'];
    }

    return $uniqueFields;
  }
}

