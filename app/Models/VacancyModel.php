<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * A property × role nobody of the organisation holds: since when, and who covers it
 * in the meantime. Filled when someone is assigned the role again; never deleted.
 */
class VacancyModel extends Model
{
  protected $table = 'vacancies';
  protected $primaryKey = 'id';
  protected $returnType = 'array';
  protected $useTimestamps = true;
  protected $allowedFields = ['pmc_id', 'property_number', 'role', 'open_since', 'coverage', 'filled_at', 'created_by', 'updated_by'];
  /** @return array<string,array> "number|role" => the open vacancy */
  public function openFor(int $pmcId): array
  {
    $out = [];
    foreach ($this->where('pmc_id', $pmcId)->where('filled_at', null)->findAll() as $v)
    {
      $out[self::key((string)$v['property_number'], (string)$v['role'])] = $v;
    }
    return $out;
  }
  public function openOne(int $pmcId, string $pn, string $role): ?array
  {
    $row = $this->where('pmc_id', $pmcId)->where('property_number', $pn)->where('role', $role)->where('filled_at', null)->first();
    return $row ?: null;
  }
  public static function key(string $pn, string $role): string
  {
    return $pn . '|' . $role;
  }
}
