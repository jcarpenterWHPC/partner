<?php

namespace App\Commands;

use App\Libraries\ContactSync;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Seed and refresh the rosters from the intranet's live property contacts, read through
 * the `home` group's `partner_property_contacts` view. Nightly after the property sync,
 * and on demand. Until the view is granted this reports the error and changes nothing.
 */
class SyncContacts extends BaseCommand
{
  protected $group = 'App';
  protected $name = 'partner:sync-contacts';
  protected $description = "Seed and refresh the rosters from the intranet's property contacts.";
  protected $usage = 'partner:sync-contacts [--group home] [--dry-run]';
  protected $options = ['--group' => 'Database group to read the contacts view from (default: Config\\Partner::$assetsGroup, home).', '--dry-run' => 'Report what would change, write nothing.'];
  public const COLUMNS = 'contact_id, property_number, role, name, job_title, company, email, business_phone, mobile_phone';
  public function run(array $params)
  {
    $cfg = config(\Config\Partner::class);
    $group = (string)($params['group'] ?? CLI::getOption('group') ?: $cfg->assetsGroup);
    $dry = array_key_exists('dry-run', $params) || CLI::getOption('dry-run') !== null;
    try
    {
      $rows = db_connect($group)->table($cfg->contactsView)->select(self::COLUMNS)->get()->getResultArray();
    }
    catch (\Throwable $e)
    {
      CLI::error("Cannot read {$cfg->contactsView} through the {$group} group: " . $e->getMessage());
      return EXIT_ERROR;
    }
    if ($rows === [])
    {
      CLI::error('The contacts view came back empty; nothing changed.');
      return EXIT_ERROR;
    }
    $r = ContactSync::apply($rows, $dry, $cfg);
    CLI::write(count($rows) . ' rows read · ' . ContactSync::describe($r, $dry), $dry ? 'yellow' : 'green');
    return EXIT_SUCCESS;
  }
}
