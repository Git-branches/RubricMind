<?php

namespace Tests\Unit;

use App\Libraries\Mailer;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\Mock\MockConnection;
use Config\Email;

/**
 * Which email settings count as "set up" (see App\Libraries\Mailer).
 *
 * @internal
 */
final class MailerTest extends CIUnitTestCase
{
    private function mailer(array $settings): Mailer
    {
        $config = new Email();

        foreach ($settings + ['protocol' => 'smtp', 'SMTPHost' => '', 'SMTPUser' => '', 'SMTPPass' => '', 'fromEmail' => '', 'testInboxURL' => ''] as $key => $value) {
            $config->{$key} = $value;
        }

        return new Mailer(new MockConnection([]), $config);
    }

    public function testGmailNeedsAccountAndPassword(): void
    {
        $gmail = ['SMTPHost' => 'smtp.gmail.com', 'fromEmail' => 'teacher@gmail.com', 'SMTPUser' => 'teacher@gmail.com'];

        $this->assertFalse($this->mailer($gmail)->smtpConfigured(), 'An account without a password must not count as set up.');
        $this->assertTrue($this->mailer($gmail + ['SMTPPass' => 'app-password'])->smtpConfigured());
    }

    public function testLocalTestServerNeedsNoLogin(): void
    {
        $mailpit = $this->mailer(['SMTPHost' => '127.0.0.1', 'fromEmail' => 'no-reply@rubricmind.local', 'testInboxURL' => 'http://localhost:8025']);

        $this->assertTrue($mailpit->smtpConfigured());
        $this->assertSame('http://localhost:8025', $mailpit->testInboxUrl());
    }

    public function testNothingIsSentWithoutSenderOrServer(): void
    {
        $this->assertFalse($this->mailer(['SMTPHost' => '127.0.0.1'])->smtpConfigured());
        $this->assertFalse($this->mailer(['fromEmail' => 'no-reply@rubricmind.local'])->smtpConfigured());
        $this->assertFalse($this->mailer(['protocol' => 'mail', 'SMTPHost' => '127.0.0.1', 'fromEmail' => 'x@y.z'])->smtpConfigured());
    }
}
