<?php

trait CRM_ImportFormTestTrait {

  /**
   * ID of the user job.
   *
   * @var int
   */
  protected $userJobID;

  /**
   * Import the csv file values.
   *
   * This function uses a flow that mimics the UI flow.
   *
   * @param string $csv Name of csv file.
   * @param array $fieldMappings
   * @param array $submittedValues
   */
  protected function importCSV(string $csv, array $submittedValues = []): void {
    $submittedValues = array_merge([
      'skipColumnHeader' => TRUE,
      'fieldSeparator' => ',',
      'contactType' => 'Individual',
      'dataSource' => 'CRM_Import_DataSource_CSV',
      'file' => ['name' => $csv],
      'dateFormats' => CRM_Utils_Date::DATE_yyyy_mm_dd,
      'onDuplicate' => CRM_Import_Parser::DUPLICATE_SKIP,
      'groups' => [],
    ], $submittedValues);
    $this->submitDataSourceForm($csv, $submittedValues);

    $form = $this->getMapFieldForm($submittedValues);
    $form->setUserJobID($this->userJobID);
    $form->buildForm();
    $this->assertTrue($form->validate());
    $form->postProcess();
    $form = $this->getPreviewForm($submittedValues);
    $form->setUserJobID($this->userJobID);
    $form->buildForm();
    $this->assertTrue($form->validate());
    try {
      $form->postProcess();
      $this->fail('Expected a redirect');
    }
    catch (CRM_Core_Exception_PrematureExitException $e) {
      $queue = Civi::queue('user_job_' . $this->userJobID);
      $this->assertEquals(1, CRM_Core_DAO::singleValueQuery('SELECT COUNT(*) FROM civicrm_queue_item'));
      $runner = new CRM_Queue_Runner([
        'queue' => $queue,
        'errorMode' => CRM_Queue_Runner::ERROR_ABORT,
      ]);
      $result = $runner->runAll();
      $this->assertEquals(TRUE, $result, $result === TRUE ? '' : CRM_Core_Error::formatTextException($result['exception']));
    }
  }


  /**
   * Get the import's mapField form.
   *
   * Defaults to contribution - other classes should override.
   *
   * @param array $submittedValues
   *
   * @return \CRM_Csvimport_Import_Form_MapField
   * @noinspection PhpUnnecessaryLocalVariableInspection
   */
  protected function getMapFieldForm(array $submittedValues): CRM_Csvimport_Import_Form_MapField {
    /* @var \CRM_Csvimport_Import_Form_MapField $form */
    $form = $this->getFormObject('CRM_CsvImport_Import_Form_MapField', $submittedValues);
    return $form;
  }


  /**
   * Get the import's preview form.
   *
   * Defaults to contribution - other classes should override.
   *
   * @param array $submittedValues
   *
   * @return \CRM_CsvImport_Import_Form_Preview
   * @noinspection PhpUnnecessaryLocalVariableInspection
   */
  protected function getPreviewForm(array $submittedValues): CRM_CsvImport_Import_Form_Preview {
    /* @var CRM_CsvImport_Import_Form_Preview $form */
    $form = $this->getFormObject('CRM_CsvImport_Import_Form_Preview', $submittedValues);
    return $form;
  }

  /**
   * Get the import's datasource form.
   *
   * Defaults to contribution - other classes should override.
   *
   * @param array $submittedValues
   *
   * @return \CRM_Csvimport_Import_Form_DataSource
   * @noinspection PhpUnnecessaryLocalVariableInspection
   */
  protected function getDataSourceForm(array $submittedValues): CRM_Csvimport_Import_Form_DataSource {
    /* @var CRM_CsvImport_Import_Form_DataSource $form */
    $form = $this->getFormObject('CRM_Csvimport_Import_Form_DataSource', $submittedValues);
    return $form;
  }

  /**
   * Instantiate form object.
   *
   * We need to instantiate the form to run preprocess, which means we have to trick it about the request method.
   *
   * @param string $class
   *   Name of form class.
   *
   * @param array $formValues
   *
   * @return \CRM_Core_Form
   */
  public function getFormObject(string $class, array $formValues = []): CRM_Core_Form {
    $_POST = $formValues;
    /* @var CRM_Core_Form $form */
    $form = new $class();
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $form->controller = new CRM_Csvimport_Import_Controller();
    $form->controller->setStateMachine(new CRM_Core_StateMachine($form->controller));
    // The submitted values should be set on one or the other of the forms in the flow.
    // For test simplicity we set on all rather than figuring out which ones go where....
    $_SESSION['_' . $form->controller->_name . '_container']['values']['DataSource'] = $formValues;
    $_SESSION['_' . $form->controller->_name . '_container']['values']['MapField'] = $formValues;
    $_SESSION['_' . $form->controller->_name . '_container']['values']['Preview'] = $formValues;
    return $form;
  }

  /**
   * @return \CRM_Import_DataSource
   */
  protected function getDataSource(): CRM_Import_DataSource {
    return new CRM_Import_DataSource_CSV($this->userJobID);
  }

  /**
   * Submit the data source form.
   *
   * @param string $csv
   * @param array $submittedValues
   */
  protected function submitDataSourceForm(string $csv, array $submittedValues = []): void {
    $reflector = new ReflectionClass(get_class($this));
    $directory = dirname($reflector->getFileName());
    $submittedValues = array_merge([
      'uploadFile' => ['name' => $directory . '/data/' . $csv],
      'skipColumnHeader' => TRUE,
      'fieldSeparator' => ',',
      'contactType' => 'Individual',
      'dataSource' => 'CRM_Import_DataSource_CSV',
      'file' => ['name' => $csv],
      'dateFormats' => CRM_Utils_Date::DATE_yyyy_mm_dd,
      'onDuplicate' => CRM_Import_Parser::DUPLICATE_SKIP,
      'groups' => [],
    ], $submittedValues);
    $form = $this->getDataSourceForm($submittedValues);
    $values = $_SESSION['_' . $form->controller->_name . '_container']['values'];
    $form->buildForm();
    $form->postProcess();
    $this->userJobID = $form->getUserJobID();
    // This gets reset in DataSource so re-do....
    $_SESSION['_' . $form->controller->_name . '_container']['values'] = $values;
  }

  /**
   * @param array $mappings
   *
   * @return array
   */
  protected function getMapperFromFieldMappings(array $mappings): array {
    $mapper = [];
    foreach ($mappings as $mapping) {
      $mapper[] = [$mapping['name']];
    }
    return $mapper;
  }

}
