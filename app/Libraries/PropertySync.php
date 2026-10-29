<?php

namespace App\Libraries;

use App\Models\PmcModel;
use App\Models\PropertyModel;

/**
 * Bring the portal's PMCs and properties in line with the intranet's `assets` table.
 *
 * Rows are Assets' own: property_number, name, city, region, units, asset_manager,
 * management_co, status, is_entity. Entities and rows with no management company are
 * not properties anyone manages here and are skipped. A property that stops appearing
 * is retired (is_active 0), never deleted — its roster links are history.
 */
class PropertySync
{
  /**
   * @param list<array<string,mixed>> $rows
   * @return array{pmcs_added:int, properties_added:int, properties_updated:int, properties_retired:int, skipped:int}
   */
  public static function apply(array $rows, bool $dryRun = false): array
  {
    $pmcs = new PmcModel();
    $props = new PropertyModel();
    $known = [];
    foreach ($pmcs->findAll() as $p)
    {
      $known[$p['name']] = (int)$p['id'];
    }
    $existing = [];
    foreach ($props->findAll() as $p)
    {
      $existing[(string)$p['property_number']] = $p;
    }
    $out = ['pmcs_added' => 0, 'properties_added' => 0, 'properties_updated' => 0, 'properties_retired' => 0, 'skipped' => 0];
    $seen = [];
    $now = date('Y-m-d H:i:s');
    foreach ($rows as $r)
    {
      $pn = trim((string)($r['property_number'] ?? ''));
      $co = trim((string)($r['management_co'] ?? ''));
      if ($pn === '' || $co === '' || (int)($r['is_entity'] ?? 0) === 1)
      {
        $out['skipped']++;
        continue;
      }
      if (!isset($known[$co]))
      {
        $out['pmcs_added']++;
        $known[$co] = $dryRun ? -$out['pmcs_added'] : $pmcs->idFor($co);
      }
      $seen[$pn] = true;
      $want = [
        'name' => (string)($r['name'] ?? ''),
        'city' => $r['city'] ?? null,
        'region' => $r['region'] ?? null,
        'units' => ($r['units'] ?? null) === null || $r['units'] === '' ? null : (int)$r['units'],
        'asset_manager' => $r['asset_manager'] ?? null,
        'management_co' => $co,
        'pmc_id' => $known[$co],
        'status' => $r['status'] ?? null,
        'is_active' => 1,
      ];
      $have = $existing[$pn] ?? null;
      if ($have === null)
      {
        $out['properties_added']++;
        if (!$dryRun)
        {
          $props->insert(['property_number' => $pn, 'synced_at' => $now] + $want);
        }
        continue;
      }
      $changed = false;
      foreach ($want as $k => $v)
      {
        if ((string)($have[$k] ?? '') !== (string)($v ?? ''))
        {
          $changed = true;
          break;
        }
      }
      if ($changed)
      {
        $out['properties_updated']++;
      }
      if (!$dryRun)
      {
        $props->update($pn, $want + ['synced_at' => $now]);
      }
    }
    foreach ($existing as $pn => $p)
    {
      if (!isset($seen[$pn]) && (int)$p['is_active'] === 1)
      {
        $out['properties_retired']++;
        if (!$dryRun)
        {
          $props->update($pn, ['is_active' => 0, 'synced_at' => $now]);
        }
      }
    }
    return $out;
  }
}
