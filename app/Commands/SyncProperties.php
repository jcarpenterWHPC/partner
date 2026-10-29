<?php

namespace App\Commands;

use App\Libraries\PropertySync;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Refresh the PMC and property lists from the intranet's `assets` table, read through
 * the `home` database group (user partner_home_ro, SELECT only). Nightly from cron, and
 * on demand.
 */
class SyncProperties extends BaseCommand
{
  protected $group = 'App';
  protected $name = 'partner:sync-properties';
  protected $description = "Refresh PMCs and properties from the intranet's assets table.";
  protected $usage = 'partner:sync-properties [--group home] [--dry-run]';
  protected $options = ['--group' => 'Database group to read assets from (default: Config\\Partner::$assetsGroup, home).', '--dry-run' => 'Report what would change, write nothing.'];
  public const COLUMNS = 'property_number, name, city, region, units, asset_manager, management_co, status, is_entity';
  public function run(array $params)
  {
    // Options arrive in $params when run through spark's runner and the test helper alike.
    $group = (string)($params['group'] ?? CLI::getOption('group') ?: config(\Config\Partner::class)->assetsGroup);
    $dry = array_key_exists('dry-run', $params) || CLI::getOption('dry-run') !== null;
    try
    {
      $rows = db_connect($group)->table('assets')->select(self::COLUMNS)->get()->getResultArray();
    }
    catch (\Throwable $e)
    {
      CLI::error("Cannot read assets through the {$group} group: " . $e->getMessage());
      return EXIT_ERROR;
    }
    if ($rows === [])
    {
      CLI::error('The assets table came back empty; nothing changed.');
      return EXIT_ERROR;
    }
    $r = PropertySync::apply($rows, $dry);
    CLI::write(sprintf('%d rows read · PMCs added %d · properties added %d, updated %d, retired %d · skipped %d%s',
      count($rows), $r['pmcs_added'], $r['properties_added'], $r['properties_updated'], $r['properties_retired'], $r['skipped'], $dry ? ' (dry run, nothing written)' : ''), $dry ? 'yellow' : 'green');
    return EXIT_SUCCESS;
  }
}
