<?php

namespace App\Controllers;

use App\Commands\SyncProperties;
use App\Libraries\Accounts;
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
      'title' => 'Portal users',
      'crumbs' => [['Home', '/'], ['Portal users', null]],
      'users' => (new UserModel())->roster(),
      'pmcs' => (new PmcModel())->active(),
      'errors' => session()->getFlashdata('errors') ?: [],
      'authored' => (new StaffChangeModel())->countByUser(),
    ]);
  }
  public function userStore()
  {
    $in = $this->userInput(null);
    if ($in['errors'] !== [])
    {
      return redirect()->back()->withInput()->with('errors', $in['errors']);
    }
    $id = (new UserModel())->insert($in['values'] + ['is_admin' => 0, 'is_active' => 1], true);
    ActivityLogModel::record('admin.user_added', (int)session('user')['id'], (string)session('user')['email'], $in['values']['email'] . ' #' . $id);
    return redirect()->to('/admin/users')->with('success', $in['values']['email'] . ' can now sign in.');
  }
  public function userEdit(int $id)
  {
    $u = $this->editable($id);
    return view('pages/admin/user-form', [
      'title' => 'Edit ' . $u['name'],
      'crumbs' => [['Home', '/'], ['Portal users', 'admin/users'], ['Edit ' . $u['name'], null]],
      'user' => $u,
      'pmcs' => (new PmcModel())->active(),
      'v' => old('email') !== null ? ['name' => old('name'), 'email' => old('email'), 'pmc_id' => old('pmc_id')] : $u,
      'errors' => session()->getFlashdata('errors') ?: [],
    ]);
  }
  public function userUpdate(int $id)
  {
    $u = $this->editable($id);
    $in = $this->userInput($u);
    if ($in['errors'] !== [])
    {
      return redirect()->back()->withInput()->with('errors', $in['errors']);
    }
    $diff = array_filter($in['values'], static fn($v, string $k): bool => (string)$v !== (string)($u[$k] ?? ''), ARRAY_FILTER_USE_BOTH);
    if ($diff !== [])
    {
      (new UserModel())->update($id, $diff);
      ActivityLogModel::record('admin.user_edited', (int)session('user')['id'], (string)session('user')['email'], $u['email'] . ' ' . implode(',', array_keys($diff)));
    }
    return redirect()->to('/admin/users')->with('success', $in['values']['name'] . ' is updated.');
  }
  public function userDelete(int $id)
  {
    $u = $this->editable($id);
    $n = (new StaffChangeModel())->where('user_id', $id)->countAllResults();
    if ($n > 0)
    {
      return redirect()->to('/admin/users')->with('error', $u['name'] . ' has made roster changes, which keep their author. Disable the account instead.');
    }
    $db = db_connect();
    $db->table('login_codes')->where('user_id', $id)->delete();
    (new UserModel())->delete($id);
    ActivityLogModel::record('admin.user_deleted', (int)session('user')['id'], (string)session('user')['email'], $u['email'] . ' #' . $id);
    return redirect()->to('/admin/users')->with('success', $u['email'] . ' is deleted.');
  }
  /** The account $id if this screen may change it; configured admins and oneself are not. */
  private function editable(int $id): array
  {
    $u = (new UserModel())->find($id);
    if (!$u)
    {
      throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
    }
    if ((int)$u['id'] === (int)session('user')['id'] || (new Accounts())->isConfiguredAdmin((string)$u['email']))
    {
      throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
    }
    return $u;
  }
  /**
   * The posted name, email and company, checked. $current is the account being edited,
   * or null for a new one.
   *
   * @return array{values:array{name:string,email:string,pmc_id:?int}, errors:array<string,string>}
   */
  private function userInput(?array $current): array
  {
    $email = mb_strtolower(trim((string)$this->request->getPost('email')));
    $name = trim((string)$this->request->getPost('name'));
    $pmcId = (int)$this->request->getPost('pmc_id');
    $errors = [];
    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false)
    {
      $errors['email'] = 'Enter a valid email address.';
    }
    elseif (config(Partner::class)->isWhpc($email))
    {
      $errors['email'] = 'WHPC admins are set in the portal configuration, not here.';
    }
    if ($name === '')
    {
      $errors['name'] = 'Enter the person\'s name.';
    }
    $pmc = $pmcId > 0 ? (new PmcModel())->find($pmcId) : null;
    if ($pmc === null && (int)($current['is_admin'] ?? 0) !== 1)
    {
      $errors['pmc_id'] = 'Choose the management company.';
    }
    if (!isset($errors['email']))
    {
      $dup = (new UserModel())->where('email', $email)->first();
      if ($dup && (int)$dup['id'] !== (int)($current['id'] ?? 0))
      {
        $errors['email'] = 'That address already has an account.';
      }
    }
    return ['values' => ['name' => $name, 'email' => $email, 'pmc_id' => $pmc === null ? null : (int)$pmc['id']], 'errors' => $errors];
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
    if ((new Accounts())->isConfiguredAdmin((string)$u['email']))
    {
      return redirect()->to('/admin/users')->with('error', 'WHPC admins are set in the portal configuration, not here.');
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
      'crumbs' => [['Home', '/'], ['Management companies', null]],
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
      'crumbs' => [['Home', '/'], ['Recent changes', null]],
      'entries' => (new StaffChangeModel())->recent(null, 200),
    ]);
  }
}
