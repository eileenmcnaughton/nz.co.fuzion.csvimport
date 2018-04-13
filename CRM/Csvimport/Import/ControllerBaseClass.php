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
   */
  function findAllReferenceFields($entity) {
    $referenceFields = array();
    $allEntities = civicrm_api3('Entity', 'get', array(
      'sequential' => 1,
    ))['values'];
    if(!in_array($entity, $allEntities)) return;

    // Get all fields for this entity type
    $entityFields = civicrm_api3($entity, 'getfields', array(
      'api_action' => "",
    ));
    if($entityFields['count'] > 0) {
      foreach ($entityFields['values'] as $k => $val) {
        //todo: Improve logic to find reference fields
        if(isset($val['FKApiName'])) {
          // this is a reference field
          $referenceFields[$k]['label'] = $val['title'];
          $referenceFields[$k]['name'] = $val['name'];
          $referenceFields[$k]['entity'] = $val['FKApiName'];
        }
      }
    }

    // Get all custom fields for this entity of type, contactReference
    $customFields = civicrm_api3('CustomField', 'get', array(
      'sequential' => 1,
      'custom_group_id.extends' => $entity,
      'data_type' => "ContactReference",
    ));
    if($customFields['count'] > 0) {
      $referenceFields['custom_fields'] = $customFields['values'];
    }

    return $referenceFields;
  }

  /**
   * Finds all unique fields for a given entity
   */
  function findAllUniqueFields($entity) {
    $uniqueFields = array();
    $entityFields = civicrm_api3($entity, 'getfields', array(
      'api_action' => "",
    ));
    if($entityFields['count'] > 0) {
      $_entityTable = reset($entityFields['values'])['table_name'];
      $sql = 'SHOW INDEX FROM '.$_entityTable.' WHERE Non_unique = 0';
      $uFields = CRM_Core_DAO::executeQuery($sql)->fetchAll();
      foreach($uFields as $field) {
        $uniqueFields[$field['Column_name']] = $entityFields['values'][$field['Column_name']]['title'];
      }
    }
    return $uniqueFields;
  }
}

