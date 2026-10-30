<?php

namespace App\Models;

use CodeIgniter\Model;

/** Portal accounts: people at the property management companies, and WHPC admins. */
class UserModel extends Model
{
  protected $table = 'users';
  protected $primaryKey = 'id';
  protected $returnType = 'array';
  protected $useTimestamps = true;
  protected $allowedFields = ['email', 'name', 'pmc_id', 'is_admin', 'is_active', 'last_login_at'];
  public function activeByEmail(string $email): ?array
  {
    $row = $this->withPmc()->where('users.email', mb_strtolower(trim($email)))->where('users.is_active', 1)->first();
    return $row ?: null;
  }
  /** One account with its PMC's name, or null. */
  public function withPmcById(int $id): ?array
  {
    $row = $this->withPmc()->where('users.id', $id)->first();
    return $row ?: null;
  }
  /** Every account with its PMC's name, admins first. */
  /**
   * Accounts that must stay: they signed in, or are the author of a roster change, a
   * person's record or a vacancy. Deleting one would orphan that history.
   *
   * @return array<int,true> user id => true
   */
  public function withHistory(): array
  {
    $db = $this->db;
    $ids = [];
    foreach ([
      ['staff_changes', 'user_id'], ['staff', 'created_by'], ['staff', 'updated_by'],
      ['vacancies', 'created_by'], ['vacancies', 'updated_by'], ['activity_logs', 'user_id'],
    ] as [$table, $col])
    {
      foreach ($db->table($table)->distinct()->select($col)->where($col . ' IS NOT NULL')->get()->getResultArray() as $r)
      {
        $ids[(int)$r[$col]] = true;
      }
    }
    return $ids;
  }
  public function hasHistory(int $id): bool
  {
    return isset($this->withHistory()[$id]);
  }
  public function roster(): array
  {
    return $this->withPmc()->orderBy('users.is_admin', 'DESC')->orderBy('pmcs.name', 'ASC')->orderBy('users.name', 'ASC')->findAll();
  }
  private function withPmc(): self
  {
    return $this->select('users.*, pmcs.name AS pmc_name')->join('pmcs', 'pmcs.id = users.pmc_id', 'left');
  }
}
