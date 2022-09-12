<?php

use Civi\Api4\Contact;
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\CiviEnvBuilder;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../CRM/ImportFormTestTrait.php';
/**
 * FIXME - Add test description.
 *
 * Tips:
 *  - With HookInterface, you may implement CiviCRM hooks directly in the test class.
 *    Simply create corresponding functions (e.g. "hook_civicrm_post(...)" or similar).
 *  - With TransactionalInterface, any data changes made by setUp() or test****() functions will
 *    rollback automatically -- as long as you don't manipulate schema or truncate tables.
 *    If this test needs to manipulate schema or truncate tables, then either:
 *       a. Do all that using setupHeadless() and Civi\Test.
 *       b. Disable TransactionalInterface, and handle all setup/teardown yourself.
 *
 * @group headless
 */
class CRM_ImportTest extends TestCase implements HeadlessInterface, HookInterface {

  use CRM_ImportFormTestTrait;

  public function tearDown(): void {
    foreach (['civicrm_queue', 'civicrm_user_job', 'civicrm_queue_item'] as $tableName) {
      CRM_Core_DAO::executeQuery('DELETE FROM ' . $tableName);
    }
    CRM_Core_DAO::executeQuery('DELETE FROM civicrm_contact WHERE id > 2');
    parent::tearDown();
  }

  /**
   * Setup used when HeadlessInterface is implemented.
   *
   * Civi\Test has many helpers, like install(), uninstall(), sql(), and sqlFile().
   *
   * @link https://github.com/civicrm/org.civicrm.testapalooza/blob/master/civi-test.md
   *
   * @return \Civi\Test\CiviEnvBuilder
   *
   * @throws \CRM_Extension_Exception_ParseException
   */
  public function setUpHeadless(): CiviEnvBuilder {
    return \Civi\Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

  /**
   * Test the import runs....
   *
   * @throws \CRM_Core_Exception
   */
  public function testImportPledge(): void {
    $row = $this->doPledgeImport();
    // It fails as the external identifier does not exist.
    $this->assertEquals('ERROR', $row['_status']);

    Contact::create()->setValues([
      'first_name' => 'Bob',
      'contact_type' => 'Individual',
      'external_identifier' => 'ext-9',
    ])->execute();
    $row = $this->doPledgeImport();
    // That's better.
    $this->assertEquals('IMPORTED', $row['_status']);
  }

  /**
   * @return array|null
   * @throws \API_Exception
   * @throws \CRM_Core_Exception
   */
  protected function doPledgeImport(): ?array {
    $this->importCSV('pledges.csv', [
      'entity' => 'Pledge',
      'mapper' => $this->getMapperFromFieldMappings([
        ['name' => 'contact_id#external_identifier'],
        ['name' => 'financial_type_id'],
        ['name' => 'start_date'],
        ['name' => 'create_date'],
        ['name' => 'amount'],
        ['name' => 'installments'],
        ['name' => 'original_installment_amount'],
      ])
    ]);
    $dataSource = new CRM_Import_DataSource_CSV($this->userJobID);
    return $dataSource->getRow();
  }

}
