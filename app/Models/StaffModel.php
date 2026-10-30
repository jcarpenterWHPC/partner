<?php

namespace App\Models;

use CodeIgniter\Model;

class StaffModel extends Model
{
  protected $table = 'staff';
  protected $primaryKey = 'id';
  protected $returnType = 'array';
  protected $useTimestamps = true;
  protected $allowedFields = ['pmc_id', 'contact_id', 'name', 'position', 'title', 'email', 'email_na', 'mobile_phone', 'office_phone', 'start_date', 'end_date', 'notes', 'synced_properties', 'synced_at', 'created_by', 'updated_by'];
  /**
   * One organisation's roster: current people first, departed last, each with the
   * property numbers they cover (`properties`) and the role at each (`roles`).
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
  /** One person with their properties and roles, or null. */
  public function withProperties(int $id): ?array
  {
    $row = $this->find($id);
    return $row ? $this->attachProperties([$row])[0] : null;
  }
  /**
   * Replace a person's assignments.
   *
   * @param array<string,string> $roles property number => role
   */
  public function setProperties(int $staffId, array $roles): void
  {
    $db = $this->db;
    $db->table('staff_properties')->where('staff_id', $staffId)->delete();
    $rows = [];
    $now = date('Y-m-d H:i:s');
    foreach (self::roleMap($roles) as $pn => $role)
    {
      $rows[] = ['staff_id' => $staffId, 'property_number' => (string)$pn, 'role' => $role, 'created_at' => $now];
    }
    if ($rows !== [])
    {
      $db->table('staff_properties')->insertBatch($rows);
    }
  }
  /** Who currently holds $role at $pn for this organisation (ids). */
  public function holding(int $pmcId, string $pn, string $role): array
  {
    return array_map('intval', array_column($this->db->table('staff_properties')->select('staff.id')
      ->join('staff', 'staff.id = staff_properties.staff_id')
      ->where('staff.pmc_id', $pmcId)->where('staff.end_date', null)
      ->where('staff_properties.property_number', $pn)->where('staff_properties.role', $role)
      ->get()->getResultArray(), 'id'));
  }
  /**
   * A role map tidied: trimmed, empty numbers dropped, sorted by number. PHP turns
   * numeric string keys into ints, so callers cast keys back with (string).
   *
   * @param array<string,string> $roles property number => role
   * @return array<string,string>
   */
  public static function roleMap(array $roles): array
  {
    $out = [];
    foreach ($roles as $pn => $role)
    {
      $pn = trim((string)$pn);
      if ($pn !== '')
      {
        $out[$pn] = trim((string)$role);
      }
    }
    ksort($out, SORT_STRING);
    return $out;
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
      $by[(int)$l['staff_id']][(string)$l['property_number']] = (string)$l['role'];
    }
    foreach ($rows as $i => $r)
    {
      $roles = $by[(int)$r['id']] ?? [];
      ksort($roles, SORT_STRING);
      $rows[$i]['roles'] = $roles;
      $rows[$i]['properties'] = array_map('strval', array_keys($roles));
    }
    return $rows;
  }
}
