<?php

trait CRM_Csvimport_Import_Form_CSVImportFormTrait {

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
   * @return \CRM_Csvimport_Import_Parser_Api
   */
  protected function getParser(): CRM_Csvimport_Import_Parser_Api {
    if (!$this->parser) {
      $this->parser = new CRM_Csvimport_Import_Parser_Api();
      $this->parser->setUserJobID($this->getUserJobID());
      $this->parser->setEntity($this->getSubmittedValue('entity'));
      $this->parser->setAllowEntityUpdate((bool) $this->getSubmittedValue('allowEntityUpdate'));
      $this->parser->setIgnoreCase((bool) $this->getSubmittedValue('ignoreCase'));
      $this->parser->init();
    }
    return $this->parser;
  }

  /**
   * Get the name of the type to be stored in civicrm_user_job.type_id.
   *
   * @return string
   */
  public function getUserJobType(): string {
    return 'csv_api_import';
  }

}
