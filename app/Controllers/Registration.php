<?php

namespace App\Controllers;

use App\Models\UserModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Public student self-registration (FR-14).
 *
 * Always creates an active account with the student role: nothing posted can
 * choose another role. A new account is in no class until the student joins
 * one with a class code (or a teacher enrolls them). No email is needed, so it
 * works offline.
 */
class Registration extends BaseController
{
    // At most this many registrations per IP address per hour.
    private const MAX_PER_HOUR = 5;

    public function form(): RedirectResponse|string
    {
        if (service('auth')->check()) {
            return redirect()->to(site_url('/'));
        }

        return view('auth/register', ['old' => ['name' => '', 'email' => ''], 'open' => service('systemSettings')->registrationEnabled()]);
    }

    public function create(): RedirectResponse|string
    {
        if (! service('systemSettings')->registrationEnabled()) {
            return view('auth/register', ['old' => ['name' => '', 'email' => ''], 'open' => false]);
        }

        $data = [
            'name'             => trim(preg_replace('/\s+/u', ' ', (string) $this->request->getPost('name')) ?? ''),
            'email'            => strtolower(trim((string) $this->request->getPost('email'))),
            'password'         => (string) $this->request->getPost('password'),
            'password_confirm' => (string) $this->request->getPost('password_confirm'),
        ];

        $rules = [
            'name'     => ['label' => 'full name', 'rules' => 'required|min_length[2]|max_length[100]'],
            'email'    => ['label' => 'email address', 'rules' => 'required|valid_email|max_length[191]|is_unique[users.email]',
                'errors' => ['is_unique' => 'An account already uses this email. Sign in instead, or use "Forgot your password?".']],
            'password' => ['label' => 'password', 'rules' => UserModel::PASSWORD_RULES],
            'password_confirm' => ['label' => 'password confirmation', 'rules' => 'required|matches[password]',
                'errors' => ['matches' => 'The two passwords do not match.']],
        ];

        $old = ['name' => $data['name'], 'email' => $data['email']];

        // Passwords are never sent back to the form.
        if (! $this->validateData($data, $rules)) {
            return view('auth/register', ['old' => $old, 'open' => true, 'errors' => $this->validator->getErrors()]);
        }

        if (! service('throttler')->check('register-' . md5($this->request->getIPAddress()), self::MAX_PER_HOUR, HOUR)) {
            return view('auth/register', ['old' => $old, 'open' => true, 'error' => 'Too many accounts were created from this device. Please try again later.']);
        }

        $id = model(UserModel::class)->insert([
            'name'                 => $data['name'],
            'email'                => $data['email'],
            'password_hash'        => UserModel::hashPassword($data['password']),
            'role'                 => 'student',
            'status'               => 'active',
            'must_change_password' => false,
        ]);

        audit_log('auth.registered', 'user', $id, ['email' => $data['email']]);

        return redirect()->to(site_url('login'))
            ->with('registered_email', $data['email'])
            ->with('success', 'Your student account is ready. Sign in, then open My classes and enter your class code.');
    }
}
