<?php

namespace App\Controllers;

use App\Libraries\Roster;
use App\Models\ActivityLogModel;
use App\Models\PmcModel;
use App\Models\PropertyModel;
use App\Models\StaffChangeModel;
use App\Models\StaffModel;
use Config\Partner;

/**
 * An organisation's staff roster. A partner user works on their own; an admin picks one.
 */
class RosterController extends BaseController
{
  private function roster(array $org): Roster
  {
    return new Roster((int)$org['id'], (int)(session('user')['id'] ?? 0));
  }
  /**
   * Property number => label, for everything this organisation may assign plus any
   * numbers in $extra (a person's own, even once retired). A service agency sees whose
   * property each one is.
   *
   * @return array<string,string>
   */
  private function names(array $org, array $extra = []): array
  {
    $props = new PropertyModel();
    $rows = $props->forOrg($org);
    $missing = array_diff(array_map('strval', $extra), array_map('strval', array_column($rows, 'property_number')));
    if ($missing !== [])
    {
      $rows = array_merge($rows, $props->whereIn('property_number', array_values($missing))->findAll());
    }
    $services = PmcModel::isServices($org);
    $out = [];
    foreach ($rows as $r)
    {
      $label = (string)$r['name'];
      if ($services && (string)$r['management_co'] !== '')
      {
        $label .= ' · ' . $r['management_co'];
      }
      $out[(string)$r['property_number']] = $label . ((int)$r['is_active'] === 1 ? '' : ' (no longer managed)');
    }
    return $out;
  }
  public function index()
  {
    $org = $this->org();
    if ($org === null)
    {
      return redirect()->to('/admin/pmcs')->with('error', 'No organization has been synced yet.');
    }
    $people = (new StaffModel())->forPmc((int)$org['id']);
    $all = [];
    foreach ($people as $p)
    {
      $all = array_merge($all, $p['properties']);
    }
    return view('pages/roster/index', [
      'title' => 'Staff roster · ' . $org['name'],
      'crumbs' => [['Home', '/'], ['Staff roster', null]],
      'pmc' => $org,
      'pmcs' => $this->orgChoices(),
      'people' => $people,
      'names' => $this->names($org, $all),
      'showDeparted' => (bool)$this->request->getGet('departed'),
    ]);
  }
  public function create()
  {
    $org = $this->org();
    if ($org === null)
    {
      return redirect()->to('/roster');
    }
    return $this->form($org, null, old('name') !== null ? $this->roster($org)->clean($this->request->getPost() ?: []) : null);
  }
  public function store()
  {
    $org = $this->org();
    if ($org === null)
    {
      return redirect()->to('/roster');
    }
    $roster = $this->roster($org);
    $clean = $roster->clean($this->request->getPost() ?: []);
    $errors = $roster->validate($clean);
    if ($errors !== [])
    {
      return redirect()->back()->withInput()->with('errors', $errors);
    }
    $id = $roster->add($clean);
    ActivityLogModel::record('roster.added', (int)session('user')['id'], (string)session('user')['email'], $org['name'] . ' #' . $id . ' ' . $clean['name']);
    return redirect()->to('/roster')->with('success', $clean['name'] . ' was added.');
  }
  public function edit(int $id)
  {
    $org = $this->org();
    $person = $org === null ? null : $this->roster($org)->person($id);
    if ($person === null)
    {
      throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
    }
    return $this->form($org, $person, old('name') !== null ? $this->roster($org)->clean($this->request->getPost() ?: []) : null);
  }
  public function update(int $id)
  {
    $org = $this->org();
    $roster = $org === null ? null : $this->roster($org);
    if ($roster === null || $roster->person($id) === null)
    {
      throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
    }
    $clean = $roster->clean($this->request->getPost() ?: []);
    $errors = $roster->validate($clean, $id);
    if ($errors !== [])
    {
      return redirect()->back()->withInput()->with('errors', $errors);
    }
    $r = $roster->update($id, $clean);
    if ($r['action'] !== 'unchanged')
    {
      ActivityLogModel::record('roster.' . $r['action'], (int)session('user')['id'], (string)session('user')['email'], $org['name'] . ' #' . $id . ' ' . $clean['name'] . ': ' . implode(', ', array_keys($r['changes'])));
    }
    $said = match ($r['action'])
    {
      'departed' => $clean['name'] . ' is marked as departed.',
      'reinstated' => $clean['name'] . ' is back on the roster.',
      'unchanged' => 'Nothing changed.',
      default => $clean['name'] . ' was updated.',
    };
    $back = $this->request->getPost('back') === 'properties' ? '/properties' : '/roster';
    return redirect()->to($back)->with('success', $said);
  }
  public function history(int $id)
  {
    $org = $this->org();
    $person = $org === null ? null : $this->roster($org)->person($id);
    if ($person === null)
    {
      throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
    }
    return view('pages/roster/history', [
      'title' => 'History · ' . $person['name'],
      'crumbs' => [['Home', '/'], ['Staff roster', 'roster'], ['Edit ' . $person['name'], 'roster/' . (int)$person['id'] . '/edit'], ['History', null]],
      'pmc' => $org,
      'person' => $person,
      'entries' => (new StaffChangeModel())->recent((int)$org['id'], 500),
      'names' => $this->names($org, $person['properties']),
    ]);
  }
  private function form(array $org, ?array $person, ?array $values)
  {
    $values = $values ?? ($person ? $person : ['properties' => [], 'roles' => []]);
    return view('pages/roster/form', [
      'title' => ($person ? 'Edit ' . $person['name'] : 'Add a person') . ' · ' . $org['name'],
      'crumbs' => [['Home', '/'], ['Staff roster', 'roster'], [$person ? 'Edit ' . $person['name'] : 'Add a person', null]],
      'pmc' => $org,
      'person' => $person,
      'v' => $values,
      'positions' => config(Partner::class)->positions,
      'names' => $this->names($org, $person['properties'] ?? []),
      'errors' => session()->getFlashdata('errors') ?: [],
      'back' => $this->request->getGet('back') === 'properties' || $this->request->getPost('back') === 'properties' ? 'properties' : '',
    ]);
  }
}
