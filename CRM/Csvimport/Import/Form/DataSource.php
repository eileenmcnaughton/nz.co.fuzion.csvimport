<?php
/*
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC. All rights reserved.                        |
 |                                                                    |
 | This work is published under the GNU AGPLv3 license with some      |
 | permitted exceptions and without any warranty. For full license    |
 | and copyright information, see https://civicrm.org/licensing       |
 +--------------------------------------------------------------------+
 */

use Civi\Api4\Note;

/**
 * This class gets the name of the file to upload
 */
class CRM_Csvimport_Import_Form_DataSource extends CRM_Import_Form_DataSource {

  use CRM_Csvimport_Import_Form_CSVImportFormTrait;

  public function getTemplateFileName(): string {
    return 'CRM/Csvimport/Import/Form/DataSource.tpl';
  }

  /**
   * Function to actually build the form - this appears to be entirely code that should be in a shared base class in core
   *
   * @throws \CRM_Core_Exception
   */
  public function buildQuickForm(): void {
    //We are gathering this as a text field for now. I tried to put it into the URL but for some reason
    //adding &x=y in the url causes it not to load at all.
    $allEntities = civicrm_api3('entity', 'get');
    $creatableEntities = [];
    foreach ($allEntities['values'] as $entity) {
      try {
        $actions = civicrm_api3($entity, 'getactions', ['entity' => $entity]);
        //can add 'submit' later when we can figure out how to specify on submit
        if (array_intersect(['create'], $actions['values'])) {
          $creatableEntities[$entity] = $entity;
        }
      }
      catch (CRM_Core_Exception) {
        // Ignore entities that raise an exception
      }
    }
    $this->add('select', 'entity', ts('Entity To Import'), ['' => ts('- select -')] + $creatableEntities, TRUE, ['class' => 'crm-select2']);

    // handle 'Note' entity
    $noteEntities = Note::getFields()
      ->setLoadOptions(TRUE)
      ->addSelect('options')
      ->addWhere('name', '=', 'entity_table')
      ->execute()[0]['options'];

    $this->add('select', 'noteEntity', ts('Which entity are you importing "Notes" to'), $noteEntities + ['0' => ts('Set this in CSV')], FALSE, ['class' => 'crm-select2']);

    $this->setDefaults(['onDuplicate' => CRM_Import_Parser::DUPLICATE_SKIP]);

    $this->setDefaults(['contactType' => 'Individual']);
    $this->addElement('checkbox', 'allowEntityUpdate', ts('Allow Updating An Entity Using Unique Fields'));
    $this->addElement('checkbox', 'ignoreCase', ts('Ignore Case For Field Option Values'));

    parent::buildQuickForm();

    $this->removeElement('savedMapping');
    //get the saved mapping details
    $mappingArray = CRM_Core_BAO_Mapping::getMappings(
    //@todo make this vary depending on api - need to create option values
      CRM_Core_PseudoConstant::getKey('CRM_Core_BAO_Mapping', 'mapping_type_id', 'Import Participant')
    );
    $this->assign('savedMapping', $mappingArray);
    $this->add('select', 'savedMapping', ts('Mapping Option'), ['' => ts('- select -')] + $mappingArray);

    if ($loadedMapping = $this->get('loadedMapping')) {
      $this->setDefaults(['savedMapping' => $loadedMapping]);
    }
  }

  /**
   * Set defaults for form
   *
   * @return array
   * @throws \CRM_Core_Exception
   */
  public function setDefaultValues(): array {
    $defaults = parent::setDefaultValues();
    $entity = CRM_Utils_Request::retrieve('entity', 'String', $this);
    //potentially we need to convert entity to full camel
    $defaults['entity'] = empty($entity) ? '' : ucfirst($entity);
    return $defaults;
  }

}
