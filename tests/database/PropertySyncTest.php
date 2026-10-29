<?php

use App\Libraries\PropertySync;
use App\Models\PmcModel;
use App\Models\PropertyModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * The property list follows Assets: a management company becomes a PMC, an entity is
 * not a property, and a property that disappears is retired rather than deleted.
 *
 * @internal
 */
final class PropertySyncTest extends CIUnitTestCase
{
  use DatabaseTestTrait;
  protected $refresh = true;
  protected $migrate = true;
  protected $namespace = 'App';
  private function rows(): array
  {
    return [
      ['property_number' => '10280', 'name' => 'Kennedy Heights', 'city' => 'Madison', 'region' => 'South', 'units' => 104, 'asset_manager' => 'A. Manager', 'management_co' => 'Meridian', 'status' => 'Active', 'is_entity' => 0],
      ['property_number' => '12140', 'name' => 'Hearthside', 'city' => 'Appleton', 'region' => 'North', 'units' => 61, 'asset_manager' => 'B. Manager', 'management_co' => 'Goldfinch Living', 'status' => 'Active', 'is_entity' => 0],
      ['property_number' => '11600', 'name' => 'Manor Drive Apartments - Suring', 'city' => 'Suring', 'region' => 'North', 'units' => 8, 'asset_manager' => null, 'management_co' => 'Goldfinch Living', 'status' => 'Active', 'is_entity' => 0],
      ['property_number' => 'MM10900', 'name' => 'WHPC-Hampton Regency MM', 'city' => null, 'region' => null, 'units' => null, 'asset_manager' => null, 'management_co' => null, 'status' => null, 'is_entity' => 1],
      ['property_number' => '19999', 'name' => 'No company', 'city' => null, 'region' => null, 'units' => 10, 'asset_manager' => null, 'management_co' => '', 'status' => 'Active', 'is_entity' => 0],
    ];
  }
  public function testManagementCompaniesBecomePmcsAndEntitiesAreSkipped(): void
  {
    $r = PropertySync::apply($this->rows());
    $this->assertSame(['pmcs_added' => 2, 'properties_added' => 3, 'properties_updated' => 0, 'properties_retired' => 0, 'skipped' => 2], $r);
    $this->assertSame(['Goldfinch Living', 'Meridian'], array_column((new PmcModel())->active(), 'name'));
    $gf = (new PmcModel())->byName('Goldfinch Living');
    $this->assertSame(['Hearthside', 'Manor Drive Apartments - Suring'], array_column((new PropertyModel())->forPmc((int)$gf['id']), 'name'));
    $kh = (new PropertyModel())->find('10280');
    $this->assertSame([104, 'Meridian', 'Madison'], [(int)$kh['units'], $kh['management_co'], $kh['city']]);
    $this->assertNotNull($kh['synced_at']);
  }
  public function testASecondSyncUpdatesMovesAndRetires(): void
  {
    PropertySync::apply($this->rows());
    $rows = $this->rows();
    $rows[0]['units'] = 105;                      // Kennedy Heights changed
    $rows[1]['management_co'] = 'Oakbrook';       // Hearthside changed hands
    unset($rows[2]);                              // Manor Drive gone
    $r = PropertySync::apply(array_values($rows));
    $this->assertSame(['pmcs_added' => 1, 'properties_added' => 0, 'properties_updated' => 2, 'properties_retired' => 1, 'skipped' => 2], $r);
    $props = new PropertyModel();
    $this->assertSame(105, (int)$props->find('10280')['units']);
    $this->assertSame('Oakbrook', $props->find('12140')['management_co']);
    $this->assertSame((int)(new PmcModel())->byName('Oakbrook')['id'], (int)$props->find('12140')['pmc_id']);
    $md = $props->find('11600');
    $this->assertSame(0, (int)$md['is_active'], 'retired, not deleted');
    $this->assertSame(['Hearthside'], array_column($props->forPmc((int)$props->find('12140')['pmc_id']), 'name'));
    // A dry run reports the same and writes nothing.
    $rows[0]['units'] = 1;
    $this->assertSame(1, PropertySync::apply(array_values($rows), true)['properties_updated']);
    $this->assertSame(105, (int)$props->find('10280')['units']);
  }
  public function testTheCommandReadsAssetsThroughADatabaseGroup(): void
  {
    $db = db_connect('tests');
    $forge = \Config\Database::forge('tests');
    $forge->addField(['property_number' => ['type' => 'VARCHAR', 'constraint' => 20], 'name' => ['type' => 'VARCHAR', 'constraint' => 255], 'city' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true], 'region' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true], 'units' => ['type' => 'INT', 'null' => true], 'asset_manager' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true], 'management_co' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true], 'status' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true], 'is_entity' => ['type' => 'TINYINT', 'default' => 0]]);
    $forge->createTable('assets', true);
    $db->table('assets')->insertBatch($this->rows());
    // The helper does not hand back CLI output here; the database says whether it ran.
    command('partner:sync-properties --group tests');
    $this->assertCount(3, (new PropertyModel())->findAll());
    $this->assertCount(2, (new PmcModel())->findAll());
    $forge->dropTable('assets', true);
  }
}
