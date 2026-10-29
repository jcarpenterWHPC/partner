<?php

namespace App\Commands;

use App\Libraries\LoginCode;
use App\Models\UserModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * A one-click sign-in link for an account, printed instead of emailed — for the TEST box
 * and the browser tests, where nobody reads the mailbox. Refused in production: there
 * the link goes to the person's inbox and nowhere else.
 */
class LoginLink extends BaseCommand
{
  protected $group = 'App';
  protected $name = 'partner:login-link';
  protected $description = 'Print a sign-in link for an account (not in production).';
  protected $usage = 'partner:login-link <email>';
  public function run(array $params)
  {
    if (ENVIRONMENT === 'production')
    {
      CLI::error('Not in production: sign-in links are only ever emailed here.');
      return EXIT_ERROR;
    }
    $email = mb_strtolower(trim((string)($params[0] ?? '')));
    $user = (new UserModel())->activeByEmail($email);
    if ($user === null)
    {
      CLI::error('No active account with that address.');
      return EXIT_ERROR;
    }
    $token = bin2hex(random_bytes(32));
    $codes = new \App\Models\LoginCodeModel();
    $codes->invalidateAllFor((int)$user['id']);
    $codes->insert([
      'user_id' => (int)$user['id'],
      'code_hash' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
      'token_hash' => hash('sha256', $token),
      'attempts' => 0,
      'expires_at' => date('Y-m-d H:i:s', time() + config(\Config\Partner::class)->codeTtlMinutes * 60),
      'created_at' => date('Y-m-d H:i:s'),
      'ip_address' => 'cli',
    ]);
    CLI::write(rtrim(config('App')->baseURL, '/') . '/login/link/' . $token);
    return EXIT_SUCCESS;
  }
}
