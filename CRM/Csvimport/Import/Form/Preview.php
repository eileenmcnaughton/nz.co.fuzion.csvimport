<?php

class CRM_Csvimport_Import_Form_Preview extends CRM_Import_Form_Preview  {

  use CRM_Csvimport_Import_Form_CSVImportFormTrait;

  /**
   * @return \CRM_Csvimport_Import_Parser_Api
   */
  protected function getParser(): CRM_Csvimport_Import_Parser_Api {
    if (!$this->parser) {
      $this->parser = new CRM_Csvimport_Import_Parser_Api();
      $this->parser->setUserJobID($this->getUserJobID());
      $this->parser->setEntity($this->getSubmittedValue('entity'));
      $this->parser->setRefFields($this->controller->get('refFields'));
      $this->parser->setAllowEntityUpdate($this->getSubmittedValue('allowEntityUpdate'));
      $this->parser->setIgnoreCase($this->getSubmittedValue('ignoreCase'));
      $this->parser->init();
    }
    return $this->parser;
  }

}
