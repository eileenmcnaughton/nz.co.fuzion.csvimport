<?php

class CRM_Csvimport_Import_Controller extends CRM_Core_Controller {

  /**
   * class constructor
   */
  function __construct($title = NULL, $action = CRM_Core_Action::NONE, $modal = TRUE) {
    parent::__construct($title, $modal);

    // lets get around the time limit issue if possible, CRM-2113
    if (!ini_get('safe_mode')) {
      set_time_limit(0);
    }

    $this->_stateMachine = new CRM_Import_StateMachine($this, $action, NULL, 'CRM_Csvimport_Import');

    // create and instantiate the pages
    $this->addPages($this->_stateMachine, $action);

    // add all the actions
    $config = CRM_Core_Config::singleton();
    $this->addActions($config->uploadDir, ['uploadFile']);
  }

}
