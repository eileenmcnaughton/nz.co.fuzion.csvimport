<?php

class CRM_Csvimport_Import_Form_MapField extends CRM_Import_Form_MapField {

  use CRM_Csvimport_Import_Form_CSVImportFormTrait;

  protected $_mappingType = 'Import Participant';

  protected $_highlightedFields = [];

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
  public function preProcess(): void {
    parent::preProcess();

    // find all reference fields for this entity
    if ($this->getSubmittedValue('noteEntity')) {
      // why??
      unset($this->_mapperFields['entity_table']);
    }

    asort($this->_mapperFields);
    $this->assign('highlightedFields', $this->_highlightedFields);
  }

  /**
   * Get the base entity for the import.
   *
   * @return string
   *
   * @throws \CRM_Core_Exception
   */
  protected function getBaseEntity(): string {
    return $this->getSubmittedValue('entity');
  }

  /**
   * Function to actually build the form
   *
   * @return void
   * @access public
   */
  public function buildQuickForm(): void {
    $this->addSavedMappingFields();
    $this->addMapper();
    $this->addFormButtons();
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
   * Add the saved mapping fields to the form.
   *
   * @throws \CRM_Core_Exception
   */
  protected function addSavedMappingFields(): void {
    $savedMappingID = $this->getSavedMappingID();
    //to save the current mappings
    if (!$savedMappingID && !$this->getTemplateJob()) {
      $saveDetailsName = ts('Save this field mapping');
      $this->applyFilter('saveMappingName', 'trim');
      $this->add('text', 'saveMappingName', ts('Name'));
      $this->add('text', 'saveMappingDesc', ts('Description'));
    }
    else {
      $this->add('hidden', 'mappingId', $savedMappingID);

      $this->addElement('checkbox', 'updateMapping', ts('Update this field mapping'), NULL);
      $saveDetailsName = ts('Save as a new field mapping');
      $this->add('text', 'saveMappingName', ts('Name'));
      $this->add('text', 'saveMappingDesc', ts('Description'));
    }
    $this->assign('savedMappingName', $this->getMappingName());
    $this->addElement('checkbox', 'saveMapping', $saveDetailsName, NULL);
    $this->addFormRule(['CRM_Import_Form_MapField', 'mappingRule']);
  }

  /**
   * Add the form buttons.
   */
  protected function addFormButtons(): void {
    $this->addButtons([
      [
        'type' => 'back',
        'name' => ts('Previous'),
      ],
      [
        'type' => 'next',
        'name' => ts('Continue'),
        'spacing' => '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;',
        'isDefault' => TRUE,
      ],
      [
        'type' => 'cancel',
        'name' => ts('Cancel'),
      ],
    ]);
  }

}
