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
 * This class previews the uploaded file and returns summary
 * statistics
 */
class CRM_Csvimport_Import_Form_Previewbaseclass extends CRM_Import_Form_Preview {

  /**
   * This is used in error urls
   * although this code specifies the Event import parser it is a completely generic function that could live anywhere (& probably does in C&P
   * manifestations
   *
   * @var unknown
   */
  protected $_importParserUrl = '&parser=CRM_Event_Import_Parser';

  /**
   * Function to set variables up before form is built
   *
   * @return void
   * @access public
   */
  public function preProcess() {
    $skipColumnHeader = $this->controller->exportValue('DataSource', 'skipColumnHeader');

    //get the data from the session
    $dataValues = $this->get('dataValues');
    $mapper = $this->get('mapper');
    $invalidRowCount = $this->get('invalidRowCount');
    $conflictRowCount = $this->get('conflictRowCount');
    $mismatchCount = $this->get('unMatchCount');
    $entity = $this->get('_entity');

    //get the mapping name displayed if the mappingId is set
    $mappingId = $this->get('loadMappingId');
    if ($mappingId) {
      $mapDAO = new CRM_Core_DAO_Mapping();
      $mapDAO->id = $mappingId;
      $mapDAO->find(TRUE);
      $this->assign('loadedMapping', $mappingId);
      $this->assign('savedName', $mapDAO->name);
    }

    if ($skipColumnHeader) {
      $this->assign('skipColumnHeader', $skipColumnHeader);
      $this->assign('rowDisplayCount', 3);
    }
    else {
      $this->assign('rowDisplayCount', 2);
    }

    if ($invalidRowCount) {
      $urlParams = 'type=' . CRM_Import_Parser::ERROR . $this->_importParserUrl;
      $this->set('downloadErrorRecordsUrl', CRM_Utils_System::url('civicrm/export', $urlParams));
    }

    if ($conflictRowCount) {
      $urlParams = 'type=' . CRM_Import_Parser::CONFLICT . $this->_importParserUrl;
      $this->set('downloadConflictRecordsUrl', CRM_Utils_System::url('civicrm/export', $urlParams));
    }

    if ($mismatchCount) {
      $urlParams = 'type=' . CRM_Import_Parser::NO_MATCH . $this->_importParserUrl;
      $this->set('downloadMismatchRecordsUrl', CRM_Utils_System::url('civicrm/export', $urlParams));
    }

    $properties = [
      'mapper',
      'dataValues',
      'columnCount',
      'totalRowCount',
      'validRowCount',
      'invalidRowCount',
      'conflictRowCount',
      'downloadErrorRecordsUrl',
      'downloadConflictRecordsUrl',
      'downloadMismatchRecordsUrl',
    ];

    foreach ($properties as $property) {
      $this->assign($property, $this->get($property));
    }
  }

  /**
   * Process the mapped fields and map it into the uploaded file
   * preview the file and extract some summary statistics
   *
   * @return void
   * @access public
   */
  public function postProcess() {
    $fileName = $this->controller->exportValue('DataSource', 'uploadFile');
    $skipColumnHeader = $this->controller->exportValue('DataSource', 'skipColumnHeader');
    $invalidRowCount = $this->get('invalidRowCount');
    $conflictRowCount = $this->get('conflictRowCount');
    $onDuplicate = $this->get('onDuplicate');
    $entity = $this->get('_entity');

    $config = CRM_Core_Config::singleton();
    $separator = $config->fieldSeparator;

    $mapper = $this->controller->exportValue('MapField', 'mapper');
    $mapperKeys = [];

    foreach ($mapper as $key => $value) {
      $mapperKeys[$key] = $mapper[$key][0];
    }

    $parser = new $this->_parser($mapperKeys);
    $parser->setEntity($entity);

    $parser->setRefFields($this->controller->get('refFields'));
    $mapFields = $this->get('fields');

    $queueBatchSize = $this->controller->exportValue('DataSource', 'queueBatchSize');
    $parser->setImportQueueBatchSize($this->controller->get('queueBatchSize'));

    $parser->setAllowEntityUpdate($this->controller->get('allowEntityUpdate'));
    $parser->setIgnoreCase($this->controller->get('ignoreCase'));

    foreach ($mapper as $key => $value) {
      $header = [];
      if (isset($mapFields[$mapper[$key][0]])) {
        $header[] = $mapFields[$mapper[$key][0]];
      }
      $mapperFields[] = implode(' - ', $header);
    }
    $parser->run($fileName, $separator,
      $mapperFields,
      $skipColumnHeader,
      CRM_Import_Parser::MODE_IMPORT,
      $this->get('contactType'),
      $onDuplicate
    );

    // add all the necessary variables to the form
    $parser->set($this, CRM_Import_Parser::MODE_IMPORT);

    // check if there is any error occurred

    $errorStack = CRM_Core_Error::singleton();
    $errors = $errorStack->getErrors();
    $errorMessage = [];

    if (is_array($errors)) {
      foreach ($errors as $key => $value) {
        $errorMessage[] = $value['message'];
      }

      $errorFile = $fileName['name'] . '.error.log';

      if ($fd = fopen($errorFile, 'w')) {
        fwrite($fd, implode('\n', $errorMessage));
      }
      fclose($fd);

      $this->set('errorFile', $errorFile);
      $urlParams = 'type=' . CRM_Import_Parser::ERROR . $this->_importParserUrl;
      $this->set('downloadErrorRecordsUrl', CRM_Utils_System::url('civicrm/export', $urlParams));
      $urlParams = 'type=' . CRM_Import_Parser::CONFLICT . $this->_importParserUrl;
      $this->set('downloadConflictRecordsUrl', CRM_Utils_System::url('civicrm/export', $urlParams));
      $urlParams = 'type=' . CRM_Import_Parser::NO_MATCH . $this->_importParserUrl;
      $this->set('downloadMismatchRecordsUrl', CRM_Utils_System::url('civicrm/export', $urlParams));
    }
    // run importer queue
    $this->runQueue();
  }

  /**
   * Run all tasks in importer queue
   */
  private function runQueue() {
    //retrieve the queue
    $queue = CRM_Csvimport_Queue_Import::singleton()->getQueue();
    $runner = new CRM_Queue_Runner([
      'title' => ts('CSVImport Queue Runner - Import'),
      //title fo the queue
      'queue' => $queue,
      //the queue object
      'errorMode' => CRM_Queue_Runner::ERROR_CONTINUE,
      // continue on error
      'onEndUrl' => CRM_Utils_System::url('civicrm/csvimporter/import', ['_qf_Summary_display' => TRUE, 'qfKey' => $this->controller->_key], FALSE, NULL, FALSE),
      //go to page after all tasks are finished
    ]);

    $runner->runAllViaWeb(); // does not return
  }

}

