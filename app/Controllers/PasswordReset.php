<?php

namespace App\Controllers;

use App\Libraries\DefenseDemo;
use App\Libraries\PasswordResets;
use App\Models\UserModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * "Forgot your password?": a one-time reset link sent by email (FR-01).
 * Administrators can still reset any password from the Users page.
 */
class PasswordReset extends BaseController
{
    // At most this many reset requests per IP address, and per email address, every 15 minutes.
    private const MAX_REQUESTS_PER_IP    = 5;
    private const MAX_REQUESTS_PER_EMAIL = 3;

    public function forgot(): string
    {
        return view('auth/forgot', ['email' => '']);
    }

    public function send(): RedirectResponse|string
    {
        $email = strtolower(trim((string) $this->request->getPost('email')));

        if (! $this->validateData(['email' => $email], ['email' => 'required|valid_email|max_length[191]'])) {
            return view('auth/forgot', ['email' => $email, 'errors' => $this->validator->getErrors()]);
        }

        $throttler = service('throttler');

        if (! $throttler->check('reset-' . md5($this->request->getIPAddress()), self::MAX_REQUESTS_PER_IP, 15 * MINUTE)
            || ! $throttler->check('reset-email-' . md5($email), self::MAX_REQUESTS_PER_EMAIL, 15 * MINUTE)) {
            return view('auth/forgot', [
                'email' => $email,
                'error' => 'Too many reset requests. Please wait a few minutes and try again.',
            ]);
        }

        $user = model(UserModel::class)->findByEmail($email);
        $demo = DefenseDemo::resetLinksEnabled($this->request->getIPAddress());

        if ($user !== null && $user['status'] === 'active') {
            $token  = (new PasswordResets(db_connect()))->create($user['id']);
            $status = $this->sendLink($user, $token);
            audit_log('auth.reset_requested', 'user', $user['id']);

            // Defense Demo Mode: show the same link on screen (see demo()).
            if ($demo) {
                audit_log('auth.reset_link_shown_demo', 'user', $user['id']);

                return redirect()->to(site_url('forgot-password/demo'))->with('demo_reset', [
                    'email'       => $user['email'],
                    'link'        => site_url("reset-password/{$token}"),
                    'expires_at'  => date('Y-m-d H:i:s', time() + PasswordResets::LIFETIME_MINUTES * 60),
                    'mail_status' => $status,
                ]);
            }
        } elseif ($demo) {
            // Only in demo mode, on this computer, is it fine to say that the account does not exist.
            return view('auth/forgot', [
                'email' => $email,
                'error' => 'No active account uses this email address. Check the spelling and try again.',
            ]);
        }

        // The same answer whether or not the account exists, so the form cannot
        // be used to find out which emails have accounts.
        $message = 'If an account exists for ' . $email . ', we sent it a link to reset the password. The link works for '
            . PasswordResets::LIFETIME_MINUTES . ' minutes.';

        if (! service('mailer')->smtpConfigured()) {
            $message .= ' This school has not set up email delivery yet, so ask your administrator to open the link from the Email outbox.';
        }

        return redirect()->to(site_url('login'))->with('info', $message);
    }

    /**
     * Defense Demo Mode: "Demo Email Ready". Shows the reset link that was just
     * created and emailed, so the flow can be shown without a mailbox. The link
     * is kept for one page view only (flash data) and never appears outside demo mode.
     */
    public function demo(): RedirectResponse|string
    {
        $demo = session()->getFlashdata('demo_reset');

        if (! DefenseDemo::resetLinksEnabled($this->request->getIPAddress()) || ! is_array($demo)) {
            return redirect()->to(site_url('forgot-password'));
        }

        return view('auth/demo_reset', $demo + ['mailpit' => service('mailer')->smtpConfigured() ? service('mailer')->testInboxUrl() : '']);
    }

    public function form(string $token): RedirectResponse|string
    {
        if ((new PasswordResets(db_connect()))->userFor($token) === null) {
            return $this->invalidLink();
        }

        return view('auth/reset', ['token' => $token]);
    }

    public function update(string $token): RedirectResponse|string
    {
        $resets = new PasswordResets(db_connect());
        $user   = $resets->userFor($token);

        if ($user === null) {
            return $this->invalidLink();
        }

        $rules = [
            'password'         => ['label' => 'new password', 'rules' => UserModel::PASSWORD_RULES],
            'password_confirm' => ['label' => 'password confirmation', 'rules' => 'required|matches[password]',
                'errors' => ['matches' => 'The two passwords do not match.']],
        ];

        $data = [
            'password'         => (string) $this->request->getPost('password'),
            'password_confirm' => (string) $this->request->getPost('password_confirm'),
        ];

        if (! $this->validateData($data, $rules)) {
            return view('auth/reset', ['token' => $token, 'errors' => $this->validator->getErrors()]);
        }

        $resets->complete($user, $data['password']);
        audit_log('auth.password_reset_by_email', 'user', $user['id']);

        return redirect()->to(site_url('login'))->with('success', 'Your password has been changed. Sign in with your new password.');
    }

    /**
     * @return string The Mailer status: 'sent', 'saved', or 'failed'.
     */
    private function sendLink(array $user, string $token): string
    {
        $firstName = explode(' ', trim($user['name']))[0];
        $link      = site_url("reset-password/{$token}");
        $minutes   = PasswordResets::LIFETIME_MINUTES;

        $body = <<<TEXT
            Hi {$firstName},

            We received a request to reset the password for your RubricMind account ({$user['email']}).

            To choose a new password, open this link:
            {$link}

            The link works once and expires in {$minutes} minutes.

            If you did not ask for this, you can ignore this email. Your password will not change.

            RubricMind
            TEXT;

        return service('mailer')->send($user['email'], 'Reset your RubricMind password', $body, $user['id']);
    }

    private function invalidLink(): RedirectResponse
    {
        return redirect()->to(site_url('forgot-password'))
            ->with('error', 'This reset link is invalid, already used, or expired. Request a new one below.');
    }
}
