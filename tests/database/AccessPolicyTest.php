<?php

use App\Libraries\Mailer;
use App\Libraries\MicrosoftSignIn;
use App\Models\ActivityLogModel;
use App\Models\UserModel;
use CodeIgniter\Config\Factories;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\Mock\MockCache;
use CodeIgniter\Throttle\Throttler;
use Config\Partner;
use Config\Services;

/**
 * Who gets in. Pinned: a configured WHPC admin signs in with no account made in advance
 * and is an admin; any other WHPC address is refused like a stranger, even with a row in
 * the table; once Microsoft sign-in is configured a WHPC address is sent to Microsoft and
 * comes back signed in, from WHPC's tenant only; the admin screen and the toggle never
 * make or touch an admin.
 *
 * @internal
 */
final class AccessPolicyTest extends CIUnitTestCase
{
  use DatabaseTestTrait;
  use FeatureTestTrait;
  protected $refresh = true;
  protected $migrate = true;
  protected $namespace = 'App';
  private const TID = '11111111-2222-3333-4444-555555555555';
  private Partner $cfg;
  private array $tokenResponse = [400, '{"error":"invalid_grant"}'];
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
    Services::injectMock('cache', new MockCache());
    Mailer::$outbox = [];
    $this->cfg = config(Partner::class);
    $this->cfg->admins = 'Joe Carpenter <joe@whpc.com>, brian@whpc.com';
    $this->cfg->microsoftTenant = '';
    $this->cfg->microsoftClientId = '';
    $this->cfg->microsoftClientSecret = '';
    (new UserModel())->insert(['email' => 'intruder@whpc.com', 'name' => 'Planted Row', 'pmc_id' => null, 'is_admin' => 1, 'is_active' => 1]);
  }
  protected function tearDown(): void
  {
    Services::reset(false);
    Factories::reset('config');
    parent::tearDown();
  }
  private function actions(string $action): array
  {
    return (new ActivityLogModel())->where('action', $action)->findAll();
  }
  private function enableMicrosoft(): void
  {
    $this->cfg->microsoftTenant = self::TID;
    $this->cfg->microsoftClientId = 'client-abc';
    $this->cfg->microsoftClientSecret = 'shh';
    Services::injectMock('microsoftSignIn', new MicrosoftSignIn($this->cfg, fn(): array => $this->tokenResponse));
  }
  /** A completed Microsoft round trip for $email from $tid, as the callback sees it. */
  private function comeBack(string $email, string $tid = self::TID, string $name = 'Joe Carpenter', ?array $pending = null, string $queryState = 'st')
  {
    $this->enableMicrosoft();
    $pending ??= ['state' => 'st', 'nonce' => 'n', 'verifier' => 'v', 'at' => time()];
    $claims = ['iss' => MicrosoftSignIn::HOST . $tid . '/v2.0', 'aud' => 'client-abc', 'exp' => time() + 600, 'tid' => $tid, 'nonce' => 'n', 'oid' => 'o', 'preferred_username' => $email, 'name' => $name];
    $jwt = MicrosoftSignIn::b64url(json_encode(['alg' => 'RS256', 'kid' => 'k1'])) . '.' . MicrosoftSignIn::b64url(json_encode($claims)) . '.' . MicrosoftSignIn::b64url('sig');
    $this->tokenResponse = [200, json_encode(['id_token' => $jwt])];
    // The signature is this suite's business elsewhere; here Microsoft's answer is taken as given.
    Services::injectMock('microsoftSignIn', new class($this->cfg, $claims, fn(): array => $this->tokenResponse) extends MicrosoftSignIn
    {
      public function __construct(Partner $cfg, private array $claims, callable $http)
      {
        parent::__construct($cfg, $http);
      }
      public function verifyIdToken(string $jwt, string $nonce, ?int $now = null): array
      {
        if ($this->claims['nonce'] !== $nonce)
        {
          throw new RuntimeException('nonce');
        }
        return $this->claims;
      }
    });
    return $this->withSession(['ms_login' => $pending])->get('login/microsoft/callback?code=c0de&state=' . $queryState);
  }
  public function testAConfiguredAdminIsEmailedACodeAndArrivesAsAnAdmin(): void
  {
    $this->assertNull((new UserModel())->where('email', 'joe@whpc.com')->first());
    $this->post('login', ['email' => 'Joe@WHPC.com'])->assertRedirectTo('/login/verify');
    $this->assertCount(1, Mailer::$outbox);
    $this->assertSame('joe@whpc.com', Mailer::$outbox[0]['to']);
    $row = (new UserModel())->where('email', 'joe@whpc.com')->first();
    $this->assertSame(['Joe Carpenter', 1, 1, null], [$row['name'], (int)$row['is_admin'], (int)$row['is_active'], $row['pmc_id']]);
    preg_match('~/login/link/([0-9a-f]{64})~', Mailer::$outbox[0]['text'], $t);
    $this->get('login/link/' . $t[1])->assertRedirectTo('/');
    $this->assertTrue(session('user')['is_admin']);
    $this->assertSame('', session('user')['pmc_name']);
  }
  public function testADisabledOrDemotedAdminRowIsPutBackByTheList(): void
  {
    $users = new UserModel();
    $id = (int)$users->insert(['email' => 'brian@whpc.com', 'name' => '', 'pmc_id' => 1, 'is_admin' => 0, 'is_active' => 0], true);
    $this->post('login', ['email' => 'brian@whpc.com'])->assertRedirectTo('/login/verify');
    $this->assertCount(1, Mailer::$outbox);
    $row = $users->find($id);
    $this->assertSame([1, 1, null, ''], [(int)$row['is_admin'], (int)$row['is_active'], $row['pmc_id'], $row['name']]);
  }
  public function testAnyOtherWhpcAddressIsAStrangerEvenWithARow(): void
  {
    $this->post('login', ['email' => 'intruder@whpc.com'])->assertRedirectTo('/login/verify');
    $this->post('login', ['email' => 'nobody@whpc.com'])->assertRedirectTo('/login/verify');
    $this->assertSame([], Mailer::$outbox);
    $this->assertNull(session('login_pending')['user_id']);
    $this->assertCount(2, $this->actions('login.unknown_email'));
    // Nor does a code or link issued to the planted row get in.
    $planted = (new UserModel())->where('email', 'intruder@whpc.com')->first();
    (new \App\Libraries\LoginCode())->issue(['id' => (int)$planted['id'], 'email' => 'intruder@whpc.com', 'name' => 'x']);
    preg_match('~/login/link/([0-9a-f]{64})~', Mailer::$outbox[0]['text'], $t);
    $this->get('login/link/' . $t[1])->assertRedirectTo('/login');
    $this->assertFalse(session()->has('user'));
    $this->assertCount(1, $this->actions('login.failed'));
  }
  public function testWithMicrosoftConfiguredAWhpcAddressIsSentThereAndThePageOffersIt(): void
  {
    $this->get('login')->assertDontSeeElement('#microsoftSignIn');
    try
    {
      $this->get('login/microsoft');
      $this->fail('the Microsoft route answered before it was configured');
    }
    catch (\CodeIgniter\Exceptions\PageNotFoundException)
    {
    }
    $this->enableMicrosoft();
    $this->get('login')->assertSeeElement('#microsoftSignIn');
    $res = $this->post('login', ['email' => 'joe@whpc.com']);
    $res->assertRedirect();
    $this->assertStringStartsWith(MicrosoftSignIn::HOST . 'organizations/oauth2/v2.0/authorize?', $res->getRedirectUrl());
    $this->assertStringContainsString('login_hint=joe%40whpc.com', $res->getRedirectUrl());
    $this->assertSame([], Mailer::$outbox, 'no code is emailed to WHPC people');
    $this->assertNull(session('login_pending'));
    $this->assertSame(64, strlen(session('ms_login')['state'] . session('ms_login')['nonce']));
    // A stranger at WHPC is sent to Microsoft too: the answer comes from there, not here.
    $this->post('login', ['email' => 'nobody@whpc.com'])->assertRedirect();
    $this->assertCount(0, $this->actions('login.unknown_email'));
    // PMC people still get a code.
    $gf = (new \App\Models\PmcModel())->idFor('Goldfinch Living');
    (new UserModel())->insert(['email' => 'jane@pmc.test', 'name' => 'Jane', 'pmc_id' => $gf]);
    $this->post('login', ['email' => 'jane@pmc.test'])->assertRedirectTo('/login/verify');
    $this->assertCount(1, Mailer::$outbox);
    $this->get('login/microsoft')->assertRedirect();
  }
  public function testTheMicrosoftCallbackSignsInWhpcsTenantOnly(): void
  {
    $this->comeBack('Joe@whpc.com')->assertRedirectTo('/');
    $u = session('user');
    $this->assertSame(['joe@whpc.com', 'Joe Carpenter', true], [$u['email'], $u['name'], $u['is_admin']]);
    $this->assertSame('microsoft', $this->actions('login.ok')[0]['detail']);
    $this->assertFalse(session()->has('ms_login'));

    $this->comeBack('brian@whpc.com', name: 'Brian From Entra')->assertRedirectTo('/');
    $this->assertSame('Brian From Entra', session('user')['name'], 'a bare address in the list takes its name from Microsoft');

    foreach ([['intruder@whpc.com', self::TID], ['nobody@whpc.com', self::TID], ['joe@whpc.com', '99999999-2222-3333-4444-555555555555'], ['joe@pmc.test', self::TID]] as [$email, $tid])
    {
      $res = $this->comeBack($email, $tid);
      $res->assertRedirectTo('/login');
      $this->assertFalse(session()->has('user'), "{$email} from {$tid} got in");
    }
    $this->assertCount(4, $this->actions('login.unknown_email'));

    $this->comeBack('joe@whpc.com', pending: ['state' => 'other', 'nonce' => 'n', 'verifier' => 'v', 'at' => time()])->assertRedirectTo('/login');
    $this->comeBack('joe@whpc.com', pending: ['state' => 'st', 'nonce' => 'n', 'verifier' => 'v', 'at' => time() - 3600])->assertRedirectTo('/login');
    $this->assertCount(1, $this->actions('login.failed'), 'a stale start is not a provider failure; a bad state is');
    $this->get('login/microsoft/callback?code=x&state=st')->assertRedirectTo('/login');
  }
  public function testTheAdminScreenMakesPmcAccountsOnlyAndLeavesAdminsAlone(): void
  {
    $admin = ['id' => 999, 'email' => 'joe@whpc.com', 'name' => 'Joe Carpenter', 'pmc_id' => null, 'pmc_name' => '', 'is_admin' => true];
    $gf = (new \App\Models\PmcModel())->idFor('Goldfinch Living');
    $users = new UserModel();
    $this->withSession(['user' => $admin])->post('admin/users', ['email' => 'new@pmc.test', 'name' => 'New Person', 'pmc_id' => $gf, 'is_admin' => 1])->assertRedirectTo('/admin/users');
    $this->assertSame(0, (int)$users->where('email', 'new@pmc.test')->first()['is_admin'], 'the old checkbox is ignored');
    $this->withSession(['user' => $admin])->post('admin/users', ['email' => 'staffer@whpc.com', 'name' => 'A Staffer', 'pmc_id' => $gf]);
    $this->assertNull($users->where('email', 'staffer@whpc.com')->first());
    $this->withSession(['user' => $admin])->post('admin/users', ['email' => 'nopmc@pmc.test', 'name' => 'No Company']);
    $this->assertNull($users->where('email', 'nopmc@pmc.test')->first());
    $page = $this->withSession(['user' => $admin])->get('admin/users');
    $page->assertStatus(200);
    $page->assertDontSee('WHPC admin');

    $brian = (int)$users->insert(['email' => 'brian@whpc.com', 'name' => 'Brian', 'pmc_id' => null, 'is_admin' => 1, 'is_active' => 1], true);
    $this->withSession(['user' => $admin])->post('admin/users/' . $brian . '/toggle')->assertRedirectTo('/admin/users');
    $this->assertSame(1, (int)$users->find($brian)['is_active'], 'a configured admin cannot be disabled');
    $this->assertStringNotContainsString('admin/users/' . $brian . '/toggle', (string)$this->withSession(['user' => $admin])->get('admin/users')->response()->getBody());
    $new = (int)$users->where('email', 'new@pmc.test')->first()['id'];
    $this->withSession(['user' => $admin])->post('admin/users/' . $new . '/toggle');
    $this->assertSame(0, (int)$users->find($new)['is_active']);
  }
}
