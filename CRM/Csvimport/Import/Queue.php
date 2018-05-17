<?php

class CRM_Csvimport_Import_Queue {

  const QUEUE_NAME = 'csvimport.queue';

  const QUEUE_TYPE = 'Sql';

  private $queue;

  static $singleton;

  /**
   * @return CRM_Csvimport_Import_Queue
   */
  public static function singleton() {
    if (!self::$singleton) {
      self::$singleton = new CRM_Csvimport_Import_Queue();
    }
    return self::$singleton;
  }

  private function __construct() {
    $this->queue = CRM_Queue_Service::singleton()->create(array(
      'type' => self::QUEUE_TYPE,
      'name' => self::QUEUE_NAME,
      'reset' => false,
    ));
  }

  /**
   * @return CRM_Csvimport_Import_Queue
   */
  public function getQueue() {
    return $this->queue;
  }
}

