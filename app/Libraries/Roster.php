<?php

namespace App\Libraries;

use App\Models\PmcModel;
use App\Models\PropertyModel;
use App\Models\StaffChangeModel;
use App\Models\StaffModel;
use Config\Partner;

/**
 * One organisation's staff roster: the rules for adding and changing a person, and the
 * history every change leaves behind. Nothing is ever deleted — a person who leaves gets
 * an end date — because the history is what will later update Assets' contacts.
 *
 * A person has a main position and a role at each property they cover (the position
 * unless said otherwise), which is Assets' own shape. A management company's people
 * cover its properties; a service agency's people may cover anyone's.
 *
 * Scoped to one organisation and one acting user at construction, so a caller cannot
 * reach another company's people by mistake.
 */
class Roster
{
  public const FIELDS = ['name', 'position', 'title', 'email', 'email_na', 'mobile_phone', 'office_phone', 'start_date', 'end_date', 'notes'];
  private Partner $cfg;
  private array $org;
  private StaffModel $staff;
  private PropertyModel $props;
  private StaffChangeModel $changes;
  private Vacancies $vacancies;
  public function __construct(private int $pmcId, private ?int $actorId, ?Partner $cfg = null)
  {
    $this->cfg = $cfg ?? config(Partner::class);
    $this->org = (new PmcModel())->find($pmcId) ?? ['id' => $pmcId, 'kind' => PmcModel::MANAGEMENT];
    $this->staff = new StaffModel();
    $this->props = new PropertyModel();
    $this->changes = new StaffChangeModel();
    $this->vacancies = new Vacancies($pmcId, $actorId, $this->cfg->propertyRoles[$this->org['kind'] ?? PmcModel::MANAGEMENT] ?? []);
  }
  /** A person of THIS organisation, with their properties and roles, or null. */
  public function person(int $staffId): ?array
  {
    $row = $this->staff->withProperties($staffId);
    return $row !== null && (int)$row['pmc_id'] === $this->pmcId ? $row : null;
  }
  /**
   * The form's input, trimmed and normalised. `properties` is the list of property
   * numbers; `roles` the role at each, the main position where the form left it blank.
   */
  public function clean(array $in): array
  {
    $out = [];
    foreach (self::FIELDS as $f)
    {
      $v = $in[$f] ?? '';
      $out[$f] = is_string($v) ? trim($v) : '';
    }
    $out['email'] = mb_strtolower($out['email']);
    $out['email_na'] = empty($in['email_na']) ? 0 : 1;
    if ($out['email_na'] === 1)
    {
      $out['email'] = '';
    }
    foreach (['start_date', 'end_date', 'notes'] as $f)
    {
      if ($out[$f] === '')
      {
        $out[$f] = null;
      }
    }
    $props = $in['properties'] ?? [];
    $props = is_array($props) ? $props : [];
    $given = is_array($in['roles'] ?? null) ? $in['roles'] : [];
    $roles = [];
    foreach ($props as $p)
    {
      $p = trim((string)$p);
      if ($p === '')
      {
        continue;
      }
      $r = trim((string)($given[$p] ?? ''));
      $roles[$p] = $r !== '' ? $r : $out['position'];
    }
    $out['roles'] = StaffModel::roleMap($roles);
    $out['properties'] = array_map('strval', array_keys($out['roles']));
    return $out;
  }
  /**
   * Why $clean cannot be saved, field => message; empty when it can.
   *
   * @return array<string,string>
   */
  public function validate(array $clean, ?int $staffId = null): array
  {
    $e = [];
    if ($clean['name'] === '')
    {
      $e['name'] = 'Enter the person\'s name.';
    }
    elseif (mb_strlen($clean['name']) > 255)
    {
      $e['name'] = 'The name is too long.';
    }
    $positions = $this->cfg->positionNames();
    if (!in_array($clean['position'], $positions, true))
    {
      $e['position'] = 'Choose a position.';
    }
    if (mb_strlen($clean['title']) > 255)
    {
      $e['title'] = 'The title is too long.';
    }
    if ($clean['email'] !== '' && filter_var($clean['email'], FILTER_VALIDATE_EMAIL) === false)
    {
      $e['email'] = 'That email address does not look right.';
    }
    foreach (['mobile_phone', 'office_phone'] as $f)
    {
      if (mb_strlen($clean[$f]) > 40)
      {
        $e[$f] = 'That phone number is too long.';
      }
    }
    foreach (['start_date', 'end_date'] as $f)
    {
      if ($clean[$f] !== null && !self::isDate($clean[$f]))
      {
        $e[$f] = 'Enter a date as YYYY-MM-DD.';
      }
    }
    if (!isset($e['start_date'], $e['end_date']) && $clean['start_date'] !== null && $clean['end_date'] !== null && $clean['end_date'] < $clean['start_date'])
    {
      $e['end_date'] = 'The end date is before the start date.';
    }
    $allowed = array_column($this->props->forOrg($this->org), 'property_number');
    if ($staffId !== null && ($have = $this->person($staffId)) !== null)
    {
      // A property this person already covers stays valid even once Assets has retired it.
      $allowed = array_merge($allowed, $have['properties']);
    }
    $bad = array_diff($clean['properties'], array_map('strval', $allowed));
    if ($bad !== [])
    {
      $e['properties'] = PmcModel::isServices($this->org) ? 'Choose properties from the list.' : 'Only this company\'s properties can be assigned.';
    }
    foreach ($clean['roles'] as $role)
    {
      if ($role !== $clean['position'] && !in_array($role, $positions, true))
      {
        $e['roles'] = 'Choose a position for each property.';
        break;
      }
    }
    return $e;
  }
  /** Add a person; returns the new id. $clean must have passed validate(). */
  public function add(array $clean): int
  {
    $db = $this->staff->db;
    $db->transStart();
    $id = (int)$this->staff->insert(['pmc_id' => $this->pmcId, 'created_by' => $this->actorId, 'updated_by' => $this->actorId] + array_intersect_key($clean, array_flip(self::FIELDS)), true);
    $this->staff->setProperties($id, $clean['roles']);
    $this->changes->insert(['staff_id' => $id, 'pmc_id' => $this->pmcId, 'user_id' => $this->actorId, 'action' => 'added', 'changes' => json_encode(array_intersect_key($clean, array_flip(self::FIELDS)) + ['properties' => self::rolesForLog($clean['roles'])], JSON_UNESCAPED_UNICODE)]);
    $this->vacancies->settle([], $clean['end_date'] === null ? $clean['roles'] : [], null);
    $db->transComplete();
    return $id;
  }
  /**
   * Change a person. Returns what was recorded: action (updated | departed |
   * reinstated | unchanged) and the field diffs.
   *
   * @return array{action:string, changes:array<string,array{from:mixed,to:mixed}>}
   */
  public function update(int $staffId, array $clean): array
  {
    $old = $this->person($staffId);
    if ($old === null)
    {
      throw new \InvalidArgumentException('Not one of this company\'s people.');
    }
    $diff = self::diff($old, $clean);
    if ($diff === [])
    {
      return ['action' => 'unchanged', 'changes' => []];
    }
    $action = 'updated';
    if (isset($diff['end_date']))
    {
      $action = $diff['end_date']['to'] !== null && $diff['end_date']['from'] === null ? 'departed' : ($diff['end_date']['to'] === null ? 'reinstated' : 'updated');
    }
    $db = $this->staff->db;
    $db->transStart();
    $this->staff->update($staffId, ['updated_by' => $this->actorId] + array_intersect_key($clean, array_flip(self::FIELDS)));
    if (isset($diff['properties']))
    {
      $this->staff->setProperties($staffId, $clean['roles']);
    }
    $this->changes->insert(['staff_id' => $staffId, 'pmc_id' => $this->pmcId, 'user_id' => $this->actorId, 'action' => $action, 'changes' => json_encode($diff, JSON_UNESCAPED_UNICODE)]);
    $wasCurrent = $old['end_date'] === null;
    $isCurrent = $clean['end_date'] === null;
    $this->vacancies->settle($wasCurrent ? $old['roles'] : [], $isCurrent ? $clean['roles'] : [], $isCurrent ? null : $clean['end_date']);
    $db->transComplete();
    return ['action' => $action, 'changes' => $diff];
  }
  /** field => [from, to] for every field that differs; `properties` as number => role maps. */
  public static function diff(array $old, array $new): array
  {
    $out = [];
    foreach (self::FIELDS as $f)
    {
      $a = $old[$f] ?? null;
      $b = $new[$f] ?? null;
      $a = $a === '' ? null : $a;
      $b = $b === '' ? null : $b;
      if ($f === 'email_na')
      {
        $a = (int)$a;
        $b = (int)$b;
      }
      if ((string)$a !== (string)$b)
      {
        $out[$f] = ['from' => $a, 'to' => $b];
      }
    }
    $ra = self::rolesForLog(self::rolesOf($old));
    $rb = self::rolesForLog(self::rolesOf($new));
    if ($ra !== $rb)
    {
      $out['properties'] = ['from' => $ra, 'to' => $rb];
    }
    return $out;
  }
  /** A person's number => role map, built from `properties` + `position` when only those are given. */
  public static function rolesOf(array $person): array
  {
    if (isset($person['roles']) && is_array($person['roles']))
    {
      return StaffModel::roleMap($person['roles']);
    }
    return StaffModel::roleMap(array_fill_keys(array_map('strval', $person['properties'] ?? []), (string)($person['position'] ?? '')));
  }
  /**
   * The map as it goes into the change log: string keys, so JSON keeps it an object
   * and older entries (plain lists of numbers) stay distinguishable.
   *
   * @return array<string,string>
   */
  public static function rolesForLog(array $roles): array
  {
    $out = [];
    foreach (StaffModel::roleMap($roles) as $pn => $role)
    {
      $out['#' . $pn] = $role;
    }
    return $out;
  }
  /** Back from the change-log form (or an older plain list) to number => role. */
  public static function rolesFromLog($logged, string $position = ''): array
  {
    $out = [];
    foreach (is_array($logged) ? $logged : [] as $k => $v)
    {
      if (is_int($k))
      {
        $out[(string)$v] = $position;
      }
      else
      {
        $out[ltrim((string)$k, '#')] = (string)$v;
      }
    }
    return $out;
  }
  private static function isDate(string $v): bool
  {
    $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $v);
    return $d !== false && $d->format('Y-m-d') === $v;
  }
}
