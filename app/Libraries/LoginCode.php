<?php

namespace App\Libraries;

use App\Models\LoginCodeModel;
use Config\Partner;

/**
 * Passwordless sign-in: a short numeric code and a one-click link, emailed together,
 * both single-use and both dead after Config\Partner::$codeTtlMinutes.
 *
 * Only digests are stored — password_hash() of the code, sha256 of the link token —
 * so the table never holds anything that signs somebody in. One live code per person:
 * issuing a new one consumes the old.
 */
class LoginCode
{
  private Partner $cfg;
  private LoginCodeModel $codes;
  private Mailer $mailer;
  public function __construct(?Partner $cfg = null, ?LoginCodeModel $codes = null, ?Mailer $mailer = null)
  {
    $this->cfg = $cfg ?? config(Partner::class);
    $this->codes = $codes ?? new LoginCodeModel();
    $this->mailer = $mailer ?? new Mailer();
  }
  /**
   * Email a fresh code + link to $user and record their digests. Sends BEFORE
   * persisting, so a delivery failure never leaves a live code nobody received.
   *
   * @param array{id:int|string,email:string,name:string} $user
   * @throws \RuntimeException when the email cannot be sent
   */
  public function issue(array $user, ?string $ip = null): void
  {
    $code = $this->generateCode();
    $token = bin2hex(random_bytes(32));
    $this->sendEmail($user, $code, $token);
    $now = time();
    $this->codes->invalidateAllFor((int)$user['id']);
    $this->codes->insert([
      'user_id' => (int)$user['id'],
      'code_hash' => password_hash($code, PASSWORD_DEFAULT),
      'token_hash' => hash('sha256', $token),
      'attempts' => 0,
      'expires_at' => date('Y-m-d H:i:s', $now + $this->cfg->codeTtlMinutes * 60),
      'created_at' => date('Y-m-d H:i:s', $now),
      'ip_address' => $ip,
    ]);
  }
  /**
   * Check a typed code.
   *
   * @return array{ok:bool, error:?string, burned:bool, remaining:int}
   *   error: null | 'no_code' | 'expired' | 'locked' | 'mismatch'
   *   burned: the code is dead and a new one must be requested.
   */
  public function verify(int $userId, string $code): array
  {
    $code = trim($code);
    $row = $this->codes->latestActiveFor($userId);
    if ($row === null)
    {
      return ['ok' => false, 'error' => 'no_code', 'burned' => true, 'remaining' => 0];
    }
    if (strtotime($row['expires_at']) < time())
    {
      $this->codes->consume((int)$row['id']);
      return ['ok' => false, 'error' => 'expired', 'burned' => true, 'remaining' => 0];
    }
    if ((int)$row['attempts'] >= $this->cfg->codeMaxAttempts)
    {
      $this->codes->consume((int)$row['id']);
      return ['ok' => false, 'error' => 'locked', 'burned' => true, 'remaining' => 0];
    }
    if ($code === '' || !password_verify($code, $row['code_hash']))
    {
      $attempts = (int)$row['attempts'] + 1;
      $this->codes->update((int)$row['id'], ['attempts' => $attempts]);
      $remaining = max(0, $this->cfg->codeMaxAttempts - $attempts);
      if ($remaining === 0)
      {
        $this->codes->consume((int)$row['id']);
        return ['ok' => false, 'error' => 'locked', 'burned' => true, 'remaining' => 0];
      }
      return ['ok' => false, 'error' => 'mismatch', 'burned' => false, 'remaining' => $remaining];
    }
    $this->codes->consume((int)$row['id']);
    return ['ok' => true, 'error' => null, 'burned' => true, 'remaining' => 0];
  }
  /** Redeem a sign-in link: the user id it belongs to, or null. Consumes it. */
  public function redeem(string $token): ?int
  {
    if (!preg_match('/^[0-9a-f]{64}$/', $token))
    {
      return null;
    }
    $row = $this->codes->activeByTokenHash(hash('sha256', $token));
    if ($row === null)
    {
      return null;
    }
    $this->codes->consume((int)$row['id']);
    return strtotime($row['expires_at']) < time() ? null : (int)$row['user_id'];
  }
  /** Seconds until $userId may be sent another code; 0 when they may now. */
  public function secondsUntilResend(int $userId): int
  {
    $row = $this->codes->latestFor($userId);
    if ($row === null)
    {
      return 0;
    }
    return max(0, strtotime($row['created_at']) + $this->cfg->resendCooldownSeconds - time());
  }
  /** `j***@example.com`, so the verify screen can say where the code went. */
  public static function mask(string $email): string
  {
    [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
    return mb_substr($local, 0, 1) . '***@' . $domain;
  }
  private function generateCode(): string
  {
    $len = max(4, min(10, $this->cfg->codeLength));
    return str_pad((string)random_int(0, (10 ** $len) - 1), $len, '0', STR_PAD_LEFT);
  }
  private function sendEmail(array $user, string $code, string $token): void
  {
    $link = rtrim(config('App')->baseURL, '/') . '/login/link/' . $token;
    $data = [
      'name' => (string)($user['name'] ?? ''),
      'code' => $code,
      'link' => $link,
      'ttlMinutes' => $this->cfg->codeTtlMinutes,
      'supportMail' => $this->cfg->supportMail,
    ];
    $html = view('emails/login-code', $data, ['return' => true]);
    $text = "Your {$data['ttlMinutes']}-minute sign-in code for the WHPC Partner Portal is {$code}.\n\nOr sign in with this link: {$link}\n\nIf you did not ask for this, ignore this email.";
    $this->mailer->send((string)$user['email'], 'Your WHPC Partner Portal sign-in code', $html, $text);
  }
}
