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
abstract class CRM_Csvimport_Import_Parser extends CRM_Import_Parser {

  protected $_fileName;

  /**#@+
   * @access protected
   * @var integer
   */

  /**
   * imported file size
   */
  protected $_fileSize;

  /**
   * separator being used
   */
  protected $_separator;

  /**
   * total number of lines in file
   */
  protected $_lineCount;

  /**
   * whether the file has a column header or not
   *
   * @var boolean
   */
  protected $_haveColumnHeader;

  /**
   * The queue used to process the import
   *
   * @var CRM_Csvimport_Import_Queue
   */
  protected $_importQueue;

  /**
   * Import queue batch size - number of items to precess each time
   *
   * @var CRM_Csvimport_Import_Queue
   */
  protected $_importQueueBatchSize;

  /**
   * Set max errors count
   */
  const MAX_ERRORS = 250;

  protected $_maxErrorCount = self::MAX_ERRORS;

  function run($fileName,
    $separator = ',',
    &$mapper,
    $skipColumnHeader = FALSE,
    $mode = self::MODE_PREVIEW,
    $onDuplicate = self::DUPLICATE_SKIP
  ) {
    if (!is_array($fileName)) {
      CRM_Core_Error::fatal();
    }
    $fileName = $fileName['name'];
    $this->_fileName = $fileName;
    $this->_errorFileName = self::errorFileName(self::ERROR);

    $this->init();

    $this->_haveColumnHeader = $skipColumnHeader;

    $this->_separator = $separator;

    $fd = fopen($fileName, "r");
    if (!$fd) {
      return FALSE;
    }

    $this->_lineCount = $this->_warningCount = 0;
    $this->_invalidRowCount = $this->_validCount = 0;
    $this->_totalCount = $this->_conflictCount = 0;

    $this->_errors = [];
    $this->_warnings = [];
    $this->_conflicts = [];

    $this->_fileSize = number_format(filesize($fileName) / 1024.0, 2);

    if ($mode == self::MODE_MAPFIELD) {
      $this->_rows = [];
    }
    else {
      $this->_activeFieldCount = count($this->_activeFields);
    }

    while (!feof($fd)) {
      $this->_lineCount++;

      $values = fgetcsv($fd, 8192, $separator);
      if (!$values) {
        continue;
      }

      self::encloseScrub($values);

      // skip column header if we're not in mapField mode
      if ($mode != self::MODE_MAPFIELD && $skipColumnHeader) {
        $skipColumnHeader = FALSE;
        continue;
      }

      /* trim whitespace around the values */

      $empty = TRUE;
      foreach ($values as $k => $v) {
        $values[$k] = trim($v, " \t\r\n");
      }

      if (CRM_Utils_System::isNull($values)) {
        continue;
      }

      $this->_totalCount++;

      if ($mode == self::MODE_MAPFIELD) {
        $returnCode = $this->mapField($values);
      }
      elseif ($mode == self::MODE_PREVIEW) {
        $returnCode = $this->preview($values);
      }
      elseif ($mode == self::MODE_SUMMARY) {
        $returnCode = $this->summary($values);
      }
      elseif ($mode == self::MODE_IMPORT) {
        $returnCode = $this->import($onDuplicate, $values);
      }
      else {
        $returnCode = self::ERROR;
      }

      // note that a line could be valid but still produce a warning
      if ($returnCode & self::VALID) {
        $this->_validCount++;
        if ($mode == self::MODE_MAPFIELD) {
          $this->_rows[] = $values;
          $this->_activeFieldCount = max($this->_activeFieldCount, count($values));
        }
      }

      if ($returnCode & self::WARNING) {
        $this->_warningCount++;
        if ($this->_warningCount < $this->_maxWarningCount) {
          $this->_warningCount[] = $line;
        }
      }

      if ($returnCode & self::ERROR) {
        $this->_invalidRowCount++;
        if ($this->_invalidRowCount < $this->_maxErrorCount) {
          $recordNumber = $this->_lineCount;
          if ($this->_haveColumnHeader) {
            $recordNumber--;
          }
          array_unshift($values, $recordNumber);
          $this->_errors[] = $values;
        }
      }

      if ($returnCode & self::CONFLICT) {
        $this->_conflictCount++;
        $recordNumber = $this->_lineCount;
        if ($this->_haveColumnHeader) {
          $recordNumber--;
        }
        array_unshift($values, $recordNumber);
        $this->_conflicts[] = $values;
      }

      if ($returnCode & self::DUPLICATE) {
        if ($returnCode & self::MULTIPLE_DUPE) {
          /* TODO: multi-dupes should be counted apart from singles
                     * on non-skip action */
        }
        $this->_duplicateCount++;
        $recordNumber = $this->_lineCount;
        if ($this->_haveColumnHeader) {
          $recordNumber--;
        }
        array_unshift($values, $recordNumber);
        $this->_duplicates[] = $values;
        if ($onDuplicate != self::DUPLICATE_SKIP) {
          $this->_validCount++;
        }
      }

      // we give the derived class a way of aborting the process
      // note that the return code could be multiple codes combined with an OR operator
      if ($returnCode & self::STOP) {
        break;
      }

      // if we are done processing the maxNumber of lines, break
      if ($this->_maxLinesToProcess > 0 && $this->_validCount >= $this->_maxLinesToProcess) {
        break;
      }
    }

    fclose($fd);


    if ($mode == self::MODE_PREVIEW || $mode == self::MODE_IMPORT) {
      $customHeaders = $mapper;

      $customFields = CRM_Core_BAO_CustomField::getFields('Activity');
      foreach ($customHeaders as $key => $value) {
        if ($id = CRM_Core_BAO_CustomField::getKeyID($value)) {
          $customHeaders[$key] = $customFields[$id][0];
        }
      }

      if ($mode == self::MODE_IMPORT) {
        // parsing is done; if mode is import, add last items in batch to queue
        $this->addBatchToQueue();
        $importErrorHeaders = array_merge(
          [ts('Reason')],
          $customHeaders
        );
        // add headers to error file for import mode
        self::exportCSV($this->_errorFileName, $importErrorHeaders, []);
      }

      if ($this->_invalidRowCount) {
        // removed view url for invalid contacts
        $headers = array_merge([
          ts('Line Number'),
          ts('Reason'),
        ],
          $customHeaders
        );
        self::exportCSV($this->_errorFileName, $headers, $this->_errors);
      }
      if ($this->_conflictCount) {
        $headers = array_merge([
          ts('Line Number'),
          ts('Reason'),
        ],
          $customHeaders
        );
        $this->_conflictFileName = self::errorFileName(self::CONFLICT);
        self::exportCSV($this->_conflictFileName, $headers, $this->_conflicts);
      }
      if ($this->_duplicateCount) {
        $headers = array_merge([
          ts('Line Number'),
          ts('View Activity History URL'),
        ],
          $customHeaders
        );

        $this->_duplicateFileName = self::errorFileName(self::DUPLICATE);
        self::exportCSV($this->_duplicateFileName, $headers, $this->_duplicates);
      }
    }
    //echo "$this->_totalCount,$this->_invalidRowCount,$this->_conflictCount,$this->_duplicateCount";
    return $this->fini();
  }

  /**
   * Given a list of the importable field keys that the user has selected
   * set the active fields array to this list
   *
   * @param array mapped array of values
   *
   * @return void
   * @access public
   */
  function setActiveFields($fieldKeys) {
    $this->_activeFieldCount = count($fieldKeys);
    foreach ($fieldKeys as $key) {
      if (empty($this->_fields[$key])) {
        $this->_activeFields[] = new CRM_Csvimport_Import_Field('', ts('- do not import -'));
      }
      else {
        if (isset($this->_refFields[$key])) {
          $refField = $this->_refFields[$key];
          $key = $refField->id;
          $this->_activeFields[] = clone($this->_fields[$key]);
          end($this->_activeFields);
          $k = key($this->_activeFields);
          $this->_activeFields[$k]->setRefField($refField);
        }
        else {
          $this->_activeFields[] = clone($this->_fields[$key]);
        }
      }
    }
  }

  /**
   * function to format the field values for input to the api
   *
   * @return array (reference ) associative array of name/value pairs
   * @access public
   */
  function &getActiveFieldParams($handleRef = FALSE) {
    $params = [];
    $combinationRefs = [];
    $combinationParams = [];

    for ($i = 0; $i < $this->_activeFieldCount; $i++) {
      if (isset($this->_activeFields[$i]->_value)
        && !isset($params[$this->_activeFields[$i]->_name])
        && !isset($this->_activeFields[$i]->_related)
      ) {

        if ($handleRef && isset($this->_activeFields[$i]->_refField)) {
          $refField = $this->_activeFields[$i]->_refField;
          // get id of entity from ref field
          $apiParams = [
            'sequential' => 1,
            'return' => ["id"],
          ];
          // for combination indexes
          if (is_array($refField->entity_field_name)) {

            if (!isset($combinationRefs[$refField->id])) {
              foreach ($refField->entity_field_name as $k => $name) {
                if ($k === 'active') {
                  continue;
                }
                $combinationRefs[$refField->id][] = $name;
              }
              sort($combinationRefs[$refField->id]);
            }

            $combinationParams[$refField->id][$refField->entity_field_name['active']] = $this->_activeFields[$i]->_value;
            $arr = array_keys($combinationParams[$refField->id]);
            sort($arr);
            if ($arr != $combinationRefs[$refField->id]) {
              // continue till all reference fields for this combination are added to apiParams
              continue;
            }
            else {
              $apiParams = array_merge($apiParams, $combinationParams[$refField->id]);
            }
          }
          else {
            $apiParams[$refField->entity_field_name] = $this->_activeFields[$i]->_value;
          }

          $params[$this->_activeFields[$i]->_name] = ['api.' . $refField->entity_name . '.get' => $apiParams];
        }
        else {
          $params[$this->_activeFields[$i]->_name] = $this->_activeFields[$i]->_value;
        }
      }
    }

    return $params;
  }

  function addField($name, $title, $type = CRM_Utils_Type::T_INT, $headerPattern = '//', $dataPattern = '//') {
    if (empty($name)) {
      $this->_fields['doNotImport'] = new CRM_Csvimport_Import_Field($name, $title, $type, $headerPattern, $dataPattern);
    }
    else {

      //$tempField = CRM_Contact_BAO_Contact::importableFields('Individual', null );
      $tempField = CRM_Contact_BAO_Contact::importableFields('All', NULL);

      // check reference fields
      $isRef = FALSE;
      foreach ($this->_refFields as $ref) {
        if ($name == $ref->id) {
          $isRef = TRUE;
        }
      }

      if (!array_key_exists($name, $tempField) || $isRef) {
        $this->_fields[$name] = new CRM_Csvimport_Import_Field($name, $title, $type, $headerPattern, $dataPattern);
      }
      else {
        $this->_fields[$name] = new CRM_Contact_Import_Field($name, $title, $type, $headerPattern, $dataPattern,
          CRM_Utils_Array::value('hasLocationType', $tempField[$name])
        );
      }
    }
  }

  /**
   * Store parser values
   *
   * @param CRM_Core_Session $store
   *
   * @param int $mode
   *
   * @return void
   * @access public
   */
  function set($store, $mode = self::MODE_SUMMARY) {
    $store->set('fileSize', $this->_fileSize);
    $store->set('lineCount', $this->_lineCount);
    $store->set('seperator', $this->_separator);
    $store->set('fields', $this->getSelectValues());
    $store->set('fieldTypes', $this->getSelectTypes());

    $store->set('headerPatterns', $this->getHeaderPatterns());
    $store->set('dataPatterns', $this->getDataPatterns());
    $store->set('columnCount', $this->_activeFieldCount);
    $store->set('_entity', $this->_entity);
    $store->set('totalRowCount', $this->_totalCount);
    $store->set('validRowCount', $this->_validCount);
    $store->set('invalidRowCount', $this->_invalidRowCount);
    $store->set('conflictRowCount', $this->_conflictCount);

    switch ($this->_contactType) {
      case 'Individual':
        $store->set('contactType', CRM_Import_Parser::CONTACT_INDIVIDUAL);
        break;

      case 'Household':
        $store->set('contactType', CRM_Import_Parser::CONTACT_HOUSEHOLD);
        break;

      case 'Organization':
        $store->set('contactType', CRM_Import_Parser::CONTACT_ORGANIZATION);
    }

    if ($this->_invalidRowCount) {
      $store->set('errorsFileName', $this->_errorFileName);
    }
    if ($this->_conflictCount) {
      $store->set('conflictsFileName', $this->_conflictFileName);
    }
    if (isset($this->_rows) && !empty($this->_rows)) {
      $store->set('dataValues', $this->_rows);
    }

    if ($mode == self::MODE_IMPORT) {
      $store->set('duplicateRowCount', $this->_duplicateCount);
      if ($this->_duplicateCount) {
        $store->set('duplicatesFileName', $this->_duplicateFileName);
      }
    }
  }

  /**
   * Export data to a CSV file
   *
   * @param $fileName
   * @param array $header
   * @param data $data
   *
   * @return void
   * @access public
   * @internal param string $filename
   */
  static function exportCSV($fileName, $header, $data) {
    $output = [];
    $fd = fopen($fileName, 'w');

    foreach ($header as $key => $value) {
      $header[$key] = "\"$value\"";
    }
    $config = CRM_Core_Config::singleton();
    $output[] = implode($config->fieldSeparator, $header);

    foreach ($data as $datum) {
      foreach ($datum as $key => $value) {
        if (is_array($value)) {
          foreach ($value[0] as $k1 => $v1) {
            if ($k1 == 'location_type_id') {
              continue;
            }
            $datum[$k1] = $v1;
          }
        }
        else {
          $datum[$key] = "\"$value\"";
        }
      }
      $output[] = implode($config->fieldSeparator, $datum);
    }
    fwrite($fd, implode("\n", $output));
    if (count($data) == 0) {
      fwrite($fd, "\n");
    }
    fclose($fd);
  }

}

