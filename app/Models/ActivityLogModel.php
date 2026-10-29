<?php

namespace App\Models;

use CodeIgniter\Model;

/** Who did what, when, from where. Sign-ins first; the portal's own actions as they arrive. */
class ActivityLogModel extends Model
{
  protected $table = 'activity_logs';
  protected $primaryKey = 'id';
  protected $returnType = 'array';
  protected $useTimestamps = true;
  protected $updatedField = '';
  protected $allowedFields = ['user_id', 'email', 'action', 'detail', 'ip_address', 'user_agent'];
  public static function record(string $action, ?int $userId = null, ?string $email = null, ?string $detail = null): void
  {
    $req = service('request');
    (new self())->insert([
      'user_id' => $userId,
      'email' => $email,
      'action' => $action,
      'detail' => $detail,
      'ip_address' => method_exists($req, 'getIPAddress') ? $req->getIPAddress() : null,
      'user_agent' => method_exists($req, 'getUserAgent') ? mb_substr((string)$req->getUserAgent(), 0, 255) : null,
    ]);
  }
}
