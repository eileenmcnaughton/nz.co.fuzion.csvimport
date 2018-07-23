<?php

class CRM_Csvimport_Task_Import {

  private static $entityFieldsMeta = array();
  private static $entityFieldOptionsMeta = array();

  /**
   * Returns fields metadata ('getfields') of given entity
   *
   * @param $entity
   * @return mixed
   */
  private static function getFieldsMeta($entity) {
    if(!isset(self::$entityFieldsMeta[$entity])) {
      try{
        self::$entityFieldsMeta[$entity] = array();
        self::$entityFieldsMeta[$entity] = civicrm_api3($entity, 'getfields', array(
          'api_action' => "getfields",
        ))['values'];
      } catch (CiviCRM_API3_Exception $e) {
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
   * @return mixed
   */
  private static function getFieldOptionsMeta($entity, $field) {
    if(!isset(self::$entityFieldOptionsMeta[$entity])) {
      try{
        self::$entityFieldOptionsMeta[$entity] = array();
        self::$entityFieldOptionsMeta[$entity] = civicrm_api3($entity, 'getoptions', array(
          'field' => $field,
          'context' => "match",
        ))['values'];
      } catch (CiviCRM_API3_Exception $e) {
        // nothing
      }
    }
    return self::$entityFieldOptionsMeta[$entity];
  }

  /**
   * Callback function for entity import task
   *
   * @param CRM_Queue_TaskContext $ctx
   * @param $entity
   * @param $batch
   * @param $errFileName
   * @return bool
   */
  public static function ImportEntity(CRM_Queue_TaskContext $ctx, $entity, $batch, $errFileName) {
    if( !$entity || !isset($batch)) {
      CRM_Core_Session::setStatus('Invalid params supplied to import queue!', 'Queue task - Init', 'error');
      return false;
    }

    $errors = array();
    $error = NULL;

    // process items from batch
    foreach ($batch as $params) {
      $error = NULL;
      $origParams = $params['rowValues'];
      unset($params['rowValues']);
      $allowUpdate = $params['allowUpdate'];
      unset($params['allowUpdate']);
      $ignoreCase = $params['ignoreCase'];
      unset($params['ignoreCase']);

      // add validation for options select fields
      $validation = self::validateFields($entity, $params, $ignoreCase);
      if(isset($validation['error'])) {
        array_unshift($origParams, $validation['error']);
        $error = $origParams;
        $validation = array();
      }
      foreach ($validation as $fieldName => $valInfo) {
        if ($valInfo['error']) {
          array_unshift($origParams, $valInfo['error']);
          $error = $origParams;
          break;
        }
        if (isset($valInfo['valueUpdated'])) {
          // if 'label' is used instead of 'name' or if multivalued fields using '|'
          $params[$valInfo['valueUpdated']['field']] = $valInfo['valueUpdated']['value'];
        }
      }

      // validation errors
      if($error) {
        $errors[] = $error;
        continue;
      }

      // check for api chaining in params and run them separately
      foreach ($params as $k => $param) {
        if (is_array($param) && count($param) == 1) {
          reset($param);
          $key = key($param);
          if (strpos($key, 'api.') === 0 && strpos($key, '.get') === (strlen($key) - 4)) {
            $refEntity = substr($key, 4, strlen($key) - 8);

            // special case: handle 'Master Address Belongs To' field using contact external_id
            if ($refEntity == 'Address' && isset($param[$key]['external_identifier'])) {
              try {
                $res = civicrm_api3('Contact', 'get', $param[$key]);
              } catch (CiviCRM_API3_Exception $e) {
                $error = $e->getMessage();
                $m = 'Error handling \'Master Address Belongs To\'! (' . $error . ')';
                array_unshift($origParams, $m);
                $error = $origParams;
                break;
              }
              $param[$key]['contact_id'] = $res['values'][0]['id'];
              unset($param[$key]['external_identifier']);
            }

            try {
              $data = civicrm_api3($refEntity, 'get', $param[$key]);
            } catch (CiviCRM_API3_Exception $e) {
              $error = $e->getMessage();
              $m = 'Error with referenced entity "get"! (' . $error . ')';
              array_unshift($origParams, $m);
              $error = $origParams;
              break;
            }
            $params[$k] = $data['values'][0]['id'];
          }
        }
      }

      // api chaining errors
      if($error) {
        $errors[] = $error;
        continue;
      }

      // Check if entity needs to be updated/created
      if($allowUpdate) {
        $uniqueFields = CRM_Csvimport_Import_ControllerBaseClass::findAllUniqueFields($entity);
        foreach ($uniqueFields as $uniqueField) {
          $fieldCount = 0;
          $tmp = array();

          foreach ($uniqueField as $name) {
            if (isset($params[$name])) {
              $fieldCount++;
              $tmp[$name] = $params[$name];
            }
          }

          if (count($uniqueField) == $fieldCount) {
            // unique field found; check if it entity exists
            try {
              $tmp['sequential'] = 1;
              $tmp['return'] = array('id');
              $existingEntity = civicrm_api3($entity, 'get', $tmp);
            } catch (CiviCRM_API3_Exception $e) {
              $error = $e->getMessage();
              $m = 'Error with entity "get"! (' . $error . ')';
              array_unshift($origParams, $m);
              $errors[] = $origParams;
              continue;
            }
            if (isset($existingEntity['values'][0]['id'])) {
              $params['id'] = $existingEntity['values'][0]['id'];
              break;
            }
          }
        }
      }

      try {
        civicrm_api3($entity, 'create', $params);
      } catch (CiviCRM_API3_Exception $e) {
        $error = $e->getMessage();
        $m = 'Error with entity "create"! (' . $error . ')';
        array_unshift($origParams, $m);
        $errors[] = $origParams;
        continue;
      }
    }

    if(count($errors) > 0) {
      $ret = self::addErrorsToReport($errFileName, $errors);
      if(isset($ret['error'])) {
        CRM_Core_Session::setStatus($ret['error'], 'Queue task', 'error');
      }
    }
    return true;
  }

  /**
   * Validates field-value pairs before importing
   *
   * @param $entity
   * @param $params
   * @param bool $ignoreCase
   * @return array
   */
  private static function validateFields($entity, $params, $ignoreCase = FALSE) {
    $fieldsMeta = self::getFieldsMeta($entity);

    $opFields = array();
    foreach ($fieldsMeta as $fieldName => $value) {
      // only try to validate option fields and yes/no fields
      if($value['type'] == CRM_Utils_Type::T_BOOLEAN || isset($value['pseudoconstant'])) {
        $opFields[] = $fieldName;
      }
    }

    $valInfo = array();
    foreach ($params as $fieldName => $value) {
      // exception with relation_type_id which is numeric, and doesn't pass the validation
      if ($entity == 'Relationship' && $fieldName == 'relationship_type_id') {
        continue;
      }
      // exception with group_id which is numeric, and doesn't pass the validation
      if ($entity == 'GroupContact' && $fieldName == 'group_id') {
        continue;
      }
      if(in_array($fieldName, $opFields)) {
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
   * @return array
   */
  private static function validateField($entity, $field, $value, $ignoreCase = FALSE) {
    // Horrible hack to get around
    // https://github.com/eileenmcnaughton/nz.co.fuzion.csvimport/issues/21
    if ($entity == 'Relationship' && $field == 'relationship_type_id') {
      return array('error' => 0);
    }

    $options = self::getFieldOptionsMeta($entity, $field);

    $optionKeys = array_keys($options);
    if($ignoreCase) {
      $optionKeys = array_map('strtolower', $optionKeys);
      $value = strtolower($value);
    }
    $value = explode('|', $value);
    $value = array_values(array_filter($value)); // filter empty values
    $valueUpdated = FALSE;
    $isValid = TRUE;

    foreach ($value as $k => $mval) {
      if(!empty($mval) && !in_array($mval, $optionKeys)) {
        $isValid = FALSE;
        // check 'label' if 'name' not found
        foreach ($options as $name => $label) {
          if($mval == $label || ($ignoreCase && strcasecmp($mval, $label) == 0)) {
            $value[$k] = $name;
            $valueUpdated = TRUE;
            $isValid = TRUE;
          }
        }
        if(!$isValid) {
          return array('error' => ts('Invalid value for field') . ' (' . $field . ') => ' . $mval);
        }
      }
    }

    if(count($value) == 1) {
      if(!$valueUpdated) {
        return array('error' => 0);
      }
      $value = array_pop($value);
    }

    return array('error' => 0, 'valueUpdated' => array('field' => $field, 'value' => $value));
  }

  /**
   * Add rows with errors to error file
   *
   * @param $filename
   * @param $errors
   * @return boolean | array
   */
  private static function addErrorsToReport($filename, $errors) {
    try {
      $file = fopen($filename, 'a');
      foreach ($errors as $item) {
        fputcsv($file, $item);
      }
      fclose($file);
    }
    catch (Exception $e) {
      $error = $e->getMessage();
      return array('error' => $error);
    }

    return TRUE;
  }

}
