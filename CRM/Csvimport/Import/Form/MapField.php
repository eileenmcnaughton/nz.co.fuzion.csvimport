<?php

class CRM_Csvimport_Import_Form_MapField extends CRM_Import_Form_MapField {

  use CRM_Csvimport_Import_Form_CSVImportFormTrait;

  protected $_parser = 'CRM_Csvimport_Import_Parser_Api';

  protected $_mappingType = 'Import Participant';

  protected $_highlightedFields = [];

  /**
   * Fields to remove from the field mapping if 'On Duplicate Update is selected
   *
   * @var array
   */
  protected $_onDuplicateUpdateRemove = [];

  /**
   * Fields to highlight in the field mapping if 'On Duplicate Update is selected
   *
   * @var array
   */
  protected $_onDuplicateUpdateHighlight = [];

  /**
   * Fields to highlight in the field mapping if 'On Duplicate Skip' or On Duplicate No Check is selected
   *
   * @var array
   */
  protected $_onDuplicateSkipHighlight = [];

  /**
   * @return \CRM_Csvimport_Import_Parser_Api
   */
  protected function getParser(): CRM_Csvimport_Import_Parser_Api {
    if (!$this->parser) {
      $this->parser = new CRM_Csvimport_Import_Parser_Api();
      $this->parser->setUserJobID($this->getUserJobID());
      $this->parser->setEntity($this->getSubmittedValue('entity'));
      $this->parser->setRefFields($this->controller->get('refFields'));

      $this->parser->init();
    }
    return $this->parser;
  }

  /**
   * entity being imported to
   *
   * @var string
   */
  protected $_entity;

  /**
   * Function to set variables up before form is built
   *
   * @return void
   * @access public
   */
  public function preProcess() {
    parent::preProcess();

    $this->doDuplicateOptionHandling();

    // find all reference fields for this entity
    if ($this->getSubmittedValue('noteEntity')) {
      // why??
      unset($this->_mapperFields['entity_table']);
    }

    // Add new fields
    $this->controller->set('refFields', $this->getReferenceFields($this->getUniqueFields()));
    asort($this->_mapperFields);
    $this->assign('highlightedFields', $this->_highlightedFields);
  }

  /**
   * Here we add or remove fields based on the selected duplicate option
   */
  function doDuplicateOptionHandling() {
    if ($this->getSubmittedValue('onDuplicate') == CRM_Import_Parser::DUPLICATE_UPDATE) {
      foreach ($this->_onDuplicateUpdateRemove as $value) {
        unset($this->_mapperFields[$value]);
      }
      foreach ($this->__onDuplicateUpdateHighlight as $name) {
        $this->_highlightedFields[] = $name;
      }
    }
    elseif ($this->getSubmittedValue('onDuplicate') == CRM_Import_Parser::DUPLICATE_SKIP ||
      $this->getSubmittedValue('onDuplicate') == CRM_Import_Parser::DUPLICATE_NOCHECK
    ) {
      $this->_highlightedFields = $this->_highlightedFields + $this->_onDuplicateUpdateHighlight;
    }
  }

  /**
   * Function to actually build the form
   *
   * @return void
   * @access public
   */
  public function buildQuickForm() {

    //to save the current mappings
    if (!$this->getSubmittedValue('savedMapping')) {
      $saveDetailsName = ts('Save this field mapping');
      $this->applyFilter('saveMappingName', 'trim');
      $this->add('text', 'saveMappingName', ts('Name'));
      $this->add('text', 'saveMappingDesc', ts('Description'));
    }
    else {
      $savedMapping = $this->getSubmittedValue('savedMapping');

      [$mappingName] = CRM_Core_BAO_Mapping::getMappingFields($savedMapping);

      $mappingName = $mappingName[1];
      //mapping is to be loaded from database

      $params = ['id' => $savedMapping];
      $temp = [];
      $mappingDetails = CRM_Core_BAO_Mapping::retrieve($params, $temp);

      $this->assign('loadedMapping', $mappingDetails->name);
      $this->set('loadedMapping', $savedMapping);

      $getMappingName = new CRM_Core_DAO_Mapping();
      $getMappingName->id = $savedMapping;
      $getMappingName->mapping_type = $this->_mappingType;
      $getMappingName->find();
      while ($getMappingName->fetch()) {
        $mapperName = $getMappingName->name;
      }

      $this->assign('savedName', $mapperName);

      $this->add('hidden', 'mappingId', $savedMapping);

      $this->addElement('checkbox', 'updateMapping', ts('Update this field mapping'), NULL);
      $saveDetailsName = ts('Save as a new field mapping');
      $this->add('text', 'saveMappingName', ts('Name'));
      $this->add('text', 'saveMappingDesc', ts('Description'));
    }

    $this->addElement('checkbox', 'saveMapping', $saveDetailsName, NULL, ['onclick' => "showSaveDetails(this)"]);

    $defaults = [];
    $mapperKeys = array_keys($this->_mapperFields);
    $hasHeaders = !empty($this->_columnHeaders);


    /* Initialize all field usages to false */

    foreach ($mapperKeys as $key) {
      $this->_fieldUsed[$key] = FALSE;
    }
    $sel1 = $this->_mapperFields;

    $js = "<script type='text/javascript'>\n";
    $formName = 'document.forms.' . $this->_name;
    // this next section has a load of copy & paste that I don't really follow
    //used to warn for mismatch column count or mismatch mapping
    $warning = 0;
    for ($i = 0; $i < $this->_columnCount; $i++) {
      $sel = &$this->addElement('hierselect', "mapper[$i]", ts('Mapper for Field %1', [1 => $i]), NULL);
      $jsSet = FALSE;
      if ($this->getSubmittedValue('savedMapping')) {
        if (isset($mappingName[$i])) {
          if ($mappingName[$i] != 'do_not_import') {
            $js .= "{$formName}['mapper[$i][3]'].style.display = 'none';\n";
            $defaults["mapper[$i]"] = [$mappingName[$i]];
            $jsSet = TRUE;
          }
          else {
            $defaults["mapper[$i]"] = [];
          }
          if (!$jsSet) {
            for ($k = 1; $k < 4; $k++) {
              $js .= "{$formName}['mapper[$i][$k]'].style.display = 'none';\n";
            }
          }
        }
        else {
          // this load section to help mapping if we ran out of saved columns when doing Load Mapping
          $js .= "swapOptions($formName, 'mapper[$i]', 0, 3, 'hs_mapper_" . $i . "_');\n";

          if ($hasHeaders) {
            $defaults["mapper[$i]"] = [''];
          }
        }
        //end of load mapping
      }
      else {
        $js .= "swapOptions($formName, 'mapper[$i]', 0, 3, 'hs_mapper_" . $i . "_');\n";
        if ($hasHeaders) {
          // Infer the default from the skipped headers if we have them
          $defaults["mapper[$i]"] = [''];
        }
      }
      $sel->setOptions([$sel1]);
    }
    $js .= "</script>\n";
    $this->assign('initHideBoxes', $js);

    $this->setDefaults($defaults);

    $this->addButtons([
        [
          'type' => 'back',
          'name' => ts('<< Previous'),
        ],
        [
          'type' => 'next',
          'name' => ts('Continue >>'),
          'spacing' => '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;',
          'isDefault' => TRUE,
        ],
        [
          'type' => 'cancel',
          'name' => ts('Cancel'),
        ],
      ]
    );
  }

  /**
   * global validation rules for the form
   *
   * @param array $fields posted values of the form
   *
   * @param $files
   * @param $self
   *
   * @return array list of errors to be posted back to the form
   * @static
   * @access public
   */
  static function formRule($fields, $files, $self) {
    $errors = [];

    if (CRM_Utils_Array::value('saveMapping', $fields)) {
      $nameField = CRM_Utils_Array::value('saveMappingName', $fields);
      if (empty($nameField)) {
        $errors['saveMappingName'] = ts('Name is required to save Import Mapping');
      }
      else {
        $mappingTypeId = CRM_Core_PseudoConstant::getKey('CRM_Core_BAO_Mapping', 'mapping_type_id', $self->_mappingType);
        if (CRM_Core_BAO_Mapping::checkMapping($nameField, $mappingTypeId)) {
          $errors['saveMappingName'] = ts('Duplicate ' . $self->_mappingType . 'Mapping Name');
        }
      }
    }

    //display Error if loaded mapping is not selected
    if (array_key_exists('loadMapping', $fields)) {
      $getMapName = CRM_Utils_Array::value('savedMapping', $fields);
      if (empty($getMapName)) {
        $errors['savedMapping'] = ts('Select saved mapping');
      }
    }

    if (!empty($errors)) {
      if (!empty($errors['saveMappingName'])) {
        $_flag = 1;
        $assignError = new CRM_Core_Page();
        $assignError->assign('mappingDetailsError', $_flag);
      }
      return $errors;
    }

    return TRUE;
  }

  /**
   * Get the type of used for civicrm_mapping.mapping_type_id.
   *
   * @return string
   */
  public function getMappingTypeName(): string {
    return $this->_mappingType;
  }

  /**
   * @param array $uniqueFields
   *
   * @return array
   */
  protected function getReferenceFields(array $uniqueFields): array {
    $refFields = [];
    foreach ($uniqueFields as $entityName => $entity) {
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
      if ($k == 'custom_fields') {
        foreach ($rfield as $each) {
          switch ($each['data_type']) {
            case 'ContactReference':
              try {
                $uf = civicrm_api3('Contact', 'getunique', [])['values'];
              }
              catch (CiviCRM_API3_Exception $e) {
                if ($e->getErrorCode() == 'not-found') {
                  // fallback method for versions < 5.2
                  $uf = $this->controller->findAllUniqueFields('Contact');
                }
              }
              $uniqueFields['Contact'][$each['name']] = $uf;
              break;
          }
        }
      }
      else {
        try {
          $uf = civicrm_api3($rfield['entity'], 'getunique', [])['values'];
        }
        catch (CiviCRM_API3_Exception $e) {
          if ($e->getErrorCode() == 'not-found') {
            // fallback method for versions < 5.2
            $uf = $this->controller->findAllUniqueFields($rfield['entity']);
          }
        }
        $uniqueFields[$rfield['entity']][$rfield['name']] = $uf;
        $extraFields = $this->controller->getSpecialCaseFields($rfield['entity']);
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

}
