<?php
/*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.4                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2013                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
*/

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2013
 */

use Civi\Api4\Note;

/**
 * This class gets the name of the file to upload
 */
class CRM_Csvimport_Import_Form_DataSource extends CRM_Import_Form_DataSource {

  use CRM_Csvimport_Import_Form_CSVImportFormTrait;
  public $_parser = 'CRM_Csvimport_Import_Parser_Api';

  protected $_enableContactOptions = FALSE;

  protected $_mappingType = 'Import Participant';//@todo make this vary depending on api - need to create option values

  public function getTemplateFileName(): string {
    return 'CRM/Csvimport/Import/Form/DataSource.tpl';
  }

  /**
   * Include duplicate options
   */
  protected $isDuplicateOptions = FALSE;

  /**
   * Function to actually build the form - this appears to be entirely code that should be in a shared base class in core
   *
   * @throws \CRM_Core_Exception
   */
  public function buildQuickForm(): void {
    //We are gathering this as a text field for now. I tried to put it into the URL but for some reason
    //adding &x=y in the url causes it not to load at all.
    $allEntities = civicrm_api3('entity', 'get', []);
    $creatableEntities = [];
    foreach ($allEntities['values'] as $entity) {
      try {
        $actions = civicrm_api3($entity, 'getactions', ['entity' => $entity]);
        //can add 'submit' later when we can figure out how to specify on submit
        if (array_intersect(['create',], $actions['values'])) {
          $creatableEntities[$entity] = $entity;
        }
      }
      catch (CRM_Core_Exception $e) {
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
    if ($this->isDuplicateOptions) {
      $duplicateOptions = [];
      $duplicateOptions[] = $this->createElement('radio',
        NULL, NULL, ts('Skip'), CRM_Import_Parser::DUPLICATE_SKIP
      );
      $duplicateOptions[] = $this->createElement('radio',
        NULL, NULL, ts('Update'), CRM_Import_Parser::DUPLICATE_UPDATE
      );
      $duplicateOptions[] = $this->createElement('radio',
        NULL, NULL, ts('No Duplicate Checking'), CRM_Import_Parser::DUPLICATE_NOCHECK
      );

      $this->addGroup($duplicateOptions, 'onDuplicate',
        ts('On Duplicate Entries')
      );
    }

    $this->setDefaults([
      'onDuplicate' =>
        CRM_Import_Parser::DUPLICATE_SKIP,
    ]);

    if ($this->_enableContactOptions) {
      $this->addContactOptions();
    }

    $this->setDefaults([
        'contactType' => 'Individual',
      ]
    );
    $this->addElement('checkbox', 'allowEntityUpdate', ts('Allow Updating An Entity Using Unique Fields'));
    $this->addElement('checkbox', 'ignoreCase', ts('Ignore Case For Field Option Values'));

    parent::buildQuickForm();

    $this->removeElement('savedMapping');
    //get the saved mapping details
    $mappingArray = CRM_Core_BAO_Mapping::getMappings(
      CRM_Core_PseudoConstant::getKey('CRM_Core_BAO_Mapping', 'mapping_type_id', $this->_mappingType)
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

  /**
   * @throws \CRM_Core_Exception
   */
  public function addContactOptions(): void {
    //contact types option
    $contactOptions = [];
    if (CRM_Contact_BAO_ContactType::isActive('Individual')) {
      $contactOptions[] = $this->createElement('radio',
        NULL, NULL, ts('Individual'), 'Individual'
      );
    }
    if (CRM_Contact_BAO_ContactType::isActive('Household')) {
      $contactOptions[] = $this->createElement('radio',
        NULL, NULL, ts('Household'), 'Household'
      );
    }
    if (CRM_Contact_BAO_ContactType::isActive('Organization')) {
      $contactOptions[] = $this->createElement('radio',
        NULL, NULL, ts('Organization'), 'Organization'
      );
    }
    $this->addGroup($contactOptions, 'contactType', ts('Contact Type'));
  }

}

