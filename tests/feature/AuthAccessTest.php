<?php

namespace Tests\Feature;

use App\Models\AuditLogModel;
use App\Models\UserModel;
use Tests\Support\FeatureTestCase;

/**
 * @internal
 */
final class AuthAccessTest extends FeatureTestCase
{
    public function testPostWithoutCsrfTokenIsRejected(): void
    {
        $this->setCsrfEnabled(true);
        $teacher = $this->makeUser('teacher');

        $result = $this->post('login', ['email' => $teacher['email'], 'password' => self::PASSWORD]);

        // The user is sent back with a message instead of an error screen, and is not signed in.
        $result->assertRedirect();
        $result->assertSessionHas('error', lang('Security.disallowedAction'));
        $result->assertSessionMissing('user_id');
    }

    public function testSignOutWithExpiredTokenGoesBackWithMessage(): void
    {
        $this->setCsrfEnabled(true);
        $teacher = $this->makeUser('teacher');

        $result = $this->signedInAs($teacher)->post('logout', ['csrf_test_name' => 'expired-token']);

        $result->assertRedirect();
        $result->assertSessionHas('error', lang('Security.disallowedAction'));
    }

    public function testGuestIsRedirectedToLogin(): void
    {
        $result = $this->get('admin/users');

        $result->assertRedirect();
        $this->assertStringEndsWith('/login', $result->getRedirectUrl());
    }

    public function testValidCredentialsSignIn(): void
    {
        $teacher = $this->makeUser('teacher');

        $result = $this->post('login', ['email' => strtoupper($teacher['email']), 'password' => self::PASSWORD]);

        $result->assertRedirect();
        $result->assertSessionHas('user_id', $teacher['id']);
        $this->seeInDatabase('audit_logs', ['action' => 'auth.login', 'user_id' => $teacher['id']]);
    }

    public function testWrongPasswordIsRejectedAndLogged(): void
    {
        $teacher = $this->makeUser('teacher');

        $result = $this->post('login', ['email' => $teacher['email'], 'password' => 'wrong-password']);

        $result->assertOK();
        $result->assertSee('Incorrect email or password.');
        $result->assertSessionMissing('user_id');
        $this->seeInDatabase('audit_logs', ['action' => 'auth.login_failed', 'entity_id' => $teacher['id']]);
    }

    public function testInactiveAccountCannotSignIn(): void
    {
        $student = $this->makeUser('student', ['status' => 'inactive']);

        $result = $this->post('login', ['email' => $student['email'], 'password' => self::PASSWORD]);

        $result->assertSee('This account is inactive.');
        $result->assertSessionMissing('user_id');
    }

    public function testSignInIsThrottledAfterRepeatedAttempts(): void
    {
        $teacher = $this->makeUser('teacher');

        for ($i = 0; $i < 5; $i++) {
            $this->post('login', ['email' => $teacher['email'], 'password' => 'wrong-password']);
        }

        $result = $this->post('login', ['email' => $teacher['email'], 'password' => self::PASSWORD]);

        $result->assertSee('Too many sign-in attempts.');
        $result->assertSessionMissing('user_id');
    }

    public function testStudentCannotOpenAdminPages(): void
    {
        $student = $this->makeUser('student');

        $this->signedInAs($student)->get('admin/users')->assertStatus(403);
    }

    public function testTeacherCannotOpenStudentDashboard(): void
    {
        $teacher = $this->makeUser('teacher');

        $this->signedInAs($teacher)->get('student')->assertStatus(403);
    }

    public function testEachRoleReachesItsOwnDashboard(): void
    {
        foreach (['admin', 'teacher', 'student'] as $role) {
            $user = $this->makeUser($role);

            $this->signedInAs($user)->get($role)->assertOK();
        }
    }

    public function testEveryAdminPageRenders(): void
    {
        $admin   = $this->makeUser('admin');
        $student = $this->makeUser('student');

        $pages = [
            'admin',
            'admin/users',
            'admin/users?role=student&status=active&q=user',
            'admin/users/new',
            "admin/users/{$student['id']}/edit",
            "admin/users/{$student['id']}/password",
            'admin/audit-logs',
            'account/password',
        ];

        foreach ($pages as $page) {
            $this->signedInAs($admin)->get($page)->assertOK();
        }
    }

    public function testDeactivatedUserIsSignedOutOnNextRequest(): void
    {
        $teacher = $this->makeUser('teacher');
        model(UserModel::class)->update($teacher['id'], ['status' => 'inactive']);

        $result = $this->signedInAs($teacher)->get('teacher');

        $result->assertRedirect();
        $this->assertStringEndsWith('/login', $result->getRedirectUrl());
    }

    public function testTemporaryPasswordForcesPasswordChange(): void
    {
        $teacher = $this->makeUser('teacher', ['must_change_password' => true]);

        $result = $this->signedInAs($teacher)->get('teacher');

        $result->assertRedirect();
        $this->assertStringEndsWith('/account/password', $result->getRedirectUrl());
    }

    public function testChangingPasswordClearsTemporaryFlag(): void
    {
        $teacher = $this->makeUser('teacher', ['must_change_password' => true]);

        $this->signedInAs($teacher)->post('account/password', [
            'current_password' => self::PASSWORD,
            'password'         => 'my-new-password',
            'password_confirm' => 'my-new-password',
        ])->assertRedirect();

        $updated = model(UserModel::class)->find($teacher['id']);
        $this->assertFalse($updated['must_change_password']);
        $this->assertTrue(password_verify('my-new-password', $updated['password_hash']));
    }

    public function testAdminCreatesUserWithTemporaryPassword(): void
    {
        $admin = $this->makeUser('admin');

        $this->signedInAs($admin)->post('admin/users', [
            'name'             => 'Maria Santos',
            'email'            => 'Maria.Santos@Example.com',
            'role'             => 'teacher',
            'password'         => 'temporary-pass',
            'password_confirm' => 'temporary-pass',
        ])->assertRedirect();

        $created = model(UserModel::class)->findByEmail('maria.santos@example.com');
        $this->assertNotNull($created);
        $this->assertSame('teacher', $created['role']);
        $this->assertTrue($created['must_change_password']);
        $this->assertTrue(password_verify('temporary-pass', $created['password_hash']));
        $this->seeInDatabase('audit_logs', ['action' => 'user.created', 'entity_id' => $created['id'], 'user_id' => $admin['id']]);
    }

    public function testAdminCannotCreateDuplicateEmail(): void
    {
        $admin   = $this->makeUser('admin');
        $teacher = $this->makeUser('teacher');

        $result = $this->signedInAs($admin)->post('admin/users', [
            'name'             => 'Someone Else',
            'email'            => $teacher['email'],
            'role'             => 'student',
            'password'         => 'temporary-pass',
            'password_confirm' => 'temporary-pass',
        ]);

        $result->assertOK();
        $result->assertSee('Please fix the following');
        $this->assertSame(2, model(UserModel::class)->countAllResults());
    }

    public function testAdminCannotDeactivateOwnAccount(): void
    {
        $admin = $this->makeUser('admin');

        $this->signedInAs($admin)->post("admin/users/{$admin['id']}/status")->assertRedirect();

        $this->assertSame('active', model(UserModel::class)->find($admin['id'])['status']);
    }

    public function testAdminDeactivatesAnotherAccount(): void
    {
        $admin   = $this->makeUser('admin');
        $student = $this->makeUser('student');

        $this->signedInAs($admin)->post("admin/users/{$student['id']}/status")->assertRedirect();

        $this->assertSame('inactive', model(UserModel::class)->find($student['id'])['status']);
        $this->seeInDatabase('audit_logs', ['action' => 'user.deactivated', 'entity_id' => $student['id']]);
    }

    public function testAdminCannotRemoveOwnAdminRole(): void
    {
        $admin = $this->makeUser('admin');

        $result = $this->signedInAs($admin)->post("admin/users/{$admin['id']}", [
            'name'  => $admin['name'],
            'email' => $admin['email'],
            'role'  => 'teacher',
        ]);

        $result->assertSee('You cannot remove the administrator role from your own account.');
        $this->assertSame('admin', model(UserModel::class)->find($admin['id'])['role']);
    }

    public function testAdminResetPasswordSetsTemporaryFlag(): void
    {
        $admin   = $this->makeUser('admin');
        $student = $this->makeUser('student');

        $this->signedInAs($admin)->post("admin/users/{$student['id']}/password", [
            'password'         => 'reset-pass-123',
            'password_confirm' => 'reset-pass-123',
        ])->assertRedirect();

        $updated = model(UserModel::class)->find($student['id']);
        $this->assertTrue($updated['must_change_password']);
        $this->assertTrue(password_verify('reset-pass-123', $updated['password_hash']));
        $this->assertSame(1, model(AuditLogModel::class)->where('action', 'user.password_reset')->countAllResults());
    }
}
