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

}
