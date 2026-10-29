<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthFilter implements FilterInterface
{
  public function before(RequestInterface $request, $arguments = null)
  {
    if (session()->has('user'))
    {
      return null;
    }
    if (self::wantsJson($request))
    {
      return service('response')->setStatusCode(401)->setJSON(['status' => false, 'error' => 'session_expired', 'message' => 'Your session has expired. Please sign in again.']);
    }
    return redirect()->to('/login?expired=1');
  }
  public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
  {
  }
  public static function wantsJson(RequestInterface $request): bool
  {
    if (!$request instanceof IncomingRequest)
    {
      return false;
    }
    return $request->isAJAX() || str_contains((string)$request->getHeaderLine('Accept'), 'application/json');
  }
}
