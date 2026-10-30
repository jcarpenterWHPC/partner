<?php

namespace App\Libraries;

use App\Models\StaffModel;
use App\Models\VacancyModel;

/**
 * Keeps the vacancy list true to the roster. After any change to who holds what, each
 * touched property × role is settled: somebody holds it, so any open vacancy is filled;
 * or nobody does, so a vacancy is opened (once) as of the date the last person left.
 * Only the roles an organisation is expected to fill (Config\Partner::$propertyRoles)
 * become vacancies; a departed leasing agent is not a hole to flag.
 */
class Vacancies
{
  private StaffModel $staff;
  private VacancyModel $vacancies;
  /** @param list<string> $expected roles that count as vacancies for this organisation */
  public function __construct(private int $pmcId, private ?int $actorId, private array $expected)
  {
    $this->staff = new StaffModel();
    $this->vacancies = new VacancyModel();
  }
  /**
   * @param array<string,string> $before property number => role the person had
   * @param array<string,string> $after  property number => role the person has now (empty when departed)
   * @param string|null $since the day the position went open, when it did
   */
  public function settle(array $before, array $after, ?string $since): void
  {
    $pairs = [];
    foreach ([$before, $after] as $map)
    {
      foreach ($map as $pn => $role)
      {
        $pairs[(string)$pn . '|' . $role] = [(string)$pn, (string)$role];
      }
    }
    foreach ($pairs as [$pn, $role])
    {
      $this->settleOne($pn, $role, $since);
    }
  }
  public function settleOne(string $pn, string $role, ?string $since): void
  {
    if (!in_array($role, $this->expected, true))
    {
      return;
    }
    $open = $this->vacancies->openOne($this->pmcId, $pn, $role);
    if ($this->staff->holding($this->pmcId, $pn, $role) !== [])
    {
      if ($open !== null)
      {
        $this->vacancies->update((int)$open['id'], ['filled_at' => date('Y-m-d H:i:s'), 'updated_by' => $this->actorId]);
      }
      return;
    }
    if ($open === null)
    {
      $this->vacancies->insert(['pmc_id' => $this->pmcId, 'property_number' => $pn, 'role' => $role, 'open_since' => $since ?? date('Y-m-d'), 'coverage' => '', 'created_by' => $this->actorId, 'updated_by' => $this->actorId]);
    }
  }
}
