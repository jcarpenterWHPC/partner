<?php

namespace App\Models;

use CodeIgniter\Model;

class LoginCodeModel extends Model
{
  protected $table = 'login_codes';
  protected $primaryKey = 'id';
  protected $returnType = 'array';
  protected $useTimestamps = false;
  protected $allowedFields = ['user_id', 'code_hash', 'token_hash', 'attempts', 'expires_at', 'consumed_at', 'created_at', 'ip_address'];
  public function latestActiveFor(int $userId): ?array
  {
    $row = $this->where('user_id', $userId)->where('consumed_at', null)->orderBy('id', 'DESC')->first();
    return $row ?: null;
  }
  public function latestFor(int $userId): ?array
  {
    $row = $this->where('user_id', $userId)->orderBy('id', 'DESC')->first();
    return $row ?: null;
  }
  public function activeByTokenHash(string $hash): ?array
  {
    $row = $this->where('token_hash', $hash)->where('consumed_at', null)->first();
    return $row ?: null;
  }
  public function invalidateAllFor(int $userId): void
  {
    $this->where('user_id', $userId)->where('consumed_at', null)->set(['consumed_at' => date('Y-m-d H:i:s')])->update();
  }
  public function consume(int $id): void
  {
    $this->update($id, ['consumed_at' => date('Y-m-d H:i:s')]);
  }
}
