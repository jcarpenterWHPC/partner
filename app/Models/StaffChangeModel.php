<?php

namespace App\Models;

use CodeIgniter\Model;

class StaffChangeModel extends Model
{
  protected $table = 'staff_changes';
  protected $primaryKey = 'id';
  protected $returnType = 'array';
  protected $useTimestamps = true;
  protected $updatedField = '';
  protected $allowedFields = ['staff_id', 'pmc_id', 'user_id', 'action', 'changes'];
  public function forStaff(int $staffId): array
  {
    return $this->where('staff_id', $staffId)->orderBy('id', 'DESC')->findAll();
  }
  /** The latest changes across one PMC, or all PMCs when null. */
  public function recent(?int $pmcId, int $limit = 50): array
  {
    $q = $this->select('staff_changes.*, staff.name AS staff_name, pmcs.name AS pmc_name, users.name AS user_name')
      ->join('staff', 'staff.id = staff_changes.staff_id', 'left')
      ->join('pmcs', 'pmcs.id = staff_changes.pmc_id', 'left')
      ->join('users', 'users.id = staff_changes.user_id', 'left');
    if ($pmcId !== null)
    {
      $q->where('staff_changes.pmc_id', $pmcId);
    }
    return $q->orderBy('staff_changes.id', 'DESC')->findAll($limit);
  }
  /** @return array<int,int> user id => roster changes they made */
  public function countByUser(): array
  {
    $out = [];
    foreach ($this->select('user_id, COUNT(*) AS n')->where('user_id IS NOT NULL')->groupBy('user_id')->findAll() as $r)
    {
      $out[(int)$r['user_id']] = (int)$r['n'];
    }
    return $out;
  }
}
