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
 * $Id$
 *
 */

/**
 * This class gets the name of the file to upload
 */
class CRM_Csvimport_Import_Form_DataSource extends CRM_Csvimport_Import_Form_DataSourceBaseClass {
  public $_parser = 'CRM_Csvimport_Import_Parser_Api';
  protected $_enableContactOptions = FALSE;
  protected $_userContext = 'civicrm/csvimporter/import';
  protected $_mappingType = 'Import Participant';//@todo make this vary depending on api - need to create option values
  protected $_entity;
  /**
  * Include duplicate options
  */
  protected $isDuplicateOptions = FALSE;

    /**
   * Function to actually build the form - this appears to be entirely code that should be in a shared base class in core
   *
   * @return None
   * @access public
   */
  public function buildQuickForm() {
    //We are gathering this as a text field for now. I tried to put it into the URL but for some reason
    //adding &x=y in the url causes it not to load at all.
    $allEntities = civicrm_api3('entity', 'get', array());
    $creatableEntities = array();
    foreach ($allEntities['values'] as $entity) {
      try {
        $actions = civicrm_api3($entity, 'getactions', array('entity' => $entity));
        //can add 'submit' later when we can figure out how to specify on submit
        if(array_intersect(array('create',), $actions['values'])) {
          $creatableEntities[$entity] = $entity;
        }
      }
      catch (CiviCRM_API3_Exception $e) {
        // Ignore entities that raise an exception
      }
    }
    $this->add('select', 'entity', ts('Entity To Import'), array('' => ts('- select -')) + $creatableEntities);
    parent::buildQuickForm();
  }

  /**
   * Set defaults for form
   * @return array
   */
  public function setDefaultValues() {
    $defaults = parent::setDefaultValues();
    $entity = CRM_Utils_Request::retrieve('entity', 'String', $this, FALSE);
    //potentially we need to convert entity to full camel
    $defaults['entity'] = empty($entity) ? '' : ucfirst($entity);
    return $defaults;

  }
}

