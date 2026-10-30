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
$routes->get('login/microsoft', 'Auth::microsoft');
$routes->get('login/microsoft/callback', 'Auth::microsoftCallback');
$routes->post('logout', 'Auth::logout');
$routes->get('logout', 'Auth::logout');

$routes->group('', ['filter' => 'auth'], static function (RouteCollection $routes)
{
  $routes->get('/', 'Portal::index');
  $routes->get('help', 'Help::index');
  $routes->get('roster', 'RosterController::index');
  $routes->get('roster/new', 'RosterController::create');
  $routes->post('roster', 'RosterController::store');
  $routes->get('roster/(:num)/edit', 'RosterController::edit/$1');
  $routes->post('roster/(:num)', 'RosterController::update/$1');
  $routes->get('roster/(:num)/history', 'RosterController::history/$1');
  $routes->get('properties', 'PropertiesController::index');
  $routes->get('properties/export', 'PropertiesController::export');
  $routes->get('properties/open', 'PropertiesController::open');
  $routes->post('properties/open', 'PropertiesController::openSave');
});

$routes->group('admin', ['filter' => ['auth', 'admin']], static function (RouteCollection $routes)
{
  $routes->get('users', 'AdminController::users');
  $routes->post('users', 'AdminController::userStore');
  $routes->post('users/(:num)/toggle', 'AdminController::userToggle/$1');
  $routes->get('users/(:num)/edit', 'AdminController::userEdit/$1');
  $routes->post('users/(:num)', 'AdminController::userUpdate/$1');
  $routes->post('users/(:num)/delete', 'AdminController::userDelete/$1');
  $routes->get('pmcs', 'AdminController::pmcs');
  $routes->post('pmcs', 'AdminController::pmcStore');
  $routes->post('pmcs/sync', 'AdminController::sync');
  $routes->get('changes', 'AdminController::changes');
});
