<?php

namespace App\Controllers;

use App\Models\ActivityLogModel;
use App\Models\PmcModel;
use App\Models\PropertyModel;
use App\Models\StaffModel;
use App\Models\VacancyModel;
use Config\Partner;

/**
 * The organisation's properties with who holds each expected role: filled, or OPEN
 * since a date with who is covering. A management company sees its own properties; a
 * service agency sees the ones its people cover or it has flagged as open.
 */
class PropertiesController extends BaseController
{
  /** @return list<string> the roles every property of this organisation should have filled */
  private function expected(array $org): array
  {
    return config(Partner::class)->propertyRoles[$org['kind'] ?? PmcModel::MANAGEMENT] ?? [];
  }
  /**
   * One row per property: its people by role, and the open vacancies.
   *
   * @return array{rows:list<array>, expected:list<string>}
   */
  private function board(array $org): array
  {
    $expected = $this->expected($org);
    $props = new PropertyModel();
    $open = (new VacancyModel())->openFor((int)$org['id']);
    $people = (new StaffModel())->forPmc((int)$org['id'], false);
    $holders = [];
    foreach ($people as $p)
    {
      foreach ($p['roles'] as $pn => $role)
      {
        $holders[(string)$pn][$role][] = $p;
      }
    }
    if (PmcModel::isServices($org))
    {
      $numbers = array_unique(array_merge(array_keys($holders), array_map(static fn(array $v): string => (string)$v['property_number'], $open)));
      $list = $numbers === [] ? [] : $props->whereIn('property_number', array_values($numbers))->orderBy('name')->findAll();
    }
    else
    {
      $list = $props->forPmc((int)$org['id']);
    }
    $rows = [];
    foreach ($list as $pr)
    {
      $pn = (string)$pr['property_number'];
      $cells = [];
      $attention = 0;
      foreach ($expected as $role)
      {
        $v = $open[VacancyModel::key($pn, $role)] ?? null;
        $who = $holders[$pn][$role] ?? [];
        if ($who === [] && ($v === null || trim((string)$v['coverage']) === ''))
        {
          $attention++;
        }
        $cells[$role] = ['people' => $who, 'vacancy' => $v];
      }
      $others = [];
      foreach ($holders[$pn] ?? [] as $role => $who)
      {
        if (!in_array($role, $expected, true))
        {
          foreach ($who as $p)
          {
            $others[] = $p['name'] . ' (' . $role . ')';
          }
        }
      }
      $rows[] = ['property' => $pr, 'cells' => $cells, 'others' => $others, 'attention' => $attention];
    }
    return ['rows' => $rows, 'expected' => $expected];
  }
  public function index()
  {
    $org = $this->org();
    if ($org === null)
    {
      return redirect()->to('/admin/pmcs')->with('error', 'No organization has been synced yet.');
    }
    $b = $this->board($org);
    return view('pages/properties/index', [
      'title' => 'Properties · ' . $org['name'],
      'crumbs' => [['Home', '/'], ['Properties', null]],
      'pmc' => $org,
      'pmcs' => $this->orgChoices(),
      'rows' => $b['rows'],
      'expected' => $b['expected'],
      'attention' => array_sum(array_column($b['rows'], 'attention')),
    ]);
  }
  /** The form for one property × role: who holds it, or since when it is open and who covers. */
  public function open()
  {
    $org = $this->org();
    $pn = trim((string)$this->request->getGet('property'));
    $role = trim((string)$this->request->getGet('role'));
    $property = $org === null ? null : (new PropertyModel())->find($pn);
    if ($property === null || !in_array($role, $this->expected($org), true))
    {
      throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
    }
    $v = (new VacancyModel())->openOne((int)$org['id'], $pn, $role);
    return view('pages/properties/open', [
      'title' => $role . ' · ' . $property['name'],
      'crumbs' => [['Home', '/'], ['Properties', 'properties'], [$property['name'] . ': ' . $role, null]],
      'pmc' => $org,
      'property' => $property,
      'role' => $role,
      'holders' => array_filter((new StaffModel())->forPmc((int)$org['id'], false), static fn(array $p): bool => ($p['roles'][$pn] ?? null) === $role),
      'v' => old('open_since') !== null ? ['open_since' => old('open_since'), 'coverage' => old('coverage')] : ['open_since' => $v['open_since'] ?? date('Y-m-d'), 'coverage' => $v['coverage'] ?? ''],
      'vacancy' => $v,
      'errors' => session()->getFlashdata('errors') ?: [],
    ]);
  }
  public function openSave()
  {
    $org = $this->org();
    $pn = trim((string)$this->request->getPost('property'));
    $role = trim((string)$this->request->getPost('role'));
    $props = new PropertyModel();
    $property = $org === null ? null : $props->find($pn);
    if ($property === null || !in_array($role, $this->expected($org), true))
    {
      throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
    }
    $allowed = array_map('strval', array_column($props->forOrg($org), 'property_number'));
    $vacancies = new VacancyModel();
    $v = $vacancies->openOne((int)$org['id'], $pn, $role);
    if (!in_array($pn, $allowed, true) && $v === null)
    {
      throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
    }
    $since = trim((string)$this->request->getPost('open_since'));
    $coverage = trim((string)$this->request->getPost('coverage'));
    $errors = [];
    $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $since);
    if ($d === false || $d->format('Y-m-d') !== $since)
    {
      $errors['open_since'] = 'Enter the date the position went open as YYYY-MM-DD.';
    }
    if (mb_strlen($coverage) > 255)
    {
      $errors['coverage'] = 'Keep the coverage note under 255 characters.';
    }
    if ($errors !== [])
    {
      return redirect()->back()->withInput()->with('errors', $errors);
    }
    $uid = (int)session('user')['id'];
    if ((new StaffModel())->holding((int)$org['id'], $pn, $role) !== [])
    {
      return redirect()->to('/properties')->with('error', 'Someone holds that position. Edit the person to end their assignment first.');
    }
    if ($v === null)
    {
      $vacancies->insert(['pmc_id' => (int)$org['id'], 'property_number' => $pn, 'role' => $role, 'open_since' => $since, 'coverage' => $coverage, 'created_by' => $uid, 'updated_by' => $uid]);
    }
    else
    {
      $vacancies->update((int)$v['id'], ['open_since' => $since, 'coverage' => $coverage, 'updated_by' => $uid]);
    }
    ActivityLogModel::record('vacancy.recorded', $uid, (string)session('user')['email'], $org['name'] . ' ' . $pn . ' ' . $role . ' open since ' . $since . ($coverage !== '' ? ', covered by ' . $coverage : ''));
    return redirect()->to('/properties')->with('success', $role . ' at ' . $property['name'] . ' is recorded as open since ' . $since . '.');
  }
  /** The current assignments as a spreadsheet, for the partner's own use. */
  public function export()
  {
    $org = $this->org();
    if ($org === null)
    {
      return redirect()->to('/properties');
    }
    $b = $this->board($org);
    $lines = [['Property number', 'Property', 'City', 'Management company', 'Role', 'Name', 'Title', 'Email', 'Mobile', 'Office', 'Open since', 'Covered by']];
    foreach ($b['rows'] as $r)
    {
      $p = $r['property'];
      $base = [(string)$p['property_number'], (string)$p['name'], (string)$p['city'], (string)$p['management_co']];
      foreach ($r['cells'] as $role => $cell)
      {
        if ($cell['people'] === [])
        {
          $v = $cell['vacancy'];
          $lines[] = array_merge($base, [$role, 'OPEN', '', '', '', '', (string)($v['open_since'] ?? ''), (string)($v['coverage'] ?? '')]);
          continue;
        }
        foreach ($cell['people'] as $s)
        {
          $lines[] = array_merge($base, [$role, $s['name'], $s['title'], (int)$s['email_na'] === 1 ? 'N/A' : $s['email'], $s['mobile_phone'], $s['office_phone'], '', '']);
        }
      }
    }
    $out = fopen('php://temp', 'w+');
    fwrite($out, "\xEF\xBB\xBF");
    foreach ($lines as $l)
    {
      fputcsv($out, $l);
    }
    rewind($out);
    $csv = stream_get_contents($out);
    fclose($out);
    $file = preg_replace('/[^A-Za-z0-9]+/', '-', $org['name']) . '-staffing-' . date('Y-m-d') . '.csv';
    return $this->response->setHeader('Content-Type', 'text/csv; charset=UTF-8')->setHeader('Content-Disposition', 'attachment; filename="' . $file . '"')->setBody($csv);
  }
}
