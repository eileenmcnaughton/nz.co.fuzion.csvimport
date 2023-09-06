<?php

class CRM_Csvimport_Import_Parser_Api extends CRM_Import_Parser {

  protected $_entity = '';

  protected $requiredFields = [];

  /**
   * Params for the current entity being prepared for the api
   *
   * @var array
   */
  protected $_params = [];

  protected $_allowEntityUpdate = FALSE;

  protected $_ignoreCase = FALSE;

  /**
   * Get user job information.
   *
   * @return \string[][]
   */
  public static function getUserJobInfo(): array {
    return [[
      'name' => 'csv_api_importer',
      'id' => 'csv_api_importer',
      'title' => 'Api Import',
    ]];
  }

  /**
   * @throws \CiviCRM_API3_Exception
   */
  public function setFieldMetadata(): void {
    $this->importableFieldsMetadata = array_merge(
      ['' => ['title' => ts('- do not import -')]],
      civicrm_api3($this->_entity, 'getfields', ['action' => 'create'])['values']
    );
    foreach ($this->importableFieldsMetadata as $field => $values) {
      if (empty($values['entity'])) {
        $this->importableFieldsMetadata[$field]['entity'] = $this->_entity;
      }
      if (empty($values['title']) && !empty($values['label'])) {
        $this->importableFieldsMetadata[$field]['title'] = $values['label'];
      }
      if (!empty($values['custom_group_id'])) {
        $this->importableFieldsMetadata[$field]['title'] = $values["groupTitle"] . ': ' . $values["title"];
      }
    }
    foreach ($this->getReferenceFields() ?? [] as $field => $values) {
      $fieldName = $values['referenced_field'];
      if (isset($this->importableFieldsMetadata[$values['referenced_field']])) {
        $this->importableFieldsMetadata[$field] = array_merge($this->importableFieldsMetadata[$fieldName], $values);
      }
    }
  }

  /**
   *
   * @return array
   */
  public function getUniqueFields(): array {
    $params = [];
    if ($noteEntity = $this->getSubmittedValue('noteEntity')) {
      $params[$this->getSubmittedValue('entity')] = $noteEntity;
    }
    $refFields = $this->findAllReferenceFields($this->getSubmittedValue('entity'), $params);

    // get all unique fields for above entities
    $uniqueFields = [];
    foreach ($refFields as $k => $rfield) {
      // handle reference fields in custom fields (only contacts for now)
      if ($k === 'custom_fields') {
        foreach ($rfield as $each) {
          if ($each['data_type'] === 'ContactReference') {
            $uniqueFields['Contact'][$each['name']] = civicrm_api3('Contact', 'getunique', [])['values'];
          }
        }
      }
      else {
        $uf = civicrm_api3($rfield['entity'], 'getunique', [])['values'];

        $uniqueFields[$rfield['entity']][$rfield['name']] = $uf;
        $extraFields = $this->getSpecialCaseFields($rfield['entity']);
        if ($extraFields) {
          foreach ($extraFields as $k => $extraField) {
            if (is_array($extraField)) {
              foreach ($extraField as $each) {
                $uniqueFields[$rfield['entity']][$k][] = [$each];
              }
            }
            else {
              $uniqueFields[$rfield['entity']][$k][] = [$extraField];
            }
          }
        }
      }
    }
    return $uniqueFields;
  }

  /**
   *
   * @return array
   */
  protected function getReferenceFields(): array {
    $refFields = [];
    foreach ($this->getUniqueFields() as $entityName => $entity) {
      foreach ($entity as $referenceField => $entityRefFields) {
        try {
          $entityFieldMetadata = civicrm_api4($entityName, 'getfields', [], 'name');

          foreach ($entityRefFields as $fieldsInUniqueIndex) {
            // skip if field name is 'id' as it would be available by default
            if ($fieldsInUniqueIndex === ['id']) {
              continue;
            }

            if (count($fieldsInUniqueIndex) === 1) {
              $indexFieldName = $fieldsInUniqueIndex[0];
              
              // skip special case fields (such as the address.external_identifier)
              if (empty($entityFieldMetadata[$indexFieldName])) {
                continue;
              }
              
              $indexFieldMetadata = array_merge(
                $entityFieldMetadata[$indexFieldName], [
                  'referenced_field' => $referenceField,
                  'entity_name' => $entityName,
                  'entity_field_name' => $indexFieldName,
                  'name' => $referenceField . '#' . $indexFieldName,
                ]
              );
              $indexFieldMetadata['html']['label'] = $indexFieldMetadata['title'] . ' (' . ts('Match using') . ' ' . $indexFieldName . ')';
              $refFields[$referenceField . '#' . $indexFieldName] = $indexFieldMetadata;
            }
            else {
              if (count($fieldsInUniqueIndex) > 1) {
                // handle combination indexes
                if ($refFields[$referenceField]) {
                  $label = $refFields[$referenceField];
                }
                else {
                  $label = $referenceField;
                }
                $indexKey = '';
                foreach ($fieldsInUniqueIndex as $col) {
                  $indexKey .= '#' . $col;
                }
                foreach ($fieldsInUniqueIndex as $col) {
                  $refFields[$referenceField . '#' . $col] = [
                    'title' => $label,
                    'name' => $indexFieldName,
                    'html' => ['label' => $label . ' - ' . $col . ' (' . ts('Match using a combination of') . str_replace('#', ' ', $indexKey) . ')'],
                    'extends' => $referenceField,
                    'entity_name' => $entityName,
                    'entity_field_name' => array_values($fieldsInUniqueIndex) + ['active' => $col],
                  ];
                }
              }
            }
          }
        }
        catch (Exception $e) {
        // Fix this - we get an exception if campaign is not enabled.... should be filtered
        // in getUniqueFields if not available.
        }
      }
    }
    return $refFields;
  }

  /**
   * Returns all unique fields of given entity
   * (this is added to core as an api 'getuique' but not available in a stable release)
   *
   * @param string $entity
   *
   * @return array
   */
  protected  function findAllUniqueFields(string $entity): array {
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

  /**
   * Finds all reference fields for a given entity
   *
   * @param string $entity
   * @param array $params
   *
   * @return array
   */
  protected function findAllReferenceFields(string $entity, array $params) {
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
        if (isset($val['FKApiName']) && $entity !== 'Note') {
          // this is a reference field
          $referenceFields[$k]['label'] = $val['title'];
          $referenceFields[$k]['name'] = $val['name'];
          $referenceFields[$k]['entity'] = $val['FKApiName'];
        }
        // spl handling for 'Note' as it can reference multiple entity types
        else {
          if ($entity === 'Note' && $val['name'] === 'entity_id' && isset($params[$entity])) {
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

  protected function getSpecialCaseFields($entity) {
    $specialCaseFields = [
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
    return $specialCaseFields[$entity] ?? NULL;
  }

  /**
   * @return array
   */
  protected function getRequiredFields(): array {
    if (!isset($this->requiredFields)) {
      $this->requiredFields = [];
      foreach ($this->importableFieldsMetadata as $field) {
        if (!empty($field['api.required'])) {
          $this->requiredFields[] = $field['name'];
        }
      }
    }
    return $this->requiredFields;
  }

  /**
   * handle the values in import mode
   *
   * @param array $values the array of values belonging to this line
   *
   * @access public
   */
  public function import(array $values): void {
    $rowNumber = (int) ($values[array_key_last($values)]);
    $entity = $this->getSubmittedValue('entity');
    try {
      $params = $this->getMappedRow($values);
      foreach ($params as $key => $value) {
        $fieldMetadata = $this->getFieldMetadata($key);
        if ($fieldMetadata['referenced_field'] ?? NULL) {
          $refEntity = $fieldMetadata['entity_name'];
          // special case: handle 'Master Address Belongs To' field using contact external_id
          if ($refEntity === 'Address' && isset($value[$key]['external_identifier'])) {
            $refEntity = 'Contact';
          }
          try {
            $params['contact_id'] = (int) civicrm_api3($refEntity, 'getsingle', [$fieldMetadata['entity_field_name'] => $value])['id'];
          }
          catch (CiviCRM_API3_Exception $e) {
            throw new CRM_Core_Exception('Failed to find referenced entity');
          }
        }
      }
      if ($this->getSubmittedValue('allowEntityUpdate')) {
        $uniqueFields = $this->findAllUniqueFields($this->getSubmittedValue('entity'));
        foreach ($uniqueFields as $uniqueField) {
          $fieldCount = 0;
          $tmp = [];

          foreach ($uniqueField as $name) {
            if (isset($params[$name])) {
              $fieldCount++;
              $tmp[$name] = $params[$name];
            }
          }
        }

        if (count($uniqueField) === $fieldCount) {
          $tmp['sequential'] = 1;
          $tmp['return'] = ['id'];
          $existingEntity = civicrm_api3($this->getSubmittedValue('entity'), 'get', $tmp);

          if (isset($existingEntity['values'][0]['id'])) {
            $params['id'] = $existingEntity['values'][0]['id'];
          }
        }
      }
      $params['skipRecentView'] = TRUE;
      $params['check_permissions'] = TRUE;
      $result = civicrm_api3($entity, 'create', $params);
    }
    catch (Exception $e) {
      $this->setImportStatus($rowNumber, 'ERROR', $e->getMessage());
      return;
    }
    $this->setImportStatus($rowNumber, 'IMPORTED', '', $result['id']);
  }

  /**
   * Set import entity
   *
   * @param string $entity
   */
  public function setEntity(string $entity): void {
    $this->_entity = $entity;
  }

  /**
   * Set if entities can be updated using unique fields
   *
   * @param bool $update
   */
  public function setAllowEntityUpdate(bool $update): void {
    $this->_allowEntityUpdate = $update;
  }

  /**
   * Set if letter-case needs to be ignored for field option values
   *
   * @param bool $ignoreCase
   */
  public function setIgnoreCase(bool $ignoreCase): void {
    $this->_ignoreCase = $ignoreCase;
  }

  /**
   * the initializer code, called before the processing
   *
   * @throws \CiviCRM_API3_Exception
   */
  public function init(): void {
    $this->setEntity($this->getSubmittedValue('entity'));
    $this->setFieldMetadata();
  }

}
