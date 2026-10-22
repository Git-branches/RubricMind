<?php

namespace App\Controllers;

use CodeIgniter\HTTP\RedirectResponse;

class Auth extends BaseController
{
    // At most this many sign-in attempts per IP address per minute.
    private const MAX_ATTEMPTS_PER_MINUTE = 5;

    // And at most this many per account every 15 minutes, against guessing from many addresses.
    private const MAX_ATTEMPTS_PER_ACCOUNT = 10;

    public function login(): RedirectResponse|string
    {
        if (service('auth')->check()) {
            return redirect()->to(site_url('/'));
        }

        // Filled in after creating an account (see Registration::create()).
        return view('auth/login', ['email' => (string) (session('registered_email') ?? '')]);
    }

    public function attempt(): RedirectResponse|string
    {
        $email    = strtolower(trim((string) $this->request->getPost('email')));
        $password = (string) $this->request->getPost('password');

        $rules = [
            'email'    => 'required|valid_email|max_length[191]',
            'password' => 'required|max_length[72]',
        ];

        // Failed attempts re-render the form instead of redirecting, so the
        // password is never copied into the session as "old input".
        if (! $this->validateData(['email' => $email, 'password' => $password], $rules)) {
            return view('auth/login', ['email' => $email, 'errors' => $this->validator->getErrors()]);
        }

        $throttler = service('throttler');

        if (! $throttler->check('login-' . md5($this->request->getIPAddress()), self::MAX_ATTEMPTS_PER_MINUTE, MINUTE)
            || ! $throttler->check('login-account-' . md5($email), self::MAX_ATTEMPTS_PER_ACCOUNT, 15 * MINUTE)) {
            return view('auth/login', [
                'email' => $email,
                'error' => 'Too many sign-in attempts. Please wait a few minutes and try again.',
            ]);
        }

        $result = service('auth')->attempt($email, $password);

        if ($result['error'] !== null) {
            audit_log('auth.login_failed', 'user', $result['user']['id'] ?? null, [
                'email'  => $email,
                'reason' => $result['error'],
            ]);

            $message = $result['error'] === 'inactive'
                ? 'This account is inactive. Please contact your administrator.'
                : 'Incorrect email or password.';

            return view('auth/login', ['email' => $email, 'error' => $message]);
        }

        service('auth')->login($result['user']);
        audit_log('auth.login', 'user', $result['user']['id']);

        // Straight to the dashboard (not "/"), so the welcome message survives one redirect.
        $firstName = explode(' ', trim($result['user']['name']))[0];

        return redirect()->to(Dashboard::homeFor($result['user']['role']))->with('success', "Welcome back, {$firstName}!");
    }

    public function logout(): RedirectResponse
    {
        audit_log('auth.logout', 'user', service('auth')->id());
        service('auth')->logout();

        return redirect()->to(site_url('login'))->with('success', 'You have been signed out.');
    }
}
