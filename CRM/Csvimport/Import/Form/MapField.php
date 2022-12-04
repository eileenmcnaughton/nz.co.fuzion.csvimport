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

    $this->addSavedMappingFields();
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

}
