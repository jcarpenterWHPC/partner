<?php

namespace App\Commands;

use App\Models\PmcModel;
use App\Models\PropertyModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * A throwaway company with one property for the browser tests, and its removal — people,
 * changes, accounts and all. Refused in production, where the only companies are real.
 *
 *   php spark partner:e2e-seed "E2E Management abc" E2E-abc
 *   php spark partner:e2e-seed "E2E Management abc" --clean
 */
class E2eSeed extends BaseCommand
{
  protected $group = 'App';
  protected $name = 'partner:e2e-seed';
  protected $description = 'Create or remove a throwaway company for the browser tests (not in production).';
  protected $usage = 'partner:e2e-seed <company> <property number> | <company> --clean';
  protected $options = ['--clean' => 'Remove the company and everything filed under it.'];
  public function run(array $params)
  {
    if (ENVIRONMENT === 'production')
    {
      CLI::error('Not in production.');
      return EXIT_ERROR;
    }
    $clean = array_key_exists('clean', $params) || CLI::getOption('clean') !== null;
    $name = trim((string)($params[0] ?? ''));
    if ($name === '' || !str_starts_with($name, 'E2E '))
    {
      CLI::error('The company name must start with "E2E ".');
      return EXIT_ERROR;
    }
    $pmcs = new PmcModel();
    $db = db_connect();
    if ($clean)
    {
      $pmc = $pmcs->byName($name);
      if ($pmc === null)
      {
        CLI::write('Nothing to remove.');
        return EXIT_SUCCESS;
      }
      $id = (int)$pmc['id'];
      $staffIds = array_column($db->table('staff')->select('id')->where('pmc_id', $id)->get()->getResultArray(), 'id');
      if ($staffIds !== [])
      {
        $db->table('staff_properties')->whereIn('staff_id', $staffIds)->delete();
      }
      $db->table('staff_changes')->where('pmc_id', $id)->delete();
      $db->table('staff')->where('pmc_id', $id)->delete();
      $userIds = array_column($db->table('users')->select('id')->where('pmc_id', $id)->get()->getResultArray(), 'id');
      if ($userIds !== [])
      {
        $db->table('login_codes')->whereIn('user_id', $userIds)->delete();
        $db->table('activity_logs')->whereIn('user_id', $userIds)->delete();
        $db->table('users')->whereIn('id', $userIds)->delete();
      }
      $db->table('properties')->where('pmc_id', $id)->delete();
      $pmcs->delete($id);
      // The admin account the suite made alongside, by its address pattern.
      $admins = $db->table('users')->select('id')->like('email', 'e2e-admin-', 'after')->get()->getResultArray();
      foreach ($admins as $a)
      {
        $db->table('login_codes')->where('user_id', $a['id'])->delete();
        $db->table('activity_logs')->where('user_id', $a['id'])->delete();
        $db->table('users')->where('id', $a['id'])->delete();
      }
      CLI::write("Removed {$name}.", 'green');
      return EXIT_SUCCESS;
    }
    $pn = trim((string)($params[1] ?? ''));
    if ($pn === '')
    {
      CLI::error('Give the property number.');
      return EXIT_ERROR;
    }
    $id = $pmcs->idFor($name);
    (new PropertyModel())->save(['property_number' => $pn, 'name' => 'E2E Property ' . $pn, 'city' => 'Testville', 'units' => 12, 'management_co' => $name, 'pmc_id' => $id, 'status' => 'Active', 'is_active' => 1, 'synced_at' => date('Y-m-d H:i:s')]);
    CLI::write("{$name} (id {$id}) with property {$pn}.", 'green');
    return EXIT_SUCCESS;
  }
}
