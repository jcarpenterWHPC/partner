<?php

namespace App\Controllers;

use App\Libraries\LoginCode;
use App\Models\ActivityLogModel;
use App\Models\UserModel;
use Config\Partner;

/**
 * Passwordless sign-in: email → one-time code (or the link in the same email) → session.
 *
 * Whether an address is registered is never revealed: an unknown address gets the same
 * "enter your code" screen, and a code typed there fails like any wrong code.
 */
class Auth extends BaseController
{
  private Partner $cfg;
  private UserModel $users;
  public function __construct()
  {
    $this->cfg = config(Partner::class);
    $this->users = new UserModel();
  }
  public function login()
  {
    if (session()->has('user'))
    {
      return redirect()->to('/');
    }
    return view('pages/login', ['supportMail' => $this->cfg->supportMail]);
  }
  public function send()
  {
    if (!$this->throttle('send'))
    {
      return redirect()->to('/login')->with('error', 'Too many sign-in attempts. Please wait a minute and try again.');
    }
    $email = mb_strtolower(trim((string)$this->request->getPost('email')));
    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false)
    {
      return redirect()->to('/login')->with('error', 'Enter the work email address your WHPC contact registered.')->withInput();
    }
    $user = $this->users->activeByEmail($email);
    session()->set('login_pending', ['email' => $email, 'user_id' => $user['id'] ?? null, 'at' => time(), 'sends' => 1]);
    if ($user !== null && !$this->issue($user))
    {
      return redirect()->to('/login')->with('error', 'We could not send the email just now. Please try again.');
    }
    if ($user === null)
    {
      ActivityLogModel::record('login.unknown_email', null, $email);
    }
    return redirect()->to('/login/verify');
  }
  public function verify()
  {
    $pending = $this->pending();
    if ($pending === null)
    {
      return redirect()->to('/login');
    }
    $wait = $pending['user_id'] ? (new LoginCode($this->cfg))->secondsUntilResend((int)$pending['user_id']) : 0;
    return view('pages/login-verify', [
      'maskedEmail' => LoginCode::mask($pending['email']),
      'codeLength' => $this->cfg->codeLength,
      'ttlMinutes' => $this->cfg->codeTtlMinutes,
      'resendWait' => $wait,
      'resendsLeft' => max(0, $this->cfg->maxResends - (int)$pending['sends']),
      'supportMail' => $this->cfg->supportMail,
    ]);
  }
  public function verifySubmit()
  {
    $pending = $this->pending();
    if ($pending === null)
    {
      return redirect()->to('/login');
    }
    if (!$this->throttle('verify'))
    {
      return redirect()->to('/login/verify')->with('error', 'Too many attempts. Please wait a minute and try again.');
    }
    $code = preg_replace('/\D/', '', (string)$this->request->getPost('code'));
    if (!$pending['user_id'])
    {
      ActivityLogModel::record('login.failed', null, $pending['email'], 'no account');
      return redirect()->to('/login/verify')->with('error', 'That code is not right. Check the email and try again.');
    }
    $result = (new LoginCode($this->cfg))->verify((int)$pending['user_id'], $code);
    if ($result['ok'])
    {
      $user = $this->users->withPmcById((int)$pending['user_id']);
      if ($user && (int)$user['is_active'] === 1)
      {
        return $this->establish($user, 'code');
      }
      $result = ['ok' => false, 'error' => 'no_code', 'burned' => true, 'remaining' => 0];
    }
    ActivityLogModel::record('login.failed', (int)$pending['user_id'], $pending['email'], (string)$result['error']);
    $message = match ($result['error'])
    {
      'expired' => 'That code has expired. Request a new one.',
      'locked' => 'Too many wrong codes. Request a new one.',
      'no_code' => 'There is no code to check. Request a new one.',
      default => 'That code is not right. ' . ($result['remaining'] === 1 ? 'One try left.' : $result['remaining'] . ' tries left.'),
    };
    return redirect()->to('/login/verify')->with('error', $message)->with('burned', $result['burned']);
  }
  public function resend()
  {
    $pending = $this->pending();
    if ($pending === null)
    {
      return redirect()->to('/login');
    }
    if (!$this->throttle('send'))
    {
      return redirect()->to('/login/verify')->with('error', 'Too many sign-in attempts. Please wait a minute and try again.');
    }
    if ((int)$pending['sends'] >= $this->cfg->maxResends)
    {
      session()->remove('login_pending');
      return redirect()->to('/login')->with('error', 'Too many codes were requested. Start again.');
    }
    if ($pending['user_id'])
    {
      $codes = new LoginCode($this->cfg);
      $wait = $codes->secondsUntilResend((int)$pending['user_id']);
      if ($wait > 0)
      {
        return redirect()->to('/login/verify')->with('error', 'A code was just sent. You can ask for another in ' . $wait . ' second' . ($wait === 1 ? '' : 's') . '.');
      }
      $user = $this->users->withPmcById((int)$pending['user_id']);
      if ($user && !$this->issue($user))
      {
        return redirect()->to('/login/verify')->with('error', 'We could not send the email just now. Please try again.');
      }
    }
    $pending['sends'] = (int)$pending['sends'] + 1;
    session()->set('login_pending', $pending);
    return redirect()->to('/login/verify')->with('info', 'A new code is on its way.');
  }
  public function link(string $token)
  {
    if (!$this->throttle('verify'))
    {
      return redirect()->to('/login')->with('error', 'Too many attempts. Please wait a minute and try again.');
    }
    $userId = (new LoginCode($this->cfg))->redeem($token);
    $user = $userId === null ? null : $this->users->withPmcById($userId);
    if (!$user || (int)$user['is_active'] !== 1)
    {
      ActivityLogModel::record('login.failed', $userId, null, 'bad link');
      return redirect()->to('/login')->with('error', 'That sign-in link has expired or was already used. Request a new code.');
    }
    return $this->establish($user, 'link');
  }
  public function logout()
  {
    $user = session('user');
    if ($user)
    {
      ActivityLogModel::record('logout', (int)$user['id'], (string)$user['email']);
    }
    session()->destroy();
    return redirect()->to('/login?signed_out=1');
  }
  /** Sign $user in. */
  private function establish(array $user, string $how)
  {
    session()->remove('login_pending');
    session()->regenerate(true);
    session()->set('user', [
      'id' => (int)$user['id'],
      'email' => (string)$user['email'],
      'name' => (string)$user['name'],
      'pmc_id' => $user['pmc_id'] === null ? null : (int)$user['pmc_id'],
      'pmc_name' => (string)($user['pmc_name'] ?? ''),
      'is_admin' => (int)$user['is_admin'] === 1,
    ]);
    $this->users->update((int)$user['id'], ['last_login_at' => date('Y-m-d H:i:s')]);
    ActivityLogModel::record('login.ok', (int)$user['id'], (string)$user['email'], $how);
    return redirect()->to('/');
  }
  private function issue(array $user): bool
  {
    try
    {
      (new LoginCode($this->cfg))->issue($user, $this->request->getIPAddress());
      ActivityLogModel::record('login.code_sent', (int)$user['id'], (string)$user['email']);
      return true;
    }
    catch (\Throwable $e)
    {
      log_message('error', '[Auth] could not issue a code for user ' . $user['id'] . ': ' . $e->getMessage());
      return false;
    }
  }
  /** The half-finished sign-in, or null once it is too old. */
  private function pending(): ?array
  {
    $p = session('login_pending');
    if (!is_array($p) || empty($p['email']))
    {
      return null;
    }
    if ((int)$p['at'] < time() - $this->cfg->pendingTtlMinutes * 60)
    {
      session()->remove('login_pending');
      return null;
    }
    return $p;
  }
  private function throttle(string $what): bool
  {
    return service('throttler')->check('login-' . $what . '-' . md5($this->request->getIPAddress()), $this->cfg->requestsPerMinute, MINUTE);
  }
}
