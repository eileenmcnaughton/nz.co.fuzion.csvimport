<?php
class CRM_Csvimport_Import_Parser_Api extends CRM_Csvimport_Import_Parser_BaseClass {
  protected $_entity = '';// for now - set in form
  protected $_fields = array();
  protected $_requiredFields = array();
  protected $_dateFields = array();
  /**
   * Params for the current entity being prepared for the api
   * @var array
   */
  protected $_params = array();

  function setFields() {
   $fields = civicrm_api3($this->_entity, 'getfields', array('action' => 'create'));
   $this->_fields = $fields['values'];
   foreach ($this->_fields as $field => $values) {
     if(!empty($values['api.required'])) {
       $this->_requiredFields[] = $field;
     }
     if(empty($values['title']) && !empty($values['label'])) {
       $this->_fields[$field]['title'] = $values['label'];
     }
     // date is 4 & time is 8. Together they make 12 - in theory a binary operator makes sense here but as it's not a common pattern it doesn't seem worth the confusion
     if(CRM_Utils_Array::value('type', $values) == 12
     || CRM_Utils_Array::value('type', $values) == 4) {
       $this->_dateFields[] = $field;
     }
   }
   $this->_fields = array_merge(array('do_not_import' => array('title' => ts('- do not import -'))), $this->_fields);
  }

  /**
   * The summary function is a magic & mystical function I have only partially made sense of but note that
   * it makes a call to setActiveFieldValues - without which import won't work - so it's more than just a presentation
   * function
   * @param array $values the array of values belonging to this line
   *
   * @return boolean      the result of this processing
   * It is called from both the preview & the import actions
   * (non-PHP doc)
   * @see CRM_Csvimport_Import_Parser_BaseClass::summary()
   */
  function summary(&$values) {
   $erroneousField = NULL;
   $response      = $this->setActiveFieldValues($values, $erroneousField);
   $errorRequired = FALSE;
   $missingField = '';
   $this->_params = &$this->getActiveFieldParams();

   foreach ($this->_requiredFields as $requiredField) {
     if(empty($this->_params[$requiredField])) {
       $errorRequired = TRUE;
       $missingField .= ' ' . $requiredField;
       CRM_Contact_Import_Parser_Contact::addToErrorMsg($this->_entity, $requiredField);
     }
   }

   if ($errorRequired) {
    array_unshift($values, ts('Missing required field(s) :') . $missingField);
    return CRM_Import_Parser::ERROR;
   }

   $errorMessage = NULL;
   //@todo add a validate fn to the apis so that we can dry run against them to check
   // pseudoconstants
   if ($errorMessage) {
     $tempMsg = "Invalid value for field(s) : $errorMessage";
     array_unshift($values, $tempMsg);
     $errorMessage = NULL;
     return CRM_Import_Parser::ERROR;
   }
   return CRM_Import_Parser::VALID;
  }

  /**
   * handle the values in import mode
   *
   * @param int $onDuplicate the code for what action to take on duplicates
   * @param array $values the array of values belonging to this line
   *
   * @return boolean      the result of this processing
   * @access public
   */
  function import($onDuplicate, &$values) {
    $response = $this->summary($values);
    $this->_params = $this->getActiveFieldParams();
    $this->formatDateParams();
    $this->_params['skipRecentView'] = TRUE;
    $this->_params['check_permissions'] = TRUE;

    try{
      civicrm_api3($this->_entity, 'create', $this->_params);
    }
    catch (CiviCRM_API3_Exception $e) {
      $error = $e->getMessage();
      array_unshift($values, $error);
      return CRM_Import_Parser::ERROR;
    }
  }

  /**
   * Format Date params
   *
   * Although the api will accept any strtotime valid string CiviCRM accepts at least one date format
   * not supported by strtotime so we should run this through a conversion
   * @internal param \unknown $params
   */
  function formatDateParams() {
    $session = CRM_Core_Session::singleton();
    $dateType = $session->get('dateTypes');
    $setDateFields = array_intersect_key($this->_params, array_flip($this->_dateFields));
    foreach ($setDateFields as $key => $value) {
      CRM_Utils_Date::convertToDefaultDate($this->_params, $dateType, $key);
      $this->_params[$key] = CRM_Utils_Date::processDate($this->_params[$key]);
    }
  }

  /**
   * Set import entity
   * @param string $entity
   */
  function setEntity($entity) {
    $this->_entity = $entity;
  }
}
