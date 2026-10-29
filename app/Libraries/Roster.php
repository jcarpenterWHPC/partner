<?php

namespace App\Libraries;

use App\Models\PropertyModel;
use App\Models\StaffChangeModel;
use App\Models\StaffModel;
use Config\Partner;

/**
 * One PMC's staff roster: the rules for adding and changing a person, and the history
 * every change leaves behind. Nothing is ever deleted — a person who leaves gets an end
 * date — because the history is what will later update Assets' contacts.
 *
 * Scoped to one PMC and one acting user at construction, so a caller cannot reach
 * another company's people by mistake.
 */
class Roster
{
  public const FIELDS = ['name', 'position', 'title', 'email', 'mobile_phone', 'office_phone', 'start_date', 'end_date', 'notes'];
  private Partner $cfg;
  private StaffModel $staff;
  private PropertyModel $props;
  private StaffChangeModel $changes;
  public function __construct(private int $pmcId, private ?int $actorId, ?Partner $cfg = null)
  {
    $this->cfg = $cfg ?? config(Partner::class);
    $this->staff = new StaffModel();
    $this->props = new PropertyModel();
    $this->changes = new StaffChangeModel();
  }
  /** A person of THIS PMC, with their properties, or null. */
  public function person(int $staffId): ?array
  {
    $row = $this->staff->withProperties($staffId);
    return $row !== null && (int)$row['pmc_id'] === $this->pmcId ? $row : null;
  }
  /** The form's input, trimmed and normalised; `properties` is a list of property numbers. */
  public function clean(array $in): array
  {
    $out = [];
    foreach (self::FIELDS as $f)
    {
      $v = $in[$f] ?? '';
      $v = is_string($v) ? trim($v) : '';
      $out[$f] = $v;
    }
    $out['email'] = mb_strtolower($out['email']);
    foreach (['start_date', 'end_date', 'notes'] as $f)
    {
      if ($out[$f] === '')
      {
        $out[$f] = null;
      }
    }
    $props = $in['properties'] ?? [];
    $props = is_array($props) ? $props : [];
    $out['properties'] = array_values(array_unique(array_filter(array_map(static fn($p): string => trim((string)$p), $props), static fn(string $p): bool => $p !== '')));
    sort($out['properties']);
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
    if (!in_array($clean['position'], $this->cfg->positionNames(), true))
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
    $allowed = array_column($this->props->forPmc($this->pmcId), 'property_number');
    if ($staffId !== null && ($have = $this->person($staffId)) !== null)
    {
      // A property this person already covers stays valid even once Assets has retired it.
      $allowed = array_merge($allowed, $have['properties']);
    }
    $bad = array_diff($clean['properties'], array_map('strval', $allowed));
    if ($bad !== [])
    {
      $e['properties'] = 'Only this company\'s properties can be assigned.';
    }
    return $e;
  }
  /** Add a person; returns the new id. $clean must have passed validate(). */
  public function add(array $clean): int
  {
    $db = $this->staff->db;
    $db->transStart();
    $id = (int)$this->staff->insert(['pmc_id' => $this->pmcId, 'created_by' => $this->actorId, 'updated_by' => $this->actorId] + array_intersect_key($clean, array_flip(self::FIELDS)), true);
    $this->staff->setProperties($id, $clean['properties']);
    $this->changes->insert(['staff_id' => $id, 'pmc_id' => $this->pmcId, 'user_id' => $this->actorId, 'action' => 'added', 'changes' => json_encode(array_intersect_key($clean, array_flip(self::FIELDS)) + ['properties' => $clean['properties']], JSON_UNESCAPED_UNICODE)]);
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
      $this->staff->setProperties($staffId, $clean['properties']);
    }
    $this->changes->insert(['staff_id' => $staffId, 'pmc_id' => $this->pmcId, 'user_id' => $this->actorId, 'action' => $action, 'changes' => json_encode($diff, JSON_UNESCAPED_UNICODE)]);
    $db->transComplete();
    return ['action' => $action, 'changes' => $diff];
  }
  /** field => [from, to] for every field that differs; properties compared as sets. */
  public static function diff(array $old, array $new): array
  {
    $out = [];
    foreach (self::FIELDS as $f)
    {
      $a = $old[$f] ?? null;
      $b = $new[$f] ?? null;
      $a = $a === '' ? null : $a;
      $b = $b === '' ? null : $b;
      if ((string)$a !== (string)$b)
      {
        $out[$f] = ['from' => $a, 'to' => $b];
      }
    }
    $pa = array_map('strval', $old['properties'] ?? []);
    $pb = array_map('strval', $new['properties'] ?? []);
    sort($pa);
    sort($pb);
    if ($pa !== $pb)
    {
      $out['properties'] = ['from' => $pa, 'to' => $pb];
    }
    return $out;
  }
  private static function isDate(string $v): bool
  {
    $d = \DateTime::createFromFormat('!Y-m-d', $v);
    return $d !== false && $d->format('Y-m-d') === $v;
  }
}
