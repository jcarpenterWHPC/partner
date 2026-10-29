<?php

namespace App\Libraries;

use App\Models\PmcModel;
use App\Models\PropertyModel;
use App\Models\StaffChangeModel;
use App\Models\StaffModel;
use Config\Partner;

/**
 * Seed and refresh the rosters from the intranet's live property contacts.
 *
 * Rows are the `partner_property_contacts` view: one per (contact, property, role).
 * A contact becomes one roster entry under the PMC that manages their properties,
 * with the position their commonest role maps to (Config\Partner::$contactRoles).
 * On a later run the sync fills fields the roster still has blank and adds properties
 * Assets has newly given the person; it never overwrites what a PMC entered here, and
 * never removes a property the PMC removed. A contact that has gone from the view is
 * marked departed. Every write is a `staff_changes` row with no user (system).
 */
class ContactSync
{
  /**
   * @param list<array<string,mixed>> $rows
   * @return array{people_added:int, people_updated:int, people_departed:int, unchanged:int, skipped_roles:array<string,int>, no_property:int, multi_pmc:int}
   */
  public static function apply(array $rows, bool $dryRun = false, ?Partner $cfg = null): array
  {
    $cfg ??= config(Partner::class);
    $out = ['people_added' => 0, 'people_updated' => 0, 'people_departed' => 0, 'unchanged' => 0, 'skipped_roles' => [], 'no_property' => 0, 'multi_pmc' => 0];
    $props = [];
    foreach ((new PropertyModel())->findAll() as $p)
    {
      $props[(string)$p['property_number']] = (int)$p['pmc_id'];
    }
    $pmcNames = [];
    foreach ((new PmcModel())->findAll() as $p)
    {
      $pmcNames[(int)$p['id']] = mb_strtolower(trim((string)$p['name']));
    }
    $staff = new StaffModel();
    $changes = new StaffChangeModel();
    $positions = array_flip($cfg->positionNames());
    $contacts = [];
    foreach ($rows as $r)
    {
      $cid = (int)($r['contact_id'] ?? 0);
      $role = trim((string)($r['role'] ?? ''));
      $pn = trim((string)($r['property_number'] ?? ''));
      if ($cid === 0)
      {
        continue;
      }
      $position = $cfg->contactRoles[$role] ?? null;
      if ($position === null || !isset($positions[$position]))
      {
        $out['skipped_roles'][$role] = ($out['skipped_roles'][$role] ?? 0) + 1;
        continue;
      }
      if (!isset($props[$pn]))
      {
        $out['no_property']++;
        continue;
      }
      $c = &$contacts[$cid];
      $c ??= ['name' => '', 'title' => '', 'company' => '', 'email' => '', 'mobile_phone' => '', 'office_phone' => '', 'positions' => [], 'by_pmc' => []];
      foreach (['name' => 'name', 'title' => 'job_title', 'company' => 'company', 'email' => 'email', 'mobile_phone' => 'mobile_phone', 'office_phone' => 'business_phone'] as $ours => $theirs)
      {
        if ($c[$ours] === '' && trim((string)($r[$theirs] ?? '')) !== '')
        {
          $c[$ours] = trim((string)$r[$theirs]);
        }
      }
      $c['positions'][$position] = ($c['positions'][$position] ?? 0) + 1;
      $c['by_pmc'][$props[$pn]][$pn] = true;
      unset($c);
    }
    $now = date('Y-m-d H:i:s');
    $seen = [];
    foreach ($contacts as $cid => $c)
    {
      $pmcId = self::pickPmc($c['by_pmc'], $pmcNames, $c['company']);
      if (count($c['by_pmc']) > 1)
      {
        $out['multi_pmc']++;
      }
      $assetsProps = array_map('strval', array_keys($c['by_pmc'][$pmcId]));
      sort($assetsProps);
      arsort($c['positions']);
      $position = (string)array_key_first($c['positions']);
      $email = mb_strtolower($c['email']);
      $seen[$cid] = true;
      $row = $staff->where('contact_id', $cid)->first();
      if ($row === null)
      {
        $out['people_added']++;
        if ($dryRun)
        {
          continue;
        }
        $record = ['name' => $c['name'] !== '' ? $c['name'] : 'Contact ' . $cid, 'position' => $position, 'title' => $c['title'], 'email' => $email, 'mobile_phone' => $c['mobile_phone'], 'office_phone' => $c['office_phone'], 'start_date' => null, 'end_date' => null, 'notes' => null];
        $db = $staff->db;
        $db->transStart();
        $id = (int)$staff->insert($record + ['pmc_id' => $pmcId, 'contact_id' => $cid, 'synced_properties' => json_encode($assetsProps), 'synced_at' => $now], true);
        $staff->setProperties($id, $assetsProps);
        $changes->insert(['staff_id' => $id, 'pmc_id' => $pmcId, 'user_id' => null, 'action' => 'imported', 'changes' => json_encode($record + ['properties' => $assetsProps], JSON_UNESCAPED_UNICODE)]);
        $db->transComplete();
        continue;
      }
      $row = $staff->withProperties((int)$row['id']);
      $diff = [];
      foreach (['title' => $c['title'], 'email' => $email, 'mobile_phone' => $c['mobile_phone'], 'office_phone' => $c['office_phone']] as $f => $v)
      {
        if (trim((string)$row[$f]) === '' && $v !== '')
        {
          $diff[$f] = ['from' => $row[$f], 'to' => $v];
        }
      }
      if (trim((string)$row['name']) === '' && $c['name'] !== '')
      {
        $diff['name'] = ['from' => $row['name'], 'to' => $c['name']];
      }
      $last = json_decode((string)$row['synced_properties'], true) ?: [];
      $new = array_values(array_diff($assetsProps, $last, $row['properties']));
      if ($new !== [])
      {
        $want = array_values(array_unique(array_merge($row['properties'], $new)));
        sort($want);
        $diff['properties'] = ['from' => $row['properties'], 'to' => $want];
      }
      if ($row['end_date'] !== null)
      {
        $diff['end_date'] = ['from' => $row['end_date'], 'to' => null];
      }
      if ($diff === [])
      {
        $out['unchanged']++;
        if (!$dryRun && json_encode($assetsProps) !== (string)$row['synced_properties'])
        {
          $staff->update((int)$row['id'], ['synced_properties' => json_encode($assetsProps), 'synced_at' => $now]);
        }
        continue;
      }
      $out['people_updated']++;
      if ($dryRun)
      {
        continue;
      }
      $db = $staff->db;
      $db->transStart();
      $set = ['synced_properties' => json_encode($assetsProps), 'synced_at' => $now];
      foreach ($diff as $f => $d)
      {
        if ($f !== 'properties')
        {
          $set[$f] = $d['to'];
        }
      }
      $staff->update((int)$row['id'], $set);
      if (isset($diff['properties']))
      {
        $staff->setProperties((int)$row['id'], $diff['properties']['to']);
      }
      $changes->insert(['staff_id' => (int)$row['id'], 'pmc_id' => (int)$row['pmc_id'], 'user_id' => null, 'action' => isset($diff['end_date']) ? 'reinstated' : 'synced', 'changes' => json_encode($diff, JSON_UNESCAPED_UNICODE)]);
      $db->transComplete();
    }
    // Contacts Assets no longer lists on any property have left, as far as Assets knows.
    foreach ($staff->where('contact_id IS NOT NULL')->where('end_date', null)->findAll() as $row)
    {
      if (isset($seen[(int)$row['contact_id']]))
      {
        continue;
      }
      $out['people_departed']++;
      if ($dryRun)
      {
        continue;
      }
      $today = date('Y-m-d');
      $staff->update((int)$row['id'], ['end_date' => $today, 'synced_at' => $now]);
      $changes->insert(['staff_id' => (int)$row['id'], 'pmc_id' => (int)$row['pmc_id'], 'user_id' => null, 'action' => 'departed', 'changes' => json_encode(['end_date' => ['from' => null, 'to' => $today]])]);
    }
    ksort($out['skipped_roles']);
    return $out;
  }
  /**
   * Which PMC a contact belongs to when their properties span several: the one whose
   * name is the contact's company, else the one with most of their properties.
   *
   * @param array<int,array<string,true>> $byPmc
   * @param array<int,string> $pmcNames lower-cased, by id
   */
  private static function pickPmc(array $byPmc, array $pmcNames, string $company): int
  {
    if (count($byPmc) === 1)
    {
      return (int)array_key_first($byPmc);
    }
    $co = mb_strtolower(trim($company));
    foreach ($byPmc as $id => $props)
    {
      if ($co !== '' && ($pmcNames[$id] ?? null) === $co)
      {
        return (int)$id;
      }
    }
    uasort($byPmc, static fn(array $a, array $b): int => count($b) <=> count($a));
    return (int)array_key_first($byPmc);
  }
  /** One line for the log or the screen. */
  public static function describe(array $r, bool $dry = false): string
  {
    $skipped = [];
    foreach ($r['skipped_roles'] as $role => $n)
    {
      $skipped[] = "{$role} {$n}";
    }
    return sprintf('People: %d added, %d updated, %d departed, %d unchanged · roles not imported: %s · rows for unknown properties %d · people across companies %d%s',
      $r['people_added'], $r['people_updated'], $r['people_departed'], $r['unchanged'], $skipped === [] ? 'none' : implode(', ', $skipped), $r['no_property'], $r['multi_pmc'], $dry ? ' (dry run, nothing written)' : '');
  }
}
