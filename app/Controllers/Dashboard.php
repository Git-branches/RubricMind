<?php

namespace App\Controllers;

use CodeIgniter\HTTP\RedirectResponse;

class Dashboard extends BaseController
{
    private const HOME_BY_ROLE = [
        'admin'   => 'admin',
        'teacher' => 'teacher',
        'student' => 'student',
    ];

    public static function homeFor(string $role): string
    {
        return site_url(self::HOME_BY_ROLE[$role]);
    }

    public function index(): RedirectResponse
    {
        return redirect()->to(self::homeFor(service('auth')->user()['role']));
    }
}
