<?php

namespace App\Models;

use CodeIgniter\Model;

class PmcModel extends Model
{
  protected $table = 'pmcs';
  protected $primaryKey = 'id';
  protected $returnType = 'array';
  protected $useTimestamps = true;
  protected $allowedFields = ['name', 'is_active'];
  public function byName(string $name): ?array
  {
    $row = $this->where('name', trim($name))->first();
    return $row ?: null;
  }
  /** The id for $name, inserting it if new. */
  public function idFor(string $name): int
  {
    $row = $this->byName($name);
    return $row ? (int)$row['id'] : (int)$this->insert(['name' => trim($name), 'is_active' => 1], true);
  }
  public function active(): array
  {
    return $this->where('is_active', 1)->orderBy('name')->findAll();
  }
}
