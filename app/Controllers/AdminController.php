<?php

namespace App\Controllers;

use App\Commands\SyncContacts;
use App\Commands\SyncProperties;
use App\Libraries\ContactSync;
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
      'authored' => (new UserModel())->withHistory(),
    ]);
  }
  public function userStore()
  {
    $in = $this->userInput(null);
    if ($in['errors'] !== [])
    {
      return redirect()->back()->withInput()->with('errors', $in['errors']);
    }
    $id = (new UserModel())->insert($in['values'] + ['is_active' => 1], true);
    ActivityLogModel::record('admin.user_added', (int)session('user')['id'], (string)session('user')['email'], $in['values']['email'] . ' #' . $id . ($in['values']['is_admin'] === 1 ? ' (WHPC admin)' : ''));
    return redirect()->to('/admin/users')->with('success', $in['values']['email'] . ($in['values']['is_admin'] === 1 ? ' can now sign in with Microsoft as a WHPC admin.' : ' can now sign in.'));
  }
  public function userEdit(int $id)
  {
    $u = $this->editable($id);
    if (!is_array($u))
    {
      return $u;
    }
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
    if (!is_array($u))
    {
      return $u;
    }
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
    if (!is_array($u))
    {
      return $u;
    }
    $users = new UserModel();
    if ($users->hasHistory($id))
    {
      return redirect()->to('/admin/users')->with('error', $u['name'] . ' has signed in or recorded changes, and that history keeps its author. Disable the account instead.');
    }
    $db = db_connect();
    $db->table('login_codes')->where('user_id', $id)->delete();
    $users->delete($id);
    ActivityLogModel::record('admin.user_deleted', (int)session('user')['id'], (string)session('user')['email'], $u['email'] . ' #' . $id);
    return redirect()->to('/admin/users')->with('success', $u['email'] . ' is deleted.');
  }
  /**
   * The account $id if this screen may change it; otherwise the redirect that says why
   * not. Standing admins are set in the configuration, and nobody edits themself here.
   *
   * @return array|\CodeIgniter\HTTP\RedirectResponse
   */
  private function editable(int $id)
  {
    $u = (new UserModel())->find($id);
    if (!$u)
    {
      throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
    }
    if ((int)$u['id'] === (int)session('user')['id'])
    {
      return redirect()->to('/admin/users')->with('error', 'You cannot change or delete your own account here. Ask another admin.');
    }
    if ((new Accounts())->isConfiguredAdmin((string)$u['email']))
    {
      return redirect()->to('/admin/users')->with('error', $u['name'] . ' is a standing admin set in the portal configuration and cannot be changed or deleted here.');
    }
    return $u;
  }
  /**
   * The posted name, email and organisation, checked. A WHPC address is an admin with no
   * organisation; any other address is a partner user and needs one. $current is the
   * account being edited, or null for a new one.
   *
   * @return array{values:array{name:string,email:string,pmc_id:?int,is_admin:int}, errors:array<string,string>}
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
    $whpc = !isset($errors['email']) && config(Partner::class)->isWhpc($email);
    if ($current !== null && !isset($errors['email']) && $whpc !== ((int)$current['is_admin'] === 1))
    {
      $errors['email'] = 'Partner users and WHPC admins are separate accounts; add a new one instead.';
    }
    if ($name === '')
    {
      $errors['name'] = 'Enter the person\'s name.';
    }
    $pmc = $pmcId > 0 ? (new PmcModel())->find($pmcId) : null;
    if ($pmc === null && !$whpc && !isset($errors['email']))
    {
      $errors['pmc_id'] = 'Choose the organization.';
    }
    if (!isset($errors['email']))
    {
      $dup = (new UserModel())->where('email', $email)->first();
      if ($dup && (int)$dup['id'] !== (int)($current['id'] ?? 0))
      {
        $errors['email'] = 'That address already has an account.';
      }
    }
    return ['values' => ['name' => $name, 'email' => $email, 'pmc_id' => $whpc || $pmc === null ? null : (int)$pmc['id'], 'is_admin' => $whpc ? 1 : 0], 'errors' => $errors];
  }
  public function userToggle(int $id)
  {
    $users = new UserModel();
    $u = $this->editable($id);
    if (!is_array($u))
    {
      return $u;
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
    $openCounts = array_column($db->table('vacancies')->select('pmc_id, COUNT(*) AS n')->where('filled_at', null)->groupBy('pmc_id')->get()->getResultArray(), 'n', 'pmc_id');
    $last = (new PropertyModel())->selectMax('synced_at')->first();
    return view('pages/admin/pmcs', [
      'title' => 'Organizations',
      'crumbs' => [['Home', '/'], ['Organizations', null]],
      'pmcs' => (new PmcModel())->orderBy('name')->findAll(),
      'kinds' => PmcModel::KINDS,
      'propCounts' => $propCounts,
      'staffCounts' => $staffCounts,
      'userCounts' => $userCounts,
      'openCounts' => $openCounts,
      'lastSync' => $last['synced_at'] ?? null,
      'errors' => session()->getFlashdata('errors') ?: [],
    ]);
  }
  /** An organisation Assets does not list as a management company: a service agency, usually. */
  public function pmcStore()
  {
    $name = trim((string)$this->request->getPost('name'));
    $kind = (string)$this->request->getPost('kind');
    $errors = [];
    if ($name === '' || mb_strlen($name) > 255)
    {
      $errors['name'] = 'Enter the organization\'s name.';
    }
    elseif ((new PmcModel())->where('LOWER(name)', mb_strtolower($name))->first() !== null)
    {
      $errors['name'] = 'That organization is already listed.';
    }
    if (!isset(PmcModel::KINDS[$kind]))
    {
      $errors['kind'] = 'Choose what kind of organization it is.';
    }
    if ($errors !== [])
    {
      return redirect()->to('/admin/pmcs')->withInput()->with('errors', $errors);
    }
    (new PmcModel())->insert(['name' => $name, 'kind' => $kind, 'is_active' => 1]);
    ActivityLogModel::record('admin.org_added', (int)session('user')['id'], (string)session('user')['email'], $name . ' (' . $kind . ')');
    return redirect()->to('/admin/pmcs')->with('success', $name . ' was added.');
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
    $msg = sprintf('Synced: %d organizations added, %d properties added, %d updated, %d retired.', $r['pmcs_added'], $r['properties_added'], $r['properties_updated'], $r['properties_retired']);
    $cfg = config(Partner::class);
    try
    {
      $contacts = db_connect($group)->table($cfg->contactsView)->select(SyncContacts::COLUMNS)->get()->getResultArray();
      $c = $contacts === [] ? null : ContactSync::apply($contacts, false, $cfg);
      $msg .= ' ' . ($c === null ? 'People: the contacts view came back empty.' : ContactSync::describe($c));
    }
    catch (\Throwable $e)
    {
      log_message('error', '[Admin] contact sync failed: ' . $e->getMessage());
      $c = null;
      $msg .= ' People: the contacts view could not be read.';
    }
    ActivityLogModel::record('admin.sync', (int)session('user')['id'], (string)session('user')['email'], json_encode(['properties' => $r, 'people' => $c]));
    return redirect()->to('/admin/pmcs')->with('success', $msg);
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
