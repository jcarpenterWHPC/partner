<?php

namespace App\Libraries;

use Config\Partner;
use RuntimeException;

/**
 * Sign in with Microsoft Entra: OpenID Connect authorization code + PKCE, the id_token
 * checked here (RS256 against Microsoft's published keys, issuer, audience, time, nonce).
 *
 * The person's identity is the token's `tid` (their directory) and `preferred_username`
 * (their sign-in address); which of those may enter is Accounts' decision, not this
 * class's. $http is (method, url, form) → [status, body], replaceable in tests.
 */
class MicrosoftSignIn
{
  public const HOST = 'https://login.microsoftonline.com/';
  /** @var callable(string,string,array):array{int,string} */
  private $http;
  public function __construct(private ?Partner $cfg = null, ?callable $http = null)
  {
    $this->cfg ??= config(Partner::class);
    $this->http = $http ?? [$this, 'curl'];
  }
  public function enabled(): bool
  {
    return $this->cfg->microsoftEnabled();
  }
  public function redirectUri(): string
  {
    return base_url('login/microsoft/callback');
  }
  /**
   * Where to send the browser, and what to remember until it comes back.
   *
   * @return array{url:string, pending:array{state:string,nonce:string,verifier:string,at:int}}
   */
  public function begin(?string $loginHint = null): array
  {
    $pending = ['state' => bin2hex(random_bytes(16)), 'nonce' => bin2hex(random_bytes(16)), 'verifier' => rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '='), 'at' => time()];
    $q = [
      'client_id' => $this->cfg->microsoftClientId,
      'response_type' => 'code',
      'redirect_uri' => $this->redirectUri(),
      'response_mode' => 'query',
      'scope' => 'openid profile email',
      'state' => $pending['state'],
      'nonce' => $pending['nonce'],
      'code_challenge' => self::b64url(hash('sha256', $pending['verifier'], true)),
      'code_challenge_method' => 'S256',
    ];
    if ($loginHint !== null && $loginHint !== '')
    {
      $q['login_hint'] = $loginHint;
    }
    else
    {
      $q['prompt'] = 'select_account';
    }
    return ['url' => self::HOST . rawurlencode($this->cfg->microsoftAuthority) . '/oauth2/v2.0/authorize?' . http_build_query($q, '', '&', PHP_QUERY_RFC3986), 'pending' => $pending];
  }
  /**
   * The callback's query against what begin() remembered → who Microsoft says this is.
   *
   * @return array{tid:string,email:string,name:string,oid:string}
   * @throws RuntimeException with a one-line reason safe for the log
   */
  public function complete(array $query, array $pending): array
  {
    if (!isset($query['state'], $pending['state']) || !hash_equals((string)$pending['state'], (string)$query['state']))
    {
      throw new RuntimeException('state mismatch');
    }
    if (isset($query['error']))
    {
      throw new RuntimeException('provider error ' . preg_replace('/[^A-Za-z0-9_.-]/', '', (string)$query['error']));
    }
    $code = (string)($query['code'] ?? '');
    if ($code === '')
    {
      throw new RuntimeException('no code');
    }
    [$status, $body] = ($this->http)('POST', self::HOST . rawurlencode($this->cfg->microsoftAuthority) . '/oauth2/v2.0/token', [
      'client_id' => $this->cfg->microsoftClientId,
      'client_secret' => $this->cfg->microsoftClientSecret,
      'grant_type' => 'authorization_code',
      'code' => $code,
      'redirect_uri' => $this->redirectUri(),
      'code_verifier' => (string)$pending['verifier'],
    ]);
    $tok = json_decode((string)$body, true);
    if ($status !== 200 || !is_array($tok) || !is_string($tok['id_token'] ?? null))
    {
      throw new RuntimeException('token endpoint ' . $status . ' ' . (is_array($tok) ? preg_replace('/[^A-Za-z0-9_.-]/', '', (string)($tok['error'] ?? '')) : 'unreadable'));
    }
    $claims = $this->verifyIdToken($tok['id_token'], (string)$pending['nonce']);
    $email = mb_strtolower(trim((string)($claims['preferred_username'] ?? '')));
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false)
    {
      $email = mb_strtolower(trim((string)($claims['email'] ?? '')));
    }
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false)
    {
      throw new RuntimeException('no usable address in token');
    }
    return ['tid' => (string)$claims['tid'], 'email' => $email, 'name' => trim((string)($claims['name'] ?? '')), 'oid' => (string)($claims['oid'] ?? '')];
  }
  /**
   * The claims of $jwt once its signature, issuer, audience, lifetime and nonce check out.
   *
   * @throws RuntimeException
   */
  public function verifyIdToken(string $jwt, string $nonce, ?int $now = null): array
  {
    $now ??= time();
    $parts = explode('.', $jwt);
    if (count($parts) !== 3)
    {
      throw new RuntimeException('malformed token');
    }
    $header = json_decode((string)self::unb64url($parts[0]), true);
    $claims = json_decode((string)self::unb64url($parts[1]), true);
    $sig = self::unb64url($parts[2]);
    if (!is_array($header) || !is_array($claims) || $sig === false || $sig === '')
    {
      throw new RuntimeException('malformed token');
    }
    if (($header['alg'] ?? '') !== 'RS256' || !is_string($header['kid'] ?? null))
    {
      throw new RuntimeException('unexpected alg');
    }
    $key = $this->key($header['kid']);
    if ($key === null)
    {
      throw new RuntimeException('unknown key ' . preg_replace('/[^A-Za-z0-9_-]/', '', $header['kid']));
    }
    if (openssl_verify($parts[0] . '.' . $parts[1], $sig, $key, OPENSSL_ALGO_SHA256) !== 1)
    {
      throw new RuntimeException('bad signature');
    }
    $tid = (string)($claims['tid'] ?? '');
    if (!preg_match('/^[0-9a-f-]{36}$/i', $tid))
    {
      throw new RuntimeException('no tenant');
    }
    if (($claims['iss'] ?? '') !== self::HOST . $tid . '/v2.0')
    {
      throw new RuntimeException('issuer');
    }
    $aud = $claims['aud'] ?? '';
    if (!is_string($aud) || !hash_equals($this->cfg->microsoftClientId, $aud))
    {
      throw new RuntimeException('audience');
    }
    if (!is_int($claims['exp'] ?? null) || $claims['exp'] < $now - 60 || (isset($claims['nbf']) && (int)$claims['nbf'] > $now + 60))
    {
      throw new RuntimeException('expired');
    }
    if (!is_string($claims['nonce'] ?? null) || !hash_equals($nonce, $claims['nonce']))
    {
      throw new RuntimeException('nonce');
    }
    return $claims;
  }
  /** Microsoft's current signing key $kid as PEM, fetching afresh once if it is not cached. */
  private function key(string $kid): ?string
  {
    $cache = cache();
    $slot = 'ms_jwks';
    $keys = $cache->get($slot);
    if (!is_array($keys) || !isset($keys[$kid]))
    {
      [$status, $body] = ($this->http)('GET', self::HOST . 'common/discovery/v2.0/keys', []);
      $doc = json_decode((string)$body, true);
      if ($status !== 200 || !is_array($doc['keys'] ?? null))
      {
        throw new RuntimeException('keys unavailable');
      }
      $keys = [];
      foreach ($doc['keys'] as $jwk)
      {
        if (is_array($jwk) && ($jwk['kty'] ?? '') === 'RSA' && isset($jwk['kid'], $jwk['n'], $jwk['e']))
        {
          $keys[(string)$jwk['kid']] = self::pem((string)$jwk['n'], (string)$jwk['e']);
        }
      }
      $cache->save($slot, $keys, DAY);
    }
    return $keys[$kid] ?? null;
  }
  /** An RSA public key from a JWK's modulus and exponent, as PEM. */
  public static function pem(string $n, string $e): string
  {
    $int = static function (string $bytes): string
    {
      $bytes = ltrim($bytes, "\0");
      if ($bytes === '' || (ord($bytes[0]) & 0x80))
      {
        $bytes = "\0" . $bytes;
      }
      return self::der(0x02, $bytes);
    };
    $rsa = self::der(0x30, $int((string)self::unb64url($n)) . $int((string)self::unb64url($e)));
    $spki = self::der(0x30, "\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00" . self::der(0x03, "\0" . $rsa));
    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spki), 64, "\n") . "-----END PUBLIC KEY-----\n";
  }
  private static function der(int $tag, string $body): string
  {
    $len = strlen($body);
    if ($len < 0x80)
    {
      return chr($tag) . chr($len) . $body;
    }
    $lenBytes = ltrim(pack('N', $len), "\0");
    return chr($tag) . chr(0x80 | strlen($lenBytes)) . $lenBytes . $body;
  }
  public static function b64url(string $raw): string
  {
    return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
  }
  public static function unb64url(string $s): string|false
  {
    return base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4), true);
  }
  /** @return array{int,string} */
  private function curl(string $method, string $url, array $form): array
  {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_HTTPHEADER => ['Accept: application/json']]);
    if ($method === 'POST')
    {
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($form, '', '&'));
    }
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return [$status, is_string($body) ? $body : ''];
  }
}
