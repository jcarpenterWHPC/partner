<?php

use App\Libraries\MicrosoftSignIn;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\Mock\MockCache;
use Config\Partner;
use Config\Services;

/**
 * The id_token check, against a key pair made here and served as Microsoft would serve
 * its JWKS. Pinned: a good token's claims come back; the wrong signature, issuer,
 * audience, nonce, lifetime, algorithm or key is refused; the authorize URL carries PKCE.
 *
 * @internal
 */
final class MicrosoftSignInTest extends CIUnitTestCase
{
  private const TID = '11111111-2222-3333-4444-555555555555';
  private \OpenSSLAsymmetricKey $key;
  private array $jwks;
  private array $calls = [];
  private Partner $cfg;
  protected function setUp(): void
  {
    parent::setUp();
    Services::injectMock('cache', new MockCache());
    $this->key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    $d = openssl_pkey_get_details($this->key)['rsa'];
    $this->jwks = ['keys' => [['kty' => 'RSA', 'kid' => 'k1', 'use' => 'sig', 'n' => MicrosoftSignIn::b64url($d['n']), 'e' => MicrosoftSignIn::b64url($d['e'])]]];
    $this->cfg = new Partner();
    $this->cfg->microsoftTenant = self::TID;
    $this->cfg->microsoftClientId = 'client-abc';
    $this->cfg->microsoftClientSecret = 'shh';
  }
  protected function tearDown(): void
  {
    Services::reset(false);
    parent::tearDown();
  }
  private function ms(?array $tokenResponse = null): MicrosoftSignIn
  {
    return new MicrosoftSignIn($this->cfg, function (string $method, string $url, array $form) use ($tokenResponse): array
    {
      $this->calls[] = [$method, $url, $form];
      if (str_ends_with($url, '/discovery/v2.0/keys'))
      {
        return [200, json_encode($this->jwks)];
      }
      return $tokenResponse ?? [400, '{"error":"invalid_grant"}'];
    });
  }
  private function jwt(array $claims, array $header = ['alg' => 'RS256', 'kid' => 'k1', 'typ' => 'JWT'], ?\OpenSSLAsymmetricKey $signWith = null): string
  {
    $claims += ['iss' => MicrosoftSignIn::HOST . self::TID . '/v2.0', 'aud' => 'client-abc', 'exp' => time() + 600, 'nbf' => time() - 60, 'iat' => time() - 60, 'tid' => self::TID, 'nonce' => 'n0nce', 'oid' => 'oid-1', 'preferred_username' => 'JCarpenter@whpc.com', 'name' => 'Joe Carpenter'];
    $h = MicrosoftSignIn::b64url(json_encode($header));
    $c = MicrosoftSignIn::b64url(json_encode($claims));
    openssl_sign($h . '.' . $c, $sig, $signWith ?? $this->key, OPENSSL_ALGO_SHA256);
    return $h . '.' . $c . '.' . MicrosoftSignIn::b64url($sig);
  }
  public function testAGoodTokenYieldsItsClaimsAndTheKeyIsCached(): void
  {
    $ms = $this->ms();
    $claims = $ms->verifyIdToken($this->jwt([]), 'n0nce');
    $this->assertSame('JCarpenter@whpc.com', $claims['preferred_username']);
    $ms->verifyIdToken($this->jwt([]), 'n0nce');
    $this->assertCount(1, $this->calls, 'the JWKS was fetched once');
  }
  public function testEveryTamperedTokenIsRefused(): void
  {
    $ms = $this->ms();
    $other = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    $cases = [
      'bad signature' => $this->jwt([], signWith: $other),
      'issuer' => $this->jwt(['iss' => MicrosoftSignIn::HOST . 'common/v2.0']),
      'issuer' . '' => $this->jwt(['iss' => MicrosoftSignIn::HOST . '99999999-2222-3333-4444-555555555555/v2.0']),
      'audience' => $this->jwt(['aud' => 'someone-else']),
      'nonce' => $this->jwt(['nonce' => 'other']),
      'expired' => $this->jwt(['exp' => time() - 120]),
      'unexpected alg' => $this->jwt([], ['alg' => 'HS256', 'kid' => 'k1']),
      'unknown key k2' => $this->jwt([], ['alg' => 'RS256', 'kid' => 'k2']),
      'malformed token' => 'a.b',
      'no tenant' => $this->jwt(['tid' => 'not-a-guid']),
    ];
    foreach ($cases as $reason => $jwt)
    {
      try
      {
        $ms->verifyIdToken($jwt, 'n0nce');
        $this->fail("accepted: {$reason}");
      }
      catch (RuntimeException $e)
      {
        $this->assertSame(rtrim($reason, "\0"), $e->getMessage());
      }
    }
    $this->assertSame('none', (static fn() => 'none')(), 'reached');
  }
  public function testCompleteExchangesTheCodeAndChecksTheState(): void
  {
    $pending = ['state' => 'st', 'nonce' => 'n0nce', 'verifier' => 'v', 'at' => time()];
    $ms = $this->ms([200, json_encode(['id_token' => $this->jwt([])])]);
    $who = $ms->complete(['code' => 'c0de', 'state' => 'st'], $pending);
    $this->assertSame(['tid' => self::TID, 'email' => 'jcarpenter@whpc.com', 'name' => 'Joe Carpenter', 'oid' => 'oid-1'], $who);
    [$method, $url, $form] = $this->calls[0];
    $this->assertSame(['POST', MicrosoftSignIn::HOST . 'organizations/oauth2/v2.0/token'], [$method, $url]);
    $this->assertSame(['client-abc', 'shh', 'authorization_code', 'c0de', base_url('login/microsoft/callback'), 'v'], array_values($form));

    foreach ([['code' => 'c0de', 'state' => 'wrong'], ['code' => 'c0de'], ['state' => 'st', 'error' => 'access_denied'], ['state' => 'st']] as $query)
    {
      try
      {
        $ms->complete($query, $pending);
        $this->fail('accepted ' . json_encode($query));
      }
      catch (RuntimeException $e)
      {
        $this->assertNotSame('', $e->getMessage());
      }
    }
    try
    {
      $this->ms()->complete(['code' => 'c0de', 'state' => 'st'], $pending);
      $this->fail('a refused code was accepted');
    }
    catch (RuntimeException $e)
    {
      $this->assertSame('token endpoint 400 invalid_grant', $e->getMessage());
    }
  }
  public function testBeginPointsAtMicrosoftWithPkceAndState(): void
  {
    $start = $this->ms()->begin('jcarpenter@whpc.com');
    $u = parse_url($start['url']);
    parse_str($u['query'], $q);
    $this->assertSame(['login.microsoftonline.com', '/organizations/oauth2/v2.0/authorize'], [$u['host'], $u['path']]);
    $this->assertSame(['client-abc', 'code', base_url('login/microsoft/callback'), 'openid profile email', 'S256', 'jcarpenter@whpc.com'], [$q['client_id'], $q['response_type'], $q['redirect_uri'], $q['scope'], $q['code_challenge_method'], $q['login_hint']]);
    $this->assertSame($start['pending']['state'], $q['state']);
    $this->assertSame($start['pending']['nonce'], $q['nonce']);
    $this->assertSame(MicrosoftSignIn::b64url(hash('sha256', $start['pending']['verifier'], true)), $q['code_challenge']);
    $this->assertArrayNotHasKey('prompt', $q);
    parse_str(parse_url($this->ms()->begin(null)['url'], PHP_URL_QUERY), $q2);
    $this->assertSame('select_account', $q2['prompt']);
    $off = new Partner();
    $off->microsoftTenant = $off->microsoftClientId = $off->microsoftClientSecret = '';
    $this->assertFalse((new MicrosoftSignIn($off))->enabled());
  }
  public function testTheConfiguredAdminListParses(): void
  {
    $cfg = new Partner();
    $cfg->admins = 'Joe Carpenter <JCarpenter@whpc.com>, brian@whpc.com ,, junk entry, <x@y.z>';
    $this->assertSame(['jcarpenter@whpc.com' => 'Joe Carpenter', 'brian@whpc.com' => '', 'x@y.z' => ''], $cfg->admins());
    $this->assertTrue($cfg->isWhpc(' Someone@WHPC.COM '));
    $this->assertFalse($cfg->isWhpc('someone@notwhpc.com'));
    $this->assertFalse($cfg->isWhpc('whpc.com@pmc.test'));
  }
}
