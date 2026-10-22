<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\EmailOutboxModel;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * The Email outbox: every email the system sent, or saved when email delivery
 * is not set up. Without SMTP settings, this is how reset links reach users.
 */
class Emails extends BaseController
{
    private const PER_PAGE = 25;

    public function index(): string
    {
        $emails = model(EmailOutboxModel::class);

        return view('admin/emails/index', [
            'emails'  => $emails->select('id, to_email, subject, status, error, created_at')->orderBy('id', 'DESC')->paginate(self::PER_PAGE),
            'pager'   => $emails->pager,
            'mailer'  => service('mailer'),
        ]);
    }

    public function show(int $id): string
    {
        $email = model(EmailOutboxModel::class)->find($id) ?? throw PageNotFoundException::forPageNotFound();

        // Opening an email can reveal a reset link, so it is recorded.
        audit_log('email.viewed', 'email', $id, ['to' => $email['to_email']]);

        return view('admin/emails/show', ['email' => $email]);
    }
}
