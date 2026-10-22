<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// Sign-in
$routes->get('login', 'Auth::login');
$routes->post('login', 'Auth::attempt');
$routes->post('logout', 'Auth::logout', ['filter' => 'auth']);

// Public student registration (FR-14); student accounts only
$routes->get('register', 'Registration::form');
$routes->post('register', 'Registration::create');

// Forgot password: a one-time reset link by email
$routes->get('forgot-password', 'PasswordReset::forgot');
$routes->post('forgot-password', 'PasswordReset::send');
$routes->get('forgot-password/demo', 'PasswordReset::demo');
$routes->get('reset-password/(:segment)', 'PasswordReset::form/$1');
$routes->post('reset-password/(:segment)', 'PasswordReset::update/$1');

// Sends each signed-in user to their role's dashboard.
$routes->get('/', 'Dashboard::index', ['filter' => 'auth']);

// Any signed-in user
$routes->group('account', ['filter' => 'auth'], static function (RouteCollection $routes) {
    $routes->get('password', 'Account::password');
    $routes->post('password', 'Account::updatePassword');
});

// Administrator
$routes->group('admin', ['namespace' => 'App\Controllers\Admin', 'filter' => ['auth', 'role:admin']], static function (RouteCollection $routes) {
    $routes->get('/', 'Dashboard::index');

    $routes->get('users', 'Users::index');
    $routes->get('users/new', 'Users::new');
    $routes->post('users', 'Users::create');
    $routes->get('users/(:num)/edit', 'Users::edit/$1');
    $routes->post('users/(:num)', 'Users::update/$1');
    $routes->post('users/(:num)/status', 'Users::toggleStatus/$1');
    $routes->get('users/(:num)/password', 'Users::resetPasswordForm/$1');
    $routes->post('users/(:num)/password', 'Users::resetPassword/$1');

    $routes->get('audit-logs', 'AuditLogs::index');

    $routes->get('emails', 'Emails::index');
    $routes->get('emails/(:num)', 'Emails::show/$1');

    $routes->get('settings', 'Settings::index');
    $routes->post('settings', 'Settings::update');
    $routes->post('retention/classes/(:num)/delete', 'Settings::purgeClass/$1');
});

// Teacher
$routes->group('teacher', ['namespace' => 'App\Controllers\Teacher', 'filter' => ['auth', 'role:teacher']], static function (RouteCollection $routes) {
    $routes->get('/', 'Dashboard::index');

    $routes->get('classes', 'Classes::index');
    $routes->get('classes/new', 'Classes::new');
    $routes->post('classes', 'Classes::create');
    $routes->get('classes/(:num)', 'Classes::show/$1');
    $routes->get('classes/(:num)/edit', 'Classes::edit/$1');
    $routes->post('classes/(:num)', 'Classes::update/$1');
    $routes->post('classes/(:num)/archive', 'Classes::toggleArchive/$1');
    $routes->post('classes/(:num)/delete', 'Classes::delete/$1');
    $routes->post('classes/(:num)/students/(:num)/remove', 'Classes::unenroll/$1/$2');
    $routes->post('classes/(:num)/code/regenerate', 'Classes::regenerateCode/$1');
    $routes->post('classes/(:num)/code/toggle', 'Classes::toggleCode/$1');

    $routes->get('students', 'Students::index');
    $routes->get('students/new', 'Students::new');
    $routes->post('students', 'Students::create');

    $routes->get('classes/(:num)/assignments/new', 'Assignments::new/$1');
    $routes->post('classes/(:num)/assignments', 'Assignments::create/$1');
    $routes->get('assignments/(:num)', 'Assignments::show/$1');
    $routes->get('assignments/(:num)/edit', 'Assignments::edit/$1');
    $routes->post('assignments/(:num)', 'Assignments::update/$1');
    $routes->post('assignments/(:num)/status', 'Assignments::updateStatus/$1');
    $routes->post('assignments/(:num)/delete', 'Assignments::delete/$1');

    $routes->post('assignments/(:num)/release', 'Assignments::releaseAll/$1');

    $routes->get('insights', 'Insights::index');
    $routes->get('assignments/(:num)/insights', 'Insights::assignment/$1');
    $routes->post('assignments/(:num)/lesson-plans', 'Insights::generatePlan/$1');
    $routes->get('lesson-plans/(:num)', 'Insights::plan/$1');
    $routes->post('lesson-plans/(:num)', 'Insights::updatePlan/$1');
    $routes->post('lesson-plans/(:num)/delete', 'Insights::deletePlan/$1');

    $routes->get('submissions/(:num)', 'Submissions::show/$1');
    $routes->get('submissions/(:num)/file', 'Submissions::file/$1');
    $routes->post('submissions/(:num)/start', 'Submissions::start/$1');
    $routes->post('submissions/(:num)/ai', 'Submissions::requestAi/$1');
    $routes->post('submissions/(:num)/style-review', 'Submissions::reviewStyle/$1');
    $routes->post('submissions/(:num)/grade', 'Submissions::grade/$1');
    $routes->post('submissions/(:num)/release', 'Submissions::release/$1');
    $routes->post('submissions/(:num)/reopen', 'Submissions::reopen/$1');

    $routes->get('rubrics', 'Rubrics::index');
    $routes->get('rubrics/new', 'Rubrics::new');
    $routes->post('rubrics', 'Rubrics::create');
    $routes->get('rubrics/(:num)', 'Rubrics::show/$1');
    $routes->get('rubrics/(:num)/edit', 'Rubrics::edit/$1');
    $routes->post('rubrics/(:num)', 'Rubrics::update/$1');
    $routes->post('rubrics/(:num)/duplicate', 'Rubrics::duplicate/$1');
    $routes->post('rubrics/(:num)/delete', 'Rubrics::delete/$1');
});

// Student
$routes->group('student', ['namespace' => 'App\Controllers\Student', 'filter' => ['auth', 'role:student']], static function (RouteCollection $routes) {
    $routes->get('/', 'Dashboard::index');

    $routes->get('classes', 'Classes::index');
    $routes->post('classes/join', 'Classes::join');
    $routes->get('classes/(:num)', 'Classes::show/$1');

    $routes->get('assignments/(:num)', 'Assignments::show/$1');
    $routes->post('assignments/(:num)/submission', 'Assignments::submit/$1');
    $routes->post('assignments/(:num)/upload', 'Assignments::upload/$1');
    $routes->get('assignments/(:num)/file', 'Assignments::file/$1');
});
