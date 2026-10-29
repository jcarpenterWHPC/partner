<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Security\Exceptions\SecurityException;

/**
 * The framework's CSRF check, plus one thing: a failure on a request with NO signed-in
 * session is a token that died with its session, not an attack, and it is answered
 * with the same "sign in again" the auth filter gives rather than a bare 403.
 */
class CsrfGuard implements FilterInterface
{
  public function before(RequestInterface $request, $arguments = null)
  {
    if (!$request instanceof IncomingRequest)
    {
      return null;
    }
    try
    {
      service('security')->verify($request);
    }
    catch (SecurityException $e)
    {
      if (session()->has('user'))
      {
        throw $e;
      }
      if (AuthFilter::wantsJson($request))
      {
        return service('response')->setStatusCode(401)->setJSON(['status' => false, 'error' => 'session_expired', 'message' => 'Your session has expired. Please sign in again.']);
      }
      return redirect()->to('/login?expired=1');
    }
    return null;
  }
  public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
  {
  }
}
