<?php

namespace App\Controllers;

use Config\Capex;

class Portal extends BaseController
{
  public function index()
  {
    return view('pages/portal', [
      'title' => config('App')->brandName,
      'user' => session('user'),
      'capexUrl' => config(Capex::class)->url,
    ]);
  }
}
