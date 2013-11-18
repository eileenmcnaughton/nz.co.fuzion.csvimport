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
  protected $_userContext = 'civicrm/csvimport/import';
  protected $_mappingType = 'Import Participant';//@todo make this vary depending on api - need to create option values
  protected $_entity;
  /**
  * Include duplicate options
  */
  protected $isDuplicateOptions = FALSE;

    /**
   * Function to actually build the form - this appears to be entirely code that should be in a shared baseclass in core
   *
   * @return None
   * @access public
   */
  public function buildQuickForm() {
    //We are gathering this as a text field for now. I tried to put it into the URL but for some reason
    //adding &x=y in the url causes it not to load at all.
    // I am thinking about a select with a setting to specify which entities are exposed (would create mapping type option values for these)
    $this->addElement('text', 'entity', ts('Entity To Import'));
    parent::buildQuickForm();
  }
}

