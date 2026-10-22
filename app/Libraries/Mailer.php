<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Email\Email;
use Config\Email as EmailConfig;
use Throwable;

/**
 * Sends the system's emails (password reset links) and keeps a copy of each
 * one in the `email_outbox` table.
 *
 * - When SMTP is set up in .env (for example Gmail with an App Password), the
 *   email is sent and the copy is marked "sent" or "failed".
 * - Otherwise nothing leaves the server: the copy is marked "saved" and an
 *   administrator reads it on the Email outbox page. This keeps password reset
 *   usable offline and in demos.
 *
 * Access it through service('mailer').
 */
final class Mailer
{
    public function __construct(
        private readonly BaseConnection $db,
        private readonly EmailConfig $config,
    ) {
    }

    /**
     * Whether emails can be sent: an SMTP server and a sender are set. The account
     * and password must both be set (Gmail) or both be empty (a local test server
     * such as Mailpit, which needs no login).
     */
    public function smtpConfigured(): bool
    {
        $hasUser = trim($this->config->SMTPUser) !== '';
        $hasPass = $this->config->SMTPPass !== '';

        return $this->config->protocol === 'smtp'
            && trim($this->config->SMTPHost) !== ''
            && trim($this->config->fromEmail) !== ''
            && $hasUser === $hasPass;
    }

    public function senderAddress(): string
    {
        return trim($this->config->fromEmail);
    }

    /**
     * "smtp.gmail.com:587", for the admin pages.
     */
    public function serverName(): string
    {
        return trim($this->config->SMTPHost) . ':' . $this->config->SMTPPort;
    }

    /**
     * The web inbox of a local test mail server (Mailpit), or '' when none is set.
     */
    public function testInboxUrl(): string
    {
        return trim($this->config->testInboxURL);
    }

    /**
     * Sends (or only saves) an email and records it in the outbox.
     *
     * @return string 'sent', 'saved', or 'failed'
     */
    public function send(string $to, string $subject, string $body, ?int $userId = null): string
    {
        $status = 'saved';
        $error  = null;

        if ($this->smtpConfigured()) {
            [$status, $error] = $this->deliver($to, $subject, $body);
        }

        $this->db->table('email_outbox')->insert([
            'user_id'    => $userId,
            'to_email'   => $to,
            'subject'    => mb_substr($subject, 0, 200),
            'body'       => $body,
            'status'     => $status,
            'error'      => $error === null ? null : mb_substr($error, 0, 500),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        if ($status === 'failed') {
            log_message('error', "Email to {$to} failed: {$error}");
        }

        return $status;
    }

    /**
     * @return array{0: string, 1: string|null} Status and error message.
     */
    private function deliver(string $to, string $subject, string $body): array
    {
        try {
            $email = new Email($this->config);
            $email->setFrom($this->config->fromEmail, $this->config->fromName !== '' ? $this->config->fromName : 'RubricMind');
            $email->setTo($to);
            $email->setSubject($subject);
            $email->setMailType('text');
            $email->setMessage($body);

            if ($email->send(false)) {
                return ['sent', null];
            }

            // The debugger output can include the SMTP conversation; keep only the first line.
            $debug = trim(strip_tags($email->printDebugger([])));

            return ['failed', strtok($debug, "\n") ?: 'The mail server did not accept the email.'];
        } catch (Throwable $e) {
            return ['failed', $e->getMessage()];
        }
    }
}
