<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('login', 'Auth::login');
$routes->post('login', 'Auth::send');
$routes->get('login/verify', 'Auth::verify');
$routes->post('login/verify', 'Auth::verifySubmit');
$routes->post('login/resend', 'Auth::resend');
$routes->get('login/link/(:segment)', 'Auth::link/$1');
$routes->post('logout', 'Auth::logout');
$routes->get('logout', 'Auth::logout');

$routes->group('', ['filter' => 'auth'], static function (RouteCollection $routes)
{
  $routes->get('/', 'Portal::index');
});
