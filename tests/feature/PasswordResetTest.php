<?php

namespace Tests\Feature;

use App\Models\UserModel;
use Config\Demo;
use Config\Email;
use Config\Services;
use Tests\Support\FeatureTestCase;

/**
 * "Forgot your password?": reset links by email, delivered to the Email outbox
 * when SMTP is not set up (as in tests).
 *
 * @internal
 */
final class PasswordResetTest extends FeatureTestCase
{
    private const NEW_PASSWORD = 'a-brand-new-passphrase';

    private function requestLink(string $email)
    {
        return $this->post('forgot-password', ['email' => $email]);
    }

    /**
     * The reset token from the newest email in the outbox.
     */
    private function tokenFromOutbox(): string
    {
        $email = db_connect()->table('email_outbox')->orderBy('id', 'DESC')->get()->getRowArray();
        $this->assertNotNull($email, 'No email was saved.');
        $this->assertSame(1, preg_match('~/reset-password/([0-9a-f]{64})~', $email['body'], $m));

        return $m[1];
    }

    public function testLoginPageLinksToForgotPassword(): void
    {
        $this->get('login')->assertSee('Forgot your password?');
        $this->get('forgot-password')->assertSee('Send reset link');
    }

    public function testRequestSavesEmailWithLinkToOutbox(): void
    {
        $user   = $this->makeUser('student', ['name' => 'Juan Dela Cruz']);
        $result = $this->requestLink($user['email']);

        $result->assertRedirectTo(site_url('login'));
        $result->assertSessionHas('info');

        $email = db_connect()->table('email_outbox')->get()->getRowArray();
        $this->assertSame($user['email'], $email['to_email']);
        $this->assertSame('saved', $email['status']);
        $this->assertStringContainsString('Hi Juan,', $email['body']);
        $this->assertSame(1, db_connect()->table('password_resets')->where('user_id', $user['id'])->countAllResults());

        // Only a hash of the token is stored.
        $token = $this->tokenFromOutbox();
        $this->assertSame(0, db_connect()->table('password_resets')->where('token_hash', $token)->countAllResults());
        $this->seeInDatabase('audit_logs', ['action' => 'auth.reset_requested', 'entity_id' => $user['id']]);
    }

    public function testUnknownAndInactiveEmailsGetTheSameAnswerButNoEmail(): void
    {
        $inactive = $this->makeUser('student', ['status' => 'inactive']);

        $unknown = $this->requestLink('nobody@example.com');
        $closed  = $this->requestLink($inactive['email']);

        $this->assertStringStartsWith('If an account exists for', $_SESSION['info']);
        $unknown->assertRedirectTo(site_url('login'));
        $closed->assertRedirectTo(site_url('login'));
        $this->assertSame(0, db_connect()->table('email_outbox')->countAllResults());
    }

    public function testResetLinkSetsNewPasswordOnce(): void
    {
        $user = $this->makeUser('teacher', ['must_change_password' => true]);
        $this->requestLink($user['email']);
        $token = $this->tokenFromOutbox();

        $this->get("reset-password/{$token}")->assertSee('Choose a new password');

        $result = $this->post("reset-password/{$token}", ['password' => self::NEW_PASSWORD, 'password_confirm' => self::NEW_PASSWORD]);
        $result->assertRedirectTo(site_url('login'));
        $result->assertSessionHas('success');

        $updated = model(UserModel::class)->find($user['id']);
        $this->assertTrue(password_verify(self::NEW_PASSWORD, $updated['password_hash']));
        $this->assertFalse($updated['must_change_password']);
        $this->seeInDatabase('audit_logs', ['action' => 'auth.password_reset_by_email', 'entity_id' => $user['id']]);

        // The new password works, and the link cannot be used again.
        $this->post('login', ['email' => $user['email'], 'password' => self::NEW_PASSWORD])->assertSessionHas('user_id', $user['id']);
        $this->get("reset-password/{$token}")->assertRedirectTo(site_url('forgot-password'));
    }

    public function testPasswordsMustMatch(): void
    {
        $user = $this->makeUser('student');
        $this->requestLink($user['email']);
        $token = $this->tokenFromOutbox();

        $result = $this->post("reset-password/{$token}", ['password' => self::NEW_PASSWORD, 'password_confirm' => 'something-else']);

        $result->assertSee('The two passwords do not match.');
        $this->assertTrue(password_verify(self::PASSWORD, model(UserModel::class)->find($user['id'])['password_hash']));
    }

    public function testExpiredAndOlderLinksDoNotWork(): void
    {
        $user = $this->makeUser('student');
        $this->requestLink($user['email']);
        $first = $this->tokenFromOutbox();

        // A new request cancels the earlier link.
        $this->requestLink($user['email']);
        $second = $this->tokenFromOutbox();
        $this->get("reset-password/{$first}")->assertRedirectTo(site_url('forgot-password'));

        db_connect()->table('password_resets')->update(['expires_at' => date('Y-m-d H:i:s', strtotime('-1 minute'))]);
        $this->get("reset-password/{$second}")->assertRedirectTo(site_url('forgot-password'));
        $this->post("reset-password/{$second}", ['password' => self::NEW_PASSWORD, 'password_confirm' => self::NEW_PASSWORD])
            ->assertRedirectTo(site_url('forgot-password'));

        $this->get('reset-password/not-a-real-token')->assertRedirectTo(site_url('forgot-password'));
    }

    public function testResetEndsOtherSessions(): void
    {
        $user = $this->makeUser('teacher');
        $old  = $this->signedInAs($user);

        $this->requestLink($user['email']);
        $token = $this->tokenFromOutbox();
        $this->post("reset-password/{$token}", ['password' => self::NEW_PASSWORD, 'password_confirm' => self::NEW_PASSWORD]);

        // The session from before the reset carries the old password fingerprint.
        // (A real request starts with a fresh auth service; tests share one.)
        Services::resetSingle('auth');
        $result = $old->get('teacher');
        $this->assertStringEndsWith('/login', $result->getRedirectUrl());
    }

    public function testRequestsAreLimitedPerEmail(): void
    {
        $user = $this->makeUser('student');

        for ($i = 0; $i < 3; $i++) {
            $this->requestLink($user['email'])->assertRedirectTo(site_url('login'));
        }

        $this->requestLink($user['email'])->assertSee('Too many reset requests.');
    }

    public function testAdminSeesOutboxAndOpensEmail(): void
    {
        $admin = $this->makeUser('admin');
        $user  = $this->makeUser('student');
        $this->requestLink($user['email']);
        $id = (int) db_connect()->table('email_outbox')->get()->getRowArray()['id'];

        $list = $this->signedInAs($admin)->get('admin/emails');
        $list->assertSee($user['email']);
        $list->assertSee('Saved, not sent');
        $list->assertSee('Email delivery is not set up');

        $this->signedInAs($admin)->get("admin/emails/{$id}")->assertSee('/reset-password/');
        $this->seeInDatabase('audit_logs', ['action' => 'email.viewed', 'entity_id' => $id]);
    }

    // --- Defense Demo Mode -------------------------------------------------------

    private function demoMode(bool $on, string $ip = '127.0.0.1'): void
    {
        config(Demo::class)->passwordResetLinks = $on;
        service('superglobals')->setServer('REMOTE_ADDR', $ip);
    }

    public function testDemoModeIsOffByDefault(): void
    {
        // The default written in Config\Demo (the .env file of the defense laptop may turn it on).
        $this->assertFalse((new \ReflectionProperty(Demo::class, 'passwordResetLinks'))->getDefaultValue(), 'Config\Demo must default to off.');

        $user = $this->makeUser('student');
        $this->demoMode(false);

        $this->requestLink($user['email'])->assertRedirectTo(site_url('login'));
        $this->get('forgot-password/demo')->assertRedirectTo(site_url('forgot-password'));
    }

    public function testDemoModeShowsTheRealWorkingLink(): void
    {
        $user = $this->makeUser('student');
        $this->demoMode(true);

        $this->requestLink($user['email'])->assertRedirectTo(site_url('forgot-password/demo'));
        // Carry the session (with the one-time link) to the next page, as the browser does.
        $page = $this->withSession($_SESSION)->get('forgot-password/demo');
        $page->assertSee('Check your email');
        $page->assertSee('Password Reset Link');
        $page->assertDontSee('Demo');

        // Without a test inbox (as in tests), the button is the reset link from the email, which works like any reset link.
        $token = $this->tokenFromOutbox();
        $this->assertStringContainsString('href="' . esc(site_url("reset-password/{$token}"), 'attr') . '"', $page->response()->getBody());
        $this->seeInDatabase('audit_logs', ['action' => 'auth.reset_link_shown_demo', 'entity_id' => $user['id']]);

        $this->post("reset-password/{$token}", ['password' => self::NEW_PASSWORD, 'password_confirm' => self::NEW_PASSWORD])
            ->assertRedirectTo(site_url('login'));
        $this->post('login', ['email' => $user['email'], 'password' => self::NEW_PASSWORD])->assertSessionHas('user_id', $user['id']);

        // Still one-time use.
        $this->get("reset-password/{$token}")->assertRedirectTo(site_url('forgot-password'));
    }

    public function testDemoModeIgnoresOtherComputers(): void
    {
        $user = $this->makeUser('student');
        $this->demoMode(true, '192.168.1.20');

        $this->requestLink($user['email'])->assertRedirectTo(site_url('login'));
        $this->assertFalse(\App\Libraries\DefenseDemo::resetLinksEnabled('192.168.1.20'));
    }

    public function testDemoModeNeverWorksInProduction(): void
    {
        $this->demoMode(true);

        $this->assertTrue(\App\Libraries\DefenseDemo::resetLinksEnabled('127.0.0.1', 'development'));
        $this->assertFalse(\App\Libraries\DefenseDemo::resetLinksEnabled('127.0.0.1', 'production'));
    }

    public function testDemoModeSaysWhenNoAccountMatches(): void
    {
        $this->demoMode(true);

        $this->requestLink('nobody@example.com')->assertSee('No active account uses this email address.');
        $this->assertSame(0, db_connect()->table('password_resets')->countAllResults());
    }

    public function testDemoButtonOpensTheEmailInTheTestInbox(): void
    {
        $user = $this->makeUser('student');
        $this->demoMode(true);

        session()->setFlashdata('demo_reset', ['email' => $user['email'], 'link' => site_url('reset-password/' . str_repeat('a', 64)),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+1 hour')), 'mail_status' => 'sent']);
        config(Email::class)->fromEmail    = 'no-reply@rubricmind.local';
        config(Email::class)->SMTPHost     = '127.0.0.1';
        config(Email::class)->testInboxURL = 'http://localhost:8025';
        Services::resetSingle('mailer');

        $page = $this->withSession($_SESSION)->get('forgot-password/demo');

        $this->assertStringContainsString('href="' . esc('http://localhost:8025/view/latest', 'attr') . '"', $page->response()->getBody());
    }

    public function testOnlyAdminsCanOpenTheOutbox(): void
    {
        $teacher = $this->makeUser('teacher');

        $this->signedInAs($teacher)->get('admin/emails')->assertStatus(403);
    }
}
