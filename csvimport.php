<?php

require_once 'csvimport.civix.php';

use CRM_Csvimport_ExtensionUtil as E;

/**
 * Implementation of hook_civicrm_config
 */
function csvimport_civicrm_config(&$config) {
  _csvimport_civix_civicrm_config($config);
}

/**
 * Implementation of hook_civicrm_install
 */
function csvimport_civicrm_install() {
  _csvimport_civix_civicrm_install();
}

/**
 * Implementation of hook_civicrm_enable
 */
function csvimport_civicrm_enable() {
  _csvimport_civix_civicrm_enable();
}

/**
 * Implementation of hook_civicrm_navigationMenu
 *
 * Adds entries to the navigation menu
 *
 * @param array $menu
 */
function csvimport_civicrm_navigationMenu(&$menu) {
  $item[] = [
    'label' => E::ts('API csv Import'),
    'name' => 'CSV to api bridge',
    'url' => 'civicrm/csvimporter/import',
    'permission' => 'administer CiviCRM',
    'operator' => NULL,
    'separator' => NULL,
  ];
  _csvimport_civix_insert_navigation_menu($menu, 'Administer', $item[0]);
  _csvimport_civix_navigationMenu($menu);
}
