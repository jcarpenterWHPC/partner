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
 * A PMC's staff roster. A PMC user works on their own company; an admin picks one.
 */
class RosterController extends BaseController
{
  /** The PMC in scope, or null when an admin has not chosen one. */
  private function pmc(): ?array
  {
    $u = session('user');
    $pmcs = new PmcModel();
    if (!empty($u['pmc_id']))
    {
      return $pmcs->find((int)$u['pmc_id']);
    }
    if (empty($u['is_admin']))
    {
      return null;
    }
    $want = (int)($this->request->getGet('pmc') ?: session('admin_pmc') ?: 0);
    $pmc = $want > 0 ? $pmcs->find($want) : null;
    if ($pmc === null)
    {
      $pmc = $pmcs->active()[0] ?? null;
    }
    if ($pmc !== null)
    {
      session()->set('admin_pmc', (int)$pmc['id']);
    }
    return $pmc;
  }
  private function roster(array $pmc): Roster
  {
    return new Roster((int)$pmc['id'], (int)(session('user')['id'] ?? 0));
  }
  /** @return array<string,string> property number => name, for this PMC plus any numbers in $extra */
  private function names(array $pmc, array $extra = []): array
  {
    $props = new PropertyModel();
    $rows = $props->forPmc((int)$pmc['id']);
    $missing = array_diff(array_map('strval', $extra), array_column($rows, 'property_number'));
    if ($missing !== [])
    {
      $rows = array_merge($rows, $props->whereIn('property_number', array_values($missing))->findAll());
    }
    $out = [];
    foreach ($rows as $r)
    {
      $out[(string)$r['property_number']] = (string)$r['name'] . ((int)$r['is_active'] === 1 ? '' : ' (no longer managed)');
    }
    return $out;
  }
  public function index()
  {
    $pmc = $this->pmc();
    if ($pmc === null)
    {
      return redirect()->to('/admin/pmcs')->with('error', 'No management company has been synced yet.');
    }
    $people = (new StaffModel())->forPmc((int)$pmc['id']);
    $all = [];
    foreach ($people as $p)
    {
      $all = array_merge($all, $p['properties']);
    }
    return view('pages/roster/index', [
      'title' => 'Staff roster · ' . $pmc['name'],
      'crumbs' => [['Home', '/'], ['Staff roster', null]],
      'pmc' => $pmc,
      'pmcs' => !empty(session('user')['is_admin']) ? (new PmcModel())->active() : [],
      'people' => $people,
      'names' => $this->names($pmc, $all),
      'showDeparted' => (bool)$this->request->getGet('departed'),
    ]);
  }
  public function create()
  {
    $pmc = $this->pmc();
    if ($pmc === null)
    {
      return redirect()->to('/roster');
    }
    return $this->form($pmc, null, old('name') !== null ? $this->roster($pmc)->clean($this->request->getPost() ?: []) : null);
  }
  public function store()
  {
    $pmc = $this->pmc();
    if ($pmc === null)
    {
      return redirect()->to('/roster');
    }
    $roster = $this->roster($pmc);
    $clean = $roster->clean($this->request->getPost() ?: []);
    $errors = $roster->validate($clean);
    if ($errors !== [])
    {
      return redirect()->back()->withInput()->with('errors', $errors);
    }
    $id = $roster->add($clean);
    ActivityLogModel::record('roster.added', (int)session('user')['id'], (string)session('user')['email'], $pmc['name'] . ' #' . $id . ' ' . $clean['name']);
    return redirect()->to('/roster')->with('success', $clean['name'] . ' was added.');
  }
  public function edit(int $id)
  {
    $pmc = $this->pmc();
    $person = $pmc === null ? null : $this->roster($pmc)->person($id);
    if ($person === null)
    {
      throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
    }
    return $this->form($pmc, $person, old('name') !== null ? $this->roster($pmc)->clean($this->request->getPost() ?: []) : null);
  }
  public function update(int $id)
  {
    $pmc = $this->pmc();
    $roster = $pmc === null ? null : $this->roster($pmc);
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
      ActivityLogModel::record('roster.' . $r['action'], (int)session('user')['id'], (string)session('user')['email'], $pmc['name'] . ' #' . $id . ' ' . $clean['name'] . ': ' . implode(', ', array_keys($r['changes'])));
    }
    $said = match ($r['action'])
    {
      'departed' => $clean['name'] . ' is marked as departed.',
      'reinstated' => $clean['name'] . ' is back on the roster.',
      'unchanged' => 'Nothing changed.',
      default => $clean['name'] . ' was updated.',
    };
    return redirect()->to('/roster')->with('success', $said);
  }
  public function history(int $id)
  {
    $pmc = $this->pmc();
    $person = $pmc === null ? null : $this->roster($pmc)->person($id);
    if ($person === null)
    {
      throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
    }
    return view('pages/roster/history', [
      'title' => 'History · ' . $person['name'],
      'crumbs' => [['Home', '/'], ['Staff roster', 'roster'], ['Edit ' . $person['name'], 'roster/' . (int)$person['id'] . '/edit'], ['History', null]],
      'pmc' => $pmc,
      'person' => $person,
      'entries' => (new StaffChangeModel())->recent((int)$pmc['id'], 500),
      'names' => $this->names($pmc, $person['properties']),
    ]);
  }
  private function form(array $pmc, ?array $person, ?array $values)
  {
    $values = $values ?? ($person ? $person + ['properties' => $person['properties']] : ['properties' => []]);
    return view('pages/roster/form', [
      'title' => ($person ? 'Edit ' . $person['name'] : 'Add a person') . ' · ' . $pmc['name'],
      'crumbs' => [['Home', '/'], ['Staff roster', 'roster'], [$person ? 'Edit ' . $person['name'] : 'Add a person', null]],
      'pmc' => $pmc,
      'person' => $person,
      'v' => $values,
      'positions' => config(Partner::class)->positions,
      'names' => $this->names($pmc, $person['properties'] ?? []),
      'errors' => session()->getFlashdata('errors') ?: [],
    ]);
  }
}
