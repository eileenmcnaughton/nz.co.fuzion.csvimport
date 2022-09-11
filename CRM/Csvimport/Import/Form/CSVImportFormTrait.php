<?php

trait CRM_Csvimport_Import_Form_CSVImportFormTrait {

  /**
   * Get the fields that can be submitted in the Import form flow.
   *
   * These could be on any form in the flow & are accessed the same way from
   * all forms.
   *
   * @return string[]
   */
  protected function getSubmittableFields(): array {
    $importerFields = [
      'entity' => 'DataSource',
      'noteEntity' => 'DataSource',
      'ignoreCase' => 'DataSource',
      'allowEntityUpdate' => 'DataSource',
    ];
    return array_merge(parent::getSubmittableFields(), $importerFields);
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
   *
   * @return array
   */
  protected function getReferenceFields(): array {
    $refFields = [];
    foreach ($this->getUniqueFields() as $entityName => $entity) {
      foreach ($entity as $refKey => $entityRefFields) {
        foreach ($entityRefFields as $indexCols) {
          // skip if field name is 'id' as it would be available by default
          if (count($indexCols) == 1 && $indexCols[0] === 'id') {
            continue;
          }

          if (count($indexCols) == 1) {
            $k = $indexCols[0];
            if (isset($this->_mapperFields[$refKey])) {
              $label = $this->_mapperFields[$refKey];
              $this->_mapperFields[$refKey . '#' . $k] = $label . ' (' . ts('Match using') . ' ' . $k . ')';
            }
            else {
              $this->_mapperFields[$refKey . '#' . $k] = $refKey . ' (' . ts('Match using') . ' ' . $k . ')';
            }
            $refFields[$refKey . '#' . $k] = new CRM_Csvimport_Import_ReferenceField($refKey, $this->_mapperFields[$refKey . '#' . $k], $entityName, $k);
          }
          else {
            if (count($indexCols) > 1) {
              // handle combination indexes
              if ($this->_mapperFields[$refKey]) {
                $label = $this->_mapperFields[$refKey];
              }
              else {
                $label = $refKey;
              }
              $indexKey = '';
              foreach ($indexCols as $col) {
                $indexKey .= '#' . $col;
              }
              foreach ($indexCols as $key => $col) {
                $this->_mapperFields[$refKey . '#' . $col] = $label . ' - ' . $col . ' (' . ts('Match using a combination of') . str_replace('#', ' ', $indexKey) . ')';
                $refFields[$refKey . '#' . $col] = new CRM_Csvimport_Import_ReferenceField($refKey, $this->_mapperFields[$refKey . '#' . $col], $entityName, array_values($indexCols) + ['active' => $col]);
              }
            }
          }
        }
      }
    }
    return $refFields;
  }

  /**
   * @param array $params
   *
   * @return array
   */
  protected function getUniqueFields(): array {
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
        try {
          $uf = civicrm_api3($rfield['entity'], 'getunique', [])['values'];
        }
        catch (CiviCRM_API3_Exception $e) {
          if ($e->getErrorCode() === 'not-found') {
            // fallback method for versions < 5.2
            $uf = $this->findAllUniqueFields($rfield['entity']);
          }
        }
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
   * @return \CRM_Csvimport_Import_Parser_Api
   */
  protected function getParser(): CRM_Csvimport_Import_Parser_Api {
    if (!$this->parser) {
      $this->parser = new CRM_Csvimport_Import_Parser_Api();
      $this->parser->setUserJobID($this->getUserJobID());
      $this->parser->setEntity($this->getSubmittedValue('entity'));
      $this->parser->setRefFields($this->getReferenceFields());
      $this->parser->setAllowEntityUpdate((bool) $this->getSubmittedValue('allowEntityUpdate'));
      $this->parser->setIgnoreCase((bool) $this->getSubmittedValue('ignoreCase'));
      $this->parser->init();
    }
    return $this->parser;
  }


}
