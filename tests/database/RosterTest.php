<?php

use App\Libraries\Mailer;
use App\Models\ActivityLogModel;
use App\Models\PmcModel;
use App\Models\PropertyModel;
use App\Models\StaffChangeModel;
use App\Models\StaffModel;
use App\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\Mock\MockCache;
use CodeIgniter\Throttle\Throttler;
use Config\Services;

/**
 * The staff roster (Joe, 2026-10-29): a PMC keeps its own people and the properties they
 * cover; nobody is deleted; every change is history; one company never sees another's;
 * admins work across companies and manage accounts.
 *
 * @internal
 */
final class RosterTest extends CIUnitTestCase
{
  use DatabaseTestTrait;
  use FeatureTestTrait;
  protected $refresh = true;
  protected $migrate = true;
  protected $namespace = 'App';
  private int $gf;
  private int $koru;
  private array $jane;
  private array $kim;
  private array $admin;
  protected function setUp(): void
  {
    parent::setUp();
    $this->mockSession();
    Services::injectMock('security', new class(config('Security')) extends \CodeIgniter\Security\Security
    {
      public function verify(\CodeIgniter\HTTP\RequestInterface $request)
      {
        return $this;
      }
    });
    Services::injectMock('throttler', new Throttler(new MockCache()));
    Mailer::$outbox = [];
    $pmcs = new PmcModel();
    $this->gf = $pmcs->idFor('Goldfinch Living');
    $this->koru = $pmcs->idFor('Koru');
    $props = new PropertyModel();
    $props->insertBatch([
      ['property_number' => '12140', 'name' => 'Hearthside', 'city' => 'Appleton', 'units' => 61, 'management_co' => 'Goldfinch Living', 'pmc_id' => $this->gf, 'is_active' => 1],
      ['property_number' => '11600', 'name' => 'Manor Drive Apartments - Suring', 'city' => 'Suring', 'units' => 8, 'management_co' => 'Goldfinch Living', 'pmc_id' => $this->gf, 'is_active' => 1],
      ['property_number' => '10500', 'name' => 'Old Place', 'city' => 'Gone', 'units' => 10, 'management_co' => 'Goldfinch Living', 'pmc_id' => $this->gf, 'is_active' => 0],
      ['property_number' => '12040', 'name' => 'Parkview Gardens I', 'city' => 'Madison', 'units' => 24, 'management_co' => 'Koru', 'pmc_id' => $this->koru, 'is_active' => 1],
    ]);
    $users = new UserModel();
    $mk = function (string $email, string $name, ?int $pmc, bool $admin) use ($users): array
    {
      $id = (int)$users->insert(['email' => $email, 'name' => $name, 'pmc_id' => $pmc, 'is_admin' => $admin ? 1 : 0], true);
      return ['id' => $id, 'email' => $email, 'name' => $name, 'pmc_id' => $pmc, 'pmc_name' => $pmc === $this->gf ? 'Goldfinch Living' : ($pmc === $this->koru ? 'Koru' : ''), 'is_admin' => $admin];
    };
    $this->jane = $mk('jane@pmc.test', 'Jane Doe', $this->gf, false);
    $this->kim = $mk('kim@koru.test', 'Kim Lee', $this->koru, false);
    $this->admin = $mk('joe@whpc.com', 'Joe Carpenter', null, true);
  }
  protected function tearDown(): void
  {
    Services::reset(false);
    parent::tearDown();
  }
  private function as(array $user, array $more = []): self
  {
    return $this->withSession(['user' => $user] + $more);
  }
  /** Under `testing` CodeIgniter rethrows a 404 rather than rendering it; this is what "404" looks like here. */
  private function assert404(callable $call): void
  {
    $level = ob_get_level();
    try
    {
      $call();
      $this->fail('expected a 404');
    }
    catch (\CodeIgniter\Exceptions\PageNotFoundException $e)
    {
      while (ob_get_level() > $level)
      {
        ob_end_clean();
      }
      $this->assertTrue(true);
    }
  }
  private function sam(array $over = []): array
  {
    return $over + ['name' => 'Sam Site', 'position' => 'Site Manager', 'title' => 'Community Manager', 'email' => 'Sam@PMC.test', 'mobile_phone' => '608-555-0101', 'office_phone' => '', 'start_date' => '2026-01-15', 'end_date' => '', 'notes' => '', 'properties' => ['12140', '11600']];
  }
  private function changes(int $staffId): array
  {
    return array_reverse((new StaffChangeModel())->forStaff($staffId));
  }
  public function testAPmcUserAddsChangesAndDepartsAPerson(): void
  {
    $this->as($this->jane)->post('roster', $this->sam())->assertRedirectTo('/roster');
    $staff = new StaffModel();
    $rows = $staff->forPmc($this->gf);
    $this->assertCount(1, $rows);
    $id = (int)$rows[0]['id'];
    $this->assertSame(['sam@pmc.test', 'Site Manager', ['11600', '12140'], $this->jane['id']], [$rows[0]['email'], $rows[0]['position'], $rows[0]['properties'], (int)$rows[0]['created_by']]);
    $this->assertSame(['added'], array_column($this->changes($id), 'action'));
    $this->assertCount(1, (new ActivityLogModel())->where('action', 'roster.added')->findAll());

    $page = $this->as($this->jane)->get('roster');
    $page->assertStatus(200);
    $page->assertSee('Sam Site');
    $page->assertSee('2 properties');
    $page->assertSeeElement('#rosterSearch');
    $page->assertSeeElement('#positionFilter');
    $page->assertSeeElement('#propertyFilter');
    $this->assertStringContainsString('id="toggleDeparted" checked', (string)$page->response()->getBody(), 'departed hidden by default');
    $this->assertStringContainsString('pp-stat__label">Site Manager<', (string)$page->response()->getBody(), 'the summary has a Site Manager tile');

    $this->as($this->jane)->post('roster/' . $id, $this->sam(['name' => 'Sam Sites', 'properties' => ['12140']]))->assertRedirectTo('/roster');
    $c = $this->changes($id);
    $this->assertSame(['added', 'updated'], array_column($c, 'action'));
    $d = json_decode($c[1]['changes'], true);
    $this->assertSame(['name', 'properties'], array_keys($d));
    $this->assertSame(['from' => 'Sam Site', 'to' => 'Sam Sites'], $d['name']);
    $this->assertSame(['from' => ['#11600' => 'Site Manager', '#12140' => 'Site Manager'], 'to' => ['#12140' => 'Site Manager']], $d['properties']);
    $this->assertSame(['12140'], $staff->withProperties($id)['properties']);

    $this->as($this->jane)->post('roster/' . $id, $this->sam(['name' => 'Sam Sites', 'properties' => ['12140']]))->assertRedirectTo('/roster');
    $this->assertCount(2, $this->changes($id), 'an unchanged save leaves no history');

    $this->as($this->jane)->post('roster/' . $id, $this->sam(['name' => 'Sam Sites', 'properties' => ['12140'], 'end_date' => '2026-10-31']))->assertRedirectTo('/roster');
    $this->assertSame('departed', $this->changes($id)[2]['action']);
    $this->as($this->jane)->get('roster')->assertDontSee('Sam Sites');
    $gone = $this->as($this->jane)->get('roster?departed=1');
    $gone->assertSee('Sam Sites');
    $gone->assertSee('Departed Oct 31, 2026');
    $hist = $this->as($this->jane)->get('roster/' . $id . '/history');
    $hist->assertStatus(200);
    $hist->assertSee('Departed');
    $hist->assertSee('Hearthside');

    $this->as($this->jane)->post('roster/' . $id, $this->sam(['name' => 'Sam Sites', 'properties' => ['12140'], 'end_date' => '']))->assertRedirectTo('/roster');
    $this->assertSame('reinstated', $this->changes($id)[3]['action']);
    $this->assertCount(1, $staff->forPmc($this->gf, false), 'current again');
  }
  public function testARoleAtOnePropertyMayDifferFromThePositionAndEmailMayBeNa(): void
  {
    $this->as($this->jane)->post('roster', $this->sam(['email' => '', 'email_na' => '1', 'roles' => ['11600' => 'Service Coord Supervisor', '12140' => '']]))->assertRedirectTo('/roster');
    $staff = new StaffModel();
    $p = $staff->forPmc($this->gf)[0];
    $this->assertSame([1, '', ['11600' => 'Service Coord Supervisor', '12140' => 'Site Manager']], [(int)$p['email_na'], $p['email'], $p['roles']]);
    $list = $this->as($this->jane)->get('roster');
    $list->assertSee('No email (N/A)');
    $list->assertSee('Manor Drive Apartments - Suring · Service Coord Supervisor');
    $this->as($this->jane)->get('roster/' . $p['id'] . '/edit')->assertSee('N/A, this person has no work email');
    $this->as($this->jane)->post('roster/' . $p['id'], $this->sam(['email' => 'sam@pmc.test', 'email_na' => '1']))->assertRedirectTo('/roster');
    $this->assertSame('', $staff->find($p['id'])['email'], 'N/A wins over a typed address');
    $this->as($this->jane)->post('roster/' . $p['id'], $this->sam(['roles' => ['12140' => 'Astronaut']]))->assertRedirect();
    $this->assertSame(['roles'], array_keys(session()->getFlashdata('errors') ?? []));
  }
  public function testAServiceAgencyCoversAnyonesPropertiesAndAManagementCompanyOnlyItsOwn(): void
  {
    $lss = (new PmcModel())->idFor('LSS', PmcModel::SERVICES);
    $lee = ['id' => (int)(new UserModel())->insert(['email' => 'lee@lss.test', 'name' => 'Lee', 'pmc_id' => $lss, 'is_admin' => 0], true), 'email' => 'lee@lss.test', 'name' => 'Lee', 'pmc_id' => $lss, 'pmc_name' => 'LSS', 'is_admin' => false];
    $this->as($lee)->get('roster/new')->assertSee('Parkview Gardens I · Koru');
    $this->as($lee)->post('roster', $this->sam(['name' => 'Cory Coordinator', 'position' => 'Service Coordinator', 'properties' => ['12140', '12040']]))->assertRedirectTo('/roster');
    $this->assertSame(['12040', '12140'], (new StaffModel())->forPmc($lss)[0]['properties']);
    $this->as($lee)->post('roster', $this->sam(['name' => 'Gone Property', 'position' => 'Service Coordinator', 'properties' => ['10500']]))->assertRedirect();
    $this->assertArrayHasKey('properties', session()->getFlashdata('errors') ?? [], 'retired properties are not on offer to anyone');
    $this->as($this->kim)->post('roster', $this->sam(['properties' => ['12140']]))->assertRedirect();
    $this->assertArrayHasKey('properties', session()->getFlashdata('errors') ?? []);
  }
  public function testThePropertiesPageShowsEachRoleFilledOrOpenAndVacanciesFollowTheRoster(): void
  {
    $vac = new \App\Models\VacancyModel();
    $this->as($this->jane)->post('roster', $this->sam())->assertRedirectTo('/roster');
    $id = (int)(new StaffModel())->forPmc($this->gf)[0]['id'];
    $board = $this->as($this->jane)->get('properties');
    $board->assertStatus(200);
    $board->assertSee('Sam Site');
    $board->assertSee('Nobody on file');
    $board->assertSee('Add or mark vacant');
    $this->as($this->jane)->get('roster/new?position=Regional+Manager&property=12140&back=properties')->assertSee('value="Regional Manager" selected');
    $this->assertSame([], $vac->findAll(), 'nothing has gone open yet; a never-staffed role is just empty');

    // Jane records the regional manager role at Hearthside as open, with coverage.
    $open = $this->as($this->jane)->get('properties/open?property=12140&role=Regional+Manager');
    $open->assertSee('Mark as vacant');
    $open->assertSee('Someone does this job');
    $this->as($this->jane)->post('properties/open', ['property' => '12140', 'role' => 'Regional Manager', 'open_since' => '2026-10-01', 'coverage' => 'Rene Mosback (interim)'])->assertRedirectTo('/properties');
    $this->assertSame('Rene Mosback (interim)', $vac->openOne($this->gf, '12140', 'Regional Manager')['coverage']);
    $this->as($this->jane)->get('properties')->assertSee('Covered by Rene Mosback (interim)');
    $this->assert404(fn() => $this->as($this->kim)->post('properties/open', ['property' => '12140', 'role' => 'Regional Manager', 'open_since' => '2026-10-01']));
    $this->as($this->jane)->post('properties/open', ['property' => '12140', 'role' => 'Regional Manager', 'open_since' => 'soon'])->assertRedirect();
    $this->assertArrayHasKey('open_since', session()->getFlashdata('errors') ?? []);

    // Sam departs: both site-manager positions open as of the end date; one is already covered when Sam is back.
    $this->as($this->jane)->post('roster/' . $id, $this->sam(['end_date' => '2026-10-31']))->assertRedirectTo('/roster');
    $open = $vac->openFor($this->gf);
    $keys = array_keys($open);
    sort($keys);
    $this->assertSame(['11600|Site Manager', '12140|Regional Manager', '12140|Site Manager'], $keys);
    $this->assertSame('2026-10-31', $open['12140|Site Manager']['open_since']);
    $this->as($this->jane)->get('properties')->assertSee('OPEN since Oct 31, 2026');
    $this->as($this->jane)->post('roster', $this->sam(['name' => 'New Hire', 'properties' => ['12140']]))->assertRedirectTo('/roster');
    $keys = array_keys($vac->openFor($this->gf));
    sort($keys);
    $this->assertSame(['11600|Site Manager', '12140|Regional Manager'], $keys, 'filled at Hearthside, still open at Manor Drive');
    $this->assertNotNull($vac->find($open['12140|Site Manager']['id'])['filled_at']);
    // A regional manager arrives: the covered vacancy closes too.
    $this->as($this->jane)->post('roster', $this->sam(['name' => 'Reg Ional', 'position' => 'Regional Manager', 'properties' => ['12140']]))->assertRedirectTo('/roster');
    $this->assertSame(['11600|Site Manager'], array_keys($vac->openFor($this->gf)));

    $csv = $this->as($this->jane)->get('properties/export');
    $csv->assertStatus(200);
    $csv->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    $this->assertStringContainsString('11600,"Manor Drive Apartments - Suring",Suring,"Goldfinch Living","Site Manager",OPEN,,,,,2026-10-31,', $csv->response()->getBody());
    $this->assertStringContainsString('"New Hire"', $csv->response()->getBody());
  }
  public function testOneCompanyNeverReachesAnothersPeople(): void
  {
    $this->as($this->jane)->post('roster', $this->sam());
    $id = (int)(new StaffModel())->forPmc($this->gf)[0]['id'];
    $this->assert404(fn() => $this->as($this->kim)->get('roster/' . $id . '/edit'));
    $this->assert404(fn() => $this->as($this->kim)->post('roster/' . $id, $this->sam(['name' => 'Taken'])));
    $this->assert404(fn() => $this->as($this->kim)->get('roster/' . $id . '/history'));
    $this->as($this->kim)->get('roster')->assertDontSee('Sam Site');
    $this->assertSame('Sam Site', (new StaffModel())->find($id)['name']);

    $this->as($this->kim)->post('roster', $this->sam(['properties' => ['12140']]))->assertRedirect();
    $this->assertArrayHasKey('properties', session()->getFlashdata('errors') ?? [], "Koru cannot assign Goldfinch's Hearthside");
    $this->assertSame([], (new StaffModel())->forPmc($this->koru));
    $this->as($this->kim)->post('roster', $this->sam(['properties' => ['12040']]))->assertRedirectTo('/roster');
    $this->assertCount(1, (new StaffModel())->forPmc($this->koru));
  }
  public function testBadInputIsRefusedFieldByField(): void
  {
    $this->as($this->jane)->post('roster', ['name' => '', 'position' => 'Janitor', 'email' => 'nope', 'start_date' => '2026-05-01', 'end_date' => '2026-04-01', 'properties' => ['10500']])->assertRedirect();
    $errors = session()->getFlashdata('errors') ?? [];
    $this->assertSame(['name', 'position', 'email', 'end_date', 'properties'], array_keys($errors), 'a retired property cannot be newly assigned');
    $this->assertSame([], (new StaffModel())->findAll());
    $this->assertSame([], (new StaffChangeModel())->findAll());
  }
  public function testAdminsWorkAcrossCompaniesAndManageAccounts(): void
  {
    $this->as($this->jane)->post('roster', $this->sam());
    $this->as($this->admin)->get('roster?pmc=' . $this->gf)->assertSee('Sam Site');
    $this->as($this->admin)->get('roster?pmc=' . $this->koru)->assertDontSee('Sam Site');
    $this->as($this->admin, ['admin_pmc' => $this->koru])->post('roster', $this->sam(['name' => 'Kay Koru', 'properties' => ['12040']]))->assertRedirectTo('/roster');
    $this->assertSame(['Kay Koru'], array_column((new StaffModel())->forPmc($this->koru), 'name'));

    $this->as($this->admin)->post('admin/users', ['name' => 'New Person', 'email' => 'New@pmc.test', 'pmc_id' => $this->gf])->assertRedirectTo('/admin/users');
    $new = (new UserModel())->activeByEmail('new@pmc.test');
    $this->assertSame(['Goldfinch Living', 0], [$new['pmc_name'], (int)$new['is_admin']]);
    $this->as($this->admin)->post('admin/users', ['name' => 'Dup', 'email' => 'new@pmc.test', 'pmc_id' => $this->gf])->assertRedirect();
    $this->assertArrayHasKey('email', session()->getFlashdata('errors') ?? []);
    $this->as($this->admin)->post('admin/users', ['name' => 'No Company', 'email' => 'nc@pmc.test'])->assertRedirect();
    $this->assertArrayHasKey('pmc_id', session()->getFlashdata('errors') ?? []);
    $this->as($this->admin)->post('admin/users/' . $new['id'] . '/toggle')->assertRedirectTo('/admin/users');
    $this->assertNull((new UserModel())->activeByEmail('new@pmc.test'), 'disabled');
    $this->as($this->admin)->post('admin/users/' . $this->admin['id'] . '/toggle')->assertRedirectTo('/admin/users');
    $this->assertNotNull((new UserModel())->activeByEmail('joe@whpc.com'), 'nobody disables themself');

    $pmcs = $this->as($this->admin)->get('admin/pmcs');
    $pmcs->assertStatus(200);
    $pmcs->assertSee('Goldfinch Living');
    $this->as($this->admin)->post('admin/pmcs', ['name' => 'LSS', 'kind' => 'services'])->assertRedirectTo('/admin/pmcs');
    $this->assertSame('services', (new PmcModel())->byName('LSS')['kind']);
    $this->as($this->admin)->post('admin/pmcs', ['name' => 'lss', 'kind' => 'services'])->assertRedirect();
    $this->assertArrayHasKey('name', session()->getFlashdata('errors') ?? []);
    $this->as($this->admin)->post('admin/pmcs', ['name' => 'Odd', 'kind' => 'club'])->assertRedirect();
    $this->assertArrayHasKey('kind', session()->getFlashdata('errors') ?? []);
    $this->as($this->admin)->get('admin/pmcs')->assertSee('Service agency');
    $this->as($this->jane)->post('admin/pmcs', ['name' => 'Nope', 'kind' => 'services'])->assertStatus(403);
    $this->as($this->admin)->get('admin/users')->assertSee('New Person');
    $this->as($this->admin)->get('admin/changes')->assertSee('Kay Koru');

    // Sync from a stand-in assets table in the test database.
    $forge = \Config\Database::forge('tests');
    $forge->addField(['property_number' => ['type' => 'VARCHAR', 'constraint' => 20], 'name' => ['type' => 'VARCHAR', 'constraint' => 255], 'city' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true], 'region' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true], 'units' => ['type' => 'INT', 'null' => true], 'asset_manager' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true], 'management_co' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true], 'status' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true], 'is_entity' => ['type' => 'TINYINT', 'default' => 0]]);
    $forge->createTable('assets', true);
    db_connect('tests')->table('assets')->insertBatch([
      ['property_number' => '12140', 'name' => 'Hearthside', 'city' => 'Appleton', 'units' => 62, 'management_co' => 'Goldfinch Living', 'is_entity' => 0],
      ['property_number' => '12050', 'name' => 'Parkview Gardens II', 'city' => 'Madison', 'units' => 23, 'management_co' => 'Koru', 'is_entity' => 0],
    ]);
    // The config is cached for the test, so the controller sees this instance.
    $cfg = config(\Config\Partner::class);
    $cfg->assetsGroup = 'tests';
    \CodeIgniter\Config\Factories::injectMock('config', 'Partner', $cfg);
    $this->as($this->admin)->post('admin/pmcs/sync')->assertRedirectTo('/admin/pmcs');
    $this->assertStringStartsWith('Synced:', (string)session()->getFlashdata('success'), 'error flash: ' . session()->getFlashdata('error'));
    $props = new PropertyModel();
    $this->assertSame(62, (int)$props->find('12140')['units']);
    $this->assertSame('Koru', $props->find('12050')['management_co']);
    $this->assertSame(0, (int)$props->find('11600')['is_active'], 'not in Assets any more: retired');
    $forge->dropTable('assets', true);
  }
  public function testPartnersAreKeptOutOfAdminPagesAndVisitorsOutAltogether(): void
  {
    $this->as($this->jane)->get('admin/users')->assertStatus(403);
    $this->as($this->jane)->post('admin/pmcs/sync')->assertStatus(403);
    $this->as($this->jane)->get('admin/changes')->assertStatus(403);
    $this->withSession([])->get('roster')->assertRedirectTo('/login?expired=1');
    $this->withSession([])->post('roster', $this->sam())->assertRedirectTo('/login?expired=1');
    $home = $this->as($this->jane)->get('/');
    $home->assertSeeElement('#cardRoster');
    $home->assertSee('New here? How this works');
    $guide = $this->as($this->jane)->get('help');
    $guide->assertStatus(200);
    $guide->assertSee('Someone leaves');
    $guide->assertDontSee('For WHPC admins');
    $this->as($this->admin)->get('help')->assertSee('For WHPC admins');
    $this->withSession([])->get('help')->assertRedirectTo('/login?expired=1');
    $home->assertDontSeeElement('#cardUsers');
    $this->as($this->admin)->get('/')->assertSeeElement('#cardUsers');
  }
}
