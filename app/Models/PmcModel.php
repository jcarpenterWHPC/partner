<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * The partner organisations. A management company (Assets' management_co) owns
 * properties; a service agency staffs other companies' properties.
 */
class PmcModel extends Model
{
  public const MANAGEMENT = 'management';
  public const SERVICES = 'services';
  public const KINDS = [self::MANAGEMENT => 'Management company', self::SERVICES => 'Service agency'];
  protected $table = 'pmcs';
  protected $primaryKey = 'id';
  protected $returnType = 'array';
  protected $useTimestamps = true;
  protected $allowedFields = ['name', 'kind', 'is_active'];
  public function byName(string $name): ?array
  {
    $row = $this->where('name', trim($name))->first();
    return $row ?: null;
  }
  /** The id for $name, inserting it (as a management company) if new. */
  public function idFor(string $name, string $kind = self::MANAGEMENT): int
  {
    $row = $this->byName($name);
    return $row ? (int)$row['id'] : (int)$this->insert(['name' => trim($name), 'kind' => $kind, 'is_active' => 1], true);
  }
  public function active(): array
  {
    return $this->where('is_active', 1)->orderBy('name')->findAll();
  }
  public static function isServices(array $org): bool
  {
    return ($org['kind'] ?? self::MANAGEMENT) === self::SERVICES;
  }
}
