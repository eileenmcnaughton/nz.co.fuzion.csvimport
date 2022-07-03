<?php

class CRM_Csvimport_Import_Controller extends CRM_Core_Controller {

  public static $specialCaseFields = [
    'MembershipType' => [
      'membership_type_id' => 'name',
    ],
    'Address' => [
      'master_id' => [
        'contact_id',
        'external_identifier', // special case; handled in import task
      ],
    ],
    'County' => [
      'county_id' => 'name',
    ],
    'StateProvince' => [
      'state_province_id' => 'name',
    ],
  ];

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
    $this->addActions($config->uploadDir, ['uploadFile']);
  }

  /**
   * Finds all reference fields for a given entity
   *
   * @param $entity
   * @param $params
   *
   * @return array
   */
  function findAllReferenceFields($entity, $params = NULL) {
    $referenceFields = [];
    $allEntities = civicrm_api3('Entity', 'get', [
      'sequential' => 1,
    ])['values'];
    if (!in_array($entity, $allEntities)) {
      return $referenceFields;
    }

    // Get all fields for this entity type
    $entityFields = civicrm_api3($entity, 'getfields', [
      'api_action' => "",
    ]);
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
        else {
          if ($entity == 'Note' && $val['name'] == 'entity_id' && isset($params[$entity])) {
            // this is a reference field
            $referenceFields[$k]['label'] = $val['title'];
            $referenceFields[$k]['name'] = $val['name'];
            $referenceFields[$k]['entity'] = $params[$entity];
          }
        }
      }
    }

    // Get all custom fields for this entity of type, contactReference
    $customFields = civicrm_api3('CustomField', 'get', [
      'sequential' => 1,
      'custom_group_id.extends' => $entity,
      'data_type' => "ContactReference",
    ]);
    if ($customFields['count'] > 0) {
      $referenceFields['custom_fields'] = $customFields['values'];
    }

    return $referenceFields;
  }

  function getSpecialCaseFields($entity) {
    if (isset(self::$specialCaseFields[$entity])) {
      return self::$specialCaseFields[$entity];
    }
    return NULL;
  }

  /**
   * Returns all unique fields of given entity
   * (this is added to core as an api 'getuique' but not available in a stable release)
   *
   * @param $entity
   *
   * @return array
   */
  public static function findAllUniqueFields($entity) {
    $uniqueFields = [];

    $dao = _civicrm_api3_get_DAO($entity);
    $uFields = $dao::indices();

    foreach ($uFields as $fieldKey => $field) {
      if (!isset($field['unique']) || !$field['unique']) {
        continue;
      }
      $uniqueFields[$fieldKey] = $field['field'];
    }

    return $uniqueFields;
  }
}
