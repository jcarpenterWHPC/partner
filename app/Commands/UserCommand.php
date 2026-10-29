<?php

namespace App\Commands;

use App\Models\UserModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Portal accounts from the command line, until there is an admin screen.
 *
 *   php spark partner:user add  jane@pmc.com "Jane Doe" --pmc "Goldfinch Living" [--admin]
 *   php spark partner:user list
 *   php spark partner:user disable jane@pmc.com
 *   php spark partner:user enable  jane@pmc.com
 */
class UserCommand extends BaseCommand
{
  protected $group = 'App';
  protected $name = 'partner:user';
  protected $description = 'Add, list, enable or disable portal accounts.';
  protected $usage = 'partner:user <add|list|enable|disable> [email] ["Name"] [--pmc "Company"] [--admin]';
  protected $options = ['--pmc' => 'The property management company, as Assets names it (add).', '--admin' => 'Make the account a WHPC admin (add).'];
  public function run(array $params)
  {
    $users = new UserModel();
    $what = $params[0] ?? 'list';
    $email = mb_strtolower(trim((string)($params[1] ?? '')));
    if ($what === 'list')
    {
      CLI::table(array_map(static fn(array $u): array => [$u['id'], $u['email'], $u['name'], (string)($u['pmc_name'] ?? ''), $u['is_admin'] ? 'admin' : '', $u['is_active'] ? 'active' : 'disabled', (string)$u['last_login_at']], $users->roster()), ['id', 'email', 'name', 'PMC', 'role', 'state', 'last sign-in']);
      return EXIT_SUCCESS;
    }
    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false)
    {
      CLI::error('Give a valid email address.');
      return EXIT_ERROR;
    }
    if ($what === 'add')
    {
      if ($users->where('email', $email)->first())
      {
        CLI::error("{$email} already exists.");
        return EXIT_ERROR;
      }
      $pmcName = trim((string)(CLI::getOption('pmc') ?? ''));
      $pmc = $pmcName === '' ? null : (new \App\Models\PmcModel())->byName($pmcName);
      if ($pmcName !== '' && $pmc === null)
      {
        CLI::error("No PMC named \"{$pmcName}\". Run partner:sync-properties, or check the spelling against Assets.");
        return EXIT_ERROR;
      }
      $id = $users->insert([
        'email' => $email,
        'name' => trim((string)($params[2] ?? '')),
        'pmc_id' => $pmc === null ? null : (int)$pmc['id'],
        'is_admin' => CLI::getOption('admin') !== null ? 1 : 0,
        'is_active' => 1,
      ], true);
      CLI::write("Added {$email} (id {$id}).", 'green');
      return EXIT_SUCCESS;
    }
    if ($what === 'enable' || $what === 'disable')
    {
      $u = $users->where('email', $email)->first();
      if (!$u)
      {
        CLI::error("{$email} is not an account.");
        return EXIT_ERROR;
      }
      $users->update((int)$u['id'], ['is_active' => $what === 'enable' ? 1 : 0]);
      CLI::write(ucfirst($what) . "d {$email}.", 'green');
      return EXIT_SUCCESS;
    }
    CLI::error("Unknown action {$what}. " . $this->usage);
    return EXIT_ERROR;
  }
}
