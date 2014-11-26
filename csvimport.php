<?php

require_once 'csvimport.civix.php';

/**
 * Implementation of hook_civicrm_config
 */
function csvimport_civicrm_config(&$config) {
  _csvimport_civix_civicrm_config($config);
}

/**
 * Implementation of hook_civicrm_xmlMenu
 *
 * @param $files array(string)
 */
function csvimport_civicrm_xmlMenu(&$files) {
  _csvimport_civix_civicrm_xmlMenu($files);
}

/**
 * Implementation of hook_civicrm_install
 */
function csvimport_civicrm_install() {
  return _csvimport_civix_civicrm_install();
}

/**
 * Implementation of hook_civicrm_uninstall
 */
function csvimport_civicrm_uninstall() {
  return _csvimport_civix_civicrm_uninstall();
}

/**
 * Implementation of hook_civicrm_enable
 */
function csvimport_civicrm_enable() {
  return _csvimport_civix_civicrm_enable();
}

/**
 * Implementation of hook_civicrm_disable
 */
function csvimport_civicrm_disable() {
  return _csvimport_civix_civicrm_disable();
}

/**
 * Implementation of hook_civicrm_upgrade
 *
 * @param $op string, the type of operation being performed; 'check' or 'enqueue'
 * @param $queue CRM_Queue_Queue, (for 'enqueue') the modifiable list of pending up upgrade tasks
 *
 * @return mixed  based on op. for 'check', returns array(boolean) (TRUE if upgrades are pending)
 *                for 'enqueue', returns void
 */
function csvimport_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _csvimport_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implementation of hook_civicrm_managed
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 */
function csvimport_civicrm_managed(&$entities) {
  return _csvimport_civix_civicrm_managed($entities);
}

/**
 * Implementation of hook_civicrm_navigationMenu
 *
 * Adds entries to the navigation menu
 * @param unknown $menu
 */
function csvimport_civicrm_navigationMenu(&$menu) {
  $maxID = CRM_Core_DAO::singleValueQuery("SELECT max(id) FROM civicrm_navigation");
  $administerMenuId = CRM_Core_DAO::getFieldValue('CRM_Core_BAO_Navigation', 'Administer', 'id', 'name');
  if (!$administerMenuId) {
    return;
  }
  $navId = $maxID + 1;
  $menu[$navId] = array (
    'attributes' => array (
      'label' => 'API csv Import',
      'name' => 'CSV to api bridge',
      'url' => 'civicrm/csvimporter/import',
      'permission' => 'administer CiviCRM',
      'operator' => null,
      'separator' => null,
      'parentID' => $administerMenuId,
      'active' => 1,
      'navID' => $navId,
    )
  );
}
