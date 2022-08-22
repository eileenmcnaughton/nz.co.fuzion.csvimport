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

  protected $_refFields = [];

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
      if (empty($values['title']) && !empty($values['label'])) {
        $this->importableFieldsMetadata[$field]['title'] = $values['label'];
      }
      if (!empty($values['custom_group_id'])) {
        $this->importableFieldsMetadata[$field]['title'] = $values["groupTitle"] . ': ' . $values["title"];
      }
    }
    foreach ($this->_refFields ?? [] as $field => $values) {
      if (isset($this->importableFieldsMetadata[$values->id])) {
        $this->importableFieldsMetadata[$field] = $this->importableFieldsMetadata[$values->id];
        $this->importableFieldsMetadata[$values->id]['_refField'] = $values->entity_field_name;
      }
    }
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
      $params['skipRecentView'] = TRUE;
      $params['check_permissions'] = TRUE;
      foreach ($params as $key => $param) {
        if (strpos($key, 'api.') === 0 && strpos($key, '.get') === (strlen($key) - 4)) {
          $refEntity = substr($key, 4, -4);
          // special case: handle 'Master Address Belongs To' field using contact external_id
          if ($refEntity === 'Address' && isset($param[$key]['external_identifier'])) {
            $refEntity = 'Contact';
          }
          try {
            $data = civicrm_api3($refEntity, 'get', $param[$key]);
            $param[$key]['contact_id'] = $data['values'][0]['id'];
            unset($param[$key]['external_identifier']);
          }
          catch (CiviCRM_API3_Exception $e) {
            throw new CRM_Core_Exception('Error with referenced entity "get"! (' . $e->getMessage() . ')');
          }
        }
      }
      if ($this->getSubmittedValue('allowEntityUpdate')) {
        $uniqueFields = CRM_Csvimport_Import_Controller::findAllUniqueFields($this->getSubmittedValue('entity'));
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
   * Set reference fields; array of ReferenceField objects
   *
   * @param array $value
   */
  public function setRefFields(array $value): void {
    $this->_refFields = $value;
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
