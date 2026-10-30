<?php

namespace App\Libraries;

use App\Models\PmcModel;
use App\Models\PropertyModel;
use App\Models\StaffChangeModel;
use App\Models\StaffModel;
use Config\Partner;

/**
 * Seed and refresh the rosters from Assets' property contacts
 * (`home.partner_property_contacts`: one row per contact, property and live role).
 *
 * A contact becomes one roster entry: under the organisation whose name is the
 * contact's company (a service agency such as LSS, or a management company), else under
 * the management company of their properties. Each property keeps the role Assets gives
 * the person there; the person's main position is the role they hold most. A contact's
 * first import is an `imported` change by nobody. Later runs fill blanks and add what
 * Assets has newly given the person; they never overwrite what an organisation entered
 * here, and never remove a property it removed. A contact that has gone from the view is
 * departed as of the day the sync noticed.
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
    $orgs = [];
    $byName = [];
    foreach ((new PmcModel())->findAll() as $p)
    {
      $orgs[(int)$p['id']] = $p;
      $byName[mb_strtolower(trim((string)$p['name']))] = (int)$p['id'];
    }
    $staff = new StaffModel();
    $changes = new StaffChangeModel();
    $positions = array_flip($cfg->positionNames());
    $settlers = [];
    $settler = static function (int $pmcId) use (&$settlers, $orgs, $cfg): Vacancies
    {
      return $settlers[$pmcId] ??= new Vacancies($pmcId, null, $cfg->propertyRoles[$orgs[$pmcId]['kind'] ?? PmcModel::MANAGEMENT] ?? []);
    };
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
      $c ??= ['name' => '', 'title' => '', 'company' => '', 'email' => '', 'mobile_phone' => '', 'office_phone' => '', 'at' => []];
      foreach (['name' => 'name', 'title' => 'job_title', 'company' => 'company', 'email' => 'email', 'mobile_phone' => 'mobile_phone', 'office_phone' => 'business_phone'] as $ours => $theirs)
      {
        if ($c[$ours] === '' && trim((string)($r[$theirs] ?? '')) !== '')
        {
          $c[$ours] = trim((string)$r[$theirs]);
        }
      }
      $c['at'][$pn][$position] = true;
      unset($c);
    }
    $now = date('Y-m-d H:i:s');
    $today = date('Y-m-d');
    $seen = [];
    foreach ($contacts as $cid => $c)
    {
      // How many properties the person holds each role at; the commonest is their position.
      $tally = [];
      foreach ($c['at'] as $pn => $roles)
      {
        foreach (array_keys($roles) as $role)
        {
          $tally[$role] = ($tally[$role] ?? 0) + 1;
        }
      }
      uksort($tally, static fn(string $a, string $b): int => ($tally[$b] <=> $tally[$a]) ?: (($a === 'Other') <=> ($b === 'Other')) ?: strcmp($a, $b));
      $position = (string)array_key_first($tally);
      $all = [];
      $byPmc = [];
      foreach ($c['at'] as $pn => $roles)
      {
        foreach (array_keys($tally) as $role)
        {
          if (isset($roles[$role]))
          {
            $all[(string)$pn] = $role;
            break;
          }
        }
        $byPmc[$props[$pn]][(string)$pn] = true;
      }
      if (count($byPmc) > 1)
      {
        $out['multi_pmc']++;
      }
      $pmcId = $byName[mb_strtolower($c['company'])] ?? null;
      if ($pmcId !== null)
      {
        $assets = $all;
      }
      else
      {
        $pmcId = self::pickPmc($byPmc);
        $assets = array_intersect_key($all, $byPmc[$pmcId]);
      }
      $assets = StaffModel::roleMap($assets);
      $assetsProps = array_map('strval', array_keys($assets));
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
        $record = ['name' => $c['name'] !== '' ? $c['name'] : 'Contact ' . $cid, 'position' => $position, 'title' => $c['title'], 'email' => $email, 'email_na' => 0, 'mobile_phone' => $c['mobile_phone'], 'office_phone' => $c['office_phone'], 'start_date' => null, 'end_date' => null, 'notes' => null];
        $db = $staff->db;
        $db->transStart();
        $id = (int)$staff->insert($record + ['pmc_id' => $pmcId, 'contact_id' => $cid, 'synced_properties' => json_encode($assetsProps), 'synced_at' => $now], true);
        $staff->setProperties($id, $assets);
        $changes->insert(['staff_id' => $id, 'pmc_id' => $pmcId, 'user_id' => null, 'action' => 'imported', 'changes' => json_encode($record + ['properties' => Roster::rolesForLog($assets)], JSON_UNESCAPED_UNICODE)]);
        $settler($pmcId)->settle([], $assets, null);
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
      if (isset($diff['email']) && (int)$row['email_na'] === 1)
      {
        $diff['email_na'] = ['from' => 1, 'to' => 0];
      }
      if (trim((string)$row['name']) === '' && $c['name'] !== '')
      {
        $diff['name'] = ['from' => $row['name'], 'to' => $c['name']];
      }
      $last = json_decode((string)$row['synced_properties'], true) ?: [];
      $new = array_values(array_diff($assetsProps, array_map('strval', $last), $row['properties']));
      $wantRoles = $row['roles'];
      if ($new !== [])
      {
        foreach ($new as $pn)
        {
          $wantRoles[$pn] = $assets[$pn];
        }
        $wantRoles = StaffModel::roleMap($wantRoles);
        $diff['properties'] = ['from' => Roster::rolesForLog($row['roles']), 'to' => Roster::rolesForLog($wantRoles)];
      }
      if ($row['end_date'] !== null)
      {
        $diff['end_date'] = ['from' => $row['end_date'], 'to' => null];
      }
      if ($diff === [])
      {
        $out['unchanged']++;
        if (!$dryRun && (string)$row['synced_properties'] !== json_encode($assetsProps))
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
      $update = ['synced_properties' => json_encode($assetsProps), 'synced_at' => $now];
      foreach ($diff as $f => $d)
      {
        if ($f !== 'properties')
        {
          $update[$f] = $d['to'];
        }
      }
      $db = $staff->db;
      $db->transStart();
      $staff->update((int)$row['id'], $update);
      if (isset($diff['properties']))
      {
        $staff->setProperties((int)$row['id'], $wantRoles);
      }
      $changes->insert(['staff_id' => (int)$row['id'], 'pmc_id' => (int)$row['pmc_id'], 'user_id' => null, 'action' => isset($diff['end_date']) ? 'reinstated' : 'synced', 'changes' => json_encode($diff, JSON_UNESCAPED_UNICODE)]);
      $settler((int)$row['pmc_id'])->settle($row['end_date'] === null ? $row['roles'] : [], $wantRoles, null);
      $db->transComplete();
    }
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
      $row = $staff->withProperties((int)$row['id']);
      $db = $staff->db;
      $db->transStart();
      $staff->update((int)$row['id'], ['end_date' => $today, 'synced_at' => $now]);
      $changes->insert(['staff_id' => (int)$row['id'], 'pmc_id' => (int)$row['pmc_id'], 'user_id' => null, 'action' => 'departed', 'changes' => json_encode(['end_date' => ['from' => null, 'to' => $today]])]);
      $settler((int)$row['pmc_id'])->settle($row['roles'], [], $today);
      $db->transComplete();
    }
    ksort($out['skipped_roles']);
    return $out;
  }
  /**
   * The management company for a contact whose company is not an organisation here:
   * the one with most of their properties.
   *
   * @param array<int,array<string,true>> $byPmc
   */
  private static function pickPmc(array $byPmc): int
  {
    uasort($byPmc, static fn(array $a, array $b): int => count($b) <=> count($a));
    return (int)array_key_first($byPmc);
  }
  /** One line for the admin page and the command. */
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
