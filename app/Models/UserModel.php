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
  protected $allowedFields = ['email', 'name', 'company', 'is_admin', 'is_active', 'last_login_at'];
  public function activeByEmail(string $email): ?array
  {
    $row = $this->where('email', mb_strtolower(trim($email)))->where('is_active', 1)->first();
    return $row ?: null;
  }
}
