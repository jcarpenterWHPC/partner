<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AdminFilter implements FilterInterface
{
  public function before(RequestInterface $request, $arguments = null)
  {
    if (!empty(session('user')['is_admin']))
    {
      return null;
    }
    if (AuthFilter::wantsJson($request))
    {
      return service('response')->setStatusCode(403)->setJSON(['status' => false, 'message' => 'Admins only.']);
    }
    return service('response')->setStatusCode(403)->setBody('Admins only.');
  }
  public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
  {
  }
}
