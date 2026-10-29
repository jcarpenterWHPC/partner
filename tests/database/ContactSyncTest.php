<?php

use App\Libraries\ContactSync;
use App\Libraries\PropertySync;
use App\Models\PmcModel;
use App\Models\StaffChangeModel;
use App\Models\StaffModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Partner;

/**
 * Rosters seeded from Assets' contacts. Pinned: one entry per contact under the right
 * PMC with the right position and properties; WHPC, HUD and unknown roles are left out
 * and reported; a second run changes nothing; a PMC's edits survive a sync while blanks
 * are filled and new properties added; a contact gone from Assets is departed, and
 * back again is reinstated; a dry run writes nothing.
 *
 * @internal
 */
final class ContactSyncTest extends CIUnitTestCase
{
  use DatabaseTestTrait;
  protected $refresh = true;
  protected $migrate = true;
  protected $namespace = 'App';
  protected function setUp(): void
  {
    parent::setUp();
    PropertySync::apply([
      ['property_number' => '10280', 'name' => 'Kennedy Heights', 'city' => 'Madison', 'region' => 'South', 'units' => 104, 'asset_manager' => 'A', 'management_co' => 'Meridian', 'status' => 'Active', 'is_entity' => 0],
      ['property_number' => '10290', 'name' => 'Kennedy Annex', 'city' => 'Madison', 'region' => 'South', 'units' => 20, 'asset_manager' => 'A', 'management_co' => 'Meridian', 'status' => 'Active', 'is_entity' => 0],
      ['property_number' => '12140', 'name' => 'Hearthside', 'city' => 'Appleton', 'region' => 'North', 'units' => 61, 'asset_manager' => 'B', 'management_co' => 'Goldfinch Living', 'status' => 'Active', 'is_entity' => 0],
    ]);
  }
  private function row(int $cid, string $pn, string $role, string $name, string $email = '', string $title = '', string $company = 'Meridian', string $mobile = '', string $office = ''): array
  {
    return ['contact_id' => $cid, 'property_number' => $pn, 'role' => $role, 'name' => $name, 'job_title' => $title, 'company' => $company, 'email' => $email, 'business_phone' => $office, 'mobile_phone' => $mobile];
  }
  private function rows(): array
  {
    return [
      $this->row(1, '10280', 'Site Manager', 'Ruby Lopez', 'Ruby@meridian.test', 'Community Manager', 'Meridian', '608-555-0101'),
      $this->row(1, '10290', 'Site Manager', 'Ruby Lopez', 'ruby@meridian.test', 'Community Manager'),
      $this->row(2, '10280', 'Regional Manager', 'Rene Mosback', 'rene@meridian.test'),
      $this->row(2, '10290', 'Regional Manager', 'Rene Mosback', 'rene@meridian.test'),
      $this->row(2, '12140', 'Regional Manager', 'Rene Mosback', 'rene@meridian.test'),
      $this->row(2, '12140', 'Executive', 'Rene Mosback', 'rene@meridian.test'),
      $this->row(3, '12140', 'Maintenance', 'Dawn Hastings', '', '', 'Goldfinch Living'),
      $this->row(4, '10280', 'Asset Manager', 'Dan Richardson', 'dan@whpc.com', '', 'WHPC'),
      $this->row(5, '10280', 'HUD Project Manager', 'H. Person', '', '', 'HUD'),
      $this->row(6, '10280', 'Drone Pilot', 'New Role', '', '', 'Meridian'),
      $this->row(7, '99999', 'Site Manager', 'Nowhere Person', '', '', 'Meridian'),
    ];
  }
  public function testContactsBecomeRosterEntriesUnderTheirPmc(): void
  {
    $r = ContactSync::apply($this->rows());
    $this->assertSame(['people_added' => 3, 'people_updated' => 0, 'people_departed' => 0, 'unchanged' => 0, 'skipped_roles' => ['Asset Manager' => 1, 'Drone Pilot' => 1, 'HUD Project Manager' => 1], 'no_property' => 1, 'multi_pmc' => 1], $r);
    $pmcs = new PmcModel();
    $meridian = (int)$pmcs->byName('Meridian')['id'];
    $gf = (int)$pmcs->byName('Goldfinch Living')['id'];
    $staff = new StaffModel();
    $people = $staff->forPmc($meridian);
    $this->assertSame(['Rene Mosback', 'Ruby Lopez'], array_column($people, 'name'));
    [$rene, $ruby] = $people;
    $this->assertSame(['Site Manager', 'Community Manager', 'ruby@meridian.test', '608-555-0101', ['10280', '10290'], 1], [$ruby['position'], $ruby['title'], $ruby['email'], $ruby['mobile_phone'], $ruby['properties'], (int)$ruby['contact_id']]);
    $this->assertSame(['Regional Manager', ['10280', '10290']], [$rene['position'], $rene['properties']], 'the commonest role wins, and only the home PMC\'s properties are kept');
    $dawn = $staff->forPmc($gf)[0];
    $this->assertSame(['Maintenance Technician', '', 3], [$dawn['position'], $dawn['email'], (int)$dawn['contact_id']]);
    $changes = (new StaffChangeModel())->recent(null, 50);
    $this->assertCount(3, $changes);
    $this->assertSame(['imported'], array_values(array_unique(array_column($changes, 'action'))));
    $this->assertNull($changes[0]['user_id']);
    $this->assertSame(['10280', '10290'], json_decode($changes[array_search('Ruby Lopez', array_column($changes, 'staff_name'), true)]['changes'], true)['properties']);

    $again = ContactSync::apply($this->rows());
    $this->assertSame([0, 0, 0, 3], [$again['people_added'], $again['people_updated'], $again['people_departed'], $again['unchanged']]);
    $this->assertCount(3, (new StaffChangeModel())->findAll(), 'a second run leaves no trace');
  }
  public function testAPmcsEditsSurviveWhileBlanksAreFilledAndNewPropertiesAdded(): void
  {
    ContactSync::apply($this->rows());
    $staff = new StaffModel();
    $dawn = $staff->where('contact_id', 3)->first();
    $ruby = $staff->where('contact_id', 1)->first();
    // The PMC renames Dawn, gives Ruby a different title, and takes Ruby off the annex.
    $staff->update((int)$dawn['id'], ['name' => 'Dawn H.', 'email' => 'dawn@gf.test']);
    $staff->update((int)$ruby['id'], ['title' => 'Property Manager']);
    $staff->setProperties((int)$ruby['id'], ['10280']);

    $rows = $this->rows();
    $rows[6]['email'] = 'dawn.hastings@goldfinch.test';
    $rows[6]['mobile_phone'] = '920-555-0199';
    $rows[0]['job_title'] = 'Senior Community Manager';
    $rows[] = $this->row(1, '12140', 'Site Manager', 'Ruby Lopez', 'ruby@meridian.test');
    $r = ContactSync::apply($rows);
    $this->assertSame([0, 1, 0, 2], [$r['people_added'], $r['people_updated'], $r['people_departed'], $r['unchanged']]);
    $dawn = $staff->withProperties((int)$dawn['id']);
    $this->assertSame(['Dawn H.', 'dawn@gf.test', '920-555-0199'], [$dawn['name'], $dawn['email'], $dawn['mobile_phone']], 'edits kept, the blank phone filled');
    $ruby = $staff->withProperties((int)$ruby['id']);
    $this->assertSame(['Property Manager', ['10280']], [$ruby['title'], $ruby['properties']], 'the annex the PMC removed is not put back, and 12140 is another PMC\'s');
    $synced = (new StaffChangeModel())->where('action', 'synced')->findAll();
    $this->assertCount(1, $synced);
    $this->assertSame(['mobile_phone'], array_keys(json_decode($synced[0]['changes'], true)));

    // Rene gains a Meridian property in Assets: it is added.
    $rows[] = $this->row(2, '10295', 'Regional Manager', 'Rene Mosback', 'rene@meridian.test');
    PropertySync::apply([['property_number' => '10295', 'name' => 'New Place', 'city' => 'Madison', 'region' => 'South', 'units' => 5, 'asset_manager' => 'A', 'management_co' => 'Meridian', 'status' => 'Active', 'is_entity' => 0]]);
    $r = ContactSync::apply($rows);
    $this->assertSame(1, $r['people_updated']);
    $this->assertSame(['10280', '10290', '10295'], $staff->withProperties((int)$staff->where('contact_id', 2)->first()['id'])['properties']);
  }
  public function testAContactGoneFromAssetsIsDepartedAndBackAgainReinstated(): void
  {
    ContactSync::apply($this->rows());
    $rows = array_values(array_filter($this->rows(), static fn(array $r): bool => $r['contact_id'] !== 3));
    $r = ContactSync::apply($rows);
    $this->assertSame([0, 0, 1, 2], [$r['people_added'], $r['people_updated'], $r['people_departed'], $r['unchanged']]);
    $staff = new StaffModel();
    $dawn = $staff->where('contact_id', 3)->first();
    $this->assertSame(date('Y-m-d'), $dawn['end_date']);
    $this->assertSame('departed', (new StaffChangeModel())->forStaff((int)$dawn['id'])[0]['action']);
    $r = ContactSync::apply($this->rows());
    $this->assertSame(1, $r['people_updated']);
    $this->assertNull($staff->find((int)$dawn['id'])['end_date']);
    $this->assertSame('reinstated', (new StaffChangeModel())->forStaff((int)$dawn['id'])[0]['action']);
  }
  public function testADryRunCountsAndWritesNothing(): void
  {
    $r = ContactSync::apply($this->rows(), true);
    $this->assertSame(3, $r['people_added']);
    $this->assertSame(0, (new StaffModel())->countAllResults());
    $this->assertSame(0, (new StaffChangeModel())->countAllResults());
    $this->assertStringContainsString('3 added, 0 updated, 0 departed, 0 unchanged · roles not imported: Asset Manager 1, Drone Pilot 1, HUD Project Manager 1', ContactSync::describe($r, true));
  }
  public function testEveryMappedRoleIsAKnownPosition(): void
  {
    $cfg = new Partner();
    $this->assertSame([], array_diff(array_values($cfg->contactRoles), $cfg->positionNames()));
  }
}
