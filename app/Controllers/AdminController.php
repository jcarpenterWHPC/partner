<?php

namespace App\Controllers;

use App\Commands\SyncProperties;
use App\Libraries\PropertySync;
use App\Models\ActivityLogModel;
use App\Models\PmcModel;
use App\Models\PropertyModel;
use App\Models\StaffChangeModel;
use App\Models\StaffModel;
use App\Models\UserModel;
use Config\Partner;

/** WHPC admins: accounts, management companies, and what has changed. */
class AdminController extends BaseController
{
  public function users()
  {
    return view('pages/admin/users', [
      'title' => 'Accounts',
      'users' => (new UserModel())->roster(),
      'pmcs' => (new PmcModel())->active(),
      'errors' => session()->getFlashdata('errors') ?: [],
    ]);
  }
  public function userStore()
  {
    $email = mb_strtolower(trim((string)$this->request->getPost('email')));
    $name = trim((string)$this->request->getPost('name'));
    $pmcId = (int)$this->request->getPost('pmc_id');
    $admin = $this->request->getPost('is_admin') ? 1 : 0;
    $errors = [];
    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false)
    {
      $errors['email'] = 'Enter a valid email address.';
    }
    if ($name === '')
    {
      $errors['name'] = 'Enter the person\'s name.';
    }
    $pmc = $pmcId > 0 ? (new PmcModel())->find($pmcId) : null;
    if (!$admin && $pmc === null)
    {
      $errors['pmc_id'] = 'Choose the management company, or make the account an admin.';
    }
    $users = new UserModel();
    if ($errors === [] && $users->where('email', $email)->first())
    {
      $errors['email'] = 'That address already has an account.';
    }
    if ($errors !== [])
    {
      return redirect()->back()->withInput()->with('errors', $errors);
    }
    $id = $users->insert(['email' => $email, 'name' => $name, 'pmc_id' => $pmc === null ? null : (int)$pmc['id'], 'is_admin' => $admin, 'is_active' => 1], true);
    ActivityLogModel::record('admin.user_added', (int)session('user')['id'], (string)session('user')['email'], $email . ' #' . $id);
    return redirect()->to('/admin/users')->with('success', $email . ' can now sign in.');
  }
  public function userToggle(int $id)
  {
    $users = new UserModel();
    $u = $users->find($id);
    if (!$u)
    {
      throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
    }
    if ((int)$u['id'] === (int)session('user')['id'])
    {
      return redirect()->to('/admin/users')->with('error', 'You cannot disable your own account.');
    }
    $on = (int)$u['is_active'] === 1 ? 0 : 1;
    $users->update($id, ['is_active' => $on]);
    ActivityLogModel::record($on ? 'admin.user_enabled' : 'admin.user_disabled', (int)session('user')['id'], (string)session('user')['email'], $u['email']);
    return redirect()->to('/admin/users')->with('success', $u['email'] . ($on ? ' is enabled.' : ' is disabled.'));
  }
  public function pmcs()
  {
    $db = db_connect();
    $propCounts = array_column($db->table('properties')->select('pmc_id, COUNT(*) AS n')->where('is_active', 1)->groupBy('pmc_id')->get()->getResultArray(), 'n', 'pmc_id');
    $staffCounts = array_column($db->table('staff')->select('pmc_id, COUNT(*) AS n')->where('end_date', null)->groupBy('pmc_id')->get()->getResultArray(), 'n', 'pmc_id');
    $userCounts = array_column($db->table('users')->select('pmc_id, COUNT(*) AS n')->where('is_active', 1)->groupBy('pmc_id')->get()->getResultArray(), 'n', 'pmc_id');
    $last = (new PropertyModel())->selectMax('synced_at')->first();
    return view('pages/admin/pmcs', [
      'title' => 'Management companies',
      'pmcs' => (new PmcModel())->orderBy('name')->findAll(),
      'propCounts' => $propCounts,
      'staffCounts' => $staffCounts,
      'userCounts' => $userCounts,
      'lastSync' => $last['synced_at'] ?? null,
    ]);
  }
  public function sync()
  {
    $group = config(Partner::class)->assetsGroup;
    try
    {
      $rows = db_connect($group)->table('assets')->select(SyncProperties::COLUMNS)->get()->getResultArray();
    }
    catch (\Throwable $e)
    {
      log_message('error', '[Admin] property sync failed: ' . $e->getMessage());
      return redirect()->to('/admin/pmcs')->with('error', 'The intranet\'s property list could not be read.');
    }
    if ($rows === [])
    {
      return redirect()->to('/admin/pmcs')->with('error', 'The intranet\'s property list came back empty; nothing changed.');
    }
    $r = PropertySync::apply($rows);
    ActivityLogModel::record('admin.sync', (int)session('user')['id'], (string)session('user')['email'], json_encode($r));
    return redirect()->to('/admin/pmcs')->with('success', sprintf('Synced: %d companies added, %d properties added, %d updated, %d retired.', $r['pmcs_added'], $r['properties_added'], $r['properties_updated'], $r['properties_retired']));
  }
  public function changes()
  {
    return view('pages/admin/changes', [
      'title' => 'Recent roster changes',
      'entries' => (new StaffChangeModel())->recent(null, 200),
    ]);
  }
}
