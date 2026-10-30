<?php

namespace App\Libraries;

use App\Models\UserModel;
use Config\Partner;

/**
 * Who may sign in, and as whom.
 *
 * WHPC people are admins, and only admins. The configured ones (`partner.admins`) are the
 * safety net: a listed address gets its account on first sign-in and is kept an active
 * admin whatever the table says. Any other WHPC address signs in only when an admin has
 * added it on Portal users (an active admin row with no organisation); a disabled or
 * missing one is refused like a stranger. Everyone else needs the partner account a WHPC
 * admin made for them; nothing in the admin screens makes such an account an admin (only
 * `partner:e2e-seed`, outside production).
 */
class Accounts
{
  private Partner $cfg;
  private UserModel $users;
  public function __construct(?Partner $cfg = null, ?UserModel $users = null)
  {
    $this->cfg = $cfg ?? config(Partner::class);
    $this->users = $users ?? new UserModel();
  }
  /** The account $email signs in as (with its organisation's name), or null when it may not. */
  public function forSignIn(string $email, ?string $name = null): ?array
  {
    $email = mb_strtolower(trim($email));
    if (!$this->cfg->isWhpc($email))
    {
      return $this->users->activeByEmail($email);
    }
    $listed = $this->cfg->admins();
    if (!array_key_exists($email, $listed))
    {
      $row = $this->users->activeByEmail($email);
      return $row !== null && (int)$row['is_admin'] === 1 && empty($row['pmc_id']) ? $row : null;
    }
    $row = $this->users->where('email', $email)->first();
    $want = ['is_admin' => 1, 'is_active' => 1, 'pmc_id' => null];
    if ($row === null)
    {
      $id = (int)$this->users->insert($want + ['email' => $email, 'name' => $listed[$email] !== '' ? $listed[$email] : (string)$name], true);
      return $this->users->withPmcById($id);
    }
    if (trim((string)$row['name']) === '' && ($listed[$email] !== '' || (string)$name !== ''))
    {
      $want['name'] = $listed[$email] !== '' ? $listed[$email] : (string)$name;
    }
    $diff = array_filter($want, static fn($v, string $k): bool => ($row[$k] ?? null) !== $v && (string)($row[$k] ?? '') !== (string)$v, ARRAY_FILTER_USE_BOTH);
    if ($diff !== [])
    {
      $this->users->update((int)$row['id'], $diff);
    }
    return $this->users->withPmcById((int)$row['id']);
  }
  /** Same, for an account already identified by id (a code or link that checked out). */
  public function forUserId(int $id): ?array
  {
    $row = $this->users->find($id);
    return $row === null ? null : $this->forSignIn((string)$row['email']);
  }
  /**
   * The account behind a Microsoft sign-in. Only WHPC's tenant is enrolled today; a token
   * from any other tenant is refused until PMC tenants are enrolled.
   */
  public function forMicrosoft(string $tenantId, string $email, ?string $name = null): ?array
  {
    if ($tenantId === '' || !hash_equals(mb_strtolower($this->cfg->microsoftTenant), mb_strtolower($tenantId)))
    {
      return null;
    }
    if (!$this->cfg->isWhpc($email))
    {
      return null;
    }
    return $this->forSignIn($email, $name);
  }
  /** True when $email is a configured WHPC admin, whose account is not managed by hand. */
  public function isConfiguredAdmin(string $email): bool
  {
    return array_key_exists(mb_strtolower(trim($email)), $this->cfg->admins());
  }
}
