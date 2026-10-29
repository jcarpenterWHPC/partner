<?php

namespace App\Models;

use CodeIgniter\Model;

class StaffModel extends Model
{
  protected $table = 'staff';
  protected $primaryKey = 'id';
  protected $returnType = 'array';
  protected $useTimestamps = true;
  protected $allowedFields = ['pmc_id', 'contact_id', 'name', 'position', 'title', 'email', 'mobile_phone', 'office_phone', 'start_date', 'end_date', 'notes', 'synced_properties', 'synced_at', 'created_by', 'updated_by'];
  /**
   * One PMC's roster: current people first, departed last, each with the property
   * numbers they cover.
   *
   * @return list<array>
   */
  public function forPmc(int $pmcId, bool $includeDeparted = true): array
  {
    $q = $this->where('pmc_id', $pmcId);
    if (!$includeDeparted)
    {
      $q->where('end_date', null);
    }
    $rows = $q->orderBy('end_date IS NOT NULL', '', false)->orderBy('name')->findAll();
    return $this->attachProperties($rows);
  }
  /** One person with their property numbers, or null. */
  public function withProperties(int $id): ?array
  {
    $row = $this->find($id);
    return $row ? $this->attachProperties([$row])[0] : null;
  }
  /** @param list<string> $numbers */
  public function setProperties(int $staffId, array $numbers): void
  {
    $db = $this->db;
    $db->table('staff_properties')->where('staff_id', $staffId)->delete();
    $numbers = array_values(array_unique(array_filter(array_map('strval', $numbers))));
    if ($numbers !== [])
    {
      $now = date('Y-m-d H:i:s');
      $db->table('staff_properties')->insertBatch(array_map(static fn(string $n): array => ['staff_id' => $staffId, 'property_number' => $n, 'created_at' => $now], $numbers));
    }
  }
  /** @param list<array> $rows */
  private function attachProperties(array $rows): array
  {
    if ($rows === [])
    {
      return [];
    }
    $links = $this->db->table('staff_properties')->whereIn('staff_id', array_column($rows, 'id'))->orderBy('property_number')->get()->getResultArray();
    $by = [];
    foreach ($links as $l)
    {
      $by[(int)$l['staff_id']][] = (string)$l['property_number'];
    }
    foreach ($rows as $i => $r)
    {
      $rows[$i]['properties'] = $by[(int)$r['id']] ?? [];
    }
    return $rows;
  }
}
