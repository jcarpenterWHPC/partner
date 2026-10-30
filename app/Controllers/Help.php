<?php

namespace App\Controllers;

use App\Models\PmcModel;
use Config\Partner;

class Help extends BaseController
{
  public function index()
  {
    $org = $this->org();
    $cfg = config(Partner::class);
    $kind = $org['kind'] ?? PmcModel::MANAGEMENT;
    return view('pages/help', [
      'title' => 'How the portal works',
      'crumbs' => [['Home', '/'], ['Help', null]],
      'user' => session('user'),
      'org' => $org,
      'services' => $kind === PmcModel::SERVICES,
      'expected' => $cfg->propertyRoles[$kind] ?? [],
      'supportMail' => $cfg->supportMail,
      'microsoft' => $cfg->microsoftEnabled(),
    ]);
  }
}
