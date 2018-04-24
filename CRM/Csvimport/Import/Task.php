<?php

class CRM_Csvimport_Import_Task {

  /**
   * Callback function for entity import task
   *
   * @param CRM_Queue_TaskContext $ctx
   * @param $entity
   * @param $params
   * @return bool
   */
  public static function ImportEntity(CRM_Queue_TaskContext $ctx, $entity, $params) {

    if( !$entity || !isset($params)) {
      CRM_Core_Session::setStatus('Invalid params supplied to import queue!', 'Queue task', 'error');
      return false;
    }

    // check for api chaining in params and run them separately
    foreach ($params as $k => $param) {
      if(is_array($param) && count($param) == 1) {
        reset($param);
        $key = key($param);
        if (strpos($key, 'api.') === 0 && strpos($key, '.get') === (strlen($key) - 4)) {
          $refEntity = substr($key, 4, strlen($key) - 8);
          try{
            $data = civicrm_api3($refEntity, 'get', $param[$key]);
          }
          catch (CiviCRM_API3_Exception $e) {
            $error = $e->getMessage();
            array_unshift($values, $error);
            CRM_Core_Session::setStatus('Error with referenced entity "get"! (' . $error . ')', 'Queue task', 'error');
            return false;
          }
          $params[$k] = $data['values'][0]['id'];
        }
      }
    }

    try{
      civicrm_api3($entity, 'create', $params);
    }
    catch (CiviCRM_API3_Exception $e) {
      $error = $e->getMessage();
      array_unshift($values, $error);
      CRM_Core_Session::setStatus('Error with entity "create"! (' . $error . ')', 'Queue task', 'error');
      return false;
    }

    return true;
  }
}