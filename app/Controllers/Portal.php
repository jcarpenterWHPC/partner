<?php

namespace App\Controllers;

use Config\Capex;

class Portal extends BaseController
{
  public function index()
  {
    $org = $this->org();
    return view('pages/portal', [
      'title' => config('App')->brandName,
      'user' => session('user'),
      'org' => $org,
      'services' => \App\Models\PmcModel::isServices($org ?? []),
      'supportMail' => config(\Config\Partner::class)->supportMail,
      'capexUrl' => config(Capex::class)->url,
    ]);
  }
}
