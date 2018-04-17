<?php

/**
 * Created by PhpStorm.
 * User: root
 * Date: 13/4/18
 * Time: 5:13 PM
 */
class CRM_Csvimport_Import_ReferenceField
{
  /**
   * field_name
   * @var string
   */
  public $id;

  /**
   * field_name label
   * @var string
   */
  public $label;

  /**
   * Entity name
   * @var string
   */
  public $entity_name;

  /**
   * field_name in entity
   * @var string
   */
  public $entity_field_name;

  function __construct($id, $label, $entity_name, $entity_field_name) {
    $this->id = $id;
    $this->label = $label;
    $this->entity_name = $entity_name;
    $this->entity_field_name = $entity_field_name;
  }

}

