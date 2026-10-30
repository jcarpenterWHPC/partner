<?php

namespace App\Controllers;

use App\Models\PmcModel;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

abstract class BaseController extends Controller
{
  /**
   * @param CLIRequest|IncomingRequest $request
   *
   * @return void
   */
  public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
  {
    parent::initController($request, $response, $logger);
  }
  /**
   * The organisation in scope: a partner user's own; for an admin the one asked for
   * (`?pmc=`), else the one they last looked at, else the first. Null when there is none.
   */
  protected function org(): ?array
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
    $org = $want > 0 ? $pmcs->find($want) : null;
    if ($org === null)
    {
      $org = $pmcs->active()[0] ?? null;
    }
    if ($org !== null)
    {
      session()->set('admin_pmc', (int)$org['id']);
    }
    return $org;
  }
  /** Every organisation, for an admin's picker; nothing for a partner user. */
  protected function orgChoices(): array
  {
    return !empty(session('user')['is_admin']) ? (new PmcModel())->active() : [];
  }
}
