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

  public $_parser = 'CRM_Csvimport_Import_Parser_Api';

  protected $_enableContactOptions = FALSE;

  protected $_userContext = 'civicrm/csvimporter/import';

  protected $_mappingType = 'Import Participant';//@todo make this vary depending on api - need to create option values

  protected $_entity;

  const IMPORT_ENTITY = 'Api Entity';

  /**
   * Get the name of the type to be stored in civicrm_user_job.type_id.
   *
   * @return string
   */
  public function getUserJobType(): string {
    return 'csv_api_importer';
  }

  /**
   * @return \CRM_Csvimport_Import_Parser_Api
   */
  protected function getParser(): CRM_Csvimport_Import_Parser_Api {
    if (!$this->parser) {
      $this->parser = new CRM_Csvimport_Import_Parser_Api();
      $this->parser->setUserJobID($this->getUserJobID());
      $this->parser->init();
    }
    return $this->parser;
  }

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
   * Include duplicate options
   */
  protected $isDuplicateOptions = FALSE;

  /**
   * Function to actually build the form - this appears to be entirely code that should be in a shared base class in core
   *
   * @access public
   */
  public function buildQuickForm() {
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
      catch (CiviCRM_API3_Exception $e) {
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

    $this->add('select', 'noteEntity', ts('Which entity are you importing "Notes" to'), $noteEntities + ['0' => ts('Set this in CSV')]);
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
        'contactType' =>
          CRM_Import_Parser::CONTACT_INDIVIDUAL,
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
   */
  public function setDefaultValues() {
    $defaults = parent::setDefaultValues();
    $entity = CRM_Utils_Request::retrieve('entity', 'String', $this, FALSE);
    //potentially we need to convert entity to full camel
    $defaults['entity'] = empty($entity) ? '' : ucfirst($entity);
    return $defaults;
  }

  /**
   * Function to set variables up before form is built
   *
   * @return void
   * @access public
   */
  public function preProcess() {
    $session = CRM_Core_Session::singleton();
    $session->pushUserContext(CRM_Utils_System::url($this->_userContext, 'reset=1'));
  }

  /**
   * Process the uploaded file
   *
   * @return void
   * @access public
   */
  public function postProcess() {
    $dateFormats = $this->controller->exportValue($this->_name, 'dateFormats');
    $entity = $this->controller->exportValue($this->_name, 'entity');
    $allowEntityUpdate = $this->controller->exportValue($this->_name, 'allowEntityUpdate');
    $ignoreCase = $this->controller->exportValue($this->_name, 'ignoreCase');
    if ($entity == 'Note') {
      $noteEntity = $this->controller->exportValue($this->_name, 'noteEntity');
      $this->set('noteEntity', $noteEntity);
    }

    $this->controller->set('allowEntityUpdate', $allowEntityUpdate);
    $this->controller->set('ignoreCase', $ignoreCase);

    $session = CRM_Core_Session::singleton();
    $session->set("dateTypes", $dateFormats);
    parent::postProcess();
  }

  public function addContactOptions() {
    //contact types option
    $contactOptions = [];
    if (CRM_Contact_BAO_ContactType::isActive('Individual')) {
      $contactOptions[] = $this->createElement('radio',
        NULL, NULL, ts('Individual'), CRM_Import_Parser::CONTACT_INDIVIDUAL
      );
    }
    if (CRM_Contact_BAO_ContactType::isActive('Household')) {
      $contactOptions[] = $this->createElement('radio',
        NULL, NULL, ts('Household'), CRM_Import_Parser::CONTACT_HOUSEHOLD
      );
    }
    if (CRM_Contact_BAO_ContactType::isActive('Organization')) {
      $contactOptions[] = $this->createElement('radio',
        NULL, NULL, ts('Organization'), CRM_Import_Parser::CONTACT_ORGANIZATION
      );
    }
    $this->addGroup($contactOptions, 'contactType', ts('Contact Type'));
  }

  /**
   * Return a descriptive name for the page, used in wizard header
   *
   * @return string
   * @access public
   */
  public function getTitle() {
    return ts('Upload Data');
  }

}

