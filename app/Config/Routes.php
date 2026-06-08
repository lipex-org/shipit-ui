<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');
$routes->get('dashboard', 'Dashboard::index');
$routes->get('login', 'Auth::login');
$routes->post('login', 'Auth::login');
$routes->get('logout', 'Auth::logout');

$routes->post('projects/deploy', 'Projects::deploy');
$routes->post('projects/rollback', 'Projects::rollback');
$routes->post('projects/init', 'Projects::init');
$routes->post('projects/webhook/regenerate', 'Projects::regenerateWebhookToken');
$routes->get('projects/env', 'Projects::getEnv');
$routes->post('projects/env', 'Projects::updateEnv');
$routes->get('projects/config', 'Projects::getProjectConfig');
$routes->post('projects/config', 'Projects::updateProjectConfig');
$routes->get('projects/validate', 'Projects::validateLog');
$routes->get('projects/logs/(:any)', 'Projects::logs/$1');
$routes->post('api/webhook/(:any)', 'Api::webhook/$1');

$routes->post('registry/prune', 'Dashboard::prune');

$routes->get('integrations/github/connect', 'Integrations::githubConnect');
$routes->get('integrations/github/callback', 'Integrations::githubCallback');
$routes->get('api/github/repos', 'Integrations::githubRepos');
$routes->post('api/github/setup-webhook', 'Integrations::setupWebhook');



