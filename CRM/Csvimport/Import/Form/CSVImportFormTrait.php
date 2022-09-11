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

}
