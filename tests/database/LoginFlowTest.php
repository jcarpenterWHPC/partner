<?php

use App\Libraries\LoginCode;
use App\Libraries\Mailer;
use App\Models\ActivityLogModel;
use App\Models\LoginCodeModel;
use App\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\Mock\MockCache;
use CodeIgniter\Throttle\Throttler;
use Config\Partner;
use Config\Services;

/**
 * Passwordless sign-in, end to end: an address, an emailed code and link, a session.
 *
 * Pinned: an unknown or disabled address is told nothing and emailed nothing; the code
 * and the link each sign in once; five wrong codes burn it; an expired code is dead;
 * a second code waits for the cooldown; signed-out visitors are sent to /login.
 *
 * @internal
 */
final class LoginFlowTest extends CIUnitTestCase
{
  use DatabaseTestTrait;
  use FeatureTestTrait;
  protected $refresh = true;
  protected $migrate = true;
  protected $namespace = 'App';
  private int $janeId;
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
    $users = new UserModel();
    $this->janeId = (int)$users->insert(['email' => 'jane@pmc.test', 'name' => 'Jane Doe', 'company' => 'Goldfinch Living'], true);
    $users->insert(['email' => 'gone@pmc.test', 'name' => 'Gone Person', 'company' => 'Koru', 'is_active' => 0]);
  }
  protected function tearDown(): void
  {
    Services::reset(false);
    parent::tearDown();
  }
  private function actions(string $action): array
  {
    return (new ActivityLogModel())->where('action', $action)->findAll();
  }
  /** The code and the link token out of the one email in the outbox. */
  private function fromOutbox(): array
  {
    $this->assertCount(1, Mailer::$outbox, 'exactly one email');
    $html = Mailer::$outbox[0]['html'];
    $this->assertMatchesRegularExpression('/letter-spacing:10px[^>]*>\s*(\d{6})\s*</', $html);
    preg_match('/letter-spacing:10px[^>]*>\s*(\d{6})\s*</', $html, $c);
    // The HTML href is attribute-escaped (`&#x2F;`); the plain-text twin carries the raw link.
    preg_match('~/login/link/([0-9a-f]{64})~', Mailer::$outbox[0]['text'], $t);
    $this->assertStringContainsString('login&#x2F;link&#x2F;' . ($t[1] ?? '?'), $html, 'the same link is in the HTML button');
    return ['code' => $c[1], 'token' => $t[1] ?? '', 'to' => Mailer::$outbox[0]['to']];
  }
  public function testAnUnknownOrDisabledAddressIsToldNothingAndEmailedNothing(): void
  {
    foreach (['nobody@pmc.test', 'gone@pmc.test'] as $email)
    {
      $res = $this->post('login', ['email' => $email]);
      $res->assertRedirectTo('/login/verify');
      $this->assertNull(session('login_pending')['user_id']);
    }
    $this->assertSame([], Mailer::$outbox);
    $this->assertCount(2, $this->actions('login.unknown_email'));
    // A code typed for an address that has none fails like any wrong code.
    $this->withSession(['login_pending' => ['email' => 'nobody@pmc.test', 'user_id' => null, 'at' => time(), 'sends' => 1]])
      ->post('login/verify', ['code' => '123456'])->assertRedirectTo('/login/verify');
    $this->assertCount(1, $this->actions('login.failed'));
    $this->post('login', ['email' => 'not an address'])->assertRedirectTo('/login');
  }
  public function testAKnownAddressIsEmailedACodeThatSignsThemIn(): void
  {
    $this->post('login', ['email' => 'Jane@PMC.test'])->assertRedirectTo('/login/verify');
    $mail = $this->fromOutbox();
    $this->assertSame('jane@pmc.test', $mail['to']);
    $this->assertSame('Your WHPC Partner Portal sign-in code', Mailer::$outbox[0]['subject']);
    $this->assertStringContainsString($mail['code'], Mailer::$outbox[0]['text'], 'the plain-text twin carries the code too');
    $pending = session('login_pending');
    $this->assertSame($this->janeId, (int)$pending['user_id']);
    $this->assertCount(1, $this->actions('login.code_sent'));

    $page = $this->withSession(['login_pending' => $pending])->get('login/verify');
    $page->assertStatus(200);
    $page->assertSee('j***@pmc.test');

    $this->withSession(['login_pending' => $pending])->post('login/verify', ['code' => ' ' . $mail['code'] . ' '])->assertRedirectTo('/');
    $user = session('user');
    $this->assertSame(['jane@pmc.test', 'Jane Doe', 'Goldfinch Living', false], [$user['email'], $user['name'], $user['company'], $user['is_admin']]);
    $this->assertNull(session('login_pending'));
    $this->assertNotNull((new UserModel())->find($this->janeId)['last_login_at']);
    $this->assertNotNull((new LoginCodeModel())->latestFor($this->janeId)['consumed_at'], 'the code is spent');
    $this->assertSame('code', $this->actions('login.ok')[0]['detail']);

    $home = $this->withSession(['user' => $user])->get('/');
    $home->assertStatus(200);
    $home->assertSee('Welcome, Jane');
    $home->assertSeeElement('#cardCapex');
    $home->assertSee('Coming soon');
  }
  public function testTheLinkSignsInOnceOnly(): void
  {
    (new LoginCode())->issue(['id' => $this->janeId, 'email' => 'jane@pmc.test', 'name' => 'Jane Doe']);
    $token = $this->fromOutbox()['token'];
    $this->assertSame(64, strlen($token));
    $this->get('login/link/' . $token)->assertRedirectTo('/');
    $this->assertSame('link', $this->actions('login.ok')[0]['detail']);
    $this->get('login/link/' . $token)->assertRedirectTo('/login');
    $this->get('login/link/not-a-token')->assertRedirectTo('/login');
    $this->assertCount(2, $this->actions('login.failed'));
    $this->assertCount(1, $this->actions('login.ok'));
  }
  public function testWrongCodesBurnItAndAnExpiredCodeIsDead(): void
  {
    $codes = new LoginCode();
    $codes->issue(['id' => $this->janeId, 'email' => 'jane@pmc.test', 'name' => 'Jane Doe']);
    $real = $this->fromOutbox()['code'];
    $wrong = $real === '000000' ? '000001' : '000000';
    $max = config(Partner::class)->codeMaxAttempts;
    for ($i = 1; $i < $max; $i++)
    {
      $r = $codes->verify($this->janeId, $wrong);
      $this->assertSame(['mismatch', false, $max - $i], [$r['error'], $r['burned'], $r['remaining']]);
    }
    $r = $codes->verify($this->janeId, $wrong);
    $this->assertSame(['locked', true], [$r['error'], $r['burned']]);
    $this->assertSame('no_code', $codes->verify($this->janeId, $real)['error'], 'burned means the real code is dead too');

    $codes->issue(['id' => $this->janeId, 'email' => 'jane@pmc.test', 'name' => 'Jane Doe']);
    $row = (new LoginCodeModel())->latestActiveFor($this->janeId);
    (new LoginCodeModel())->update((int)$row['id'], ['expires_at' => date('Y-m-d H:i:s', time() - 1)]);
    $this->assertSame('expired', $codes->verify($this->janeId, '123456')['error']);
    $this->assertNull($codes->redeem(str_repeat('a', 64)));
  }
  public function testASecondCodeWaitsForTheCooldown(): void
  {
    $this->post('login', ['email' => 'jane@pmc.test']);
    $pending = session('login_pending');
    $this->assertCount(1, Mailer::$outbox);
    $this->withSession(['login_pending' => $pending])->post('login/resend')->assertRedirectTo('/login/verify');
    $this->assertCount(1, Mailer::$outbox, 'inside the cooldown nothing is sent');

    $model = new LoginCodeModel();
    $row = $model->latestFor($this->janeId);
    $model->update((int)$row['id'], ['created_at' => date('Y-m-d H:i:s', time() - config(Partner::class)->resendCooldownSeconds - 1)]);
    $this->withSession(['login_pending' => $pending])->post('login/resend')->assertRedirectTo('/login/verify');
    $this->assertCount(2, Mailer::$outbox);
    $this->assertSame(2, (int)session('login_pending')['sends']);
    $this->assertNotNull($model->find((int)$row['id'])['consumed_at'], 'the first code died when the second was issued');
  }
  public function testSignedOutVisitorsAreSentToLogin(): void
  {
    $this->get('/')->assertRedirectTo('/login?expired=1');
    $this->get('login')->assertStatus(200);
    $this->withSession(['user' => ['id' => 1, 'email' => 'x@y', 'name' => 'X', 'company' => '', 'is_admin' => false]])->get('login')->assertRedirectTo('/');
    $this->withSession(['user' => ['id' => 1, 'email' => 'x@y', 'name' => 'X', 'company' => '', 'is_admin' => false]])->post('logout')->assertRedirectTo('/login?signed_out=1');
    $this->assertCount(1, $this->actions('logout'));
    $this->get('login/verify')->assertRedirectTo('/login');
  }
}
