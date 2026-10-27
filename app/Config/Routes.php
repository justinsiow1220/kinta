<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');
$routes->get('api/flutter-test', 'Api\\FlutterTestController::index');
$routes->get('api/mysql-test', 'Api\\DatabaseTestController::index');
$routes->get('api/flutter-db-test', 'Api\\DatabaseTestController::records');
$routes->get('api/flutter-crud-test', 'Api\\FlutterCrudTestController::index');
$routes->post('api/flutter-crud-test', 'Api\\FlutterCrudTestController::create');
$routes->put('api/flutter-crud-test/(:num)', 'Api\\FlutterCrudTestController::update/$1');
$routes->delete('api/flutter-crud-test/(:num)', 'Api\\FlutterCrudTestController::delete/$1');
$routes->options('api/flutter-crud-test', 'Api\\FlutterCrudTestController::preflight');
$routes->options('api/flutter-crud-test/(:num)', 'Api\\FlutterCrudTestController::preflight');
$routes->get('api/master-data/users', 'Api\\MasterDataController::users');
$routes->get('api/master-data/machines', 'Api\\MasterDataController::machines');
$routes->get('api/master-data/sectors', 'Api\\MasterDataController::sectors');
$routes->get('api/master-data/activities', 'Api\\MasterDataController::activities');
$routes->get('api/master-data/sales-orders', 'Api\\MasterDataController::salesOrders');
$routes->get('api/master-data/problems', 'Api\\MasterDataController::problems');
$routes->get('api/processes/active', 'Api\\ProcessController::active');
$routes->get('api/processes/(:num)', 'Api\\ProcessController::show/$1');
$routes->post('api/processes', 'Api\\ProcessController::start');
$routes->options('api/processes', 'Api\\ProcessController::preflight');
$routes->put('api/processes/(:num)/end', 'Api\\ProcessController::end/$1');
$routes->options('api/processes/(:num)/end', 'Api\\ProcessController::preflight');
$routes->get('api/processes/(:num)/qc-inspections', 'Api\\ProcessController::qcInspections/$1');
$routes->post('api/processes/(:num)/qc-inspections', 'Api\\ProcessController::recordQcInspection/$1');
$routes->options('api/processes/(:num)/qc-inspections', 'Api\\ProcessController::preflight');
$routes->get('api/processes/(:num)/problems', 'Api\\ProcessController::processProblems/$1');
$routes->post('api/processes/(:num)/problems', 'Api\\ProcessController::attachProblems/$1');
$routes->options('api/processes/(:num)/problems', 'Api\\ProcessController::preflight');
$routes->get('api/processes/(:num)/pic-history', 'Api\\ProcessController::picHistory/$1');
$routes->put('api/processes/(:num)/pic', 'Api\\ProcessController::changePic/$1');
$routes->options('api/processes/(:num)/pic', 'Api\\ProcessController::preflight');
$routes->get('api/processes/(:num)/operator-history', 'Api\\ProcessController::operatorHistory/$1');
$routes->put('api/processes/(:num)/operators', 'Api\\ProcessController::updateOperators/$1');
$routes->options('api/processes/(:num)/operators', 'Api\\ProcessController::preflight');
