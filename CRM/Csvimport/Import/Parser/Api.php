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
  function import(array $values): void {
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

        if (count($uniqueField) == $fieldCount) {
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
  public function setEntity($entity) {
    $this->_entity = $entity;
  }

  /**
   * Set reference fields; array of ReferenceField objects
   *
   * @param array $value
   */
  public function setRefFields($value): void {
    $this->_refFields = $value;
  }

  private static $entityFieldsMeta = [];

  private static $entityFieldOptionsMeta = [];

  /**
   * Returns fields metadata ('getfields') of given entity
   *
   * @param $entity
   *
   * @return mixed
   */
  private static function getFieldsMeta($entity) {
    if (!isset(self::$entityFieldsMeta[$entity])) {
      try {
        self::$entityFieldsMeta[$entity] = [];
        self::$entityFieldsMeta[$entity] = civicrm_api3($entity, 'getfields', [
          'api_action' => "getfields",
        ])['values'];
      }
      catch (CiviCRM_API3_Exception $e) {
        // nothing
      }
    }
    return self::$entityFieldsMeta[$entity];
  }

  /**
   * Returns 'getoptions' values for given entity and field
   *
   * @param $entity
   * @param $field
   *
   * @return mixed
   */
  private static function getFieldOptionsMeta($entity, $field) {
    if (!isset(self::$entityFieldOptionsMeta[$entity][$field])) {
      try {
        self::$entityFieldOptionsMeta[$entity][$field] = [];
        self::$entityFieldOptionsMeta[$entity][$field] = civicrm_api3($entity, 'getoptions', [
          'field' => $field,
          'context' => "match",
        ])['values'];
      }
      catch (CiviCRM_API3_Exception $e) {
        // nothing
      }
    }
    return self::$entityFieldOptionsMeta[$entity][$field];
  }

  /**
   * Validates field-value pairs before importing
   *
   * @param $entity
   * @param $params
   * @param bool $ignoreCase
   *
   * @return array
   */
  private static function validateFields($entity, $params, $ignoreCase = FALSE) {
    $fieldsMeta = self::getFieldsMeta($entity);

    $opFields = [];
    foreach ($fieldsMeta as $fieldName => $value) {
      // only try to validate option fields and yes/no fields
      if ($value['type'] == CRM_Utils_Type::T_BOOLEAN || isset($value['pseudoconstant'])) {
        $opFields[] = $fieldName;
      }
    }

    $valInfo = [];
    foreach ($params as $fieldName => $value) {
      if (in_array($fieldName, $opFields)) {
        $valInfo[$fieldName] = self::validateField($entity, $fieldName, $value, $ignoreCase);
      }
    }

    return $valInfo;
  }

  /**
   * Validates given option/value field against allowed values
   * Also handles multi valued fields separated by '|'
   *
   * @param $entity
   * @param $field
   * @param $value
   * @param bool $ignoreCase
   *
   * @return array
   */
  private static function validateField($entity, $field, $value, $ignoreCase = FALSE) {
    $options = self::getFieldOptionsMeta($entity, $field);

    $optionKeys = array_keys($options);
    if ($ignoreCase) {
      $optionKeys = array_map('strtolower', $optionKeys);
      $value = strtolower($value);
    }
    $value = explode('|', $value);
    $value = array_values(array_filter($value)); // filter empty values
    $valueUpdated = FALSE;
    $isValid = TRUE;

    foreach ($value as $k => $mval) {
      if (!empty($mval) && !in_array($mval, $optionKeys)) {
        $isValid = FALSE;
        // check 'label' if 'name' not found
        foreach ($options as $name => $label) {
          if ($mval == $label || ($ignoreCase && strcasecmp($mval, $label) == 0)) {
            $value[$k] = $name;
            $valueUpdated = TRUE;
            $isValid = TRUE;
          }
        }
        if (!$isValid) {
          return ['error' => ts('Invalid value for field') . ' (' . $field . ') => ' . $mval];
        }
      }
    }

    if (count($value) == 1) {
      if (!$valueUpdated) {
        return ['error' => 0];
      }
      $value = array_pop($value);
    }

    return ['error' => 0, 'valueUpdated' => ['field' => $field, 'value' => $value]];
  }

  /**
   * Set if entities can be updated using unique fields
   *
   * @param $size
   */
  public function setAllowEntityUpdate($update) {
    $this->_allowEntityUpdate = $update;
  }

  /**
   * Set if letter-case needs to be ignored for field option values
   *
   * @param $size
   */
  function setIgnoreCase($ignoreCase) {
    $this->_ignoreCase = $ignoreCase;
  }

  /**
   * the initializer code, called before the processing
   *
   * @return void
   * @access public
   */
  function init() {
    $this->setEntity($this->getSubmittedValue('entity'));
    $this->setFieldMetadata();
  }

}
